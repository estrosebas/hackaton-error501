"""YuNet detection and SFace embeddings, using locally installed OpenCV Zoo models."""
from __future__ import annotations

import hashlib
import os
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, Optional, Tuple

import cv2
import numpy as np

from config import (CPU_THREADS, DETECTION_SIZE, EMBEDDING_DIM, EMBEDDING_MODEL_ID,
                    MODEL_NAME, ROOT_DIR)
from utils import setup_logger

LOGGER = setup_logger("detect_faces")


def compute_model_fingerprint(model_files: Iterable[Path], *, opencv_version: str) -> str:
    """Bind the gallery to both ordered weights and the OpenCV preprocessing ABI."""
    files = tuple(Path(path) for path in model_files)
    if len(files) != 2 or len(set(files)) != 2:
        raise ValueError("Exactly two distinct model files are required")
    if not isinstance(opencv_version, str) or not opencv_version or ":" in opencv_version:
        raise ValueError("A valid OpenCV version is required")
    digest = hashlib.sha256(f"yunet-sface-aligncrop-l2:v1:{opencv_version}".encode("ascii"))
    for path in files:
        with path.open("rb") as stream:
            digest.update(hashlib.file_digest(stream, "sha256").digest())
    return digest.hexdigest()


@dataclass
class Face:
    bbox: np.ndarray
    det_score: float
    kps: np.ndarray
    detector_row: np.ndarray
    normed_embedding: Optional[np.ndarray] = None


class FaceDetector:
    def __init__(self, model_name: str = MODEL_NAME,
                 det_size: Tuple[int, int] = DETECTION_SIZE,
                 providers: Optional[Iterable[str]] = None,
                 model_dir: Optional[Path] = None, cpu_threads: int = CPU_THREADS,
                 min_face_size=60, min_sharpness=20.0, min_detection_score=0.5):
        if model_name != "sface_yunet":
            raise ValueError("Only sface_yunet is supported")
        if providers is not None and list(providers) != ["CPUExecutionProvider"]:
            raise ValueError("This OpenCV backend currently supports CPUExecutionProvider only")
        if cpu_threads < 1 or len(det_size) != 2 or any(s < 160 or s % 32 for s in det_size):
            raise ValueError("Positive CPU threads and detection sides >=160, multiple of 32 required")
        self.model_id = EMBEDDING_MODEL_ID
        self.embedding_dim = EMBEDDING_DIM
        self._quality_options = dict(min_face_size=min_face_size, min_sharpness=min_sharpness,
                                     min_detection_score=min_detection_score)
        configured_dir = model_dir or os.getenv("FACE_MODEL_DIR", "models/sface_yunet")
        directory = Path(configured_dir)
        if not directory.is_absolute():
            directory = ROOT_DIR / directory
        files = [directory / "face_detection_yunet_2023mar.onnx",
                 directory / "face_recognition_sface_2021dec.onnx"]
        for path in files:
            if not path.is_file():
                raise FileNotFoundError(f"Model missing: {path}. Install OpenCV Zoo weights before starting.")
        self.model_fingerprint = compute_model_fingerprint(files, opencv_version=cv2.__version__)
        cv2.setNumThreads(cpu_threads)
        self.detector = cv2.FaceDetectorYN.create(str(files[0]), "", det_size,
                                                   score_threshold=0.5)
        self.recognizer = cv2.FaceRecognizerSF.create(str(files[1]), "")
        LOGGER.info("Models ready: %s; OpenCV CPU threads=%d", self.model_id, cpu_threads)

    def detect(self, image_bgr: np.ndarray):
        from face_quality import assess_face
        if image_bgr is None or image_bgr.ndim != 3 or image_bgr.shape[2] != 3 or not image_bgr.size:
            raise ValueError("A nonempty BGR image is required")
        height, width = image_bgr.shape[:2]
        self.detector.setInputSize((width, height))
        _, rows = self.detector.detect(image_bgr)
        faces = []
        for row in [] if rows is None else rows:
            x, y, w, h = (float(value) for value in row[:4])
            face = Face(bbox=np.array([x, y, x + w, y + h]), det_score=float(row[14]),
                        kps=np.asarray(row[4:14], dtype=np.float32).reshape(5, 2),
                        detector_row=np.asarray(row, dtype=np.float32))
            faces.append(face)
        if len(faces) == 1 and assess_face(image_bgr, faces[0], **self._quality_options).accepted:
            aligned = self.recognizer.alignCrop(image_bgr, faces[0].detector_row)
            vector = np.asarray(self.recognizer.feature(aligned), dtype=np.float32).reshape(-1)
            norm = float(np.linalg.norm(vector))
            if vector.shape != (self.embedding_dim,) or not np.isfinite(vector).all() or norm <= 0:
                raise ValueError("invalid_embedding")
            faces[0].normed_embedding = vector / norm
        return faces

    @staticmethod
    def select_largest_face(faces):
        return max(faces, key=lambda f: max(0, f.bbox[2]-f.bbox[0]) *
                   max(0, f.bbox[3]-f.bbox[1]), default=None)

    def detect_largest_face(self, image_bgr):
        return self.select_largest_face(self.detect(image_bgr))


def face_to_embedding(face) -> np.ndarray:
    return np.asarray(face.normed_embedding, dtype=np.float32)


def face_to_bbox(face) -> Tuple[int, int, int, int]:
    return tuple(int(v) for v in face.bbox)
