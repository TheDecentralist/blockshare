<?php
/**
 * Plugin Name: Block Share Onboarding v2
 * Description: Routes /join/ /quest/ /home/ to the onboarding SPAs, serves the PWA manifest, and redirects legacy /onboarding/ to /join/.
 * Version: 2.0.0-restore
 */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    foreach (['join', 'quest', 'home'] as $slug) {
        add_rewrite_rule('^' . $slug . '/?$', 'index.php?bs_onb=' . $slug, 'top');
    }
    add_rewrite_rule('^onboarding/?$', 'index.php?bs_onb=legacy', 'top');
    add_rewrite_rule('^bs-manifest\.json$', 'index.php?bs_onb=manifest', 'top');
});
add_filter('query_vars', function ($v) { $v[] = 'bs_onb'; return $v; });

register_activation_hook(__FILE__, function () {
    // rules are added on init; flush after they exist
    add_option('bs_onb_flush', 1);
});
add_action('init', function () {
    if (get_option('bs_onb_flush')) { flush_rewrite_rules(); delete_option('bs_onb_flush'); }
}, 99);

add_action('template_redirect', function () {
    $p = get_query_var('bs_onb');
    if (!$p) return;

    if ($p === 'legacy') {
        $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
        wp_redirect(home_url('/join/') . $qs, 302);
        exit;
    }
    if ($p === 'manifest') {
        header('Content-Type: application/manifest+json; charset=UTF-8');
        header('Cache-Control: public, max-age=86400');
        echo wp_json_encode([
            'name' => 'Block Share', 'short_name' => 'Block Share',
            'start_url' => '/home/', 'scope' => '/', 'display' => 'standalone',
            'background_color' => '#F3EFE6', 'theme_color' => '#0a5e3e', 'icons' => [],
        ]);
        exit;
    }
    $file = __DIR__ . '/' . $p . '.html';
    if (in_array($p, ['join', 'quest', 'home'], true) && file_exists($file)) {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('Referrer-Policy: same-origin'); // keep ?token= out of Referer headers
        readfile($file);
        exit;
    }
    status_header(404);
});
