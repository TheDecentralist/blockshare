<?php
/**
 * Block Share — send all WordPress mail through Brevo SMTP.
 * Credentials live in wp-config.php (never in the repo):
 *   BS_SMTP_USER  your Brevo SMTP login
 *   BS_SMTP_PASS  your Brevo SMTP key
 *   BS_MAIL_FROM  a sender address on a Brevo-verified domain (e.g. hello@blockshare.ca)
 */
if (!defined('ABSPATH')) exit;

add_action('phpmailer_init', function ($m) {
    if (!defined('BS_SMTP_USER') || !defined('BS_SMTP_PASS')) return;
    $m->isSMTP();
    $m->Host       = 'smtp-relay.brevo.com';
    $m->Port       = 2525;
    $m->SMTPAuth   = true;
    $m->SMTPSecure = 'tls';
    $m->Username   = BS_SMTP_USER;
    $m->Password   = BS_SMTP_PASS;
});
add_filter('wp_mail_from', function ($from) {
    return defined('BS_MAIL_FROM') ? BS_MAIL_FROM : $from;
});
add_filter('wp_mail_from_name', function () { return 'Block Share'; });
