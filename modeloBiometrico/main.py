"""Start only the private photo-check service, never the club gallery server."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent / 'src'))
from config import ROOT_DIR  # Loads this service's .env before creating the app.

if __name__ == '__main__':
    if len(sys.argv) > 1 and sys.argv[1] != 'photo-check':
        raise SystemExit('Uso: python main.py photo-check')
    from photo_check_service import main
    main()
