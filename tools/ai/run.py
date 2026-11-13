import argparse
import json
import os
import sys

import torch
from PIL import Image

ROOT = os.path.dirname(os.path.abspath(__file__))
CACHE = os.path.join(ROOT, "cache")
WEIGHTS = os.path.join(ROOT, "weights", "RealESRGAN_x4plus.pth")
REMOVE_MODEL = "ZhengPeng7/BiRefNet_HR-matting"
os.environ.setdefault("HF_HOME", CACHE)
os.environ.setdefault("HF_HUB_DISABLE_TELEMETRY", "1")
os.environ.setdefault("HF_HUB_DISABLE_PROGRESS_BARS", "1")
os.environ.setdefault("HF_HUB_DISABLE_SYMLINKS_WARNING", "1")
os.environ.setdefault("TRANSFORMERS_VERBOSITY", "error")


def emit(payload):
    sys.stdout.write(json.dumps(payload, ensure_ascii=False) + "\n")
    sys.stdout.flush()


def fail(message):
    emit({"ok": False, "error": message})
    return 1


def load_rgb(path):
    try:
        image = Image.open(path)
        image.load()
    except Exception:
        raise RuntimeError("No se pudo abrir la imagen.")
    if image.mode != "RGB":
        image = image.convert("RGB")
    return image


def fade_in(amount):
    # 0 below 25%, full above 85%. Keeps the ring out of the strict setting.
    span = (amount - 0.25) / 0.60
    span = max(0.0, min(1.0, span))
    return span * span * (3.0 - 2.0 * span)


def inclusion_alpha(image, mask, keep):
    import numpy as np

    keep = max(0, min(100, int(keep)))
    amount = keep / 100.0
    ai = np.asarray(mask.convert("L")).astype("float32") / 255.0
    gamma = 1.8 - 1.2 * amount
    subject = np.power(np.clip(ai, 0.0, 1.0), gamma)

    rgb = np.asarray(image.convert("RGB")).astype("float32")
    border = np.concatenate(
        [
            rgb[:2, :, :].reshape(-1, 3),
            rgb[-2:, :, :].reshape(-1, 3),
            rgb[:, :2, :].reshape(-1, 3),
            rgb[:, -2:, :].reshape(-1, 3),
        ],
        axis=0,
    )
    background = np.median(border, axis=0)
    distance = np.linalg.norm(rgb - background, axis=2)
    cutoff = 36.0 - 18.0 * amount
    design = np.clip((distance - cutoff) / 14.0, 0.0, 1.0)

    notice = ""
    strong = float(np.percentile(ai, 99.5))
    if strong < 0.2:
        # The model missed the subject. The slider still decides how far from
        # the border color a pixel has to be before it stays.
        cutoff = 78.0 - 62.0 * amount
        alpha = np.clip((distance - cutoff) / 14.0, 0.0, 1.0)
        notice = "No distinguí un objeto principal, así que saqué el color parejo del borde. A la izquierda quedan los colores más marcados; a la derecha, también los trazos suaves."
    else:
        alpha = np.maximum(subject, design * fade_in(amount))
    pixels = (np.clip(alpha, 0.0, 1.0) * 255.0).round().astype("uint8")
    return Image.fromarray(pixels, "L"), notice


def remove_background(path, output, keep):
    from transformers import AutoModelForImageSegmentation

    image = load_rgb(path)
    device = torch.device("cpu")
    try:
        model = AutoModelForImageSegmentation.from_pretrained(
            REMOVE_MODEL,
            trust_remote_code=True,
            dtype=torch.float32,
        )
    except TypeError:
        model = AutoModelForImageSegmentation.from_pretrained(
            REMOVE_MODEL,
            trust_remote_code=True,
            torch_dtype=torch.float32,
        )
    model.float()
    model.to(device)
    model.eval()

    last_error = None
    mask = None
    for side in (2048, 1024):
        try:
            mask = predict_mask(model, image, side, device)
            break
        except RuntimeError as error:
            text = str(error).lower()
            if "memory" not in text and "alloc" not in text:
                raise
            last_error = error
            mask = None
    if mask is None:
        raise RuntimeError("No hubo memoria suficiente para quitar el fondo. " + str(last_error))

    mask = mask.resize(image.size, Image.Resampling.LANCZOS)
    alpha, notice = inclusion_alpha(image, mask, keep)
    result = image.copy()
    result.putalpha(alpha)
    result.save(output, "PNG")
    return notice


