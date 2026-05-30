import json
import os
from typing import Optional


class KafkaSink:
    """
    Lightweight Kafka publisher for enriched events.

    Enabled only when PIPELINE_KAFKA_ENABLED=true and confluent-kafka is installed.
    """

    def __init__(self) -> None:
        enabled = os.getenv("PIPELINE_KAFKA_ENABLED", "false").lower() == "true"
        self._enabled = enabled
        self._producer = None
        self._topic = os.getenv("PIPELINE_KAFKA_TOPIC", "dlds.enriched")

        if not enabled:
            return

        try:
            from confluent_kafka import Producer
        except Exception as exc:  # pragma: no cover - optional dependency
            print(f"[KafkaSink] Disabled: confluent-kafka not available ({exc})")
            self._enabled = False
            return

        bootstrap = os.getenv("PIPELINE_KAFKA_BOOTSTRAP_SERVERS", "127.0.0.1:19092")
        self._producer = Producer(
            {
                "bootstrap.servers": bootstrap,
                "enable.idempotence": True,
                "acks": "all",
                "linger.ms": int(os.getenv("PIPELINE_KAFKA_LINGER_MS", "10")),
                "compression.type": os.getenv("PIPELINE_KAFKA_COMPRESSION", "lz4"),
                "delivery.timeout.ms": int(
                    os.getenv("PIPELINE_KAFKA_DELIVERY_TIMEOUT_MS", "120000")
                ),
                "max.in.flight.requests.per.connection": 5,
            }
        )

    @property
    def enabled(self) -> bool:
        return self._enabled and self._producer is not None

    def publish(self, event: dict, topic: Optional[str] = None) -> None:
        if not self.enabled:
            return

        payload = json.dumps(event, ensure_ascii=False).encode("utf-8")
        key = str(event.get("event_id", "")).encode("utf-8") or None
        target = topic or self._topic

        # Process callbacks quickly and avoid queue growth.
        self._producer.poll(0)
        self._producer.produce(target, key=key, value=payload)

    def flush(self, timeout: float = 2.0) -> None:
        if self.enabled:
            self._producer.flush(timeout)
