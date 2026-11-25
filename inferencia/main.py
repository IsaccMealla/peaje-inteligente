import io
import re
import cv2
import easyocr
import numpy as np
from fastapi import FastAPI, File, UploadFile
from PIL import Image
from ultralytics import YOLO

app = FastAPI(title="Microservicio IA - Peaje Inteligente")

# Carga de modelos al iniciar el servicio
modelo_yolo = YOLO("yolov8n.pt")
lector_ocr = easyocr.Reader(["es", "en"], gpu=False)

# Mapeo de clases COCO de YOLO a categorías de tarifa en Laravel
MAPEO_CLASES = {
    "car": "Liviano",
    "motorcycle": "Motocicleta",
    "bus": "Bus",
    "truck": "Pesado",
}


def limpiar_placa(texto: str) -> str:
    limpio = re.sub(r"[^A-Z0-9]", "", texto.upper())
    return limpio if 5 <= len(limpio) <= 8 else ""


@app.get("/health")
def health():
    return {"status": "ok", "modelo": "yolov8n + easyocr"}


@app.post("/inferir")
async def inferir_vehiculo(imagen: UploadFile = File(...)):
    contenido = await imagen.read()
    pil_img = Image.open(io.BytesIO(contenido)).convert("RGB")
    img_np = np.array(pil_img)
    img_bgr = cv2.cvtColor(img_np, cv2.COLOR_RGB2BGR)

    # 1. Detección de vehículo con YOLOv8
    resultados = modelo_yolo(img_bgr, verbose=False)[0]
    clase_detectada = "Liviano"
    confianza_vehiculo = 0.0
    recorte_vehiculo = img_bgr

    for box in resultados.boxes:
        cls_id = int(box.cls[0])
        nombre_coco = modelo_yolo.names[cls_id]
        conf = float(box.conf[0])

        if nombre_coco in MAPEO_CLASES and conf > confianza_vehiculo:
            confianza_vehiculo = conf
            clase_detectada = MAPEO_CLASES[nombre_coco]
            x1, y1, x2, y2 = map(int, box.xyxy[0])
            recorte_vehiculo = img_bgr[max(0, y1):y2, max(0, x1):x2]

    # 2. Lectura de placa con EasyOCR sobre la región del vehículo
    lecturas = lector_ocr.readtext(recorte_vehiculo)
    placa_final = None
    confianza_ocr = 0.0

    for (_, texto, prob) in lecturas:
        candidata = limpiar_placa(texto)
        if candidata and prob > confianza_ocr:
            placa_final = candidata
            confianza_ocr = float(prob)

    confianza_total = round((confianza_vehiculo * 0.5 + confianza_ocr * 0.5) * 100, 2) if placa_final else round(confianza_vehiculo * 50, 2)

    return {
        "placa": placa_final,
        "clase_vehiculo": clase_detectada,
        "confianza": confianza_total,
    }