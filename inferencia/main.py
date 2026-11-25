import glob
import io
import os
import re
import time
import cv2
import easyocr
import numpy as np
from fastapi import FastAPI, File, UploadFile
from PIL import Image
from ultralytics import YOLO

app = FastAPI(title="Microservicio IA - Peaje Inteligente (M1 + M2 + M3)")

# Buscar automáticamente el archivo .onnx de placas en modelos/
archivos_m1 = glob.glob("modelos/yolo-v9-t-384-license-plates*.onnx")
PATH_M1_PLACA = archivos_m1[0] if archivos_m1 else "modelos/placa_m1.onnx"
PATH_M2_SEG = "modelos/m2_vehiculos.pt"
PATH_M3_POSE = "modelos/m3_llantas.pt"

modelo_m1 = YOLO(PATH_M1_PLACA, task="detect") if os.path.exists(PATH_M1_PLACA) else None
modelo_m2 = YOLO(PATH_M2_SEG) if os.path.exists(PATH_M2_SEG) else YOLO("yolov8n-seg.pt")
modelo_m3 = YOLO(PATH_M3_POSE) if os.path.exists(PATH_M3_POSE) else None

lector_ocr = easyocr.Reader(["es", "en"], gpu=False)

MAPEO_CATEGORIAS = {
    "moto": "moto",
    "motorcycle": "moto",
    "auto": "auto",
    "car": "auto",
    "minibus": "minibus",
    "van": "minibus",
    "camion": "camion",
    "truck": "camion",
    "bus": "bus",
}


def normalizar_placa(texto: str) -> str:
    limpio = re.sub(r"[^A-Z0-9]", "", texto.upper())
    if re.match(r"^[0-9]{3,4}[A-Z]{3}$", limpio) or (
        5 <= len(limpio) <= 8 and any(c.isdigit() for c in limpio) and any(c.isalpha() for c in limpio)
    ):
        return limpio
    return ""


def contar_ejes_desde_keypoints(res_m3, categoria: str) -> int:
    """Usa el centro de cada llanta detectada por m3_llantas.pt (keypoint 0) para contar ejes."""
    if res_m3 is None or res_m3.keypoints is None or len(res_m3.keypoints.xy) == 0:
        return 1 if categoria == "moto" else (3 if categoria == "camion" else 2)

    kps_xy = res_m3.keypoints.xy.cpu().numpy()
    boxes = res_m3.boxes.xyxy.cpu().numpy()

    llantas = []
    for box, pts in zip(boxes, kps_xy):
        altura = max(1.0, float(box[3] - box[1]))
        cx = float(pts[0][0]) if len(pts) > 0 else float((box[0] + box[2]) / 2)
        llantas.append({"cx": cx, "altura": altura})

    if not llantas:
        return 1 if categoria == "moto" else 2

    # Filtrar llantas del lado cercano (misma lógica del notebook de ejes)
    hmax = max(l["altura"] for l in llantas)
    cercanas = [l for l in llantas if l["altura"] >= 0.65 * hmax]
    xs = sorted(l["cx"] for l in cercanas)
    diam = float(np.median([l["altura"] for l in cercanas]))

    ejes = 1 + sum(1 for p, q in zip(xs, xs[1:]) if (q - p) > 0.6 * diam)
    return max(1, ejes) if categoria == "moto" else max(2, ejes)


@app.get("/health")
def health():
    return {
        "status": "ok",
        "m1_placa": os.path.basename(PATH_M1_PLACA) if modelo_m1 else None,
        "m2_vehiculos": os.path.exists(PATH_M2_SEG),
        "m3_llantas": modelo_m3 is not None,
    }


@app.post("/inferir")
async def inferir_vehiculo(imagen: UploadFile = File(...)):
    inicio = time.perf_counter()

    contenido = await imagen.read()
    pil_img = Image.open(io.BytesIO(contenido)).convert("RGB")
    img_bgr = cv2.cvtColor(np.array(pil_img), cv2.COLOR_RGB2BGR)

    # --- M2: Segmentación de instancias (Categoría del vehículo) ---
    res_m2 = modelo_m2(img_bgr, verbose=False)[0]
    categoria = "auto"
    conf_m2 = 0.0
    recorte_vehiculo = img_bgr

    for box in res_m2.boxes:
        nombre_cls = modelo_m2.names[int(box.cls[0])].lower()
        conf = float(box.conf[0])
        if nombre_cls in MAPEO_CATEGORIAS and conf > conf_m2:
            conf_m2 = conf
            categoria = MAPEO_CATEGORIAS[nombre_cls]
            x1, y1, x2, y2 = map(int, box.xyxy[0])
            recorte_vehiculo = img_bgr[max(0, y1):y2, max(0, x1):x2]

    # --- M1: Detección de placa (YOLO-v9 ONNX) + OCR ---
    recorte_placa = recorte_vehiculo
    conf_m1 = 0.0

    if modelo_m1 is not None:
        res_m1 = modelo_m1(img_bgr, verbose=False)[0]
        for box in res_m1.boxes:
            conf = float(box.conf[0])
            if conf > conf_m1:
                conf_m1 = conf
                px1, py1, px2, py2 = map(int, box.xyxy[0])
                recorte_placa = img_bgr[max(0, py1):py2, max(0, px1):px2]

    lecturas = lector_ocr.readtext(recorte_placa)
    placa_detectada = None
    conf_ocr = 0.0

    for (_, texto, prob) in lecturas:
        candidata = normalizar_placa(texto)
        if candidata and prob > conf_ocr:
            placa_detectada = candidata
            conf_ocr = float(prob)

    # --- M3: Keypoints sobre las ruedas (Número de ejes) ---
    res_m3 = modelo_m3(recorte_vehiculo, verbose=False)[0] if modelo_m3 is not None else None
    ejes = contar_ejes_desde_keypoints(res_m3, categoria)

    if placa_detectada:
        confianza = round(((conf_m2 + conf_ocr) / 2) * 100, 2)
    else:
        confianza = round(conf_m2 * 50, 2)

    latencia_ms = round((time.perf_counter() - inicio) * 1000, 2)

    return {
        "placa": placa_detectada,
        "confianza": confianza,
        "categoria": categoria,
        "ejes": ejes,
        "latencia": latencia_ms,
    }