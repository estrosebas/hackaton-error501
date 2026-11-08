import sys
from io import BytesIO
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

import numpy as np
from fastapi.testclient import TestClient
from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'src'))
from photo_check_service import create_app


def test_extract_is_private_and_check_has_no_template():
    detector = SimpleNamespace(model_id='sface_yunet', model_fingerprint='a' * 64)
    app = create_app(token='test-only', detector_factory=lambda: detector)
    output = BytesIO()
    Image.new('RGB', (100, 100), (120, 120, 120)).save(output, format='JPEG')
    headers = {'Authorization': 'Bearer test-only', 'Content-Type': 'image/jpeg'}
    vector = np.ones(128, dtype=np.float32) / np.sqrt(128)
    with patch('photo_check_service.extract_enrollment_embedding', return_value=vector):
        with TestClient(app) as client:
            assert client.post('/extract', content=output.getvalue()).status_code == 401
            extracted = client.post('/extract', content=output.getvalue(), headers=headers).json()
            checked = client.post('/check', content=output.getvalue(), headers=headers).json()
    assert len(extracted['embedding']) == 128
    assert np.isclose(np.linalg.norm(extracted['embedding']), 1)
    assert 'embedding' not in checked
    assert extracted['liveness'] == 'not_evaluated'
