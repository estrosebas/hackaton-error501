"""Enrollment extraction reused from clubmejia/modeloJose."""
import numpy as np
from face_quality import assess_face


def extract_enrollment_embedding(image_bgr, detector, *, quality_assessor=assess_face) -> np.ndarray:
    faces = detector.detect(image_bgr)
    if len(faces) != 1:
        raise ValueError("no_face" if not faces else "multiple_faces")
    quality = quality_assessor(image_bgr, faces[0])
    if not quality.accepted:
        raise ValueError(quality.reason)
    if getattr(faces[0], 'normed_embedding', None) is None and quality_assessor is not assess_face:
        # El detector ya aplica calidad basica antes de generar el template.
        # Recupera el motivo concreto (borrosa, poca luz) si no llego a generarlo.
        calidad_basica = assess_face(
            image_bgr,
            faces[0],
            **getattr(detector, '_quality_options', {}),
        )
        if not calidad_basica.accepted:
            raise ValueError(calidad_basica.reason)
    embedding = np.asarray(faces[0].normed_embedding, dtype=np.float32)
    if (embedding.shape != (getattr(detector, "embedding_dim", 512),)
            or not np.isfinite(embedding).all() or np.linalg.norm(embedding) <= 0):
        raise ValueError("invalid_embedding")
    return embedding / np.linalg.norm(embedding)

