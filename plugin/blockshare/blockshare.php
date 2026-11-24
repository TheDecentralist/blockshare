<?php
/**
 * Plugin Name: Block Share
 * Description: Block Share REST API, Community Credits (CC), surveys, shared items and referrals.
 * Version: 2.0.0-restore
 * Author: Block Share
 *
 * Rebuilt Nov 2026 from CLAUDE.md (API spec), the Feb 2026 v1 plugin, bs-feed.php and home.html.
 * REST base: /wp-json/blockshare/v1/
 */
if (!defined('ABSPATH')) exit;

define('BS_VERSION', '2.0.0-restore');
define('BS_DIR', plugin_dir_path(__FILE__));

// ── Tunable constants (CC amounts are assumptions — edit here) ───────────────
if (!defined('BS_CC_REGISTER_LOCKED')) define('BS_CC_REGISTER_LOCKED', 20); // locked until Section A done
if (!defined('BS_CC_PER_QUESTION'))    define('BS_CC_PER_QUESTION', 3);     // 5 questions = 15 CC
if (!defined('BS_CC_REFERRAL'))        define('BS_CC_REFERRAL', 10);        // referrer, locked until referee finishes A
if (!defined('BS_SECTION_A_QUESTIONS'))define('BS_SECTION_A_QUESTIONS', 5);
if (!defined('BS_DEFAULT_PROJECT'))    define('BS_DEFAULT_PROJECT', 'save-the-pond');

require_once BS_DIR . 'includes/referral.php';
require_once BS_DIR . 'includes/phone-verify.php';

// ── Database ─────────────────────────────────────────────────────────────────
register_activation_hook(__FILE__, 'bs_install');
add_action('plugins_loaded', function () {
    if (get_option('bs_db_version') !== BS_VERSION) bs_install();
});

function bs_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $c = $wpdb->get_charset_collate();
    $p = $wpdb->prefix;

    dbDelta("CREATE TABLE {$p}bs_blocks (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        block_number INT NOT NULL,
        postal_prefix VARCHAR(3) NOT NULL,
        neighbourhood VARCHAR(100) NOT NULL DEFAULT 'Vancouver, BC',
        median_income INT NOT NULL DEFAULT 0,
        households INT NOT NULL DEFAULT 0,
        member_count INT NOT NULL DEFAULT 0,
        cc_total DECIMAL(12,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY postal_prefix (postal_prefix),
        KEY block_number (block_number)
    ) $c;");

    dbDelta("CREATE TABLE {$p}bs_survey (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        question_id INT NOT NULL,
        section CHAR(1) NOT NULL DEFAULT 'A',
        project VARCHAR(60) NOT NULL DEFAULT '',
        answer TEXT NOT NULL,
        cc_awarded INT NOT NULL DEFAULT 0,
        block_number INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_q (user_id, section, question_id),
        KEY idx_block_section (block_number, section)
    ) $c;");

    dbDelta("CREATE TABLE {$p}bs_cc_ledger (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        tx_type VARCHAR(30) NOT NULL,
        bucket VARCHAR(12) NOT NULL DEFAULT 'spendable',
        amount DECIMAL(10,2) NOT NULL,
        description VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user (user_id, created_at)
    ) $c;");

    dbDelta("CREATE TABLE {$p}bs_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(120) NOT NULL,
        description VARCHAR(255) NOT NULL DEFAULT '',
        category VARCHAR(30) NOT NULL DEFAULT 'tools',
        status VARCHAR(15) NOT NULL DEFAULT 'available',
        postal_prefix VARCHAR(3) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_prefix (postal_prefix, status),
        KEY idx_user (user_id)
    ) $c;");

    dbDelta("CREATE TABLE {$p}bs_feed (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        event_type VARCHAR(30) NOT NULL,
        payload TEXT,
        scope VARCHAR(15) NOT NULL DEFAULT 'block',
        postal_code VARCHAR(3) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_scope (scope, postal_code, created_at)
    ) $c;");

    dbDelta("CREATE TABLE {$p}bs_referrals (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        referrer_id BIGINT UNSIGNED NOT NULL,
        referred_id BIGINT UNSIGNED NOT NULL,
        code VARCHAR(40) NOT NULL,
        project VARCHAR(60) NOT NULL DEFAULT '',
        status VARCHAR(10) NOT NULL DEFAULT 'pending',
        cc_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        credited_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY referred (referred_id),
        KEY referrer (referrer_id)
    ) $c;");

    update_option('bs_db_version', BS_VERSION);
}

