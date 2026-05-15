import os
import joblib
import pandas as pd

class MLEngine:
    def __init__(self, model_path="ml/models/rf_model.pkl"):
        # Make path absolute relative to project root
        base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        abs_model_path = os.path.join(base_dir, model_path)
        
        if os.path.exists(abs_model_path):
            data = joblib.load(abs_model_path)
            self.model = data['model']
            self.features = data['features']
            self.proto_encoder = data['proto_encoder']
            self.flag_encoder = data['flag_encoder']
            self.is_loaded = True
        else:
            self.model = None
            self.is_loaded = False
            
    def _safe_encode(self, encoder, val):
        val_str = str(val)
        if val_str in encoder.classes_:
            return encoder.transform([val_str])[0]
        return 0 # unseen category defaults to 0

    def classify(self, event: dict) -> dict:
        """
        Classifies a normalized network event and appends ML metadata.
        """
        if not self.is_loaded:
            event["ml_label"] = "unscored"
            event["ml_confidence"] = 0.0
            event["evidence_summary"] = "AI model not found. Run training script."
            return event

        # Feature Extraction
        dns_pres = 1 if str(event.get('dns_query', '')).strip() != '' else 0
        http_pres = 1 if str(event.get('http_host', '')).strip() != '' else 0
        tls_pres = 1 if str(event.get('tls_sni', '')).strip() != '' else 0
        
        proto_enc = self._safe_encode(self.proto_encoder, event.get('protocol', ''))
        flags_enc = self._safe_encode(self.flag_encoder, event.get('tcp_flags', ''))
        
        # Build strict feature array
        X = [
            int(event.get("src_port") or 0),
            int(event.get("dst_port") or 0),
            proto_enc,
            int(event.get("bytes_sent") or 0),
            float(event.get("duration") or 0.0),
            int(event.get("severity") or 0),
            int(event.get("frame_length") or event.get("bytes_sent") or 0),
            flags_enc,
            dns_pres,
            http_pres,
            tls_pres
        ]
        
        X_df = pd.DataFrame([X], columns=self.features)
        
        # Prediction
        probs = self.model.predict_proba(X_df)[0]
        prediction = self.model.classes_[probs.argmax()]
        confidence = probs.max()
        
        event["ml_label"] = prediction
        event["ml_confidence"] = round(float(confidence), 4)
        
        # Explainability Logic
        reasons = []
        if prediction == "malicious":
            if int(event.get("severity") or 0) >= 2:
                reasons.append("High IDS severity")
            if int(event.get("bytes_sent") or 0) > 50000:
                reasons.append("Anomalous high byte transfer")
            if int(event.get("dst_port") or 0) in [4444, 1337]:
                reasons.append("Suspicious destination port commonly used by Trojans")
            event["evidence_summary"] = "AI identified Malicious behavior: " + " | ".join(reasons)
        elif prediction == "suspicious":
            event["evidence_summary"] = "AI identified Suspicious behavior based on traffic pattern deviations."
        else:
            event["evidence_summary"] = "Traffic conforms to known benign profiles."

        return event
