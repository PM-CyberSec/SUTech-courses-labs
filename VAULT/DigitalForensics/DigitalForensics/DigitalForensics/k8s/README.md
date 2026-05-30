# DLDS Kubernetes Deployment Guide

This folder contains a Kubernetes-ready baseline for DLDS operational deployment.

## Prerequisites

- Kubernetes cluster (v1.27+)
- `kubectl` and `kustomize`
- Kafka service reachable by DNS:
  - `kafka-1.kafka.svc.cluster.local:9092`
  - `kafka-2.kafka.svc.cluster.local:9092`
  - `kafka-3.kafka.svc.cluster.local:9092`
- Elasticsearch + Kibana + Logstash deployed in cluster (operator or Helm recommended)

## Apply Base

```bash
kubectl apply -k k8s/base
```

## Apply Production Overlay

```bash
kubectl apply -k k8s/overlays/production
```

## What Gets Deployed

- `dlds-pipeline` deployment (with Filebeat sidecar)
- HPA for pipeline pods
- Persistent volume claim for ops reports and baseline/current data
- Drift monitor CronJob (hourly)
- Chaos drill CronJob (weekly Logstash restart)
- RBAC with minimal permissions for chaos runner

## Required Secret

`k8s/base/secret-laravel-api.example.yaml` is an example only.
Create a real secret before applying to production:

```bash
kubectl -n dlds-soc create secret generic dlds-secrets \
  --from-literal=LARAVEL_API_URL='http://laravel-api.dlds-soc.svc.cluster.local/api/dlds/events'
```

## Operational Checks

```bash
kubectl -n dlds-soc get deploy,pods,hpa,cronjobs
kubectl -n dlds-soc logs deploy/dlds-pipeline -c pipeline --tail=100
kubectl -n dlds-soc logs deploy/dlds-pipeline -c filebeat-sidecar --tail=100
```

## Notes

- Replace image `ghcr.io/your-org/dlds-pipeline` with your built/pushed image.
- For true production HA, use managed storage classes and multi-zone node pools.
