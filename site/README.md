# BlockShare site restore package (built 22 Nov 2026)

Layout mirrors the server:
- bs-nav-footer.js                       -> /var/www/html/bs-nav-footer.js   (nav/footer v3.2, June 2026)
- wp-content/blockshare-pages/           -> /var/www/html/wp-content/blockshare-pages/
- wp-content/uploads/bs-shared/          -> /var/www/html/wp-content/uploads/bs-shared/
- wp-content/plugins/blockshare-static-pages/ -> /var/www/html/wp-content/plugins/blockshare-static-pages/
- dynamic-pages.json + seed-pages.sh     -> run once on the server (registers slugs)
- MANIFEST.tsv                           -> slug / file / source / notes for every page

Known gaps (no copy found): the-fine-print, host-dashboard, food, game-of-towers, missing-pools,
little-mountain-holborn, policy-decoder, join/quest/home app pages, blockshare WP plugin (REST API).
