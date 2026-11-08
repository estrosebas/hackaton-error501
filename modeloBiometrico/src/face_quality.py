"""Shared image-quality gates; these checks do not constitute liveness/PAD."""
from dataclasses import dataclass, field
import math

import cv2
import numpy as np


@dataclass(frozen=True)
class QualityResult:
    accepted: bool
    reason: str
    metrics: dict = field(default_factory=dict)


def assess_face(image, face, min_face_size=60, min_sharpness=20.0,
                min_detection_score=0.5):
    if (min_face_size <= 0 or not math.isfinite(min_sharpness) or min_sharpness < 0
            or not math.isfinite(min_detection_score) or not 0 <= min_detection_score <= 1):
        raise ValueError("Invalid quality thresholds")
    if image is None or image.ndim != 3 or image.shape[2] != 3 or not image.size:
        return QualityResult(False, "invalid_image")
    bbox = np.asarray(getattr(face, "bbox", None), dtype=float)
    if bbox.shape != (4,) or not np.isfinite(bbox).all():
        return QualityResult(False, "invalid_bbox")
    x1, y1, x2, y2 = bbox
    h, w = image.shape[:2]
    if x2 <= x1 or y2 <= y1:
        return QualityResult(False, "invalid_bbox")
    if x1 < 0 or y1 < 0 or x2 > w or y2 > h:
        return QualityResult(False, "face_cut_off")
    if min(x2-x1, y2-y1) < min_face_size:
        return QualityResult(False, "face_too_small")
    score = getattr(face, "det_score", None)
    if score is None or not math.isfinite(float(score)) or float(score) < min_detection_score:
        return QualityResult(False, "weak_detection")
    crop = image[int(y1):int(y2), int(x1):int(x2)]
    if not crop.size:
        return QualityResult(False, "invalid_crop")
    gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
    metrics = {"sharpness": float(cv2.Laplacian(gray, cv2.CV_64F).var()),
               "brightness": float(gray.mean()), "detection_score": float(score)}
    if metrics["sharpness"] < min_sharpness:
        return QualityResult(False, "blurred", metrics)
    if not 20 <= metrics["brightness"] <= 235:
        return QualityResult(False, "poor_lighting", metrics)
    points = getattr(face, "kps", None)
    if points is None:
        return QualityResult(False, "missing_landmarks", metrics)
    points = np.asarray(points, dtype=float)
    if points.shape != (5, 2) or not np.isfinite(points).all():
        return QualityResult(False, "invalid_landmarks", metrics)
    eye_distance = float(np.linalg.norm(points[1]-points[0]))
    if eye_distance < 12:
        return QualityResult(False, "eyes_too_close", metrics)
    if (points[:, 0].min() < x1 or points[:, 0].max() > x2 or
            points[:, 1].min() < y1 or points[:, 1].max() > y2):
        return QualityResult(False, "landmarks_outside_face", metrics)
    metrics["eye_distance"] = eye_distance
    return QualityResult(True, "ok", metrics)