// ── Projects (campaigns that referral links and quests attach to) ────────────
function bs_projects() {
    return [
        'save-the-pond' => [
            'id'        => 'save-the-pond',
            'name'      => 'Save Our Pond',
            'tagline'   => "Keep Vancouver's park ponds alive through summer",
            'description' => 'Help restore water to Vancouver park ponds by backing practical alternatives to shutting the water off.',
            'ccReward'  => BS_CC_REFERRAL,
        ],
    ];
}
function bs_project($id) {
    $p = bs_projects();
    return $p[$id] ?? $p[BS_DEFAULT_PROJECT];
}

// ── Tokens: base64(uid:hash) ─────────────────────────────────────────────────
function bs_token_hash($uid) {
    return substr(hash_hmac('sha256', 'bs|' . intval($uid), wp_salt('auth')), 0, 32);
}
function bs_make_token($uid) {
    return base64_encode(intval($uid) . ':' . bs_token_hash($uid));
}
/** Returns the user id if the token is valid, else false. */
function bs_validate_token($token) {
    $raw = base64_decode((string) $token, true);
    if (!$raw || strpos($raw, ':') === false) return false;
    list($uid, $hash) = explode(':', $raw, 2);
    $uid = intval($uid);
    if (!$uid || !hash_equals(bs_token_hash($uid), (string) $hash)) return false;
    return get_userdata($uid) ? $uid : false;
}
/** Authenticate a request; returns uid or false. A supplied uid must match the token. */
function bs_auth($req) {
    $uid = bs_validate_token($req->get_param('token'));
    if (!$uid) return false;
    $given = intval($req->get_param('uid'));
    if ($given && $given !== $uid) return false;
    return $uid;
}
function bs_unauth() {
    return new WP_REST_Response(['error' => 'auth required'], 401);
}

// ── Blocks ───────────────────────────────────────────────────────────────────
function bs_postal_prefix($postal) {
    return substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $postal)), 0, 3);
}
/** Find or create the block for a postal prefix. */
function bs_block_for_prefix($prefix) {
    global $wpdb;
    $t = $wpdb->prefix . 'bs_blocks';
    $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE postal_prefix = %s", $prefix));
    if ($b) return $b;
    $next = intval($wpdb->get_var("SELECT COALESCE(MAX(block_number),0)+1 FROM $t"));
    $wpdb->insert($t, ['block_number' => $next, 'postal_prefix' => $prefix, 'neighbourhood' => 'Vancouver, BC']);
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE postal_prefix = %s", $prefix));
}

// ── Community Credits ledger ─────────────────────────────────────────────────
/** bucket: spendable | locked. Positive amounts add, negative subtract. */
function bs_award_cc($uid, $amount, $type, $desc = '', $bucket = 'spendable') {
    global $wpdb;
    $amount = floatval($amount);
    if (!$amount) return;
    $key = $bucket === 'locked' ? 'bs_cc_locked' : 'bs_cc_spendable';
    $cur = floatval(get_user_meta($uid, $key, true));
    update_user_meta($uid, $key, max(0, $cur + $amount));
    $wpdb->insert($wpdb->prefix . 'bs_cc_ledger', [
        'user_id' => $uid, 'tx_type' => $type, 'bucket' => $bucket,
        'amount' => $amount, 'description' => mb_substr($desc, 0, 255),
    ]);
    if ($amount > 0 && $bucket === 'spendable') {
        $bn = get_user_meta($uid, 'bs_block_number', true);
        if ($bn) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}bs_blocks SET cc_total = cc_total + %f WHERE block_number = %d", $amount, $bn));
    }
}
/** Move a user's locked CC to spendable (Section A unlock). */
function bs_unlock_cc($uid) {
    $locked = floatval(get_user_meta($uid, 'bs_cc_locked', true));
    if ($locked <= 0) return 0;
    bs_award_cc($uid, -$locked, 'unlock', 'Section A complete', 'locked');
    bs_award_cc($uid, $locked, 'unlock', 'Section A complete', 'spendable');
    return $locked;
}

