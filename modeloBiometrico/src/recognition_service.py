"""Image decoding shared by the private photo service."""
import cv2
import numpy as np


def decode_image(image_bytes):
    image = cv2.imdecode(np.frombuffer(image_bytes, dtype=np.uint8), cv2.IMREAD_COLOR)
    if image is None:
        raise ValueError('unreadable_image')
    return image
