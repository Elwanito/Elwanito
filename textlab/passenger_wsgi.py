# cPanel "Setup Python App" entry point (Passenger is WSGI, FastAPI is ASGI).
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from a2wsgi import ASGIMiddleware
from app import app

application = ASGIMiddleware(app)
