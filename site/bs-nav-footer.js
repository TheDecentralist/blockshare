/**
 * bs-nav-footer.js  ·  Block Share Global Navigation & Footer
 * Version 3.3  ·  Updated November 2026 (single Block Budget entry; /block-budget/ is an alias of /blockbudget/)
 *
 * TWO USAGE PATTERNS — both work automatically, no config needed:
 *
 * A) EXISTING WORDPRESS PAGES (standard pattern)
 *    <head>
 *      <link rel="stylesheet" href="/wp-content/uploads/bs-shared/bs-nav-footer.css?v=8">
 *    </head>
 *    <body>
 *      <nav id="bs-nav"></nav>          ← placeholder; JS fills it
 *      <!-- media bar here if needed -->
 *      <!-- page content -->
 *      <script src="/bs-nav-footer.js"></script>
 *    </body>
 *
 * B) STANDALONE HTML PAGES (no WordPress, no external CSS)
 *    <body>
 *      <!-- page content — no placeholder needed -->
 *      <script src="/bs-nav-footer.js"></script>
 *    </body>
 *    JS detects missing CSS link and injects styles automatically.
 *    JS detects missing #bs-nav and prepends it to <body>.
 *
 * Active page is auto-detected from window.location.pathname.
 * ─────────────────────────────────────────────────────────────────────────────
 */

