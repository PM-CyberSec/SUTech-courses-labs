import os
import json
import logging
import time
import hmac
import hashlib
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional, Dict, Any
import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

try:
    from dotenv import load_dotenv
except ImportError:  # pragma: no cover - optional at runtime
    load_dotenv = None

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

OVERRIDE_KEYWORDS = {
    "ATTACK",
    "EXPLOIT",
    "TROJAN",
    "MALWARE",
    "SHELLCODE",
    "SCAN",
    "COMMAND",
    "ROOT",
    "C2",
    "EXFILTRATION",
    "SUSPICIOUS",
}

MALICIOUS_KEYWORDS = {
    "ATTACK",
    "EXPLOIT",
    "TROJAN",
    "MALWARE",
    "SHELLCODE",
    "ROOT",
    "C2",
    "EXFILTRATION",
}


def _load_runtime_env() -> None:
    if load_dotenv is None:
        return

    root_env = Path(__file__).resolve().parents[1] / ".env"
    if root_env.is_file():
        load_dotenv(root_env, override=False)


class HTTPIngester:
    """Posts normalized events to Laravel DLDS API via HTTP POST."""

    def __init__(self, api_url: Optional[str] = None):
        _load_runtime_env()
        raw_url = (api_url or os.getenv("DLDS_API_URL", "http://localhost:8000")).rstrip("/")
        self.endpoint = raw_url if raw_url.endswith("/api/dlds/events") else f"{raw_url}/api/dlds/events"
        self.api_key = os.getenv("DLDS_API_KEY", "")
        self.hmac_secret = os.getenv("DLDS_HMAC_SECRET", "")
        self.timeout = float(os.getenv("DLDS_API_TIMEOUT_SEC", "10"))
        self.enabled = os.getenv("DLDS_API_INGEST_ENABLED", "true").lower() == "true"
        self.max_retries = int(os.getenv("DLDS_API_MAX_RETRIES", "3"))
        
        self.session = self._create_session()

    def _create_session(self) -> requests.Session:
        """Create a requests session with retry strategy."""
        session = requests.Session()
        retry_strategy = Retry(
            total=self.max_retries,
            backoff_factor=1,
            status_forcelist=[429, 500, 502, 503, 504],
            allowed_methods=["POST"],
        )
        adapter = HTTPAdapter(max_retries=retry_strategy)
        session.mount("http://", adapter)
        session.mount("https://", adapter)
        return session

    def ingest(self, normalized_event: dict) -> bool:
        """
        POST a normalized event to Laravel API.
        Returns True if successful, False otherwise.
        """
        if not self.enabled:
            return True

        if not self.api_key or not self.hmac_secret:
            logger.warning("[HTTP Ingester] Missing DLDS_API_KEY or DLDS_HMAC_SECRET; skipping ingestion")
            return False

        try:
            # Transform normalized pipeline format to Laravel StoreDldsEventRequest format
            payload = self._transform_payload(normalized_event)
            body = json.dumps(payload, separators=(",", ":"))
            timestamp = str(int(time.time()))
            signature = hmac.new(
                self.hmac_secret.encode("utf-8"),
                f"{timestamp}.{body}".encode("utf-8"),
                hashlib.sha256,
            ).hexdigest()
            
            headers = {
                "Content-Type": "application/json",
                "X-API-KEY": self.api_key,
                "X-TIMESTAMP": timestamp,
                "X-SIGNATURE": f"sha256={signature}",
                "User-Agent": "DLDS-Python-Pipeline/1.0",
            }

            response = self.session.post(
                self.endpoint,
                data=body,
                headers=headers,
                timeout=self.timeout,
            )

            if response.status_code in [200, 201]:
                logger.info(f"[HTTP Ingester] Event {payload.get('event_hash', '?')} ingested: {response.status_code}")
                return True
            elif response.status_code == 409:
                logger.debug(f"[HTTP Ingester] Duplicate event {payload.get('event_hash', '?')}: {response.status_code}")
                return True  # Duplicates are expected and OK
            else:
                logger.warning(f"[HTTP Ingester] Failed to ingest event: HTTP {response.status_code} - {response.text[:200]}")
                return False

        except requests.exceptions.Timeout:
            logger.error(f"[HTTP Ingester] Request timeout to {self.endpoint}")
            return False
        except requests.exceptions.ConnectionError:
            logger.error(f"[HTTP Ingester] Connection error to {self.endpoint}")
            return False
        except Exception as e:
            logger.error(f"[HTTP Ingester] Exception while posting event: {e}")
            return False

    def _transform_payload(self, normalized_event: dict) -> dict:
        """
        Transform pipeline normalized event format to Laravel StoreDldsEventRequest.
        Maps pipeline fields to Laravel API expected fields.
        """
        inferred_type = self._infer_event_type(normalized_event)

        payload = {
            # Event metadata
            "timestamp": self._normalize_timestamp(normalized_event.get("timestamp", "")),
            "event_hash": normalized_event.get("event_id", ""),
            
            # Event type determination
            "event_type": inferred_type,
            "type": inferred_type,
            
            # Network fields
            "source_ip": normalized_event.get("src_ip", ""),
            "dest_ip": normalized_event.get("dst_ip", ""),
            "source_port": int(normalized_event.get("src_port") or 0),
            "dest_port": int(normalized_event.get("dst_port") or 0),
            "protocol": normalized_event.get("protocol", ""),
            "bytes_sent": int(normalized_event.get("bytes_sent") or 0),
            
            # Alert/Signature fields (for Suricata)
            "alert_signature": normalized_event.get("alert_signature", ""),
            "alert_category": normalized_event.get("alert_category", ""),
            "severity": self._normalize_severity(normalized_event.get("severity")),
            
            # Process fields
            "pid": int(normalized_event.get("pid") or 0),
            "process": normalized_event.get("process_name", normalized_event.get("comm", "")),
            "process_name": normalized_event.get("process_name", normalized_event.get("comm", "")),
            "executable": normalized_event.get("exe", ""),
            "path": normalized_event.get("file_path", normalized_event.get("name", "")),
            "file_path": normalized_event.get("file_path", normalized_event.get("name", "")),

            # Description and enrichment
            "description": self._build_description(normalized_event),
            
            # ML/AI fields
            "ai_label": normalized_event.get("ml_label", "benign"),
            "confidence": float(normalized_event.get("ml_confidence") or 0.0),
            "anomaly_score": float(normalized_event.get("anomaly_score") or normalized_event.get("ml_confidence") or 0.0),
            "ai_reason": str(normalized_event.get("ai_reason") or ""),
            "ai_evidence": self._normalize_ai_evidence(normalized_event.get("evidence_summary")),
        }

        return self._apply_classification_guard(payload)

    def _infer_event_type(self, event: dict) -> str:
        """Infer event type from normalized event fields."""
        sensor = event.get("sensor", "").lower()
        pid = int(event.get("pid") or 0)
        has_process_hints = any([
            bool(event.get("process_name")),
            bool(event.get("comm")),
            bool(event.get("file_path")),
            bool(event.get("name")),
            pid > 0,
        ])
        
        # Suricata alerts
        if sensor == "suricata" or event.get("alert_signature"):
            return "alert"
        
        # Network/Zeek events
        if sensor == "zeek" or event.get("flow_id"):
            return "network"
        
        # Process events
        if sensor == "auditd" or has_process_hints:
            return "process"
        
        return "network"  # default

    def _build_description(self, event: dict) -> str:
        """Build human-readable description from event fields."""
        parts = []

        process_name = event.get("process_name") or event.get("comm")
        file_path = event.get("file_path") or event.get("name")
        alert_category = event.get("alert_category")

        if event.get("alert_signature"):
            parts.append(f"Alert: {event.get('alert_signature')}")

        if event.get("alert_category"):
            parts.append(f"Category: {event.get('alert_category')}")

        if process_name:
            parts.append(f"Process: {process_name}")

        if file_path:
            parts.append(f"File: {file_path}")

        if event.get("src_ip"):
            parts.append(f"{event.get('src_ip')}:{event.get('src_port', '?')}")
        
        if event.get("dst_ip"):
            parts.append(f"-> {event.get('dst_ip')}:{event.get('dst_port', '?')}")
        
        if event.get("protocol"):
            parts.append(f"proto={event.get('protocol')}")

        if alert_category and not parts:
            parts.append(f"Category: {alert_category}")

        return " ".join(parts) or "Event"

    def _normalize_timestamp(self, value: Any) -> str:
        if isinstance(value, (int, float)):
            return datetime.fromtimestamp(float(value), tz=timezone.utc).isoformat()

        raw = str(value or "").strip()
        if raw == "":
            return datetime.now(timezone.utc).isoformat()

        try:
            return datetime.fromtimestamp(float(raw), tz=timezone.utc).isoformat()
        except ValueError:
            pass

        normalized = raw[:-1] + "+00:00" if raw.endswith("Z") else raw

        try:
            parsed = datetime.fromisoformat(normalized)
        except ValueError:
            return raw

        if parsed.tzinfo is None:
            parsed = parsed.replace(tzinfo=timezone.utc)

        return parsed.astimezone(timezone.utc).isoformat()

    def _normalize_severity(self, value: Any) -> str:
        if isinstance(value, str):
            normalized = value.strip().upper()
            if normalized in {"LOW", "MEDIUM", "HIGH", "CRITICAL"}:
                return normalized

            if normalized.isdigit():
                value = int(normalized)
            else:
                return "LOW"

        try:
            numeric = int(value or 0)
        except (TypeError, ValueError):
            return "LOW"

        if numeric <= 1:
            return "CRITICAL"
        if numeric == 2:
            return "HIGH"
        if numeric == 3:
            return "MEDIUM"
        return "LOW"

    def _normalize_ai_evidence(self, value: Any) -> list[str]:
        if isinstance(value, list):
            return [str(item).strip() for item in value if str(item).strip()]

        text = str(value or "").strip()
        return [text] if text else []

    def _apply_classification_guard(self, payload: dict) -> dict:
        event_type = str(payload.get("type") or "").lower()
        severity = str(payload.get("severity") or "LOW").upper()
        alert_signature = str(payload.get("alert_signature") or "").strip()
        description = str(payload.get("description") or "").strip()
        label = str(payload.get("ai_label") or "benign").lower()
        confidence = self._clamp(float(payload.get("confidence") or 0.0))
        anomaly_score = self._clamp(float(payload.get("anomaly_score") or confidence))
        current_reason = str(payload.get("ai_reason") or "").strip()

        keyword = self._first_keyword(alert_signature, description)
        override_label = None
        override_confidence = None
        override_anomaly = None
        override_reason = None

        if event_type == "alert":
            if severity == "CRITICAL":
                override_label = "malicious"
                override_confidence = 0.98
                override_anomaly = 0.99
                override_reason = "Severity-aware safety override: CRITICAL security alerts cannot be benign."
            elif severity == "HIGH" and keyword:
                override_label = "malicious" if keyword in MALICIOUS_KEYWORDS else "suspicious"
                override_confidence = 0.93 if override_label == "malicious" else 0.86
                override_anomaly = 0.97 if override_label == "malicious" else 0.82
                override_reason = (
                    f"Severity-aware safety override: HIGH alert matched attack keyword '{keyword}'"
                    f"{' in ' + alert_signature if alert_signature else ''}."
                )
            elif severity == "HIGH" and alert_signature:
                override_label = "suspicious"
                override_confidence = 0.84
                override_anomaly = 0.80
                override_reason = "Severity-aware safety override: HIGH IDS alerts cannot be classified as benign."
            elif severity == "MEDIUM" and (alert_signature or keyword) and label == "benign":
                override_label = "suspicious"
                override_confidence = 0.72
                override_anomaly = 0.65
                override_reason = "Severity-aware safety override: MEDIUM IDS alerts require at least a suspicious label."

        if override_label is not None:
            payload["ai_label"] = override_label
            payload["confidence"] = override_confidence
            payload["anomaly_score"] = override_anomaly
            payload["ai_reason"] = (
                f"{override_reason} Previous classification was '{label}'."
                if label != override_label
                else override_reason
            )
            return payload

        payload["ai_label"] = label
        payload["confidence"] = confidence
        payload["anomaly_score"] = self._aligned_anomaly(label, anomaly_score)
        payload["ai_reason"] = current_reason
        return payload

    def _first_keyword(self, alert_signature: str, description: str) -> Optional[str]:
        haystack = f"{alert_signature} {description}".upper()

        for keyword in sorted(OVERRIDE_KEYWORDS):
            if keyword in haystack:
                return keyword

        return None

    def _aligned_anomaly(self, label: str, score: float) -> float:
        normalized = self._clamp(score)

        if label == "malicious":
            return round(max(normalized, 0.85), 4)
        if label == "suspicious":
            return round(max(normalized, 0.60), 4)
        return round(min(normalized, 0.30), 4)

    def _clamp(self, value: float) -> float:
        return min(max(float(value), 0.0), 1.0)
