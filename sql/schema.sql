CREATE TABLE IF NOT EXISTS levels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  blurb VARCHAR(255) NOT NULL,
  monthly_credits INT NOT NULL,
  price_ars INT NOT NULL,
  highlighted TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_levels_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tools (
  slug VARCHAR(32) NOT NULL,
  name VARCHAR(80) NOT NULL,
  summary VARCHAR(255) NOT NULL,
  credit_cost INT NOT NULL DEFAULT 0,
  credit_note VARCHAR(160) NOT NULL,
  sort_order INT NOT NULL,
  PRIMARY KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS level_tools (
  level_id INT UNSIGNED NOT NULL,
  tool VARCHAR(32) NOT NULL,
  allowed TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (level_id, tool),
  CONSTRAINT fk_level_tools_level FOREIGN KEY (level_id) REFERENCES levels (id),
  CONSTRAINT fk_level_tools_tool FOREIGN KEY (tool) REFERENCES tools (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  level_id INT UNSIGNED NOT NULL,
  credits INT NOT NULL DEFAULT 0,
  theme VARCHAR(20) NOT NULL DEFAULT 'naranja',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  signup_ip VARCHAR(45) NOT NULL DEFAULT '',
  device_id VARCHAR(64) NOT NULL DEFAULT '',
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  CONSTRAINT fk_users_level FOREIGN KEY (level_id) REFERENCES levels (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS credit_moves (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  delta INT NOT NULL,
  reason VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_credit_user (user_id, created_at),
  CONSTRAINT fk_credit_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  level_id INT UNSIGNED NOT NULL,
  external_reference VARCHAR(64) NOT NULL,
  mp_order_id VARCHAR(64) NULL,
  amount_ars INT NOT NULL,
  credits INT NOT NULL,
  status VARCHAR(32) NOT NULL,
  granted TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_ref (external_reference),
  UNIQUE KEY uq_payments_order (mp_order_id),
  KEY idx_payments_user (user_id, created_at),
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_payments_level FOREIGN KEY (level_id) REFERENCES levels (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL,
  body VARCHAR(255) NOT NULL,
  ref_user_id INT UNSIGNED NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_notices_user (user_id, id),
  CONSTRAINT fk_notices_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solicitudes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind VARCHAR(20) NOT NULL,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  reference_code VARCHAR(80) NOT NULL DEFAULT '',
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_solicitudes_kind (kind, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO levels (id, slug, name, blurb, monthly_credits, price_ars, highlighted, sort_order) VALUES
(1, 'mesa', 'Mesa', 'Para probar el taller. Convertir, recortar y espejar, sin créditos de IA.', 0, 0, 0, 1),
(2, 'puesto', 'Puesto', 'Para encargos sueltos que necesitan fondo o escala.', 80, 3500, 0, 2),
(3, 'cuadrilla', 'Cuadrilla', 'Para quien procesa imágenes casi todos los días.', 250, 9500, 1, 3),
(4, 'planta', 'Planta', 'Para un volumen alto o más de un puesto.', 700, 18000, 0, 4)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  blurb = VALUES(blurb),
  monthly_credits = VALUES(monthly_credits),
  price_ars = VALUES(price_ars),
  highlighted = VALUES(highlighted),
  sort_order = VALUES(sort_order);

INSERT INTO tools (slug, name, summary, credit_cost, credit_note, sort_order) VALUES
('remove_bg', 'Quitar fondo', 'PNG transparente o sobre un color.', 2, 'Cada imagen usa 2 créditos.', 1),
('upscale', 'Escalar', '2× o 4× con Real-ESRGAN.', 2, '2× usa 2 créditos. 4× usa 4.', 2),
('convert', 'Convertir', 'Una imagen. Calidad y formato.', 0, 'No usa créditos.', 3),
('crop', 'Recortar', 'Saca el margen vacío.', 0, 'No usa créditos.', 4),
('flip', 'Espejar', 'Horizontal, vertical o ambos.', 0, 'No usa créditos.', 5),
('halftone', 'Semitono', 'Puntos para DTF, foto o base blanca.', 0, 'No usa créditos.', 6),
('solid', 'Sólidos', 'Saca el halo y deja el píxel macizo.', 0, 'No usa créditos.', 7),
('mockup', 'Crear Mockup', 'Diseño sobre remera, taza, gorra, medalla, mate, jarro o cartuchera.', 1, 'Cada imagen usa 1 crédito.', 8)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  summary = VALUES(summary),
  credit_cost = VALUES(credit_cost),
  credit_note = VALUES(credit_note),
  sort_order = VALUES(sort_order);

INSERT INTO level_tools (level_id, tool, allowed) VALUES
(1, 'remove_bg', 0),
(1, 'upscale', 0),
(1, 'convert', 1),
(1, 'crop', 1),
(1, 'flip', 1),
(1, 'halftone', 1),
(1, 'solid', 1),
(1, 'mockup', 1),
(2, 'remove_bg', 1),
(2, 'upscale', 1),
(2, 'convert', 1),
(2, 'crop', 1),
(2, 'flip', 1),
(2, 'halftone', 1),
(2, 'solid', 1),
(2, 'mockup', 1),
(3, 'remove_bg', 1),
(3, 'upscale', 1),
(3, 'convert', 1),
(3, 'crop', 1),
(3, 'flip', 1),
(3, 'halftone', 1),
(3, 'solid', 1),
(3, 'mockup', 1),
(4, 'remove_bg', 1),
(4, 'upscale', 1),
(4, 'convert', 1),
(4, 'crop', 1),
(4, 'flip', 1),
(4, 'halftone', 1),
(4, 'solid', 1),
(4, 'mockup', 1)
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);