(function () {
  'use strict';

  /* ═══════════════════════════════════════════════════════════════════════════
     SITE MAP  —  All pages, grouped into nav sections
     slug: matches the pathname segment (e.g. '/blockonomics/' → 'blockonomics')
     ═══════════════════════════════════════════════════════════════════════════ */
  const BASE = 'https://app.blockshare.ca';
  const DECODER = 'https://policy-decoder.blockshare.ca';
  const ROOT = 'https://blockshare.ca';

  const NAV = [
    /* ── Top-level singles ───────────────────────────────────────────────── */
    {
      label: 'Home',
      slug: 'bs-home',
      href: BASE + '/bs-home/',
      topLevel: true,
    },
    {
      label: 'About',
      slug: 'about',
      href: BASE + '/about/',
      topLevel: true,
    },

    /* ── PLATFORM ─────────────────────────────────────────────────────────── */
    {
      label: 'Platform',
      slug: 'platform',
      topLevel: true,
      children: [
        {
          groupLabel: 'Your Block',
          items: [
            { label: 'Find Your Block',      slug: 'find-your-block',  href: BASE + '/find-your-block/' },
            { label: 'Join Your Block',      slug: 'join-block',       href: BASE + '/join-block/' },
            { label: 'Block Budget Map',     slug: 'blockbudget',      href: BASE + '/blockbudget/' },
            { label: 'Blockonomics',         slug: 'blockonomics',     href: BASE + '/blockonomics/' },
          ],
        },
        {
          groupLabel: 'Hosting',
          items: [
            { label: 'Become a Host',    slug: 'become-host',      href: BASE + '/become-host/' },
            { label: 'Host Dashboard',   slug: 'host-dashboard',   href: BASE + '/host-dashboard/' },
          ],
        },
        {
          groupLabel: 'Community App',
          items: [
            { label: 'Block Share App',  slug: 'app',              href: BASE + '/app/' },
            { label: 'Onboarding',       slug: 'onboarding',       href: BASE + '/onboarding/' },
          ],
        },
      ],
    },

    /* ── PROGRAMS ─────────────────────────────────────────────────────────── */
    {
      label: 'Programs',
      slug: 'programs',
      topLevel: true,
      children: [
        {
          groupLabel: 'Economy',
          items: [
            { label: 'Community Credits (MYCC)',  slug: 'credits',              href: BASE + '/credits/' },
            { label: 'Share Pool',                slug: 'share',                href: BASE + '/share/' },
            { label: 'Buy Back Your Block',       slug: 'buy-back-your-block',  href: BASE + '/buy-back-your-block/' },
            { label: 'Restore to Own',            slug: 'restore-to-own',       href: BASE + '/restore-to-own/' },
          ],
        },
        {
          groupLabel: 'Food & Land',
          items: [
            { label: 'Beef Collective (Food)',    slug: 'food',                 href: BASE + '/food/' },
            { label: 'Food for Generations',      slug: 'food-for-generations', href: BASE + '/food-for-generations/' },
            { label: 'Permanent Land Trust',      slug: 'land-trusts',          href: BASE + '/land-trusts/' },
          ],
        },
        {
          groupLabel: 'Education',
          items: [
            { label: 'Game of Towers',            slug: 'game-of-towers',       href: BASE + '/game-of-towers/' },
            { label: 'Mobility for Life',         slug: 'mobility-for-life',    href: BASE + '/mobility-for-life/' },
          ],
        },
      ],
    },

    /* ── RESEARCH ─────────────────────────────────────────────────────────── */
    {
      label: 'Research',
      slug: 'research',
      topLevel: true,
      children: [
        {
          groupLabel: 'All Publications',
          items: [
            { label: '← All Publications',       slug: 'publications',         href: BASE + '/publications/', highlight: true },
          ],
        },
        {
          groupLabel: 'Land & Housing',
          items: [
            { label: 'Little Mountain / Holborn ↗', slug: 'little-mountain-holborn', href: BASE + '/little-mountain-holborn/', badge: 'New' },
            { label: 'Land for Whom',             slug: 'land-for-whom',        href: BASE + '/land-for-whom/' },
            { label: 'Permanent Land Trusts',     slug: 'land-trusts',          href: BASE + '/land-trusts/' },
            { label: 'Vancouver Housing Analysis', slug: 'vancouver-housing-policy-analysis', href: BASE + '/vancouver-housing-policy-analysis/' },
            { label: 'Missing Pools (Aquatic)',   slug: 'missing-pools',        href: BASE + '/missing-pools/' },
          ],
        },
        {
          groupLabel: 'Economics & Systems',
          items: [
            { label: 'Private Taxes',             slug: 'private-taxes',        href: BASE + '/private-taxes/' },
            { label: 'The Fine Print',            slug: 'the-fine-print',       href: BASE + '/the-fine-print/' },
            { label: 'Tale of Two Cycles',        slug: 'tale-of-two-cycles',   href: BASE + '/tale-of-two-cycles/' },
            { label: 'The 3rd Way',               slug: 'third-way',            href: BASE + '/third-way/' },
            { label: 'Debt, Interest & GDP',      slug: 'debt-interest-death-of-gdp', href: BASE + '/debt-interest-death-of-gdp/' },
          ],
        },
        {
          groupLabel: 'Governance & Civic',
          items: [
            { label: 'Unrepresented',             slug: 'unrepresented',        href: BASE + '/unrepresented/' },
            { label: 'Bill M216 Analysis',        slug: 'bill-m216-article',    href: BASE + '/bill-m216-article/' },
            { label: 'Why a Citizens Dashboard',  slug: 'citizen-dashboard-article', href: BASE + '/citizen-dashboard-article/' },
          ],
        },
      ],
    },

    /* ── TOOLS ────────────────────────────────────────────────────────────── */
    {
      label: 'Tools',
      slug: 'tools',
      topLevel: true,
      children: [
        {
          groupLabel: 'Interactive',
          items: [
            { label: 'Policy Decoder Machine ↗',  slug: '_decoder',             href: DECODER,                          external: true },
            { label: 'Citizens Dashboard',         slug: 'citizens-dashboard',   href: BASE + '/citizens-dashboard/' },
            { label: 'LEI / LTE Calculator',       slug: 'lei-calculator',       href: BASE + '/lei-calculator/' },
            { label: 'CC System Diagram',          slug: 'cc-system-diagram',    href: BASE + '/cc-system-diagram/' },
          ],
        },
        {
          groupLabel: 'Analysis',
          items: [
            { label: 'Micro-Asset Real Estate',    slug: 'micro-asset-real-estate-example', href: BASE + '/micro-asset-real-estate-example/' },
            { label: 'Buy-Back Calculator',        slug: 'micro-asset-buy-back', href: BASE + '/micro-asset-buy-back/' },
          ],
        },
      ],
    },

    /* ── Contact ──────────────────────────────────────────────────────────── */
    {
      label: 'Contact',
      slug: 'contact',
      href: BASE + '/contact/',
      topLevel: true,
    },
  ];

  /* ═══════════════════════════════════════════════════════════════════════════
     DETECT CURRENT PAGE
     ═══════════════════════════════════════════════════════════════════════════ */
  function currentSlug() {
    const path = window.location.pathname.replace(/^\/|\/$/g, '');
    const parts = path.split('/');
    return parts[parts.length - 1] || parts[0] || 'bs-home';
  }

  function isActive(slug) {
    return slug === currentSlug();
  }

  function anyChildActive(children) {
    return children.some(group =>
      group.items.some(item => isActive(item.slug))
    );
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     BUILD DESKTOP DROPDOWN
     ═══════════════════════════════════════════════════════════════════════════ */
  function buildDropdown(groups) {
    let html = '<div class="bs-dropdown">';
    groups.forEach(group => {
      html += `<div class="bs-drop-group">`;
      html += `<div class="bs-drop-label">${group.groupLabel}</div>`;
      group.items.forEach(item => {
        const active = isActive(item.slug) ? ' bs-active' : '';
        const hi     = item.highlight ? ' bs-hi' : '';
        const badge  = item.badge ? `<span class="bs-badge">${item.badge}</span>` : '';
        const target = item.external ? ' target="_blank" rel="noopener"' : '';
        html += `<a href="${item.href}" class="bs-drop-item${active}${hi}"${target}>${item.label}${badge}</a>`;
      });
      html += `</div>`;
    });
    html += '</div>';
    return html;
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     BUILD MOBILE ACCORDION SECTION
     ═══════════════════════════════════════════════════════════════════════════ */
  function buildMobileSection(entry, idx) {
    if (entry.topLevel && !entry.children) return ''; // singles handled separately
    let open = anyChildActive(entry.children) ? ' bs-mob-open' : '';
    let html = `<div class="bs-mob-section${open}">`;
    html += `<button class="bs-mob-toggle" aria-expanded="${open ? 'true' : 'false'}" data-idx="${idx}">
               ${entry.label}
               <span class="bs-mob-chevron">▾</span>
             </button>`;
    html += '<div class="bs-mob-panel">';
    entry.children.forEach(group => {
      html += `<div class="bs-mob-group-label">${group.groupLabel}</div>`;
      group.items.forEach(item => {
        const active = isActive(item.slug) ? ' bs-active' : '';
        const target = item.external ? ' target="_blank" rel="noopener"' : '';
        html += `<a href="${item.href}" class="bs-mob-link${active}"${target}>${item.label}</a>`;
      });
    });
    html += '</div></div>';
    return html;
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     BUILD NAV HTML
     ═══════════════════════════════════════════════════════════════════════════ */
  function buildNav() {
    /* ── desktop top bar ── */
    let desktopItems = '';
    NAV.forEach((entry, idx) => {
      if (!entry.topLevel) return;
      if (!entry.children) {
        const active = isActive(entry.slug) ? ' bs-active' : '';
        desktopItems += `<a href="${entry.href}" class="bs-nav-link${active}">${entry.label}</a>`;
      } else {
        const active = anyChildActive(entry.children) ? ' bs-active' : '';
        desktopItems += `
          <div class="bs-nav-item" data-menu="${idx}">
            <button class="bs-nav-link${active}" aria-haspopup="true" aria-expanded="false">
              ${entry.label} <span class="bs-caret">▾</span>
            </button>
            ${buildDropdown(entry.children)}
          </div>`;
      }
    });

    /* ── mobile drawer ── */
    let mobileLinks = '';
    NAV.forEach((entry, idx) => {
      if (!entry.topLevel) return;
      if (!entry.children) {
        const active = isActive(entry.slug) ? ' bs-active' : '';
        mobileLinks += `<a href="${entry.href}" class="bs-mob-link${active}">${entry.label}</a>`;
      } else {
        mobileLinks += buildMobileSection(entry, idx);
      }
    });

    /* Return INNER content only — the <nav id="bs-nav"> wrapper is handled
       by inject() so it can either fill an existing placeholder or create one */
    return `
  <div class="bs-nav-inner">

    <!-- Logo -->
    <a href="${BASE}/bs-home/" class="bs-logo" aria-label="Block Share Home">
      <span class="bs-logo-mark">◈</span>
      <span class="bs-logo-text">Block<span>Share</span></span>
    </a>

    <!-- Desktop links -->
    <div class="bs-nav-links" id="bs-desktop-nav">
      ${desktopItems}
    </div>

    <!-- CTA -->
    <a href="${BASE}/join-block/" class="bs-nav-cta">Join Your Block</a>

    <!-- Hamburger -->
    <button class="bs-hamburger" id="bs-hamburger" aria-label="Open menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>

  <!-- Mobile drawer -->
  <div class="bs-mob-drawer" id="bs-mob-drawer" aria-hidden="true">
    <div class="bs-mob-inner">
      ${mobileLinks}
      <div style="padding:20px 0 8px;">
        <a href="${BASE}/join-block/" class="bs-nav-cta" style="display:block;text-align:center;">Join Your Block</a>
      </div>
    </div>
  </div>`;
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     BUILD FOOTER HTML
     ═══════════════════════════════════════════════════════════════════════════ */
  function buildFooter() {
    return `
<footer id="bs-footer">
  <div class="bs-footer-inner">

    <div class="bs-footer-brand">
      <div class="bs-footer-logo">◈ Block<span>Share</span></div>
      <p>A neighbourhood-scale cooperative economics platform built for the people who live there.</p>
      <a href="${DECODER}" class="bs-footer-decoder" target="_blank" rel="noopener">
        Try the Policy Decoder →
      </a>
    </div>

    <div class="bs-footer-col">
      <div class="bs-footer-heading">Platform</div>
      <a href="${BASE}/bs-home/">Home</a>
      <a href="${BASE}/about/">About</a>
      <a href="${BASE}/blockonomics/">Blockonomics</a>
      <a href="${BASE}/find-your-block/">Find Your Block</a>
      <a href="${BASE}/blockbudget/">Block Budget Map</a>
      <a href="${BASE}/app/">Community App</a>
      <a href="${BASE}/contact/">Contact</a>
    </div>

    <div class="bs-footer-col">
      <div class="bs-footer-heading">Programs</div>
      <a href="${BASE}/credits/">Community Credits</a>
      <a href="${BASE}/share/">Share Pool</a>
      <a href="${BASE}/food/">Beef Collective</a>
      <a href="${BASE}/buy-back-your-block/">Buy Back Your Block</a>
      <a href="${BASE}/land-trusts/">Permanent Land Trust</a>
      <a href="${BASE}/game-of-towers/">Game of Towers</a>
      <a href="${BASE}/mobility-for-life/">Mobility for Life</a>
      <a href="${BASE}/food-for-generations/">Food for Generations</a>
      <a href="${BASE}/restore-to-own/">Restore to Own</a>
    </div>

    <div class="bs-footer-col">
      <div class="bs-footer-heading">Research</div>
      <a href="${BASE}/publications/">All Publications</a>
      <a href="${BASE}/little-mountain-holborn/">Little Mountain / Holborn</a>
      <a href="${BASE}/private-taxes/">Private Taxes</a>
      <a href="${BASE}/the-fine-print/">The Fine Print</a>
      <a href="${BASE}/land-for-whom/">Land for Whom</a>
      <a href="${BASE}/third-way/">The 3rd Way</a>
      <a href="${BASE}/unrepresented/">Unrepresented</a>
      <a href="${BASE}/missing-pools/">Missing Pools</a>
    </div>

    <div class="bs-footer-col">
      <div class="bs-footer-heading">Tools</div>
      <a href="${DECODER}" target="_blank" rel="noopener">Policy Decoder ↗</a>
      <a href="${BASE}/citizens-dashboard/">Citizens Dashboard</a>
      <a href="${BASE}/lei-calculator/">LEI Calculator</a>
      <a href="${BASE}/vancouver-housing-policy-analysis/">Housing Policy Analysis</a>
      <a href="${BASE}/cc-system-diagram/">CC System Diagram</a>
      <a href="${BASE}/become-host/">Become a Host</a>
    </div>

  </div>

  <div class="bs-footer-bottom">
    <span>© 2026 Block Share · <a href="${ROOT}">blockshare.ca</a></span>
    <span class="bs-footer-sep">·</span>
    <a href="${BASE}/about/">About</a>
    <span class="bs-footer-sep">·</span>
    <a href="${BASE}/contact/">Contact</a>
    <span class="bs-footer-sep">·</span>
    <a href="${BASE}/ward-stirrat-biography/">Ward Stirrat</a>
    <span class="bs-footer-sep">·</span>
    <a href="https://thedecentralist.net" target="_blank" rel="noopener">The Decentralist ↗</a>
  </div>
</footer>`;
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     STYLES  (injected once into <head>)
     ═══════════════════════════════════════════════════════════════════════════ */
  const CSS = `
/* ─── BS NAV ROOT ─────────────────────────────────────────────────────────── */
:root {
  --bs-navy:   #1a3a5c;
  --bs-terra:  #c45c3a;
  --bs-forest: #2d5a4f;
  --bs-gold:   #d4a843;
  --bs-cream:  #f5f0e8;
  --bs-dark:   #0f1f30;
  --bs-light:  #faf7f2;
  --bs-nav-h:  64px;
}

/* ─── NAV SHELL ───────────────────────────────────────────────────────────── */
#bs-nav {
  position: sticky;
  top: 0;
  z-index: 9000;
  background: var(--bs-dark);
  border-bottom: 3px solid var(--bs-terra);
  font-family: 'Arial', 'Helvetica Neue', sans-serif;
}
.bs-nav-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  height: var(--bs-nav-h);
  padding: 0 20px;
  gap: 0;
}

/* ─── LOGO ────────────────────────────────────────────────────────────────── */
.bs-logo {
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  flex-shrink: 0;
  margin-right: 28px;
}
.bs-logo-mark {
  font-size: 22px;
  color: var(--bs-gold);
  line-height: 1;
}
.bs-logo-text {
  font-size: 17px;
  font-weight: 700;
  color: #fff;
  letter-spacing: -0.01em;
}
.bs-logo-text span { color: var(--bs-gold); }

/* ─── DESKTOP LINKS ───────────────────────────────────────────────────────── */
.bs-nav-links {
  display: flex;
  align-items: stretch;
  gap: 2px;
  flex: 1;
}
.bs-nav-link {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 0 13px;
  height: var(--bs-nav-h);
  color: #c8d8e8;
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
  background: none;
  border: none;
  cursor: pointer;
  white-space: nowrap;
  transition: color .15s, background .15s;
  border-bottom: 3px solid transparent;
  margin-bottom: -3px;
}
.bs-nav-link:hover, .bs-nav-link.bs-active {
  color: #fff;
  background: rgba(255,255,255,0.06);
  border-bottom-color: var(--bs-gold);
}
.bs-caret {
  font-size: 10px;
  opacity: .7;
  transition: transform .2s;
}
.bs-nav-item { position: relative; display: flex; align-items: stretch; }
.bs-nav-item[aria-expanded="true"] .bs-caret,
.bs-nav-item.bs-open .bs-caret { transform: rotate(180deg); }

/* ─── DROPDOWN ────────────────────────────────────────────────────────────── */
.bs-dropdown {
  display: none;
  position: absolute;
  top: calc(var(--bs-nav-h) + 0px);
  left: 0;
  min-width: 220px;
  background: var(--bs-dark);
  border: 1px solid rgba(255,255,255,0.1);
  border-top: 3px solid var(--bs-terra);
  border-radius: 0 0 6px 6px;
  box-shadow: 0 8px 28px rgba(0,0,0,.45);
  padding: 10px 0 12px;
  z-index: 9100;
  /* For multi-column groups: flex wrap */
  flex-wrap: wrap;
  flex-direction: column;
}
.bs-nav-item.bs-open .bs-dropdown { display: flex; }

.bs-drop-group {
  min-width: 180px;
  padding: 6px 0;
  border-bottom: 1px solid rgba(255,255,255,0.07);
}
.bs-drop-group:last-child { border-bottom: none; }
.bs-drop-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: var(--bs-gold);
  padding: 6px 18px 4px;
  font-weight: 700;
}
.bs-drop-item {
  display: block;
  padding: 7px 18px;
  font-size: 13.5px;
  color: #b0c8d8;
  text-decoration: none;
  transition: background .12s, color .12s;
  line-height: 1.35;
}
.bs-drop-item:hover, .bs-drop-item.bs-active {
  background: rgba(255,255,255,0.07);
  color: #fff;
}
.bs-drop-item.bs-hi {
  font-weight: 700;
  color: var(--bs-gold);
}
.bs-badge {
  display: inline-block;
  background: var(--bs-terra);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 3px;
  margin-left: 6px;
  vertical-align: middle;
  line-height: 1.6;
}

/* Research dropdown: wider, multi-column layout */
.bs-nav-item[data-menu="3"] .bs-dropdown {
  flex-direction: row;
  min-width: 700px;
  left: 50%;
  transform: translateX(-50%);
}
.bs-nav-item[data-menu="3"] .bs-drop-group { min-width: 170px; }

/* Tools dropdown: 2-col */
.bs-nav-item[data-menu="4"] .bs-dropdown {
  flex-direction: row;
  min-width: 400px;
}
.bs-nav-item[data-menu="4"] .bs-drop-group { min-width: 190px; }

/* Platform dropdown: 3-col */
.bs-nav-item[data-menu="2"] .bs-dropdown {
  flex-direction: row;
  min-width: 540px;
}
.bs-nav-item[data-menu="2"] .bs-drop-group { min-width: 170px; }

/* Programs dropdown: 3-col */
.bs-nav-item[data-menu="3_prog"] .bs-dropdown {
  flex-direction: row;
  min-width: 560px;
}

/* ─── CTA BUTTON ──────────────────────────────────────────────────────────── */
.bs-nav-cta {
  display: inline-block;
  background: var(--bs-terra);
  color: #fff;
  text-decoration: none;
  padding: 9px 18px;
  border-radius: 4px;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: .02em;
  margin-left: 16px;
  flex-shrink: 0;
  white-space: nowrap;
  transition: background .15s;
}
.bs-nav-cta:hover { background: #a04830; }

/* ─── HAMBURGER ───────────────────────────────────────────────────────────── */
.bs-hamburger {
  display: none;
  flex-direction: column;
  justify-content: center;
  gap: 5px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 8px;
  margin-left: 12px;
}
.bs-hamburger span {
  display: block;
  width: 24px;
  height: 2px;
  background: #fff;
  border-radius: 2px;
  transition: transform .22s, opacity .22s;
}
.bs-hamburger.bs-open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.bs-hamburger.bs-open span:nth-child(2) { opacity: 0; }
.bs-hamburger.bs-open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ─── MOBILE DRAWER ───────────────────────────────────────────────────────── */
.bs-mob-drawer {
  display: none;
  background: var(--bs-dark);
  border-top: 1px solid rgba(255,255,255,0.1);
  max-height: 0;
  overflow: hidden;
  transition: max-height .3s ease;
}
.bs-mob-drawer.bs-open { max-height: 80vh; overflow-y: auto; }
.bs-mob-inner { padding: 8px 0 16px; }

.bs-mob-link {
  display: block;
  padding: 11px 20px;
  font-size: 14px;
  color: #b0c8d8;
  text-decoration: none;
  font-family: Arial, sans-serif;
  border-bottom: 1px solid rgba(255,255,255,0.05);
}
.bs-mob-link.bs-active { color: var(--bs-gold); font-weight: 700; }
.bs-mob-link:hover { color: #fff; background: rgba(255,255,255,0.05); }

.bs-mob-section { border-bottom: 1px solid rgba(255,255,255,0.08); }
.bs-mob-toggle {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  padding: 13px 20px;
  background: none;
  border: none;
  color: #c8d8e8;
  font-size: 14px;
  font-weight: 700;
  font-family: Arial, sans-serif;
  cursor: pointer;
  text-align: left;
}
.bs-mob-toggle:hover { color: #fff; }
.bs-mob-chevron { font-size: 11px; transition: transform .2s; }
.bs-mob-section.bs-mob-open .bs-mob-chevron { transform: rotate(180deg); }

.bs-mob-panel { display: none; padding-bottom: 8px; }
.bs-mob-section.bs-mob-open .bs-mob-panel { display: block; }
.bs-mob-group-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: var(--bs-gold);
  padding: 8px 20px 4px 28px;
  font-weight: 700;
  font-family: Arial, sans-serif;
}

/* ─── FOOTER ──────────────────────────────────────────────────────────────── */
#bs-footer {
  background: var(--bs-dark);
  color: #8aabb8;
  font-family: Arial, sans-serif;
  border-top: 3px solid var(--bs-terra);
  margin-top: 0;
}
.bs-footer-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
  gap: 40px;
  padding: 52px 24px 40px;
}
.bs-footer-brand p {
  font-size: 13.5px;
  line-height: 1.6;
  margin: 12px 0 18px;
  color: #7090a8;
}
.bs-footer-logo {
  font-size: 20px;
  font-weight: 700;
  color: #fff;
  font-family: Georgia, serif;
  margin-bottom: 2px;
}
.bs-footer-logo span { color: var(--bs-gold); }
.bs-footer-decoder {
  display: inline-block;
  background: var(--bs-terra);
  color: #fff;
  text-decoration: none;
  padding: 9px 16px;
  border-radius: 4px;
  font-size: 13px;
  font-weight: 700;
}
.bs-footer-decoder:hover { background: #a04830; }
.bs-footer-col {}
.bs-footer-heading {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: var(--bs-gold);
  font-weight: 700;
  margin-bottom: 14px;
  padding-bottom: 6px;
  border-bottom: 1px solid rgba(212,168,67,0.25);
}
.bs-footer-col a {
  display: block;
  color: #8aabb8;
  text-decoration: none;
  font-size: 13px;
  padding: 4px 0;
  transition: color .12s;
}
.bs-footer-col a:hover { color: #fff; }
.bs-footer-bottom {
  border-top: 1px solid rgba(255,255,255,0.08);
  padding: 16px 24px;
  font-size: 12px;
  color: #4a6878;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
  max-width: 1200px;
  margin: 0 auto;
}
.bs-footer-bottom a { color: #6a90a8; text-decoration: none; }
.bs-footer-bottom a:hover { color: var(--bs-gold); }
.bs-footer-sep { color: #2a4050; }

/* ─── PAGE BODY OFFSET ────────────────────────────────────────────────────── */
body { padding-top: 0 !important; }

/* ─── RESPONSIVE ──────────────────────────────────────────────────────────── */
@media (max-width: 960px) {
  .bs-nav-links { display: none; }
  .bs-nav-cta   { display: none; }
  .bs-hamburger { display: flex; margin-left: auto; }
  .bs-mob-drawer { display: block; }
  .bs-nav-item[data-menu="2"] .bs-dropdown,
  .bs-nav-item[data-menu="3"] .bs-dropdown,
  .bs-nav-item[data-menu="4"] .bs-dropdown { display: none !important; }

  .bs-footer-inner {
    grid-template-columns: 1fr 1fr;
    gap: 28px;
    padding: 36px 20px 28px;
  }
  .bs-footer-brand { grid-column: 1 / -1; }
}
@media (max-width: 560px) {
  .bs-footer-inner { grid-template-columns: 1fr; }
}
`;

  /* ═══════════════════════════════════════════════════════════════════════════
     INJECT STYLES + HTML
     ─────────────────────────────────────────────────────────────────────────
     Pattern A (WordPress pages):
       - <link rel="stylesheet" href="...bs-nav-footer.css"> already in <head>
       - <nav id="bs-nav"></nav> placeholder already in <body>
       → skip CSS injection, fill the existing placeholder

     Pattern B (standalone HTML pages):
       - No external CSS link, no placeholder
       → inject CSS into <head>, create <nav id="bs-nav"> and prepend to <body>
     ═══════════════════════════════════════════════════════════════════════════ */
  function inject() {

    /* ── 1. CSS: only inject if bs-nav-footer.css is NOT already linked ── */
    const cssLinked = !!document.querySelector('link[href*="bs-nav-footer"]');
    if (!cssLinked) {
      const style = document.createElement('style');
      style.id = 'bs-nav-styles';
      style.textContent = CSS;
      document.head.appendChild(style);
    }

    /* ── 2. Nav: fill existing #bs-nav placeholder, or create + prepend ── */
    let nav = document.getElementById('bs-nav');
    if (nav) {
      /* Pattern A — placeholder exists: fill it in place so the media bar
         that follows it in the DOM stays exactly where it is */
      nav.setAttribute('role', 'navigation');
      nav.setAttribute('aria-label', 'Block Share site navigation');
      nav.innerHTML = buildNav();
    } else {
      /* Pattern B — no #bs-nav placeholder found.
         Check if an existing nav/header is already the first body element
         (older pages with their own built-in nav). If so, REPLACE it so we
         don't end up with two navs stacked. Otherwise just prepend. */
      const existingNav = document.querySelector(
        'body > nav, body > header, body > div > nav:first-child'
      );
      nav = document.createElement('nav');
      nav.id = 'bs-nav';
      nav.setAttribute('role', 'navigation');
      nav.setAttribute('aria-label', 'Block Share site navigation');
      nav.innerHTML = buildNav();
      if (existingNav) {
        existingNav.parentNode.replaceChild(nav, existingNav);
      } else {
        document.body.insertBefore(nav, document.body.firstChild);
      }
    }

    /* ── Ensure nav is always the first child of body ──────────────────────
       Older pages place <nav id="bs-nav"></nav> near the bottom of the file
       (works with position:fixed but not position:sticky). After filling it,
       always move it to the top regardless of where it was in the DOM.     */
    if (document.body.firstChild !== nav) {
      document.body.insertBefore(nav, document.body.firstChild);
    }

    /* ── 3. Footer: fill existing #bs-footer placeholder if present, ────────
       otherwise append a new one. Prevents duplicate footers on pages that
       already have <footer id="bs-footer"></footer> in their HTML.         */
    let footer = document.getElementById('bs-footer');
    if (footer) {
      footer.innerHTML = buildFooter().replace(/^<footer[^>]*>|<\/footer>$/g, '');
    } else {
      const footerWrap = document.createElement('div');
      footerWrap.innerHTML = buildFooter();
      document.body.appendChild(footerWrap.firstElementChild);
    }
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     INTERACTIONS
     ═══════════════════════════════════════════════════════════════════════════ */
  function bindEvents() {
    /* Desktop dropdowns — open on hover/focus */
    document.querySelectorAll('#bs-nav .bs-nav-item').forEach(item => {
      item.addEventListener('mouseenter', () => item.classList.add('bs-open'));
      item.addEventListener('mouseleave', () => item.classList.remove('bs-open'));
      const btn = item.querySelector('.bs-nav-link');
      if (btn) {
        btn.addEventListener('focus', () => item.classList.add('bs-open'));
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const isOpen = item.classList.contains('bs-open');
          document.querySelectorAll('#bs-nav .bs-nav-item').forEach(i => i.classList.remove('bs-open'));
          if (!isOpen) item.classList.add('bs-open');
        });
      }
    });

    /* Close dropdowns on outside click */
    document.addEventListener('click', e => {
      if (!e.target.closest('#bs-nav .bs-nav-item')) {
        document.querySelectorAll('#bs-nav .bs-nav-item.bs-open').forEach(i => i.classList.remove('bs-open'));
      }
    });

    /* Close on Escape */
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        document.querySelectorAll('#bs-nav .bs-nav-item.bs-open').forEach(i => i.classList.remove('bs-open'));
        document.getElementById('bs-mob-drawer')?.classList.remove('bs-open');
        document.getElementById('bs-hamburger')?.classList.remove('bs-open');
      }
    });

    /* Hamburger toggle */
    const ham   = document.getElementById('bs-hamburger');
    const drawer = document.getElementById('bs-mob-drawer');
    if (ham && drawer) {
      ham.addEventListener('click', () => {
        const open = ham.classList.toggle('bs-open');
        drawer.classList.toggle('bs-open', open);
        ham.setAttribute('aria-expanded', open);
        drawer.setAttribute('aria-hidden', !open);
      });
    }

    /* Mobile accordion sections */
    document.querySelectorAll('.bs-mob-toggle').forEach(btn => {
      btn.addEventListener('click', () => {
        const section = btn.closest('.bs-mob-section');
        const isOpen  = section.classList.toggle('bs-mob-open');
        btn.setAttribute('aria-expanded', isOpen);
      });
    });
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     INIT
     ─────────────────────────────────────────────────────────────────────────
     Pages can opt out of nav injection by adding either:
       <meta name="bs-nav" content="skip">          (any page)
       <body data-bs-nav="skip">                    (any page)
     The /app/ slug is also excluded automatically (full React app UI).
     ═══════════════════════════════════════════════════════════════════════════ */
  function init() {
    const slug = currentSlug();
    const metaSkip = document.querySelector('meta[name="bs-nav"][content="skip"]');
    const bodySkip = document.body.dataset.bsNav === 'skip';
    const appSlugs = ['app', 'onboarding'];          /* full-screen app pages */
    if (metaSkip || bodySkip || appSlugs.includes(slug)) return;
    inject();
    bindEvents();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