// ── REST routes ──────────────────────────────────────────────────────────────
add_action('rest_api_init', function () {
    $ns = 'blockshare/v1';
    $open = '__return_true';
    register_rest_route($ns, '/health',        ['methods' => 'GET',  'callback' => 'bs_api_health',   'permission_callback' => $open]);
    register_rest_route($ns, '/register',      ['methods' => 'POST', 'callback' => 'bs_api_register', 'permission_callback' => $open]);
    register_rest_route($ns, '/survey',        ['methods' => 'POST', 'callback' => 'bs_api_survey',   'permission_callback' => $open]);
    register_rest_route($ns, '/home',          ['methods' => 'GET',  'callback' => 'bs_api_home',     'permission_callback' => $open]);
    register_rest_route($ns, '/items',         ['methods' => 'GET',  'callback' => 'bs_api_items',    'permission_callback' => $open]);
    register_rest_route($ns, '/items',         ['methods' => 'POST', 'callback' => 'bs_api_item_add', 'permission_callback' => $open]);
    register_rest_route($ns, '/block/(?P<postal>[A-Za-z0-9 ]+)', ['methods' => 'GET', 'callback' => 'bs_api_block', 'permission_callback' => $open]);
    // /feed is registered by mu-plugins/bs-feed.php; /referral-code and /referral-info by includes/referral.php
});

function bs_api_health() {
    return new WP_REST_Response(['ok' => true, 'version' => BS_VERSION, 'time' => gmdate('c')], 200);
}

function bs_api_register($req) {
    global $wpdb;
    // Throttle by IP: 10 registrations / hour
    $ip  = preg_replace('/[^0-9a-f:.]/i', '', $_SERVER['REMOTE_ADDR'] ?? 'x');
    $tk  = 'bs_reg_' . md5($ip);
    $cnt = intval(get_transient($tk));
    if ($cnt >= 10) return new WP_REST_Response(['error' => 'Too many sign-ups from this connection. Try again later.'], 429);

    $first  = sanitize_text_field($req->get_param('first_name'));
    $last   = sanitize_text_field($req->get_param('last_name'));
    if (!$first && $req->get_param('name')) {   // older join pages send one "name" field
        $parts = preg_split('/\s+/', trim(sanitize_text_field($req->get_param('name'))), 2);
        $first = $parts[0] ?? '';
        if (!$last) $last = $parts[1] ?? '';
    }
    $email  = sanitize_email($req->get_param('email'));
    // Mobile numbers must be verified first (POST /send-code, then POST /verify); stored as E.164.
    $phone_in = trim((string) $req->get_param('phone'));
    $phone    = '';
    if ($phone_in !== '') {
        $phone = bs_normalize_phone($phone_in);
        if (!$phone) return new WP_REST_Response(['error' => 'That mobile number does not look right.'], 400);
        if (!bs_phone_is_verified($phone)) {
            return new WP_REST_Response(['error' => 'Please verify your mobile number first.', 'needs_phone_verification' => true], 400);
        }
    }
    $postal = strtoupper(sanitize_text_field($req->get_param('postal_code')));
    $ref    = sanitize_text_field($req->get_param('ref'));
    $project= sanitize_key($req->get_param('project')) ?: BS_DEFAULT_PROJECT;

    if (!$first) return new WP_REST_Response(['error' => 'First name is required.'], 400);
    if (!$email && !$phone) return new WP_REST_Response(['error' => 'Email or phone is required.'], 400);
    if ($email && !is_email($email)) return new WP_REST_Response(['error' => 'That email address does not look right.'], 400);
    $prefix = bs_postal_prefix($postal);
    if (strlen($prefix) < 3) return new WP_REST_Response(['error' => 'A postal code is needed to find your block.'], 400);

    if ($email && email_exists($email)) {
        return new WP_REST_Response(['error' => 'That email is already registered.'], 409);
    }
    if ($phone) {
        $variants = [$phone, ltrim($phone, '+')];                 // also match numbers stored in older formats
        if (strpos($phone, '+1') === 0) $variants[] = substr($phone, 2);
        $dupe = get_users(['meta_key' => 'bs_phone', 'meta_value' => $variants, 'meta_compare' => 'IN', 'number' => 1, 'fields' => 'ID']);
        if ($dupe) return new WP_REST_Response(['error' => 'That phone number is already registered.'], 409);
    }

    $login = $email ?: ('bs_' . ltrim($phone, '+'));
    $uid = wp_insert_user([
        'user_login'   => $login,
        'user_pass'    => wp_generate_password(24),
        'user_email'   => $email ?: ('noemail+' . substr(md5($phone), 0, 10) . '@blockshare.invalid'),
        'first_name'   => $first,
        'last_name'    => $last,
        'display_name' => trim($first . ' ' . mb_substr($last, 0, 1)),
        'role'         => 'subscriber',
    ]);
    if (is_wp_error($uid)) return new WP_REST_Response(['error' => $uid->get_error_message()], 400);
    set_transient($tk, $cnt + 1, HOUR_IN_SECONDS);

    $block = bs_block_for_prefix($prefix);
    update_user_meta($uid, 'bs_first_name', $first);
    update_user_meta($uid, 'bs_postal_code', $postal);
    update_user_meta($uid, 'bs_block_number', intval($block->block_number));
    update_user_meta($uid, 'bs_phone', $phone);
    if ($phone) { update_user_meta($uid, 'bs_phone_verified', gmdate('c')); bs_phone_consume($phone); }
    $age = sanitize_text_field($req->get_param('age_range'));
    if ($age) update_user_meta($uid, 'bs_age_range', $age);
    update_user_meta($uid, 'bs_project', $project);
    update_user_meta($uid, 'bs_registered_at', gmdate('c'));
    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}bs_blocks SET member_count = member_count + 1 WHERE id = %d", $block->id));

    $code = bs_generate_referral_code($uid, $first, $block->block_number);
    update_user_meta($uid, 'bs_referral_code', $code);

    bs_award_cc($uid, BS_CC_REGISTER_LOCKED, 'register', 'Welcome bonus (unlocks when quest is done)', 'locked');
    bs_attribute_referral($uid, $ref, $project);

    return new WP_REST_Response([
        'success'       => true,
        'uid'           => $uid,
        'user_id'       => $uid,
        'token'         => bs_make_token($uid),
        'block_number'  => intval($block->block_number),
        'referral_code' => $code,
        'cc_locked'     => floatval(get_user_meta($uid, 'bs_cc_locked', true)),
    ], 200);
}

