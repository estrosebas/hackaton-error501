import sys
import unittest
import threading
from concurrent.futures import ThreadPoolExecutor
from io import BytesIO
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch, Mock

from fastapi.testclient import TestClient
from PIL import Image
import numpy as np

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'src'))
from photo_check_service import create_app
from portrait_quality import POLICY, assess_portrait


class PhotoCheckTests(unittest.TestCase):
    def setUp(self):
        self.detector = SimpleNamespace(model_id='sface_yunet', model_fingerprint='test-fingerprint')
        self.factory = Mock(return_value=self.detector)
        self.app = create_app(detector_factory=self.factory, token='test-only', max_bytes=20000, max_pixels=10000)
        self.headers = {'Authorization': 'Bearer test-only', 'Content-Type': 'image/jpeg'}
        output = BytesIO()
        Image.new('RGB', (80, 80), (120, 120, 120)).save(output, format='JPEG')
        self.image = output.getvalue()

    def test_requires_token_and_has_no_gallery_or_device_routes(self):
        with TestClient(self.app) as client:
            self.assertEqual(client.post('/check', content=self.image).status_code, 401)
            self.assertEqual(client.get('/health', headers=self.headers).json()['status'], 'ok')
            self.assertEqual(client.get('/v1/gallery/sync', headers=self.headers).status_code, 404)
        self.factory.assert_called_once()

    def test_reuses_enrollment_extraction_and_does_not_return_embedding(self):
        with patch('photo_check_service.extract_enrollment_embedding', return_value=np.ones(128)) as extract:
            with TestClient(self.app) as client:
                response = client.post('/check', content=self.image, headers=self.headers)
        self.assertTrue(response.json()['accepted'])
        self.assertNotIn('embedding', response.json())
        self.assertEqual(response.headers['cache-control'], 'no-store')
        self.assertEqual(response.json()['liveness'], 'not_evaluated')
        self.assertIs(extract.call_args.args[1], self.detector)
        self.assertIs(extract.call_args.kwargs['quality_assessor'], assess_portrait)
        self.assertEqual(response.json()['quality_policy'], POLICY)

    def test_portrait_reasons_reach_the_client(self):
        for reason in ('eye_obstruction', 'not_frontal', 'facial_features_unclear',
                       'face_not_centered', 'face_too_large', 'low_resolution'):
            with self.subTest(reason=reason), patch('photo_check_service.extract_enrollment_embedding', side_effect=ValueError(reason)):
                with TestClient(self.app) as client:
                    result = client.post('/check', content=self.image, headers=self.headers).json()
            self.assertFalse(result['accepted'])
            self.assertEqual(result['reason'], reason)

    def test_quality_failure_is_rejected_not_a_matching_failure(self):
        with patch('photo_check_service.extract_enrollment_embedding', side_effect=ValueError('multiple_faces')):
            with TestClient(self.app) as client:
                result = client.post('/check', content=self.image, headers=self.headers).json()
        self.assertFalse(result['accepted'])
        self.assertEqual(result['reason'], 'multiple_faces')

    def test_missing_embedding_preserves_basic_quality_reason_over_http(self):
        # Ejecuta la extraccion real: no simula directamente el error que esperamos.
        from face_quality import assess_face
        for dark in (False, True):
            with self.subTest(dark=dark):
                image = np.full((480, 480, 3), 120, dtype=np.uint8)
                if dark:
                    y, x = np.mgrid[:480, :480]
                    image[:] = ((x + y) % 2 * 25)[..., None]
                face = SimpleNamespace(
                    bbox=np.array([80., 80., 400., 400.]),
                    det_score=.99,
                    normed_embedding=None,
                )
                reason = assess_face(image, face).reason
                self.assertEqual(reason, 'poor_lighting' if dark else 'blurred')
                detector = SimpleNamespace(
                    model_id='sface_yunet',
                    model_fingerprint='test',
                    embedding_dim=128,
                    detect=lambda _: [face],
                )
                app = create_app(detector_factory=lambda: detector, token='test-only')
                output = BytesIO()
                Image.fromarray(image).save(output, format='PNG')
                with TestClient(app) as client:
                    result = client.post('/check', content=output.getvalue(), headers={
                        'Authorization': 'Bearer test-only', 'Content-Type': 'image/png',
                    }).json()
                self.assertFalse(result['accepted'])
                self.assertEqual(result['reason'], reason)

    def test_limits_and_corruption_do_not_call_model(self):
        with patch('photo_check_service.extract_enrollment_embedding') as extract:
            with TestClient(self.app) as client:
                self.assertEqual(client.post('/check', content=b'x' * 20001, headers=self.headers).status_code, 413)
                self.assertEqual(client.post('/check', content=iter([b'x' * 12000, b'x' * 12000]), headers=self.headers).status_code, 413)
                corrupt = client.post('/check', content=b'bad jpeg', headers=self.headers)
                self.assertFalse(corrupt.json()['accepted'])
                output = BytesIO()
                Image.new('RGB', (200, 200)).save(output, format='JPEG')
                large = client.post('/check', content=output.getvalue(), headers=self.headers)
                self.assertEqual(large.json()['reason'], 'image_dimensions_exceeded')
        extract.assert_not_called()

    def test_missing_secret_prevents_startup(self):
        app = create_app(detector_factory=self.factory, token='')
        with self.assertRaises(RuntimeError):
            with TestClient(app):
                pass
        self.factory.assert_not_called()

    def test_concurrent_inference_is_rejected_until_first_finishes(self):
        entered, release = threading.Event(), threading.Event()

        def extraction(*_args, **_kwargs):
            entered.set()
            if not release.wait(5):
                raise RuntimeError('Test did not release inference')
            return np.ones(128)

        with patch('photo_check_service.extract_enrollment_embedding', side_effect=extraction):
            with TestClient(self.app) as client, ThreadPoolExecutor(1) as pool:
                first = pool.submit(client.post, '/check', content=self.image, headers=self.headers)
                try:
                    self.assertTrue(entered.wait(5))
                    self.assertEqual(client.post('/check', content=self.image, headers=self.headers).status_code, 429)
                finally:
                    release.set()
                self.assertTrue(first.result().json()['accepted'])


if __name__ == '__main__':
    unittest.main()
