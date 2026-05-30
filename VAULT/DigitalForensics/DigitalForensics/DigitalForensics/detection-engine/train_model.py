"""
Compatibility wrapper.

Production training lives in ../ml/train_model.py and requires real datasets.
This script delegates to it, then mirrors the model artifact into detection-engine/models
for legacy loaders.
"""

from __future__ import annotations

import os
import shutil
import subprocess
import sys
from pathlib import Path


def main() -> int:
    here = Path(__file__).resolve().parent
    project_root = here.parent
    ml_script = project_root / "ml" / "train_model.py"
    if not ml_script.exists():
        print(f"[!] Training script not found: {ml_script}")
        return 1

    cmd = [sys.executable, str(ml_script)]
    cmd.extend(sys.argv[1:])
    rc = subprocess.call(cmd, cwd=str(project_root))
    if rc != 0:
        return rc

    src = project_root / "ml" / "models" / "rf_model.pkl"
    dst_dir = here / "models"
    dst_dir.mkdir(parents=True, exist_ok=True)
    dst = dst_dir / "rf_model.pkl"
    if src.exists():
        shutil.copy2(src, dst)
        print(f"[✔] Mirrored trained model to {dst}")
    else:
        print(f"[!] Expected trained model not found at {src}")
        return 2
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

