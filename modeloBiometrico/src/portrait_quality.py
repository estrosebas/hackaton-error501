"""Broad public framing limits without eye alignment or posture gates.

YuNet has different boxes than MediaPipe, so server limits are slightly wider.
The caller requires one face and a valid embedding; live policies are unchanged.
"""
import numpy as np
from face_quality import QualityResult

POLICY = "public_face_framing_v8"


def assess_portrait(image, face):
    if image is None or image.ndim != 3 or not image.size:
        return QualityResult(False, "invalid_image")
    box = np.asarray(getattr(face, "bbox", None), dtype=float)
    if box.shape != (4,) or not np.isfinite(box).all():
        return QualityResult(False, "invalid_bbox")
    x1, y1, x2, y2 = box
    if x2 <= x1 or y2 <= y1:
        return QualityResult(False, "invalid_bbox")
    h, w = image.shape[:2]
    height, width = (y2-y1) / h, (x2-x1) / w
    metrics = {"face_height": height, "face_width": width}
    if height > 0.84 or width > 0.74:
        return QualityResult(False, "face_too_large", metrics)
    if min(x1 / w, y1 / h, (w-x2) / w, (h-y2) / h) < 0.03:
        return QualityResult(False, "face_cut_off", metrics)
    if height < 0.32 or width < 0.22:
        return QualityResult(False, "face_too_small", metrics)
    return QualityResult(True, "ok", metrics)
