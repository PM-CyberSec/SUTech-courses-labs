import logging
import os
from typing import Dict, Any, List

import pandas as pd

LOGGER = logging.getLogger(__name__)

_OVERRIDE_KEYWORDS = {
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

_MALICIOUS_OVERRIDE_KEYWORDS = {
    "ATTACK",
    "EXPLOIT",
    "TROJAN",
    "MALWARE",
    "SHELLCODE",
    "ROOT",
    "C2",
    "EXFILTRATION",
}


class AIEngine:
    def __init__(self):
        self.model = None
        self.model_version = "unloaded"
        self.features: List[str] = [
            "bytes_sent",
            "src_port",
            "dst_port",
            "severity_score",
            "is_sensitive_file",
        ]
        self.allow_mock = os.getenv("DLDS_ALLOW_MOCK_MODEL", "false").lower() == "true"
        self._load_model()

    def _load_model(self) -> None:
        local_model = os.path.join(os.path.dirname(__file__), "models", "rf_model.pkl")
        project_model = os.path.join(
            os.path.dirname(os.path.dirname(__file__)), "ml", "models", "rf_model.pkl"
        )
        model_path = local_model if os.path.exists(local_model) else project_model
        if not os.path.exists(model_path):
            LOGGER.warning("AI model file not found at %s or %s", local_model, project_model)
            self.model_version = "missing-model"
            return

        try:
            import joblib

            loaded = joblib.load(model_path)
            if isinstance(loaded, dict):
                self.model = loaded.get("model")
                loaded_features = loaded.get("features")
                if isinstance(loaded_features, list) and loaded_features:
                    self.features = loaded_features
                self.model_version = loaded.get("model_version", "bundle-model")
            else:
                self.model = loaded
                self.model_version = "rf-v1.0"

            LOGGER.info("AI model loaded successfully from %s", model_path)
        except Exception as exc:
            LOGGER.exception("Failed to load AI model from %s: %s", model_path, exc)
            self.model = None
            self.model_version = "load-error"

    def extract_features(self, event: Dict[str, Any]) -> Dict[str, Any]:
        """
        Stable feature extraction shared by model and deterministic fallback.
        """
        sev = str(event.get("severity", "")).upper()
        severity_score = 0.0
        if sev == "CRITICAL":
            severity_score = 1.0
        elif sev == "HIGH":
            severity_score = 0.8
        elif sev == "MEDIUM":
            severity_score = 0.5
        elif sev == "LOW":
            severity_score = 0.2

        path = str(event.get("file_path", "") or event.get("file", ""))
        is_sensitive = 1.0 if path.startswith("/etc/") or "secret" in path.lower() else 0.0

        return {
            "bytes_sent": int(event.get("bytes_sent", 0) or 0),
            "src_port": int(event.get("src_port", 0) or 0),
            "dst_port": int(event.get("dst_port", 0) or 0),
            "severity_score": severity_score,
            "is_sensitive_file": is_sensitive,
        }

    def _evidence(self, features: Dict[str, Any]) -> List[str]:
        evidence = []
        if features["bytes_sent"] > 5000:
            evidence.append(
                f"High data transfer detected ({features['bytes_sent']} bytes)"
            )
        if features["severity_score"] >= 0.8:
            evidence.append("Base severity is critically high")
        if features["is_sensitive_file"] > 0:
            evidence.append("Sensitive file access involved")
        return evidence

    def _mock_predict(self, features: Dict[str, Any]) -> Dict[str, Any]:
        """
        Deterministic fallback for controlled environments only.
        """
        score = 0.1
        if features["bytes_sent"] > 5000:
            score += 0.35
        if features["severity_score"] >= 0.8:
            score += 0.35
        if features["is_sensitive_file"] > 0:
            score += 0.30
        score = min(1.0, score)

        if score >= 0.8:
            label = "malicious"
            reason = "Deterministic fallback flagged high-risk indicators."
        elif score >= 0.5:
            label = "suspicious"
            reason = "Deterministic fallback flagged abnormal indicator combination."
        else:
            label = "benign"
            reason = "Deterministic fallback indicates normal behavior."

        return {
            "ai_label": label,
            "confidence": round(score, 2),
            "anomaly_score": self._aligned_anomaly_score(label, score),
            "ai_reason": reason,
        }

    def _aligned_anomaly_score(self, label: str, raw_score: float) -> float:
        score = max(0.0, min(float(raw_score), 1.0))
        normalized_label = str(label or "benign").lower()

        if normalized_label == "benign":
            return round(min(score, 0.30), 2)
        if normalized_label == "suspicious":
            return round(max(score, 0.60), 2)
        if normalized_label == "malicious":
            return round(max(score, 0.85), 2)

        return round(score, 2)

    def _first_keyword(self, event: Dict[str, Any]) -> str | None:
        haystack = " ".join(
            [
                str(event.get("alert_type") or ""),
                str(event.get("description") or ""),
            ]
        ).upper()

        for keyword in sorted(_OVERRIDE_KEYWORDS):
            if keyword in haystack:
                return keyword

        return None

    def _apply_safety_override(self, event: Dict[str, Any], result: Dict[str, Any]) -> Dict[str, Any]:
        event_type = str(event.get("type") or "").lower()
        severity = str(event.get("severity") or "LOW").upper()
        alert_type = str(event.get("alert_type") or "").strip()
        current_label = str(result.get("ai_label") or "benign").lower()
        keyword = self._first_keyword(event)
        prior_reason = str(result.get("ai_reason") or "").strip()

        override_label = None
        override_confidence = None
        override_anomaly = None
        override_reason = None

        if event_type == "alert":
            if severity == "CRITICAL":
                override_label = "malicious"
                override_confidence = 0.98
                override_anomaly = 0.99
                override_reason = "Severity-aware safety override: CRITICAL security alerts cannot be classified as benign."
            elif severity == "HIGH" and keyword:
                override_label = "malicious" if keyword in _MALICIOUS_OVERRIDE_KEYWORDS else "suspicious"
                override_confidence = 0.93 if override_label == "malicious" else 0.86
                override_anomaly = 0.97 if override_label == "malicious" else 0.82
                override_reason = (
                    f"Severity-aware safety override: HIGH alert matched attack keyword '{keyword}'"
                    f"{' in ' + alert_type if alert_type else ''}."
                )
            elif severity == "HIGH" and alert_type:
                override_label = "suspicious"
                override_confidence = 0.84
                override_anomaly = 0.80
                override_reason = "Severity-aware safety override: HIGH alerts with a populated alert signature cannot be benign."
            elif severity == "MEDIUM" and (alert_type or keyword):
                override_label = "suspicious"
                override_confidence = 0.72
                override_anomaly = 0.65
                override_reason = "Severity-aware safety override: MEDIUM alerts with IDS context require at least a suspicious label."

        if override_label is not None:
            result["ai_label"] = override_label
            result["confidence"] = round(override_confidence, 2)
            result["anomaly_score"] = round(override_anomaly, 2)
            if current_label == override_label and prior_reason:
                result["ai_reason"] = prior_reason
            else:
                result["ai_reason"] = (
                    f"{override_reason} Previous classification was '{current_label or 'unknown'}'."
                    if current_label != override_label
                    else override_reason
                )
            return result

        result["anomaly_score"] = self._aligned_anomaly_score(
            current_label,
            float(result.get("anomaly_score") or result.get("confidence") or 0.0),
        )
        result["confidence"] = round(max(0.0, min(float(result.get("confidence") or 0.0), 1.0)), 2)
        return result

    def predict(self, event: Dict[str, Any]) -> Dict[str, Any]:
        features = self.extract_features(event)
        evidence = self._evidence(features)

        if self.model is None:
            if not self.allow_mock:
                return {
                    "ai_label": "unscored",
                    "confidence": 0.0,
                    "anomaly_score": 0.0,
                    "ai_reason": (
                        "AI model unavailable. Set DLDS_ALLOW_MOCK_MODEL=true only in "
                        "non-production labs."
                    ),
                    "ai_evidence": evidence,
                    "model_version": self.model_version,
                    "correlation_summary": "AI unscored: model unavailable.",
                }
            mock = self._mock_predict(features)
            result = {
                **mock,
                "ai_evidence": evidence,
                "model_version": "deterministic-fallback",
                "correlation_summary": (
                    f"AI classified as {mock['ai_label']}. {mock['ai_reason']}"
                ),
            }
            return self._apply_safety_override(event, result)

        row = {name: features.get(name, 0) for name in self.features}
        df = pd.DataFrame([row], columns=self.features)

        prediction = self.model.predict(df)[0]
        probs = None
        if hasattr(self.model, "predict_proba"):
            probs = self.model.predict_proba(df)[0]

        if isinstance(prediction, str):
            label = prediction.lower()
            label = "malicious" if label == "malicious" else ("suspicious" if label == "suspicious" else "benign")
        else:
            label_map = {2: "malicious", 1: "suspicious", 0: "benign"}
            label = label_map.get(int(prediction), "benign")

        confidence = round(float(max(probs)) if probs is not None else 0.75, 2)
        if label == "malicious":
            reason = "Model predicted malicious behavior from learned attack patterns."
        elif label == "suspicious":
            reason = "Model predicted suspicious behavior from traffic deviations."
        else:
            reason = "Model predicted benign behavior."

        result = {
            "ai_label": label,
            "confidence": confidence,
            "anomaly_score": self._aligned_anomaly_score(label, confidence),
            "ai_reason": reason,
            "ai_evidence": evidence,
            "model_version": self.model_version,
            "correlation_summary": f"AI classified as {label}. {reason}",
        }
        return self._apply_safety_override(event, result)