function bs_api_survey($req) {
    global $wpdb;
    $uid = bs_auth($req);
    if (!$uid) return bs_unauth();

    $qid     = intval($req->get_param('question_id'));
    $section = strtoupper(substr(sanitize_text_field($req->get_param('section') ?: 'A'), 0, 1));
    $project = sanitize_key($req->get_param('project')) ?: BS_DEFAULT_PROJECT;
    $answer  = $req->get_param('answer');
    if ($qid < 1 || $qid > 50) return new WP_REST_Response(['error' => 'bad question_id'], 400);

    $t = $wpdb->prefix . 'bs_survey';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d AND section=%s AND question_id=%d", $uid, $section, $qid));
    $newly_awarded = 0;
    if (!$exists) {
        // CC is fixed server-side; the client-sent amount is ignored.
        $cc = BS_CC_PER_QUESTION;
        $wpdb->insert($t, [
            'user_id' => $uid, 'question_id' => $qid, 'section' => $section, 'project' => $project,
            'answer' => is_array($answer) ? wp_json_encode($answer) : sanitize_textarea_field((string) $answer),
            'cc_awarded' => $cc,
            'block_number' => intval(get_user_meta($uid, 'bs_block_number', true)) ?: null,
        ]);
        bs_award_cc($uid, $cc, 'survey_earn', "Survey {$section}{$qid}", 'spendable');
        $newly_awarded = $cc;
    }

    $done = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE user_id=%d AND section='A'", $uid)));
    $complete = false;
    if ($section === 'A' && $done >= BS_SECTION_A_QUESTIONS && !get_user_meta($uid, 'bs_section_a_done', true)) {
        update_user_meta($uid, 'bs_section_a_done', gmdate('c'));
        bs_unlock_cc($uid);
        bs_credit_referral($uid);
        do_action('bs_section_a_complete', $uid);
        $complete = true;
    }

    return new WP_REST_Response([
        'saved'         => true,
        'cc_awarded'    => $newly_awarded,
        'new_balance'   => floatval(get_user_meta($uid, 'bs_cc_spendable', true)),
        'quests_done'   => $done,
        'section_a_complete' => $complete || (bool) get_user_meta($uid, 'bs_section_a_done', true),
    ], 200);
}

