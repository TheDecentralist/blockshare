#!/bin/bash
# Exercises the whole flow against the live site, then deletes the test user.
B=https://app.blockshare.ca
API=$B/wp-json/blockshare/v1
j() { python3 -c "import sys,json;d=json.load(sys.stdin);print(d$1)" 2>/dev/null; }
echo "health:   $(curl -s $API/health)"
R=$(curl -s -X POST $API/register -H 'Content-Type: application/json' \
  -d "{\"first_name\":\"Smoke\",\"email\":\"smoke$RANDOM@example.com\",\"postal_code\":\"V6H 1A1\"}")
echo "register: $R"
UID_=$(echo "$R" | j "['uid']"); TOK=$(echo "$R" | j "['token']"); CODE=$(echo "$R" | j "['referral_code']")
[ -z "$UID_" ] && { echo "FAILED: no uid"; exit 1; }
A="uid=$UID_&token=$TOK"
echo "referral-info: $(curl -s "$API/referral-info?ref=$CODE")"
echo "home (before): $(curl -s "$API/home?$A")"
for q in 1 2 3 4 5; do
  echo "survey $q: $(curl -s -X POST $API/survey -H 'Content-Type: application/json' -d "{\"uid\":$UID_,\"token\":\"$TOK\",\"question_id\":$q,\"section\":\"A\",\"answer\":\"test\"}")"
done
echo "home (after):  $(curl -s "$API/home?$A")"
echo "add item: $(curl -s -X POST $API/items -H 'Content-Type: application/json' -d "{\"uid\":$UID_,\"token\":\"$TOK\",\"title\":\"Smoke Drill\",\"category\":\"tools\"}")"
echo "items:    $(curl -s "$API/items?$A")"
echo "feed:     $(curl -s "$API/feed?$A")"
echo "referral-code: $(curl -s "$API/referral-code?$A")"
echo "bad token -> HTTP $(curl -s -o /dev/null -w '%{http_code}' "$API/home?uid=$UID_&token=bogus")  (expect 401)"
for p in join quest home; do echo "/$p/ -> HTTP $(curl -s -o /dev/null -w '%{http_code}' $B/$p/)"; done
echo "/onboarding/ -> $(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' $B/onboarding/)"
echo "cleanup: run  wp user delete $UID_ --yes --allow-root  (and: wp db query \"DELETE FROM wp_bs_items WHERE user_id=$UID_\" --allow-root)"
