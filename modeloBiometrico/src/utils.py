"""Shared logging for Club Mejía services."""
import logging


def setup_logger(name: str = "clubmejia") -> logging.Logger:
    logger = logging.getLogger(name)
    if logger.handlers:
        return logger
    logger.setLevel(logging.INFO)
    handler = logging.StreamHandler()
    handler.setFormatter(logging.Formatter("%(asctime)s [%(levelname)s] %(name)s: %(message)s", datefmt="%H:%M:%S"))
    logger.addHandler(handler)
    logger.propagate = False
    return logger
