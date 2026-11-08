"""Do not run recognition inference on frames discarded by the V1 policy."""
import sys
import unittest
from pathlib import Path
from unittest.mock import MagicMock

import numpy as np

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "src"))
from detect_faces import FaceDetector


class DetectorGateTests(unittest.TestCase):
    def setUp(self):
        self.detector = FaceDetector.__new__(FaceDetector)
        self.detector.embedding_dim = 128
        self.detector.detector = MagicMock()
        self.detector.recognizer = MagicMock()
        self.detector._quality_options = {}
        self.box = np.array([[10, 10, 95, 95, .99]], dtype=np.float32)
        self.kps = np.array([[[30, 35], [70, 35], [50, 55], [35, 75], [65, 75]]], dtype=np.float32)
        self.image = np.random.default_rng(1).integers(40, 210, (110, 110, 3), dtype=np.uint8)

    def test_multiple_faces_do_not_run_embedding_model(self):
        rows = np.concatenate((np.tile(np.array([[10, 10, 85, 85]], dtype=np.float32), (8, 1)),
                               np.repeat(self.kps.reshape(1, 10), 8, axis=0),
                               np.full((8, 1), .99, dtype=np.float32)), axis=1)
        self.detector.embedding_dim = 128
        self.detector.detector.detect.return_value = (8, rows)
        self.assertEqual(len(self.detector.detect(self.image)), 8)
        self.detector.recognizer.feature.assert_not_called()

    def test_blurry_single_face_does_not_run_embedding_model(self):
        self.detector.detector.detect.return_value = (1, np.concatenate((np.array([[10, 10, 85, 85]], dtype=np.float32), self.kps.reshape(1, 10), np.array([[.99]], dtype=np.float32)), axis=1))
        self.assertEqual(len(self.detector.detect(np.full_like(self.image, 128))), 1)
        self.detector.recognizer.feature.assert_not_called()

    def test_valid_single_face_runs_embedding_model_once(self):
        self.detector.detector.detect.return_value = (1, np.concatenate((np.array([[10, 10, 85, 85]], dtype=np.float32), self.kps.reshape(1, 10), np.array([[.99]], dtype=np.float32)), axis=1))
        self.detector.recognizer.feature.return_value = np.ones((1, 128), dtype=np.float32)
        self.assertEqual(len(self.detector.detect(self.image)), 1)
        self.detector.recognizer.feature.assert_called_once()
