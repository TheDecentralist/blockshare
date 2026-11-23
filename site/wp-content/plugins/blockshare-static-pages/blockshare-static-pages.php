<?php
/**
 * Plugin Name: BlockShare Static Pages
 * Description: Serves all BlockShare static HTML pages at clean WordPress URLs.
 * Version: 2.1.0
 * Author: Block Share
 */
if (!defined('ABSPATH')) exit;

define('BS_PAGES_DIR', WP_CONTENT_DIR . '/blockshare-pages/');

// ── Hardcoded core pages ──────────────────────────────────────────────────────
$bs_page_map_core = [
    'blockonomics'    => 'blockonomics.html',
    'blockbudget'     => 'blockbudget/index.html',
    'bs-home'         => 'blockshare-homepage.html',
    'about'           => 'about.html',
    'share'           => 'share.html',
    'food'            => 'food.html',
    'credits'         => 'credits.html',
    'find-your-block' => 'find-your-block.html',
    'join-block'      => 'join-block.html',
    'become-host'     => 'become-host.html',
    'host-dashboard'  => 'host-dashboard.html',
];

// ── Dynamic pages stored in DB ────────────────────────────────────────────────
function bs_get_dynamic_pages() {
    return get_option('bs_dynamic_pages', []);
}

function bs_save_dynamic_pages($pages) {
    update_option('bs_dynamic_pages', $pages);
}

// ── Merge both maps ───────────────────────────────────────────────────────────
function bs_get_page_map() {
    global $bs_page_map_core;
    return array_merge($bs_page_map_core, bs_get_dynamic_pages());
}

