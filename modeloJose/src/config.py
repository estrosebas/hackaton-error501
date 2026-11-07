"""Local YuNet/SFace configuration, isolated from the original club."""
import os
from pathlib import Path
from dotenv import load_dotenv

ROOT_DIR = Path(__file__).resolve().parents[1]
load_dotenv(ROOT_DIR / '.env')
MODEL_NAME = 'sface_yunet'
EMBEDDING_MODEL_ID = MODEL_NAME
EMBEDDING_DIM = 128
CPU_THREADS = int(os.getenv('FACE_CPU_THREADS', '2'))
DETECTION_SIZE = (640, 640)
