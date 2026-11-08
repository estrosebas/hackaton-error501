"""Broad distance limits do not require eye landmarks or exact centering."""
import sys
import unittest
from pathlib import Path
from types import SimpleNamespace
import numpy as np

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'src'))
from portrait_quality import assess_portrait


class PortraitQualityTests(unittest.TestCase):
    def evaluate(self, box):
        return assess_portrait(np.zeros((480, 480, 3), dtype=np.uint8), SimpleNamespace(bbox=box))

    def test_off_center_face_without_eye_landmarks_passes(self):
        self.assertTrue(self.evaluate([30, 30, 210, 270]).accepted)
        self.assertTrue(self.evaluate([120, 70, 420, 430]).accepted)

    def test_too_close_far_and_cut_off_fail(self):
        self.assertEqual(self.evaluate([30, 20, 450, 460]).reason, 'face_too_large')
        self.assertEqual(self.evaluate([150, 150, 230, 250]).reason, 'face_too_small')
        for box in ([0, 60, 220, 340], [270, 60, 490, 340], [100, 0, 320, 280], [100, 210, 320, 490]):
            self.assertEqual(self.evaluate(box).reason, 'face_cut_off')

    def test_invalid_boxes_fail(self):
        for box in (None, [], [1, 2, 1, 5], [1, 2, float('nan'), 5]):
            self.assertEqual(self.evaluate(box).reason, 'invalid_bbox')


if __name__ == '__main__':
    unittest.main()