// ── Activation ───────────────────────────────────────────────────────────────
register_activation_hook(__FILE__, function() {
    if (!file_exists(BS_PAGES_DIR)) wp_mkdir_p(BS_PAGES_DIR);
    if (!file_exists(BS_PAGES_DIR . 'blockbudget/')) wp_mkdir_p(BS_PAGES_DIR . 'blockbudget/');
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// ── Rewrite rules ─────────────────────────────────────────────────────────────
add_action('init', function() {
    foreach (array_keys(bs_get_page_map()) as $slug) {
        add_rewrite_rule('^' . preg_quote($slug, '/') . '/?$', 'index.php?bs_page=' . $slug, 'top');
    }
    add_rewrite_rule('^blockbudget/index\.html?$', 'index.php?bs_page=blockbudget', 'top');
});

add_filter('query_vars', function($vars) {
    $vars[] = 'bs_page';
    return $vars;
});

// ── Serve pages ───────────────────────────────────────────────────────────────
add_action('template_redirect', function() {
    $page = get_query_var('bs_page');
    if (!$page) return;

    $map = bs_get_page_map();
    if (isset($map[$page])) {
        $file = BS_PAGES_DIR . $map[$page];
        if (file_exists($file)) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: public, max-age=3600');
            readfile($file);
            exit;
        }
    }

    wp_die(
        '<div style="font-family:system-ui;max-width:600px;margin:40px auto;padding:30px;">'
        . '<h1 style="color:#0f2b1d;">◆ BlockShare Page Not Found</h1>'
        . '<p>The page <code>' . esc_html($page) . '</code> hasn\'t been uploaded yet.</p>'
        . '<p>Go to <strong>Static Pages</strong> in your WordPress admin to upload it.</p></div>',
        'Page Not Found', ['response' => 404]
    );
});

// ============================================================
// ADMIN PAGE
// ============================================================
add_action('admin_menu', function() {
    add_menu_page('Static Pages','Static Pages','manage_options','blockshare-static-pages','bs_static_pages_admin','dashicons-media-code',31);
});

function bs_static_pages_admin() {
    global $bs_page_map_core;
    $dynamic = bs_get_dynamic_pages();
    $map     = bs_get_page_map();
    $notices = [];

    // ── Handle: Register new page ────────────────────────────────────────────
    if (isset($_POST['bs_register_page']) && wp_verify_nonce($_POST['_wpnonce'], 'bs_register_page')) {
        $new_slug = sanitize_title($_POST['new_slug']);
        $new_desc = sanitize_text_field($_POST['new_desc']);
        $new_file = sanitize_file_name($_POST['new_file']);

        if ($new_slug && $new_file) {
            if (isset($map[$new_slug])) {
                $notices[] = ['error', "Slug <strong>{$new_slug}</strong> already exists."];
            } else {
                $dynamic[$new_slug] = $new_file;
                bs_save_dynamic_pages($dynamic);
                // Store description separately
                $descs = get_option('bs_page_descriptions', []);
                $descs[$new_slug] = $new_desc;
                update_option('bs_page_descriptions', $descs);
                flush_rewrite_rules();
                $map = bs_get_page_map();
                $notices[] = ['success', "Page <strong>{$new_slug}</strong> registered. Now upload the HTML file using Single Upload below."];
            }
        } else {
            $notices[] = ['error', 'Slug and filename are both required.'];
        }
    }

    // ── Handle: Delete dynamic page ──────────────────────────────────────────
    if (isset($_POST['bs_delete_page']) && wp_verify_nonce($_POST['_wpnonce'], 'bs_delete_page')) {
        $del_slug = sanitize_title($_POST['del_slug']);
        if (isset($dynamic[$del_slug])) {
            unset($dynamic[$del_slug]);
            bs_save_dynamic_pages($dynamic);
            $map = bs_get_page_map();
            flush_rewrite_rules();
            $notices[] = ['success', "Page <strong>{$del_slug}</strong> removed from registry (file not deleted from disk)."];
        }
    }

    // ── Handle: Single upload ────────────────────────────────────────────────
    if (isset($_POST['bs_upload_page']) && wp_verify_nonce($_POST['_wpnonce'], 'bs_upload_page')) {
        $target = sanitize_text_field($_POST['page_target']);
        $map = bs_get_page_map();
        if (isset($map[$target]) && !empty($_FILES['page_file']['tmp_name']) && $_FILES['page_file']['error'] === 0) {
            $dest = BS_PAGES_DIR . $map[$target];
            $dir  = dirname($dest);
            if (!file_exists($dir)) wp_mkdir_p($dir);
            $content = file_get_contents($_FILES['page_file']['tmp_name']);
            if ($content !== false) {
                $written = file_put_contents($dest, $content);
                if ($written !== false) {
                    $url = home_url('/' . $target . '/');
                    $notices[] = ['success', "Uploaded <strong>{$target}</strong> (" . size_format($written) . "). <a href='{$url}' target='_blank'>View →</a>"];
                } else {
                    $notices[] = ['error', "Write failed for <code>{$dest}</code> — check folder permissions."];
                }
            }
        }
    }

    // ── Handle: Bulk ZIP upload ───────────────────────────────────────────────
    if (isset($_POST['bs_bulk_upload']) && wp_verify_nonce($_POST['_wpnonce'], 'bs_bulk_upload')) {
        if (!empty($_FILES['bulk_zip']['tmp_name']) && $_FILES['bulk_zip']['error'] === 0) {
            $zip = new ZipArchive;
            if ($zip->open($_FILES['bulk_zip']['tmp_name']) === TRUE) {
                $map   = bs_get_page_map();
                $count = 0;
                $skipped = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry    = $zip->getNameIndex($i);
                    $basename = basename($entry); // handles subfolder ZIPs
                    if (substr($basename, -5) !== '.html') continue;

                    $matched = false;
                    foreach ($map as $slug => $file) {
                        if (basename($file) === $basename) {
                            $dest    = BS_PAGES_DIR . $file;
                            $dir     = dirname($dest);
                            if (!file_exists($dir)) wp_mkdir_p($dir);
                            $content = $zip->getFromIndex($i);
                            if ($content !== false) {
                                file_put_contents($dest, $content);
                                $count++;
                            }
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) $skipped[] = $basename;
                }
                $zip->close();

                $msg = "Bulk upload complete: <strong>{$count} pages</strong> installed.";
                if (!empty($skipped)) {
                    $msg .= '<br>⚠ These files were in the ZIP but not registered (no matching slug): <code>' . implode(', ', $skipped) . '</code>. Register them first using the form below, then re-upload.';
                }
                $notices[] = ['success', $msg];
            } else {
                $notices[] = ['error', 'Could not open ZIP file.'];
            }
        }
    }

    // ── Handle: Update links ─────────────────────────────────────────────────
    if (isset($_POST['bs_update_links']) && wp_verify_nonce($_POST['_wpnonce'], 'bs_update_links')) {
        $base        = rtrim(home_url(), '/');
        $count_total = 0;
        $files       = array_merge(glob(BS_PAGES_DIR . '*.html') ?: [], glob(BS_PAGES_DIR . '*/*.html') ?: []);
        $replacements = [
            'https://blockshare.great-site.net/blockonomics/blockonomics.html' => $base . '/blockonomics/',
            'https://blockshare.great-site.net/blockonomics/blocksurvey.html'  => $base . '/onboarding/',
            'https://blockshare.great-site.net/blockbudget/index.html'         => $base . '/blockbudget/',
            'http://blockshare.great-site.net/blockonomics/blockonomics.html'  => $base . '/blockonomics/',
            'http://blockshare.great-site.net/blockonomics/blocksurvey.html'   => $base . '/onboarding/',
            'http://blockshare.great-site.net/blockbudget/index.html'          => $base . '/blockbudget/',
            'http://134.122.44.158'         => $base,
            'href="blockshare-homepage.html"' => 'href="' . $base . '/bs-home/"',
            'href="about.html"'             => 'href="' . $base . '/about/"',
            'href="share.html"'             => 'href="' . $base . '/share/"',
            'href="food.html"'              => 'href="' . $base . '/food/"',
            'href="credits.html"'           => 'href="' . $base . '/credits/"',
            'href="find-your-block.html"'   => 'href="' . $base . '/find-your-block/"',
            'href="join-block.html"'        => 'href="' . $base . '/join-block/"',
            'href="become-host.html"'       => 'href="' . $base . '/become-host/"',
            'href="host-dashboard.html"'    => 'href="' . $base . '/host-dashboard/"',
        ];
        // Add dynamic page replacements
        foreach (bs_get_dynamic_pages() as $slug => $file) {
            $replacements['href="' . basename($file) . '"'] = 'href="' . $base . '/' . $slug . '/"';
        }
        foreach ($files as $f) {
            $content = file_get_contents($f);
            $c = 0;
            foreach ($replacements as $old => $new) {
                $content = str_replace($old, $new, $content, $n);
                $c += $n;
            }
            if ($c > 0) { file_put_contents($f, $content); $count_total += $c; }
        }
        $onboarding = WP_CONTENT_DIR . '/plugins/blockshare-onboarding-v2/onboarding.html';
        if (file_exists($onboarding)) {
            $content = file_get_contents($onboarding);
            foreach ($replacements as $old => $new) $content = str_replace($old, $new, $content);
            file_put_contents($onboarding, $content);
        }
        $notices[] = ['success', "Updated <strong>{$count_total}</strong> links across all pages."];
    }

    // ── Descriptions ─────────────────────────────────────────────────────────
    $core_descriptions = [
        'blockonomics'    => '3rd Way ecosystem overview, Block Budget context',
        'blockbudget'     => 'Leaflet map — 5,806 Vancouver blocks, SuperBlocks',
        'bs-home'         => 'Main homepage — Share, Food, Credits overview',
        'about'           => 'Our Story, principles, thinkers, timeline',
        'share'           => 'Sharing pools — categories, savings math, how it works',
        'food'            => 'Beef Collective — box pricing, order calendar, sourcing',
        'credits'         => 'Community Credits — backing model, earn/spend, ranks',
        'find-your-block' => 'Postal code lookup → block assignment',
        'join-block'      => 'Resident registration form',
        'become-host'     => 'Host application with multi-step form',
        'host-dashboard'  => 'Host management dashboard (demo)',
    ];
    $dynamic_descriptions = get_option('bs_page_descriptions', []);
    $all_descriptions = array_merge($core_descriptions, $dynamic_descriptions);

    ?>
    <div class="wrap" style="max-width:1020px;">
        <h1>◆ BlockShare Static Pages</h1>
        <p style="color:#666;">Static HTML pages served at clean WordPress URLs. Core pages are hardcoded; additional pages can be registered below.</p>

        <?php foreach ($notices as [$type, $msg]): ?>
        <div class="notice notice-<?php echo $type; ?> is-dismissible"><p><?php echo $msg; ?></p></div>
        <?php endforeach; ?>

        <?php
        // Reload map after any saves
        $dynamic = bs_get_dynamic_pages();
        $map     = bs_get_page_map();
        ?>

        <!-- PAGE TABLE -->
        <table class="widefat striped" style="margin:20px 0;">
            <thead><tr><th>Slug</th><th>File</th><th>URL</th><th>Status</th><th>Size</th><th>Type</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($map as $slug => $file):
                $full   = BS_PAGES_DIR . $file;
                $exists = file_exists($full);
                $size   = $exists ? size_format(filesize($full)) : '—';
                $url    = home_url('/' . $slug . '/');
                $desc   = $all_descriptions[$slug] ?? '';
                $is_dynamic = isset($dynamic[$slug]);
            ?>
            <tr>
                <td><strong><?php echo esc_html($slug); ?></strong><?php if ($desc): ?><br><small style="color:#888;"><?php echo esc_html($desc); ?></small><?php endif; ?></td>
                <td><code style="font-size:0.8em;"><?php echo esc_html($file); ?></code></td>
                <td><a href="<?php echo esc_url($url); ?>" target="_blank" style="font-size:0.8em;"><?php echo esc_html($url); ?></a></td>
                <td><?php echo $exists
                    ? '<span style="color:#2e7d32;font-weight:600;">✓ Uploaded</span>'
                    : '<span style="color:#c62828;font-weight:600;">✗ Missing</span>'; ?>
                </td>
                <td><?php echo esc_html($size); ?></td>
                <td><span style="font-size:0.75em;color:<?php echo $is_dynamic ? '#1e4d7b' : '#888'; ?>;"><?php echo $is_dynamic ? '● Dynamic' : 'Core'; ?></span></td>
                <td><?php if ($is_dynamic): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Remove <?php echo esc_js($slug); ?> from registry?');">
                        <?php wp_nonce_field('bs_delete_page'); ?>
                        <input type="hidden" name="del_slug" value="<?php echo esc_attr($slug); ?>">
                        <input type="hidden" name="bs_delete_page" value="1">
                        <button type="submit" class="button button-small" style="color:#c62828;">Remove</button>
                    </form>
                <?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td><strong>onboarding</strong><br><small style="color:#888;">Community onboarding survey (plugin)</small></td>
                <td><code style="font-size:0.8em;">plugin</code></td>
                <td><a href="<?php echo home_url('/onboarding/'); ?>" target="_blank" style="font-size:0.8em;"><?php echo home_url('/onboarding/'); ?></a></td>
                <td><span style="color:#2e7d32;font-weight:600;">✓ Active (plugin)</span></td>
                <td>—</td><td><span style="font-size:0.75em;color:#888;">Core</span></td><td></td>
            </tr>
            </tbody>
        </table>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

            <!-- SINGLE UPLOAD -->
            <div style="background:#fff;padding:24px;border-radius:8px;border:1px solid #e0e0e0;">
                <h2 style="margin-top:0;">Upload Single Page</h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('bs_upload_page'); ?>
                    <p>
                        <select name="page_target" style="width:100%;padding:8px;">
                            <?php foreach ($map as $slug => $file): ?>
                            <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($slug); ?> (<?php echo esc_html(basename($file)); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p><input type="file" name="page_file" accept=".html,.htm"></p>
                    <input type="hidden" name="bs_upload_page" value="1">
                    <?php submit_button('Upload Page', 'primary', '', false); ?>
                </form>
            </div>

            <!-- BULK ZIP -->
            <div style="background:#fff;padding:24px;border-radius:8px;border:1px solid #e0e0e0;">
                <h2 style="margin-top:0;">Bulk Upload (ZIP)</h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('bs_bulk_upload'); ?>
                    <p>ZIP the HTML files directly (not a folder). Files are matched by filename to registered slugs.<br><small style="color:#888;">Any unregistered filenames will be listed so you can register them first.</small></p>
                    <p><input type="file" name="bulk_zip" accept=".zip"></p>
                    <input type="hidden" name="bs_bulk_upload" value="1">
                    <?php submit_button('Upload ZIP', 'primary', '', false); ?>
                </form>
            </div>
        </div>

        <!-- REGISTER NEW PAGE -->
        <div style="background:#f0f7ff;padding:24px;border-radius:8px;border:1.5px solid #1e4d7b;margin-bottom:20px;">
            <h2 style="margin-top:0;color:#1e4d7b;">Register a New Page</h2>
            <p style="color:#444;">Add a new slug → filename mapping. Once registered, the slug appears in the upload dropdown and ZIP matching above.</p>
            <form method="post">
                <?php wp_nonce_field('bs_register_page'); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:10px;align-items:end;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;">URL Slug <small style="font-weight:300;">(e.g. block-explorer)</small></label>
                        <input type="text" name="new_slug" placeholder="block-explorer" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;" required>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;">HTML Filename <small style="font-weight:300;">(e.g. block-explorer-v5.html)</small></label>
                        <input type="text" name="new_file" placeholder="block-explorer-v5.html" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;" required>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:4px;">Description <small style="font-weight:300;">(optional)</small></label>
                        <input type="text" name="new_desc" placeholder="Block Budget Explorer v5" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
                    </div>
                    <div>
                        <input type="hidden" name="bs_register_page" value="1">
                        <?php submit_button('Register', 'primary', '', false, ['style'=>'margin-top:0;']); ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- UPDATE LINKS -->
        <div style="background:#fff;padding:24px;border-radius:8px;border:1px solid #e0e0e0;">
            <h2 style="margin-top:0;">Update All Internal Links</h2>
            <p>Rewrites <code>.html</code> file references and old URLs to clean WordPress paths using <code><?php echo home_url(); ?></code></p>
            <form method="post">
                <?php wp_nonce_field('bs_update_links'); ?>
                <input type="hidden" name="bs_update_links" value="1">
                <?php submit_button('Update All Links', 'secondary', '', false); ?>
            </form>
        </div>
    </div>
    <?php
}
