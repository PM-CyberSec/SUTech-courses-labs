# ELK + Kafka Workflow Guide

This document describes the production DLDS telemetry path:

`Pipeline -> local JSON logs -> Filebeat (disk queue) -> Kafka (replicated topic) -> Logstash (persistent queue) -> Elasticsearch -> Kibana`

## 1. Start the Streaming and ELK Layers

```bash
docker compose --project-name dlds_kafka -f docker-compose.kafka.yml up -d
./scripts/create_kafka_topics.sh
docker compose --project-name dlds_elk -f docker-compose.elk.yml up -d
docker ps
```

## 2. Verify Health

- Elasticsearch: `curl http://localhost:9200`
- Kibana: `http://localhost:5601`
- Kafka UI: `http://localhost:8085`
- Logstash API: `curl http://localhost:9600`

## 3. Run the Real-Time Pipeline

```bash
export PYTHONPATH=$(pwd)
export PIPELINE_KAFKA_ENABLED=true
export PIPELINE_KAFKA_BOOTSTRAP_SERVERS=127.0.0.1:19092
export PIPELINE_KAFKA_TOPIC=dlds.enriched
python3 pipeline/main.py
```

Notes:
- `OutputManager` always writes local JSON/CSV first.
- Filebeat tails `data/logs/*.json` and publishes to `dlds.raw.telemetry`.
- Deterministic `event_id` is generated and used as Elasticsearch `document_id` for dedup on retries.

## 4. Zero-Data-Loss Controls

- Filebeat `queue.disk` enabled (`10GB`).
- Local Kafka topics are created with RF=1 and `min.insync.replicas=1`.
- Logstash `queue.type: persisted` and DLQ enabled.
- Elasticsearch indexing uses stable `event_id` to avoid duplicate event inflation.

## 5. Kibana Data View Setup

1. Open Kibana at `http://localhost:5601`.
2. Go to `Stack Management -> Data Views`.
3. Create data view:
4. Name: `DLDS Events`
5. Index pattern: `dlds-events-*`
6. Timestamp field: `@timestamp`

## 6. Recommended Dashboards

- Event Throughput: Count over `@timestamp`.
- Threat Label Split: `ml_label` pie (`benign/suspicious/malicious/unscored`).
- Top Talkers: `src_ip`, `dst_ip`.
- Top Target Ports: `dst_port`.
- High Severity Alerts: filter `severity >= 2`.
- MITRE Replay Timeline: filter by replay tags and inspect anomaly spikes.
