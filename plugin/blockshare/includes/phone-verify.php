<?php
/**
 * Block Share — mobile verification (Twilio Verify) and inbound SMS webhook.
 *
 * wp-config.php constants (never commit these):
 *   BS_TWILIO_SID         Twilio Account SID   (AC…)
 *   BS_TWILIO_TOKEN       Twilio Auth Token
 *   BS_TWILIO_VERIFY_SID  Twilio Verify Service SID (VA…)
 *
 * Routes (namespace blockshare/v1):
 *   POST /send-code  {phone}        → text a 6-digit code        → {success, message?}
 *   POST /verify     {phone, code}  → check the code             → {verified, message?}
 *   POST /sms        (Twilio webhook, signature-checked)         → empty TwiML
 * A verified number is remembered for 30 minutes so /register can accept it.
 */
if (!defined('ABSPATH')) exit;

function bs_twilio_ready() {
    foreach (['BS_TWILIO_SID', 'BS_TWILIO_TOKEN', 'BS_TWILIO_VERIFY_SID'] as $c) {
        if (!defined($c) || !constant($c) || strpos((string) constant($c), 'YOUR_') === 0) return false;
    }
    return true;
}

/** Normalise to E.164. 10 digits or 1+10 digits → +1…; a leading + keeps the country. '' if invalid. */
function bs_normalize_phone($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') return '';
    $d = preg_replace('/\D/', '', $raw);
    if (strpos($raw, '+') === 0) return (strlen($d) >= 8 && strlen($d) <= 15) ? '+' . $d : '';
    if (strlen($d) === 10) return '+1' . $d;
    if (strlen($d) === 11 && $d[0] === '1') return '+' . $d;
    return '';
}

function bs_phone_is_verified($e164) { return (bool) get_transient('bs_pv_' . md5($e164)); }
function bs_phone_consume($e164)     { delete_transient('bs_pv_' . md5($e164)); }

/** Count an event against a rolling hourly limit; true if still allowed. */
function bs_rate_ok($key, $max) {
    $n = intval(get_transient($key));
    if ($n >= $max) return false;
    set_transient($key, $n + 1, HOUR_IN_SECONDS);
    return true;
}

function bs_client_ip() {
    return preg_replace('/[^0-9a-f:.]/i', '', $_SERVER['REMOTE_ADDR'] ?? 'x');
}

/** Call the Twilio Verify API. Returns [http_status, decoded_body]. */
function bs_twilio_verify_call($path, $fields) {
    $url = 'https://verify.twilio.com/v2/Services/' . BS_TWILIO_VERIFY_SID . '/' . $path;
    $r = wp_remote_post($url, [
        'timeout' => 15,
        'headers' => ['Authorization' => 'Basic ' . base64_encode(BS_TWILIO_SID . ':' . BS_TWILIO_TOKEN)],
        'body'    => $fields,
    ]);
    if (is_wp_error($r)) return [0, ['message' => $r->get_error_message()]];
    return [wp_remote_retrieve_response_code($r), json_decode(wp_remote_retrieve_body($r), true) ?: []];
}

function bs_api_send_code($req) {
    if (!bs_twilio_ready()) {
        return new WP_REST_Response(['success' => false, 'message' => 'Mobile verification is not available right now. Please use your email instead.'], 503);
    }
    $phone = bs_normalize_phone($req->get_param('phone'));
    if (!$phone) return new WP_REST_Response(['success' => false, 'message' => 'That mobile number does not look right.'], 400);

    if (!bs_rate_ok('bs_sc_ip_' . md5(bs_client_ip()), 10) || !bs_rate_ok('bs_sc_ph_' . md5($phone), 4)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Too many codes requested. Please wait a while and try again.'], 429);
    }

    list($http, $body) = bs_twilio_verify_call('Verifications', ['To' => $phone, 'Channel' => 'sms']);
    if ($http >= 200 && $http < 300) return new WP_REST_Response(['success' => true], 200);

    $tc = $body['code'] ?? 0;
    error_log('[blockshare] Twilio Verify start failed: HTTP ' . $http . ', Twilio code ' . $tc);
    if ($tc == 60203) $msg = 'Too many attempts for this number. Please try again later.';
    elseif ($tc == 60200 || $tc == 21211) $msg = 'That mobile number does not look right.';
    else $msg = 'We could not send a code. Please check the number and try again.';
    return new WP_REST_Response(['success' => false, 'message' => $msg], 502);
}

