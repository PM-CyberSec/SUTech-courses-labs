#!/usr/bin/env bash
set -euo pipefail

ACTION="${1:-create}"
BOOTSTRAP="${KAFKA_BOOTSTRAP_SERVERS:-127.0.0.1:19092}"
KAFKA_CLI="${KAFKA_TOPICS_BIN:-kafka-topics.sh}"
PARTITIONS="${KAFKA_DEFAULT_PARTITIONS:-12}"
REPLICATION_FACTOR="${KAFKA_REPLICATION_FACTOR:-1}"
MIN_INSYNC_REPLICAS="${KAFKA_MIN_INSYNC_REPLICAS:-1}"
KAFKA_CLI_CMD=("${KAFKA_CLI}")

choose_container_cli() {
  if docker exec dlds_kafka_1 command -v kafka-topics >/dev/null 2>&1; then
    KAFKA_CLI_CMD=(docker exec dlds_kafka_1 kafka-topics)
    return
  fi

  if docker exec dlds_kafka_1 command -v kafka-topics.sh >/dev/null 2>&1; then
    KAFKA_CLI_CMD=(docker exec dlds_kafka_1 kafka-topics.sh)
    return
  fi

  echo "[Kafka] Neither kafka-topics nor kafka-topics.sh exists in dlds_kafka_1."
  exit 1
}

create_topic() {
  local topic="$1"
  local retention_ms="${2:-604800000}"

  "${KAFKA_CLI_CMD[@]}" \
    --bootstrap-server "${BOOTSTRAP}" \
    --create \
    --if-not-exists \
    --topic "${topic}" \
    --partitions "${PARTITIONS}" \
    --replication-factor "${REPLICATION_FACTOR}" \
    --config min.insync.replicas="${MIN_INSYNC_REPLICAS}" \
    --config retention.ms="${retention_ms}"
}

if ! command -v "${KAFKA_CLI}" >/dev/null 2>&1; then
  if docker ps --format '{{.Names}}' | grep -q '^dlds_kafka_1$'; then
    choose_container_cli
    if [[ -z "${KAFKA_BOOTSTRAP_SERVERS:-}" ]]; then
      BOOTSTRAP="kafka-1:29092"
    fi
  else
    echo "[Kafka] Kafka topic CLI not found and dlds_kafka_1 is not running."
    echo "        Start docker-compose.kafka.yml first, or install Kafka client tools."
    exit 1
  fi
fi

if [[ "${ACTION}" == "list" ]]; then
  echo "[Kafka] Listing DLDS topics on ${BOOTSTRAP}"
  "${KAFKA_CLI_CMD[@]}" --bootstrap-server "${BOOTSTRAP}" --list
  exit 0
fi

if [[ "${ACTION}" != "create" ]]; then
  echo "Usage: $0 [create|list]"
  exit 2
fi

echo "[Kafka] Creating DLDS topics on ${BOOTSTRAP}"
echo "[Kafka] partitions=${PARTITIONS} replication_factor=${REPLICATION_FACTOR} min_insync_replicas=${MIN_INSYNC_REPLICAS}"
create_topic "dlds.raw.telemetry"
create_topic "dlds.raw.zeek"
create_topic "dlds.raw.suricata"
create_topic "dlds.enriched"
create_topic "dlds.alerts"
create_topic "dlds.dlq" "1209600000"
echo "[Kafka] Topic provisioning complete."
