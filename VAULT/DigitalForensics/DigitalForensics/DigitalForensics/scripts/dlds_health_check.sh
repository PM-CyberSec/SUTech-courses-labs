#!/usr/bin/env bash
set -u

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}" || exit 1

FAILURES=0

pass() {
  printf '[PASS] %s\n' "$1"
}

fail() {
  printf '[FAIL] %s\n' "$1"
  FAILURES=$((FAILURES + 1))
}

check() {
  local name="$1"
  shift

  if "$@" >/tmp/dlds-health-check.out 2>&1; then
    pass "${name}"
  else
    fail "${name}"
    sed 's/^/       /' /tmp/dlds-health-check.out
  fi
}

check "Laravel routes" php artisan route:list --path=api/ai/ask
check "Database connectivity" php artisan migrate:status
check "Public DLDS stats API" curl -fsS http://127.0.0.1:8000/api/dlds/public/stats

check "Reverb env alignment" php <<'PHP'
<?php

function env_value(string $key, string $default = ''): string
{
  $path = getcwd().'/.env';
  if (! is_file($path)) {
    return $default;
  }

  foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line === '' || str_starts_with(ltrim($line), '#') || ! str_contains($line, '=')) {
      continue;
    }

    [$name, $value] = explode('=', $line, 2);
    if (trim($name) !== $key) {
      continue;
    }

    $value = trim($value);
    if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
      || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
      $value = substr($value, 1, -1);
    }

    return $value;
  }

  return $default;
}

$appUrl = rtrim(env_value('APP_URL', 'http://127.0.0.1:8000'), '/');
$allowedOrigins = array_values(array_filter(array_map('trim', explode(',', env_value('REVERB_ALLOWED_ORIGINS')))));
$reverbHost = env_value('REVERB_HOST', '127.0.0.1');
$reverbPort = env_value('REVERB_PORT', '8080');
$reverbScheme = env_value('REVERB_SCHEME', 'http');
$resolved = static function (string $value) use ($reverbHost, $reverbPort, $reverbScheme): string {
  return strtr($value, [
    '${REVERB_HOST}' => $reverbHost,
    '${REVERB_PORT}' => $reverbPort,
    '${REVERB_SCHEME}' => $reverbScheme,
  ]);
};
$viteHost = $resolved(env_value('VITE_REVERB_HOST'));
$vitePort = $resolved(env_value('VITE_REVERB_PORT'));
$viteScheme = $resolved(env_value('VITE_REVERB_SCHEME'));

if ($viteHost !== $reverbHost || $vitePort !== $reverbPort || $viteScheme !== $reverbScheme) {
  fwrite(STDERR, "VITE_REVERB_* does not resolve to REVERB_*\n");
  exit(1);
}

if (! in_array($appUrl, $allowedOrigins, true)) {
  fwrite(STDERR, "APP_URL is not listed in REVERB_ALLOWED_ORIGINS\n");
  exit(1);
}
PHP

check "Reverb WebSocket origin" php <<'PHP'
<?php

function env_value(string $key, string $default = ''): string
{
    $path = getcwd().'/.env';
    if (! is_file($path)) {
        return $default;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || str_starts_with(ltrim($line), '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        if (trim($name) !== $key) {
            continue;
        }

        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    return $default;
}

$host = env_value('REVERB_HOST', '127.0.0.1');
$port = (int) env_value('REVERB_PORT', '8080');
$appKey = env_value('REVERB_APP_KEY');
$origin = env_value('APP_URL', 'http://127.0.0.1:8000');

if ($appKey === '') {
    fwrite(STDERR, "REVERB_APP_KEY is empty\n");
    exit(1);
}

$socket = @fsockopen($host, $port, $errno, $errstr, 2.0);
if (! $socket) {
    fwrite(STDERR, "Cannot connect to Reverb: {$errstr} ({$errno})\n");
    exit(1);
}

$key = base64_encode(random_bytes(16));
$path = "/app/{$appKey}?protocol=7&client=js&version=8.4.0&flash=false";
$request = "GET {$path} HTTP/1.1\r\n"
    ."Host: {$host}:{$port}\r\n"
    ."Origin: {$origin}\r\n"
    ."Upgrade: websocket\r\n"
    ."Connection: Upgrade\r\n"
    ."Sec-WebSocket-Key: {$key}\r\n"
    ."Sec-WebSocket-Version: 13\r\n\r\n";

fwrite($socket, $request);
stream_set_timeout($socket, 2);
$response = fread($socket, 2048);
fclose($socket);

if (! str_contains($response, '101 Switching Protocols')) {
    fwrite(STDERR, $response."\n");
    exit(1);
}
PHP

check "Kafka compose services" docker compose --project-name dlds_kafka -f docker-compose.kafka.yml ps
check "ELK compose services" docker compose --project-name dlds_elk -f docker-compose.elk.yml ps
check "Kafka topics" bash scripts/create_kafka_topics.sh list
check "Elasticsearch health" curl -fsS http://127.0.0.1:9200/_cluster/health
check "Kibana HTTP" curl -fsSI http://127.0.0.1:5601
check "AI evaluation" php artisan ai:evaluate

rm -f /tmp/dlds-health-check.out

if [ "${FAILURES}" -gt 0 ]; then
  printf '\nDLDS health check failed: %s issue(s)\n' "${FAILURES}"
  exit 1
fi

printf '\nDLDS health check passed.\n'
