#!/bin/bash
set -euo pipefail

BASE="http://127.0.0.1:8123"
JAR=$(mktemp)

xsrf() {
  grep XSRF-TOKEN "$JAR" | awk '{print $NF}' | python3 -c "import sys,urllib.parse; print(urllib.parse.unquote(sys.stdin.read().strip()))"
}

curl -s -c "$JAR" -b "$JAR" "$BASE/login" -o /dev/null

TOKEN=$(xsrf)
curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/api/login" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" \
  -d '{"identifier_value":"p013-demo-candidate@wasplex.test","password":"Password123!"}' -o /tmp/p013-login.json
cat /tmp/p013-login.json
echo

TOKEN=$(xsrf)
SESSION=$(curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/api/feed/sessions" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['feed_session']['id'])")
echo "session=$SESSION"

NEXT=$(curl -s -c "$JAR" -b "$JAR" "$BASE/api/feed/next?feed_session_id=$SESSION" -H "Accept: application/json")
echo "$NEXT"
DELIVERY_ID=$(echo "$NEXT" | python3 -c "import sys,json; print(json.load(sys.stdin)['delivery']['id'])")
REQUIRED_MS=$(echo "$NEXT" | python3 -c "import sys,json; print(json.load(sys.stdin)['delivery']['required_duration_ms'])")
echo "delivery_id=$DELIVERY_ID required_ms=$REQUIRED_MS"

TOKEN=$(xsrf)
curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/api/feed/deliveries/$DELIVERY_ID/start" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" -o /tmp/p013-start.json
cat /tmp/p013-start.json
echo

SLEEP_S=$(python3 -c "print(($REQUIRED_MS + 500) / 1000)")
echo "sleeping ${SLEEP_S}s for the required duration to elapse"
sleep "$SLEEP_S"

TOKEN=$(xsrf)
curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/api/feed/deliveries/$DELIVERY_ID/heartbeat" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" \
  -d "{\"visible_duration_ms\": $REQUIRED_MS}" -o /tmp/p013-heartbeat.json
cat /tmp/p013-heartbeat.json
echo

TOKEN=$(xsrf)
curl -s -c "$JAR" -b "$JAR" -X POST "$BASE/api/feed/deliveries/$DELIVERY_ID/complete" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" -o /tmp/p013-complete.json
cat /tmp/p013-complete.json
echo

TOKEN=$(xsrf)
curl -s -c "$JAR" -b "$JAR" "$BASE/api/me/wallet" -H "Accept: application/json" -o /tmp/p013-wallet.json
cat /tmp/p013-wallet.json
echo

rm -f "$JAR"
