<?php

require_once __DIR__ . '/bootstrap.php';

class MercadoPago
{
    private $token;

    public function __construct($token)
    {
        $this->token = trim((string) $token);
        if ($this->token === '') {
            throw new UserException('Falta el Access Token de Mercado Pago en config.php.');
        }
        if (!function_exists('curl_init')) {
            throw new UserException('Este PHP no tiene cURL, hace falta para Mercado Pago.');
        }
    }

    public static function fromConfig()
    {
        $config = clh_config();
        $token = '';
        if (!empty($config['mercadopago']['access_token'])) {
            $token = $config['mercadopago']['access_token'];
        }
        return new self($token);
    }

    public function isTest()
    {
        return strpos($this->token, 'TEST-') === 0 || $this->sandboxAccount();
    }

    public function sandboxAccount()
    {
        static $sandbox = null;
        if ($sandbox !== null) {
            return $sandbox;
        }
        $sandbox = false;
        try {
            $me = $this->request('GET', '/users/me', null);
            if (!empty($me['tags']) && is_array($me['tags']) && in_array('test_user', $me['tags'], true)) {
                $sandbox = true;
            }
        } catch (UserException $error) {
            $sandbox = false;
        }
        return $sandbox;
    }

    public function createCheckout(array $item, $externalReference, $payerEmail, array $returnUrls)
    {
        $amount = number_format((float) $item['amount'], 2, '.', '');
        $body = [
            'type' => 'online',
            'processing_mode' => 'manual',
            'total_amount' => $amount,
            'external_reference' => (string) $externalReference,
            'description' => (string) $item['title'],
            'items' => [
                [
                    'title' => (string) $item['title'],
                    'unit_price' => $amount,
                    'quantity' => 1,
                ],
            ],
        ];
        $payerEmail = trim((string) $payerEmail);
        if ($payerEmail !== '' && strpos($payerEmail, '@') !== false) {
            $body['payer'] = ['email' => $payerEmail];
        }
        if ($returnUrls) {
            $online = [];
            foreach (['success_url', 'failure_url', 'pending_url'] as $key) {
                if (!empty($returnUrls[$key])) {
                    $online[$key] = $returnUrls[$key];
                }
            }
            if ($online) {
                $online['auto_return'] = 'all';
                $body['config'] = ['online' => $online];
            }
        }

        $order = $this->request('POST', '/v1/orders', $body);
        $url = isset($order['checkout_url']) ? (string) $order['checkout_url'] : '';
        $id = isset($order['id']) ? (string) $order['id'] : '';
        if ($id === '' || $url === '' || strpos($url, 'https://') !== 0) {
            throw new UserException('Mercado Pago no devolvió el checkout.');
        }
        return ['id' => $id, 'checkout_url' => $url, 'status' => isset($order['status']) ? (string) $order['status'] : ''];
    }

    public function getOrder($orderId)
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', (string) $orderId)) {
            throw new UserException('El número de pago no es válido.');
        }
        return $this->request('GET', '/v1/orders/' . rawurlencode((string) $orderId), null);
    }

    public static function isPaid(array $order)
    {
        $status = isset($order['status']) ? (string) $order['status'] : '';
        if (in_array($status, ['processed', 'paid', 'accredited'], true)) {
            return true;
        }
        $payments = [];
        if (!empty($order['transactions']['payments']) && is_array($order['transactions']['payments'])) {
            $payments = $order['transactions']['payments'];
        }
        foreach ($payments as $payment) {
            if (is_array($payment) && isset($payment['status']) && $payment['status'] === 'approved') {
                return true;
            }
        }
        return false;
    }

    private function request($method, $path, $body)
    {
        $ch = curl_init('https://api.mercadopago.com' . $path);
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Accept: application/json',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'X-Idempotency-Key: ' . self::uuid();
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new UserException('No se pudo hablar con Mercado Pago. ' . $error);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new UserException('Mercado Pago respondió algo que no pude leer.');
        }
        if ($code < 200 || $code >= 300) {
            throw new UserException(self::errorText($decoded, $code));
        }
        return $decoded;
    }

    private static function errorText(array $decoded, $code)
    {
        $parts = [];
        foreach (['message', 'error'] as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                $parts[] = $decoded[$key];
            }
        }
        if (!empty($decoded['cause']) && is_array($decoded['cause'])) {
            foreach ($decoded['cause'] as $cause) {
                if (is_array($cause) && !empty($cause['description']) && is_string($cause['description'])) {
                    $parts[] = $cause['description'];
                }
            }
        }
        if (!empty($decoded['errors']) && is_array($decoded['errors'])) {
            foreach ($decoded['errors'] as $err) {
                if (!is_array($err)) {
                    continue;
                }
                if (!empty($err['message']) && is_string($err['message'])) {
                    $parts[] = $err['message'];
                }
                if (!empty($err['details']) && is_array($err['details'])) {
                    foreach ($err['details'] as $detail) {
                        if (is_string($detail)) {
                            $parts[] = $detail;
                        }
                    }
                }
            }
        }
        if (!$parts) {
            $parts[] = 'Mercado Pago rechazó el pedido (' . (int) $code . ').';
        }
        return implode(' ', $parts);
    }

    private static function uuid()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
