"""Download the fixed SFHQ archive and copy 20 unmodified synthetic JPEGs.

Only updates bundled fixtures, never a user's avatar or biometric reference.
Run: python scripts/download-demo-avatars.py
"""
import hashlib
import io
import json
from pathlib import Path
from urllib.request import urlopen
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1]
URL = 'https://huggingface.co/datasets/pravsels/SFHQ_256/resolve/main/shard_10_of_86.zip'
ARCHIVE_SHA256 = '6029e014fafb7cbc089eaabffc8be10c878d83210be45884c9a835066db65ac8'
# One-based positions, visually checked adult portraits; aligned with fixture names.
PORTRAITS = [21, 1, 2, 4, 24, 5, 25, 7, 31, 10, 12, 16, 18, 19, 30, 20, 27, 22, 35, 32]


def main():
    archive = ROOT / '.local' / 'synthetic-faces.zip'
    archive.parent.mkdir(parents=True, exist_ok=True)
    if not archive.exists() or hashlib.sha256(archive.read_bytes()).hexdigest() != ARCHIVE_SHA256:
        with urlopen(URL, timeout=120) as response:
            data = response.read(50_000_001)
        if hashlib.sha256(data).hexdigest() != ARCHIVE_SHA256:
            raise RuntimeError('Unexpected synthetic image archive; no fixtures were changed.')
        archive.write_bytes(data)
    target = ROOT / 'database' / 'data' / 'demo' / 'avatars'
    target.mkdir(parents=True, exist_ok=True)
    manifest = {
        'source': 'https://github.com/SelfishGene/SFHQ-dataset',
        'mirror': URL, 'archive_sha256': ARCHIVE_SHA256,
        'description': 'Photorealistic AI-generated SFHQ portraits, local demonstration only. Names and stories are fictional.',
        'images': [],
    }
    with ZipFile(io.BytesIO(archive.read_bytes())) as bundle:
        # Fixed members make re-downloads reproducible; no resizing or editing.
        for index, position in enumerate(PORTRAITS, start=1):
            source = bundle.namelist()[position - 1]
            data = bundle.read(source)
            if not data.startswith(b'\xff\xd8') or len(data) > 1_000_000:
                raise RuntimeError('Invalid demo JPEG')
            filename = f'{index:02}.jpg'
            (target / filename).write_bytes(data)
            manifest['images'].append({'file': filename, 'source_member': source, 'sha256': hashlib.sha256(data).hexdigest()})
    (target / 'manifest.json').write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    print('20 synthetic portraits saved in database/data/demo/avatars (no runtime downloads).')


if __name__ == '__main__':
    main()
