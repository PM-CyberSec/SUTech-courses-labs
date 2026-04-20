"""Shared configuration helpers for the DLDS detection engine."""

from __future__ import annotations

import logging
import os
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterable

from dotenv import load_dotenv
from requests import Session
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

_LOGGER = logging.getLogger(__name__)
_ENV_LOADED = False


def load_environment() -> None:
    """Load .env from detection-engine and project root once."""
    global _ENV_LOADED
    if _ENV_LOADED:
        return

    base_dir = Path(__file__).resolve().parent
    root_dir = base_dir.parent

    load_dotenv(base_dir / ".env", override=False)
    load_dotenv(root_dir / ".env", override=False)

    _ENV_LOADED = True


def getenv_str(name: str, default: str = "") -> str:
    load_environment()
    return os.environ.get(name, default).strip()


def getenv_int(name: str, default: int) -> int:
    raw = getenv_str(name, str(default))
    try:
        return int(raw)
    except ValueError:
        _LOGGER.warning("Invalid integer for %s=%r, using default=%d", name, raw, default)
        return default


def getenv_float(name: str, default: float) -> float:
    raw = getenv_str(name, str(default))
    try:
        return float(raw)
    except ValueError:
        _LOGGER.warning("Invalid float for %s=%r, using default=%s", name, raw, default)
        return default


def getenv_bool(name: str, default: bool = False) -> bool:
    raw = getenv_str(name, "1" if default else "0").lower()
    return raw in {"1", "true", "yes", "y", "on"}


def detection_api_url() -> str:
    """Prefer DLDS_API_URL, fallback to legacy LARAVEL_API_URL."""
    url = getenv_str("DLDS_API_URL")
    if url:
        return url
    return getenv_str("LARAVEL_API_URL")


def parse_timestamp_to_utc_iso(value: object) -> str:
    """
    Convert various timestamp formats to an ISO8601 UTC string.
    Accepts:
    - UNIX epoch int/float
    - ISO8601 strings with Z / ±HH:MM / ±HHMM offsets
    """
    if isinstance(value, (int, float)):
        return datetime.fromtimestamp(float(value), tz=timezone.utc).isoformat()

    if isinstance(value, str):
        raw = value.strip()
        if raw:
            if raw.isdigit():
                return datetime.fromtimestamp(int(raw), tz=timezone.utc).isoformat()

            try:
                return datetime.fromtimestamp(float(raw), tz=timezone.utc).isoformat()
            except ValueError:
                pass

            normalized = raw
            if normalized.endswith("Z"):
                normalized = normalized[:-1] + "+00:00"

            try:
                return datetime.fromisoformat(normalized).astimezone(timezone.utc).isoformat()
            except ValueError:
                pass

            for fmt in (
                "%Y-%m-%dT%H:%M:%S.%f%z",
                "%Y-%m-%dT%H:%M:%S%z",
                "%Y-%m-%d %H:%M:%S.%f%z",
                "%Y-%m-%d %H:%M:%S%z",
                "%Y-%m-%dT%H:%M:%S.%f",
                "%Y-%m-%dT%H:%M:%S",
                "%Y-%m-%d %H:%M:%S.%f",
                "%Y-%m-%d %H:%M:%S",
            ):
                try:
                    parsed = datetime.strptime(normalized, fmt)
                    if parsed.tzinfo is None:
                        parsed = parsed.replace(tzinfo=timezone.utc)
                    return parsed.astimezone(timezone.utc).isoformat()
                except ValueError:
                    continue

    return datetime.now(timezone.utc).isoformat()


def _existing_first(paths: Iterable[Path]) -> str:
    for path in paths:
        if path.exists() and path.is_file():
            return str(path)
    return ""


def suricata_eve_path() -> str:
    """
    Resolve Suricata eve.json path with backward compatibility aliases.
    """
    direct = getenv_str("SURICATA_EVE_PATH")
    if direct:
        return direct

    legacy = getenv_str("SURICATA_EVE")
    if legacy:
        return legacy

    discovered = _existing_first(
        [
            Path("/var/log/suricata/eve.json"),
            Path("/usr/local/var/log/suricata/eve.json"),
        ]
    )
    return discovered or "/var/log/suricata/eve.json"


def zeek_conn_log_path() -> str:
    """
    Resolve Zeek conn.log path via explicit file, directory, then common defaults.
    """
    explicit = getenv_str("ZEEK_CONN_LOG")
    if explicit:
        return explicit

    zeek_log_dir = getenv_str("ZEEK_LOG_DIR")
    if zeek_log_dir:
        return str(Path(zeek_log_dir) / "conn.log")

    discovered = _existing_first(
        [
            Path("/opt/zeek/logs/current/conn.log"),
            Path("/usr/local/zeek/logs/current/conn.log"),
            Path("/var/log/zeek/current/conn.log"),
            Path("/nsm/zeek/logs/current/conn.log"),
        ]
    )
    return discovered or "/opt/zeek/logs/current/conn.log"


def detection_api_key() -> str:
    return getenv_str("DLDS_API_KEY")


def detection_api_headers() -> dict[str, str]:
    headers = {
        "Content-Type": "application/json",
        "User-Agent": "dlds-detection-engine/1.0",
    }
    api_key = detection_api_key()
    if api_key:
        headers["X-API-KEY"] = api_key
    return headers


def detection_api_timeout() -> float:
    return max(1.0, getenv_float("DLDS_HTTP_TIMEOUT", 10.0))


def build_http_session() -> Session:
    retry = Retry(
        total=getenv_int("DLDS_HTTP_RETRIES", 3),
        connect=getenv_int("DLDS_HTTP_RETRIES", 3),
        read=getenv_int("DLDS_HTTP_RETRIES", 3),
        backoff_factor=getenv_float("DLDS_HTTP_BACKOFF", 0.5),
        status_forcelist=(429, 500, 502, 503, 504),
        allowed_methods=frozenset({"POST", "GET"}),
        raise_on_status=False,
    )
    adapter = HTTPAdapter(max_retries=retry)

    session = Session()
    session.mount("http://", adapter)
    session.mount("https://", adapter)
    session.headers.update(detection_api_headers())
    return session


def setup_logging() -> None:
    load_environment()
    level_name = getenv_str("DLDS_LOG_LEVEL", "INFO").upper()
    level = getattr(logging, level_name, logging.INFO)

    logging.basicConfig(
        level=level,
        format="%(asctime)s %(levelname)s [%(name)s] %(message)s",
    )

    if not getenv_bool("DLDS_HTTP_VERBOSE_RETRIES", default=False):
        logging.getLogger("urllib3.connectionpool").setLevel(logging.ERROR)