function bs_api_verify_code($req) {
    if (!bs_twilio_ready()) {
        return new WP_REST_Response(['verified' => false, 'message' => 'Mobile verification is not available right now.'], 503);
    }
    $phone = bs_normalize_phone($req->get_param('phone'));
    $code  = preg_replace('/\D/', '', (string) $req->get_param('code'));
    if (!$phone || strlen($code) < 4 || strlen($code) > 10) {
        return new WP_REST_Response(['verified' => false, 'message' => 'Please enter the code from the text message.'], 400);
    }
    if (!bs_rate_ok('bs_vc_ph_' . md5($phone), 10)) {
        return new WP_REST_Response(['verified' => false, 'message' => 'Too many attempts. Please request a new code later.'], 429);
    }

    list($http, $body) = bs_twilio_verify_call('VerificationCheck', ['To' => $phone, 'Code' => $code]);
    if ($http >= 200 && $http < 300 && ($body['status'] ?? '') === 'approved') {
        set_transient('bs_pv_' . md5($phone), 1, 30 * MINUTE_IN_SECONDS);
        return new WP_REST_Response(['verified' => true], 200);
    }
    if ($http === 0 || $http >= 500) {
        error_log('[blockshare] Twilio Verify check failed: HTTP ' . $http);
        return new WP_REST_Response(['verified' => false, 'message' => 'Verification is temporarily unavailable. Please try again.'], 502);
    }
    // 404 = no pending verification (expired / already used); 200 + pending = wrong code
    return new WP_REST_Response(['verified' => false, 'message' => 'Invalid or expired code.'], 200);
}

/** Inbound SMS webhook. Verifies Twilio's signature, logs minimally, replies with empty TwiML. */
function bs_api_sms_inbound($req) {
    if (!defined('BS_TWILIO_TOKEN') || !BS_TWILIO_TOKEN || strpos((string) BS_TWILIO_TOKEN, 'YOUR_') === 0) {
        return new WP_REST_Response(['error' => 'SMS is not configured.'], 503);
    }
    $params = $req->get_body_params();
    ksort($params);
    $tail = '';
    foreach ($params as $k => $v) { if (!is_array($v)) $tail .= $k . $v; }
    $given = (string) $req->get_header('x_twilio_signature');
    $url   = rest_url('blockshare/v1/sms');
    $ok = false;
    foreach (array_unique([$url, set_url_scheme($url, 'https')]) as $u) {
        $calc = base64_encode(hash_hmac('sha1', $u . $tail, BS_TWILIO_TOKEN, true));
        if ($given !== '' && hash_equals($calc, $given)) { $ok = true; break; }
    }
    if (!$ok) return new WP_REST_Response(['error' => 'Invalid signature.'], 403);

    $from = preg_replace('/\D/', '', (string) ($params['From'] ?? ''));
    error_log('[blockshare] inbound SMS from ***' . substr($from, -4));   // number tail only; message body is not stored

    nocache_headers();
    header('Content-Type: text/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
}

add_action('rest_api_init', function () {
    $open = '__return_true';
    register_rest_route('blockshare/v1', '/send-code', ['methods' => 'POST', 'callback' => 'bs_api_send_code',  'permission_callback' => $open]);
    register_rest_route('blockshare/v1', '/verify',    ['methods' => 'POST', 'callback' => 'bs_api_verify_code', 'permission_callback' => $open]);
    register_rest_route('blockshare/v1', '/sms',       ['methods' => 'POST', 'callback' => 'bs_api_sms_inbound', 'permission_callback' => $open]);
});
