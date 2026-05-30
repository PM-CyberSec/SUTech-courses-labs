#!/usr/bin/env python3
"""
Run production-like tcpreplay validation scenarios and generate a machine-readable report.
"""

from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
import time
from dataclasses import dataclass, asdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List, Optional
from urllib.error import URLError
from urllib.request import urlopen


def _utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _json_http(url: str, timeout: float = 3.0) -> Optional[Dict[str, Any]]:
    try:
        with urlopen(url, timeout=timeout) as resp:
            return json.loads(resp.read().decode("utf-8", errors="ignore"))
    except (URLError, TimeoutError, json.JSONDecodeError, OSError):
        return None


def _es_count(es_url: str) -> Optional[int]:
    payload = _json_http(f"{es_url.rstrip('/')}/dlds-events-*/_count")
    if not payload:
        return None
    value = payload.get("count")
    return int(value) if isinstance(value, int) else None


def _service_health(es_url: str, kibana_url: str, kafka_ui_url: str) -> Dict[str, str]:
    out = {}
    out["elasticsearch"] = "up" if _json_http(es_url) else "down"
    out["kibana"] = "up" if _json_http(kibana_url) else "down"
    out["kafka_ui"] = "up" if _json_http(kafka_ui_url) else "down"
    return out


def _run(cmd: List[str], timeout: Optional[int] = None) -> subprocess.CompletedProcess:
    return subprocess.run(
        cmd,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        timeout=timeout,
        check=False,
        text=True,
    )


def _requires_sudo() -> bool:
    return os.geteuid() != 0


@dataclass
class ScenarioResult:
    name: str
    pcap: str
    loop: int
    pps: int
    started_at: str
    finished_at: str
    elapsed_sec: float
    return_code: int
    status: str
    stdout: str
    stderr: str


def _tcpreplay_cmd(iface: str, pcap: str, loop: int, pps: int, use_sudo: bool) -> List[str]:
    cmd = ["tcpreplay", "--intf1", iface, "--loop", str(loop), "--pps", str(pps), pcap]
    if use_sudo:
        return ["sudo", "-n"] + cmd
    return cmd


def _load_scenarios(path: Path) -> List[Dict[str, Any]]:
    raw = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(raw, list):
        raise ValueError("Scenario file must be a JSON array")
    return raw


def run_validation(
    iface: str,
    scenario_file: Path,
    report_file: Path,
    settle_sec: float,
    es_url: str,
    kibana_url: str,
    kafka_ui_url: str,
    no_sudo: bool,
) -> int:
    if not scenario_file.exists():
        raise FileNotFoundError(f"Scenario file not found: {scenario_file}")

    if shutil_which("tcpreplay") is None:
        raise RuntimeError("tcpreplay not found in PATH")

    scenarios = _load_scenarios(scenario_file)
    use_sudo = _requires_sudo() and not no_sudo
    started_at = _utc_now()

    health_before = _service_health(es_url=es_url, kibana_url=kibana_url, kafka_ui_url=kafka_ui_url)
    es_count_before = _es_count(es_url)

    results: List[ScenarioResult] = []
    failed = False
    for idx, scenario in enumerate(scenarios, start=1):
        name = str(scenario.get("name", f"scenario_{idx}"))
        pcap = str(scenario.get("pcap", ""))
        loop = int(scenario.get("loop", 1))
        pps = int(scenario.get("pps", 1000))
        pcap_abs = str((Path(pcap)).resolve())
        if not Path(pcap_abs).exists():
            failed = True
            results.append(
                ScenarioResult(
                    name=name,
                    pcap=pcap_abs,
                    loop=loop,
                    pps=pps,
                    started_at=_utc_now(),
                    finished_at=_utc_now(),
                    elapsed_sec=0.0,
                    return_code=1,
                    status="failed_missing_pcap",
                    stdout="",
                    stderr=f"PCAP file not found: {pcap_abs}",
                )
            )
            continue

        cmd = _tcpreplay_cmd(iface=iface, pcap=pcap_abs, loop=loop, pps=pps, use_sudo=use_sudo)
        scenario_started = time.time()
        started = _utc_now()
        proc = _run(cmd)
        finished = _utc_now()
        elapsed = round(time.time() - scenario_started, 3)
        status = "passed" if proc.returncode == 0 else "failed_replay"
        if proc.returncode != 0:
            failed = True

        results.append(
            ScenarioResult(
                name=name,
                pcap=pcap_abs,
                loop=loop,
                pps=pps,
                started_at=started,
                finished_at=finished,
                elapsed_sec=elapsed,
                return_code=proc.returncode,
                status=status,
                stdout=proc.stdout[-4000:],
                stderr=proc.stderr[-4000:],
            )
        )
        time.sleep(settle_sec)

    es_count_after = _es_count(es_url)
    health_after = _service_health(es_url=es_url, kibana_url=kibana_url, kafka_ui_url=kafka_ui_url)

    report = {
        "run_started_at": started_at,
        "run_finished_at": _utc_now(),
        "interface": iface,
        "scenario_file": str(scenario_file),
        "health_before": health_before,
        "health_after": health_after,
        "es_count_before": es_count_before,
        "es_count_after": es_count_after,
        "es_ingested_delta": (
            (es_count_after - es_count_before)
            if isinstance(es_count_before, int) and isinstance(es_count_after, int)
            else None
        ),
        "scenarios": [asdict(item) for item in results],
        "overall_status": "failed" if failed else "passed",
    }
    report_file.parent.mkdir(parents=True, exist_ok=True)
    report_file.write_text(json.dumps(report, indent=2), encoding="utf-8")
    return 1 if failed else 0


def shutil_which(binary: str) -> Optional[str]:
    for candidate in os.environ.get("PATH", "").split(os.pathsep):
        path = os.path.join(candidate, binary)
        if os.path.isfile(path) and os.access(path, os.X_OK):
            return path
    return None


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Run tcpreplay scenarios and output operational validation report."
    )
    parser.add_argument("--iface", default="eth0", help="Network interface used by tcpreplay.")
    parser.add_argument(
        "--scenario-file",
        default="simulation/tcpreplay_scenarios.json",
        help="Path to JSON scenario file.",
    )
    parser.add_argument(
        "--report-file",
        default="data/output/live_tcpreplay_report.json",
        help="Output report file (JSON).",
    )
    parser.add_argument(
        "--settle-sec",
        type=float,
        default=15.0,
        help="Wait time between scenarios for downstream indexing.",
    )
    parser.add_argument("--es-url", default="http://127.0.0.1:9200")
    parser.add_argument("--kibana-url", default="http://127.0.0.1:5601/api/status")
    parser.add_argument("--kafka-ui-url", default="http://127.0.0.1:8085/actuator/health")
    parser.add_argument(
        "--no-sudo",
        action="store_true",
        help="Run tcpreplay without sudo (useful when binary has capabilities or for privileged shells).",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    try:
        return run_validation(
            iface=args.iface,
            scenario_file=Path(args.scenario_file),
            report_file=Path(args.report_file),
            settle_sec=args.settle_sec,
            es_url=args.es_url,
            kibana_url=args.kibana_url,
            kafka_ui_url=args.kafka_ui_url,
            no_sudo=args.no_sudo,
        )
    except Exception as exc:
        print(f"[live-tcpreplay-validation] ERROR: {exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