function bs_api_home($req) {
    global $wpdb;
    $uid = bs_auth($req);
    if (!$uid) return bs_unauth();

    $first = get_user_meta($uid, 'bs_first_name', true) ?: get_userdata($uid)->display_name;
    $bn    = intval(get_user_meta($uid, 'bs_block_number', true));
    $prefix= bs_postal_prefix(get_user_meta($uid, 'bs_postal_code', true));
    $block = $prefix ? bs_block_for_prefix($prefix) : null;
    $pid   = get_user_meta($uid, 'bs_project', true) ?: BS_DEFAULT_PROJECT;
    $proj  = bs_project($pid);

    $survey = $wpdb->prefix . 'bs_survey';
    $q_done = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $survey WHERE user_id=%d AND section='A'", $uid)));
    $participants = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT user_id) FROM $survey WHERE project=%s", $proj['id'])));
    $today = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}bs_cc_ledger
          WHERE user_id=%d AND bucket='spendable' AND amount>0 AND tx_type IN ('survey_earn','referral','item_share')
            AND created_at >= %s", $uid, gmdate('Y-m-d 00:00:00'))));
    $quest_done = (bool) get_user_meta($uid, 'bs_section_a_done', true);
    $pending_cc = $quest_done ? 0 : floatval(get_user_meta($uid, 'bs_cc_locked', true));

    return new WP_REST_Response([
        'user' => [
            'first_name'      => $first,
            'block_number'    => (string) $bn,
            'neighbourhood'   => $block ? $block->neighbourhood : 'Vancouver, BC',
            'cc_spendable'    => floatval(get_user_meta($uid, 'bs_cc_spendable', true)),
            'cc_locked'       => floatval(get_user_meta($uid, 'bs_cc_locked', true)),
            'cc_earned_today' => $today,
            'quests_done'     => $q_done,
        ],
        'projects' => [[
            'id'           => $proj['id'],
            'name'         => $proj['name'],
            'tagline'      => $proj['tagline'],
            'participants' => $participants,
            'your_q_done'  => $q_done,
            'quest_done'   => $quest_done,
            'cc_pending'   => $pending_cc,
        ]],
        'block_pulse' => ['members' => $block ? intval($block->member_count) : 0],
    ], 200);
}

function bs_api_items($req) {
    global $wpdb;
    $uid = bs_auth($req);
    if (!$uid) return bs_unauth();
    $t = $wpdb->prefix . 'bs_items';
    $prefix = bs_postal_prefix(get_user_meta($uid, 'bs_postal_code', true));
    $cols = 'id, title, description, category, status';
    $nearby = $wpdb->get_results($wpdb->prepare(
        "SELECT $cols FROM $t WHERE postal_prefix=%s AND user_id<>%d AND status='available' ORDER BY created_at DESC LIMIT 20", $prefix, $uid), ARRAY_A);
    $mine = $wpdb->get_results($wpdb->prepare(
        "SELECT $cols FROM $t WHERE user_id=%d ORDER BY created_at DESC LIMIT 50", $uid), ARRAY_A);
    return new WP_REST_Response(['nearby' => $nearby ?: [], 'mine' => $mine ?: []], 200);
}

function bs_api_item_add($req) {
    global $wpdb;
    $uid = bs_auth($req);
    if (!$uid) return bs_unauth();
    $title = sanitize_text_field($req->get_param('title'));
    if (!$title) return new WP_REST_Response(['error' => 'title required'], 400);
    $allowed = ['tools','camping','kitchen','bikes','vehicles','sports','garden','furniture','party','medical','watercraft','patio'];
    $cat = sanitize_key($req->get_param('category'));
    if (!in_array($cat, $allowed, true)) $cat = 'tools';
    $item = [
        'user_id' => $uid, 'title' => mb_substr($title, 0, 120),
        'description' => mb_substr(sanitize_text_field($req->get_param('description')), 0, 255),
        'category' => $cat, 'status' => 'available',
        'postal_prefix' => bs_postal_prefix(get_user_meta($uid, 'bs_postal_code', true)),
    ];
    $wpdb->insert($wpdb->prefix . 'bs_items', $item);
    $item['id'] = $wpdb->insert_id;
    do_action('bs_item_shared', $uid, $item);
    return new WP_REST_Response(['success' => true, 'item' => $item], 200);
}

function bs_api_block($req) {
    global $wpdb;
    $prefix = bs_postal_prefix($req['postal']);
    $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bs_blocks WHERE postal_prefix=%s", $prefix));
    if (!$b) return new WP_REST_Response(['error' => 'Block not found'], 404);
    $budget = intval($b->median_income) * intval($b->households);
    return new WP_REST_Response([
        'block_number' => intval($b->block_number), 'neighbourhood' => $b->neighbourhood,
        'postal_prefix' => $b->postal_prefix, 'median_income' => intval($b->median_income),
        'households' => intval($b->households), 'total_budget' => $budget,
        'five_pct_redirect' => $budget * 0.05, 'multiplier_impact' => $budget * 0.05 * 2.6,
        'registered_members' => intval($b->member_count), 'cc_in_circulation' => floatval($b->cc_total),
    ], 200);
}