def predict_mask(model, image, side, device):
    from torchvision import transforms

    transform = transforms.Compose(
        [
            transforms.Resize((side, side)),
            transforms.ToTensor(),
            transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225]),
        ]
    )
    batch = transform(image).unsqueeze(0).to(device)
    with torch.no_grad():
        prediction = model(batch)[-1].sigmoid().cpu()
    mask = transforms.ToPILImage()(prediction[0].squeeze())
    del batch
    del prediction
    return mask


def upscale(path, output, scale):
    from spandrel import ImageModelDescriptor, ModelLoader

    if scale not in (2, 4):
        raise RuntimeError("La escala tiene que ser 2 o 4.")
    if not os.path.isfile(WEIGHTS):
        raise RuntimeError("Falta el archivo RealESRGAN_x4plus.pth.")

    image = load_rgb(path)
    model = ModelLoader().load_from_file(WEIGHTS)
    if not isinstance(model, ImageModelDescriptor):
        raise RuntimeError("El archivo de escalado no es un modelo de imagen.")
    model.to("cpu")
    model.eval()

    scaled = infer_tiles(model, image, tile=256, overlap=16)
    if scale == 2:
        scaled = scaled.resize((image.width * 2, image.height * 2), Image.Resampling.LANCZOS)
    scaled.save(output, "PNG")


def infer_tiles(model, image, tile, overlap):
    import numpy as np

    array = np.asarray(image).astype("float32") / 255.0
    height, width, _ = array.shape
    scale = int(model.scale)
    canvas = np.zeros((height * scale, width * scale, 3), dtype=np.float32)
    weight = np.zeros((height * scale, width * scale, 1), dtype=np.float32)
    step = max(1, tile - overlap)

    for top in range(0, height, step):
        for left in range(0, width, step):
            y0 = top
            x0 = left
            if y0 + tile > height and height >= tile:
                y0 = height - tile
            if x0 + tile > width and width >= tile:
                x0 = width - tile
            bottom = min(y0 + tile, height)
            right = min(x0 + tile, width)
            crop = array[y0:bottom, x0:right, :]
            tensor = torch.from_numpy(crop).permute(2, 0, 1).unsqueeze(0)
            with torch.no_grad():
                output = model(tensor).squeeze(0).permute(1, 2, 0).clamp(0, 1).numpy()
            out_top = y0 * scale
            out_left = x0 * scale
            out_bottom = out_top + output.shape[0]
            out_right = out_left + output.shape[1]
            canvas[out_top:out_bottom, out_left:out_right, :] += output
            weight[out_top:out_bottom, out_left:out_right, :] += 1.0

    canvas /= np.maximum(weight, 1.0)
    pixels = (canvas * 255.0).round().astype("uint8")
    return Image.fromarray(pixels, "RGB")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--task", required=True, choices=["remove_bg", "upscale"])
    parser.add_argument("--input", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--scale", type=int, default=4)
    parser.add_argument("--keep", type=int, default=80)
    args = parser.parse_args()

    os.makedirs(CACHE, exist_ok=True)
    torch.set_num_threads(max(1, os.cpu_count() or 1))
    notice = ""
    try:
        if args.task == "remove_bg":
            notice = remove_background(args.input, args.output, args.keep) or ""
        else:
            upscale(args.input, args.output, args.scale)
    except Exception as error:
        return fail(str(error))

    if not os.path.isfile(args.output) or os.path.getsize(args.output) < 32:
        return fail("La IA no escribió una imagen.")
    payload = {"ok": True}
    if notice:
        payload["notice"] = notice
    emit(payload)
    return 0


if __name__ == "__main__":
    sys.exit(main())
