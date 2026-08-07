#!/bin/bash
set -euo pipefail

BASE="http://127.0.0.1:8123"

xsrf() {
  grep XSRF-TOKEN "$1" | awk '{print $NF}' | python3 -c "import sys,urllib.parse; print(urllib.parse.unquote(sys.stdin.read().strip()))"
}

echo "=== Studio annonceur : rapport de campagne ==="
JAR_ADV=$(mktemp)
curl -s -c "$JAR_ADV" -b "$JAR_ADV" "$BASE/login" -o /dev/null
TOKEN=$(xsrf "$JAR_ADV")
curl -s -c "$JAR_ADV" -b "$JAR_ADV" -X POST "$BASE/api/login" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" \
  -d '{"identifier_value":"p013-demo-advertiser@wasplex.test","password":"Password123!"}' -o /tmp/p013-adv-login.json
ADVERTISER_ID=$(python3 -c "import json; print(json.load(open('/tmp/p013-adv-login.json'))['id'])")
echo "advertiser_id=$ADVERTISER_ID"

TOKEN=$(xsrf "$JAR_ADV")
curl -s -c "$JAR_ADV" -b "$JAR_ADV" "$BASE/api/me/spaces" -H "Accept: application/json" -o /tmp/p013-spaces.json
cat /tmp/p013-spaces.json
echo
SPACE_ID=$(python3 -c "import json; d=json.load(open('/tmp/p013-spaces.json')); print([s['user_space_id'] for s in d['spaces'] if s['space_type']=='advertiser'][0])")
echo "space_id=$SPACE_ID"

TOKEN=$(xsrf "$JAR_ADV")
curl -s -c "$JAR_ADV" -b "$JAR_ADV" -X POST "$BASE/api/me/spaces/$SPACE_ID/switch" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" -o /tmp/p013-switch.json
cat /tmp/p013-switch.json
echo

TOKEN=$(xsrf "$JAR_ADV")
curl -s -c "$JAR_ADV" -b "$JAR_ADV" "$BASE/api/advertiser/campaigns/report" -H "Accept: application/json" -o /tmp/p013-report.json
cat /tmp/p013-report.json
echo
rm -f "$JAR_ADV"

echo
echo "=== Fondateur : dashboard + rapprochement ==="
JAR_ADM=$(mktemp)
curl -s -c "$JAR_ADM" -b "$JAR_ADM" "$BASE/login" -o /dev/null
TOKEN=$(xsrf "$JAR_ADM")
curl -s -c "$JAR_ADM" -b "$JAR_ADM" -X POST "$BASE/api/login" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" \
  -d '{"identifier_value":"fondateur@wasplex.test","password":"FounderPass123!"}' -o /tmp/p013-founder-login.json
cat /tmp/p013-founder-login.json
echo

TOKEN=$(xsrf "$JAR_ADM")
curl -s -c "$JAR_ADM" -b "$JAR_ADM" -X POST "$BASE/api/me/mfa" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" -o /tmp/p013-mfa-enroll.json
cat /tmp/p013-mfa-enroll.json
echo
SECRET=$(python3 -c "import json; print(json.load(open('/tmp/p013-mfa-enroll.json'))['secret'])")
CODE=$(python3 -c "import pyotp; print(pyotp.TOTP('$SECRET').now())")

TOKEN=$(xsrf "$JAR_ADM")
curl -s -c "$JAR_ADM" -b "$JAR_ADM" -X PUT "$BASE/api/me/mfa" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" \
  -d "{\"code\":\"$CODE\"}" -o /tmp/p013-mfa-confirm.json
cat /tmp/p013-mfa-confirm.json
echo

CODE=$(python3 -c "import pyotp; print(pyotp.TOTP('$SECRET').now())")
TOKEN=$(xsrf "$JAR_ADM")
curl -s -c "$JAR_ADM" -b "$JAR_ADM" -X POST "$BASE/api/me/mfa/verify" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" \
  -d "{\"code\":\"$CODE\"}" -o /tmp/p013-mfa-verify.json
cat /tmp/p013-mfa-verify.json
echo

TOKEN=$(xsrf "$JAR_ADM")
curl -s -c "$JAR_ADM" -b "$JAR_ADM" "$BASE/api/admin/dashboard/summary" -H "Accept: application/json" -o /tmp/p013-dashboard.json
cat /tmp/p013-dashboard.json
echo

TOKEN=$(xsrf "$JAR_ADM")
curl -s -c "$JAR_ADM" -b "$JAR_ADM" -X POST "$BASE/api/admin/reconciliation/runs" \
  -H "Content-Type: application/json" -H "Accept: application/json" -H "X-XSRF-TOKEN: $TOKEN" -o /tmp/p013-reconciliation.json
cat /tmp/p013-reconciliation.json
echo
rm -f "$JAR_ADM"
