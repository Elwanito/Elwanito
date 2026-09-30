from pathlib import Path

from fastapi import FastAPI
from fastapi.responses import FileResponse
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel, Field
from dataclasses import asdict

import detector
import humanizer

app = FastAPI(title="TextLab")
STATIC = Path(__file__).parent / "static"


class DetectIn(BaseModel):
    text: str = Field(min_length=1, max_length=50000)


class HumanizeIn(DetectIn):
    target: int = Field(25, ge=0, le=100)
    style: str = Field("", max_length=1000)


@app.post("/api/detect")
def detect(body: DetectIn):
    return asdict(detector.analyze(body.text))


@app.post("/api/humanize")
def humanize(body: HumanizeIn):
    return humanizer.humanize(body.text, target=body.target, style=body.style)


app.mount("/static", StaticFiles(directory=STATIC), name="static")


@app.get("/")
def index():
    return FileResponse(STATIC / "index.html")
