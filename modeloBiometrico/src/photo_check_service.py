"""Private photo enrollment check with stricter public portrait quality gates."""
from contextlib import asynccontextmanager
from io import BytesIO
import os
import secrets
import threading
import time
import warnings

from fastapi import Depends, FastAPI, HTTPException, Request
from fastapi.concurrency import run_in_threadpool
from PIL import Image, UnidentifiedImageError

from detect_faces import FaceDetector
from enrollment import extract_enrollment_embedding
from recognition_service import decode_image
from portrait_quality import POLICY, assess_portrait

REASONS = {
    'no_face': 'No encontramos un rostro. Mira de frente a la camara.',
    'multiple_faces': 'Debe aparecer una sola persona en la fotografia.',
    'face_cut_off': 'Tu rostro esta cortado. Alejate un poco y toma otra foto.',
    'face_too_small': 'Acerca un poco tu rostro a la camara.',
    'face_too_large': 'Alejate un poco hasta que tu rostro quede dentro del ovalo.',
    'face_not_centered': 'Centra tu rostro en el ovalo y deja visibles tus ojos.',
    'low_resolution': 'La imagen tiene poca resolucion. Prueba otra camara o dispositivo.',
    'blurred': 'La fotografia esta borrosa. Mantente quieto y vuelve a tomarla.',
    'poor_lighting': 'Mejora la iluminacion de tu rostro y vuelve a tomar la foto.',
    'weak_detection': 'No vemos tu rostro con suficiente claridad.',
    'not_frontal': 'Mira de frente, sin girar ni inclinar demasiado la cabeza.',
    'eye_obstruction': 'Hay reflejos que ocultan tus ojos. Quitate los lentes para la captura y evita la luz de pantallas.',
    'facial_features_unclear': 'No se distinguen bien tus ojos, nariz y boca. Limpia la camara, mejora la luz y mantente quieto.',
}


def create_app(*, detector_factory=FaceDetector, token=None, max_bytes=None, max_pixels=None):
    secret = token if token is not None else os.getenv('FACE_PHOTO_CHECK_TOKEN', '')
    byte_limit = max_bytes if max_bytes is not None else int(os.getenv('FACE_PHOTO_CHECK_MAX_BYTES', 4 * 1024 * 1024))
    pixel_limit = max_pixels if max_pixels is not None else int(os.getenv('FACE_PHOTO_CHECK_MAX_PIXELS', 16000000))
    busy = threading.Lock()

    @asynccontextmanager
    async def lifespan(app):
        if not secret or byte_limit <= 0 or pixel_limit <= 0:
            raise RuntimeError('Configure FACE_PHOTO_CHECK_TOKEN y limites positivos antes de iniciar photo-check.')
        app.state.detector = detector_factory()
        yield
        app.state.detector = None

    async def authenticated(request: Request):
        if not secrets.compare_digest(request.headers.get('authorization', '').encode('utf-8'), f'Bearer {secret}'.encode('utf-8')):
            raise HTTPException(401, 'Credencial de validacion invalida.')

    app = FastAPI(title='Vecino Private Photo Check', lifespan=lifespan,
                  docs_url=None, redoc_url=None, openapi_url=None,
                  dependencies=[Depends(authenticated)])

    @app.middleware('http')
    async def private_response(request, call_next):
        response = await call_next(request)
        response.headers['Cache-Control'] = 'no-store'
        return response

    @app.get('/health')
    def health():
        detector = app.state.detector
        return {'status': 'ok', 'model': detector.model_id,
                'model_fingerprint': detector.model_fingerprint, 'purpose': 'enrollment_quality',
                'quality_policy': POLICY}

    @app.post('/extract')
    @app.post('/check')
    async def check(request: Request):
        if request.headers.get('content-type', '').split(';')[0].lower() not in {
                'image/jpeg', 'image/png', 'image/webp', 'application/octet-stream'}:
            raise HTTPException(415, 'Envie bytes JPEG, PNG o WebP.')
        try:
            declared = int(request.headers.get('content-length', '0'))
        except ValueError:
            raise HTTPException(400, 'Content-Length invalido.')
        if declared < 0 or declared > byte_limit:
            raise HTTPException(413, 'La fotografia excede el limite de peso.')
        if not busy.acquire(blocking=False):
            raise HTTPException(429, 'Validador ocupado. Vuelva a intentar.', headers={'Retry-After': '2'})
        try:
            data = bytearray()
            async for chunk in request.stream():
                if len(data) + len(chunk) > byte_limit:
                    raise HTTPException(413, 'La fotografia excede el limite de peso.')
                data.extend(chunk)
            if not data:
                raise HTTPException(422, 'La fotografia esta vacia.')
            started = time.monotonic()

            def evaluate():
                try:
                    # Reject decompression bombs before OpenCV allocates the decoded image.
                    with warnings.catch_warnings():
                        warnings.simplefilter('error', Image.DecompressionBombWarning)
                        with Image.open(BytesIO(data)) as probe:
                            if probe.format not in {'JPEG', 'PNG', 'WEBP'}:
                                raise ValueError('unsupported_image')
                            if probe.width * probe.height > pixel_limit:
                                raise ValueError('image_dimensions_exceeded')
                            probe.verify()
                    image = decode_image(bytes(data))
                    embedding = extract_enrollment_embedding(image, app.state.detector,
                                                             quality_assessor=assess_portrait)
                    result = {'accepted': True, 'reason': 'ok', 'message': 'Fotografia procesable para el registro facial.'}
                    # Only Laravel calls /extract with the private service token.
                    # /check never exposes a template; this process stores no gallery.
                    if request.url.path == '/extract':
                        result['embedding'] = embedding.tolist()
                    return result
                except (ValueError, UnidentifiedImageError, OSError, Image.DecompressionBombError,
                        Image.DecompressionBombWarning) as exc:
                    reason = str(exc) if isinstance(exc, ValueError) else 'unreadable_image'
                    return {'accepted': False, 'reason': reason if reason in REASONS or reason in {
                        'image_dimensions_exceeded', 'invalid_embedding', 'unsupported_image', 'unreadable_image'
                    } else 'face_quality_rejected',
                            'message': REASONS.get(reason, 'No pudimos procesar el rostro. Toma otra foto clara y de frente.')}

            result = await run_in_threadpool(evaluate)
            return {**result, 'model': app.state.detector.model_id,
                    'quality_policy': POLICY,
                    'model_fingerprint': app.state.detector.model_fingerprint,
                    'elapsed_ms': round((time.monotonic() - started) * 1000, 1),
                    'liveness': 'not_evaluated'}
        finally:
            busy.release()

    return app


app = create_app()


def main():
    import uvicorn
    uvicorn.run('photo_check_service:app', host='127.0.0.1',
                port=int(os.getenv('FACE_PHOTO_CHECK_PORT', '8782')), workers=1,
                limit_concurrency=8, timeout_keep_alive=5)


if __name__ == '__main__':
    main()
