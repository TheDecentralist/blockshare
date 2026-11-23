<?php
/**
 * Block Share — referral engine.
 * Code format: VAN-B{block#}-{FIRSTNAME}{2-digit}  e.g. VAN-B1-SARAH03
 * Referrer's CC is awarded LOCKED at registration (status 'pending') and
 * unlocked ('credited') when the referred user completes Section A.
 */
if (!defined('ABSPATH')) exit;

function bs_generate_referral_code($uid, $first, $block_number) {
    $name = strtoupper(preg_replace('/[^A-Za-z]/', '', remove_accents($first)));
    $name = substr($name ?: 'USER', 0, 12);
    for ($i = 0; $i < 60; $i++) {
        $code = sprintf('VAN-B%d-%s%02d', intval($block_number), $name, ($i < 30 ? wp_rand(0, 99) : wp_rand(100, 999)));
        if (!bs_user_by_code($code)) return $code;
    }
    return sprintf('VAN-B%d-%s%d', intval($block_number), $name, $uid);
}

function bs_user_by_code($code) {
    $code = strtoupper(sanitize_text_field($code));
    if (!preg_match('/^VAN-B\d+-[A-Z]+\d+$/', $code)) return false;
    $u = get_users(['meta_key' => 'bs_referral_code', 'meta_value' => $code, 'number' => 1, 'fields' => 'ID']);
    return $u ? intval($u[0]) : false;
}

/** Called from register. Silently ignores invalid codes and self-referral. */
function bs_attribute_referral($new_uid, $code, $project) {
    global $wpdb;
    if (!$code) return false;
    $referrer = bs_user_by_code($code);
    if (!$referrer || $referrer === $new_uid) return false;
    $wpdb->insert($wpdb->prefix . 'bs_referrals', [
        'referrer_id' => $referrer, 'referred_id' => $new_uid, 'code' => strtoupper($code),
        'project' => $project, 'status' => 'pending', 'cc_amount' => BS_CC_REFERRAL,
    ]);
    update_user_meta($new_uid, 'bs_referred_by', $referrer);
    bs_award_cc($referrer, BS_CC_REFERRAL, 'referral', 'Referral pending — unlocks when they finish the quest', 'locked');
    $name = get_user_meta($new_uid, 'bs_first_name', true);
    $ref_name = get_user_meta($referrer, 'bs_first_name', true);
    if (function_exists('bs_log_feed_event')) {
        bs_log_feed_event($new_uid, 'referral_used', ['name' => $name, 'referrer' => $ref_name], 'block');
    }
    return $referrer;
}

/** Called when a user completes Section A: unlock the referrer's locked CC. */
function bs_credit_referral($referred_uid) {
    global $wpdb;
    $t = $wpdb->prefix . 'bs_referrals';
    $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE referred_id=%d AND status='pending'", $referred_uid));
    if (!$r) return false;
    $amt = floatval($r->cc_amount);
    bs_award_cc($r->referrer_id, -$amt, 'referral', 'Referral credited', 'locked');
    bs_award_cc($r->referrer_id, $amt,  'referral', 'Referral credited', 'spendable');
    $wpdb->update($t, ['status' => 'credited', 'credited_at' => current_time('mysql', true)], ['id' => $r->id]);
    return true;
}

add_action('rest_api_init', function () {
    register_rest_route('blockshare/v1', '/referral-code', [
        'methods' => 'GET', 'callback' => 'bs_api_referral_code', 'permission_callback' => '__return_true']);
    register_rest_route('blockshare/v1', '/referral-info', [
        'methods' => 'GET', 'callback' => 'bs_api_referral_info', 'permission_callback' => '__return_true']);
});

/** GET /referral-code?uid&token → matches types/referral.ts (ReferralCode) */
function bs_api_referral_code($req) {
    global $wpdb;
    $uid = bs_auth($req);
    if (!$uid) return bs_unauth();
    $code = get_user_meta($uid, 'bs_referral_code', true);
    if (!$code) {
        $code = bs_generate_referral_code($uid, get_user_meta($uid, 'bs_first_name', true) ?: 'User', get_user_meta($uid, 'bs_block_number', true) ?: 1);
        update_user_meta($uid, 'bs_referral_code', $code);
    }
    $project = bs_project($req->get_param('project') ?: BS_DEFAULT_PROJECT);
    $t = $wpdb->prefix . 'bs_referrals';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE referrer_id=%d ORDER BY created_at DESC LIMIT 100", $uid));
    $stats = ['totalReferrals' => 0, 'pendingReferrals' => 0, 'creditedReferrals' => 0, 'totalCCEarned' => 0];
    $list = [];
    foreach ((array) $rows as $r) {
        $stats['totalReferrals']++;
        if ($r->status === 'credited') { $stats['creditedReferrals']++; $stats['totalCCEarned'] += floatval($r->cc_amount); }
        else $stats['pendingReferrals']++;
        $list[] = [
            'id' => (string) $r->id,
            'referredName' => get_user_meta($r->referred_id, 'bs_first_name', true) ?: 'Neighbour',
            'status' => $r->status, 'ccAmount' => floatval($r->cc_amount),
            'createdAt' => $r->created_at, 'creditedAt' => $r->credited_at,
        ];
    }
    return new WP_REST_Response([
        'code' => $code,
        'webUrl' => home_url('/join/?ref=' . rawurlencode($code) . '&project=' . $project['id']),
        'project' => $project, 'stats' => $stats, 'referrals' => $list,
    ], 200);
}

/** GET /referral-info?ref=CODE → validate an inbound code (public; first name only) */
function bs_api_referral_info($req) {
    $uid = bs_user_by_code($req->get_param('ref'));
    if (!$uid) return new WP_REST_Response(['valid' => false], 200);
    $pid = sanitize_key($req->get_param('project')) ?: BS_DEFAULT_PROJECT;
    $p = bs_project($pid);
    return new WP_REST_Response([
        'valid' => true,
        'referrer_first_name' => get_user_meta($uid, 'bs_first_name', true),
        'block_number' => intval(get_user_meta($uid, 'bs_block_number', true)),
        'project' => ['id' => $p['id'], 'name' => $p['name'], 'tagline' => $p['tagline']],
    ], 200);
}
