import io
import os
import re
import time
import cv2
import easyocr
import numpy as np
import onnxruntime as ort
from fastapi import FastAPI, File, UploadFile
from PIL import Image
from ultralytics import YOLO

app = FastAPI(title="Microservicio IA - Peaje Inteligente (M1 + M2 + M3)")

DIRECTORIO_MODELOS = os.path.join(os.path.dirname(os.path.abspath(__file__)), "modelos")
PATH_M1_PLACA = os.path.join(
    DIRECTORIO_MODELOS,
    "yolo-v9-t-384-license-plates-end2end.onnx",
)
PATH_M2_SEG = os.path.join(DIRECTORIO_MODELOS, "m2_vehiculos.pt")
PATH_M3_POSE = os.path.join(DIRECTORIO_MODELOS, "m3_llantas.pt")

for ruta_modelo in (PATH_M1_PLACA, PATH_M2_SEG, PATH_M3_POSE):
    if not os.path.isfile(ruta_modelo):
        raise FileNotFoundError(f"No se encontró el peso requerido: {ruta_modelo}")

sesion_m1 = ort.InferenceSession(
    PATH_M1_PLACA,
    providers=["CPUExecutionProvider"],
)
nombre_entrada_m1 = sesion_m1.get_inputs()[0].name
nombre_salida_m1 = sesion_m1.get_outputs()[0].name
modelo_m2 = YOLO(PATH_M2_SEG)
modelo_m3 = YOLO(PATH_M3_POSE)

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


def detectar_recorte_placa(img_bgr: np.ndarray) -> tuple[np.ndarray, float]:
    alto, ancho = img_bgr.shape[:2]
    escala = min(384 / ancho, 384 / alto)
    ancho_redimensionado = round(ancho * escala)
    alto_redimensionado = round(alto * escala)
    imagen_redimensionada = cv2.resize(
        img_bgr,
        (ancho_redimensionado, alto_redimensionado),
        interpolation=cv2.INTER_LINEAR,
    )

    pad_x = (384 - ancho_redimensionado) / 2
    pad_y = (384 - alto_redimensionado) / 2
    izquierda = round(pad_x - 0.1)
    derecha = round(pad_x + 0.1)
    arriba = round(pad_y - 0.1)
    abajo = round(pad_y + 0.1)
    imagen_entrada = cv2.copyMakeBorder(
        imagen_redimensionada,
        arriba,
        abajo,
        izquierda,
        derecha,
        cv2.BORDER_CONSTANT,
        value=(114, 114, 114),
    )
    imagen_entrada = cv2.cvtColor(imagen_entrada, cv2.COLOR_BGR2RGB)
    tensor = np.transpose(imagen_entrada, (2, 0, 1))[None].astype(np.float32) / 255
    detecciones = sesion_m1.run(
        [nombre_salida_m1],
        {nombre_entrada_m1: tensor},
    )[0].reshape(-1, 7)

    detecciones_validas = [
        deteccion
        for deteccion in detecciones
        if deteccion[6] > 0 and int(deteccion[5]) == 0
    ]
    if not detecciones_validas:
        return img_bgr, 0.0

    deteccion = max(detecciones_validas, key=lambda fila: float(fila[6]))
    x1, y1, x2, y2 = (float(valor) for valor in deteccion[1:5])
    pad_x = (x2 - x1) * 0.1
    pad_y = (y2 - y1) * 0.15
    x1 = max(0, min(ancho, int((x1 - pad_x - izquierda) / escala)))
    x2 = max(0, min(ancho, int((x2 + pad_x - izquierda) / escala)))
    y1 = max(0, min(alto, int((y1 - pad_y - arriba) / escala)))
    y2 = max(0, min(alto, int((y2 + pad_y - arriba) / escala)))

    if x2 <= x1 or y2 <= y1:
        return img_bgr, 0.0

    return img_bgr[y1:y2, x1:x2], float(deteccion[6])


@app.get("/health")
def health():
    return {
        "status": "ok",
        "m1_placa": os.path.basename(PATH_M1_PLACA),
        "m2_vehiculos": os.path.basename(PATH_M2_SEG),
        "m3_llantas": os.path.basename(PATH_M3_POSE),
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
    recorte_placa, conf_m1 = detectar_recorte_placa(img_bgr)

    if recorte_placa.shape[1] < 400:
        escala_ocr = 400 / recorte_placa.shape[1]
        recorte_placa = cv2.resize(
            recorte_placa,
            None,
            fx=escala_ocr,
            fy=escala_ocr,
            interpolation=cv2.INTER_CUBIC,
        )

    lecturas = lector_ocr.readtext(recorte_placa)
    placa_detectada = None
    conf_ocr = 0.0

    for (_, texto, prob) in lecturas:
        candidata = normalizar_placa(texto)
        if candidata and prob > conf_ocr:
            placa_detectada = candidata
            conf_ocr = float(prob)

    # --- M3: Keypoints sobre las ruedas (Número de ejes) ---
    res_m3 = modelo_m3(recorte_vehiculo, verbose=False)[0]
    ejes = contar_ejes_desde_keypoints(res_m3, categoria)

    if placa_detectada:
        confianza = round((conf_m2 + conf_ocr) / 2, 4)
    else:
        confianza = round(conf_m2 * 0.5, 4)

    latencia_ms = round((time.perf_counter() - inicio) * 1000, 2)

    return {
        "placa": placa_detectada,
        "confianza": confianza,
        "categoria": categoria,
        "ejes": ejes,
        "latencia": latencia_ms,
    }