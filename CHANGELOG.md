# Changelog

All notable changes to LivQ AccessFix are documented here.

## [1.0.0] - 2026-06-29

### Added

**Frontend Modules**
- External link labelling - appends a screen-reader notice to every `target="_blank"` link and adds `rel="noopener noreferrer"` automatically. WCAG 2.4.4.
- Skip navigation link - injects a skip link as the first focusable element in `<body>` via `wp_body_open`. WCAG 2.4.1.
- Decorative image alt fix - ensures images with no meaningful alt text receive `alt=""` instead of nothing. Works on `wp_get_attachment_image_attributes` and on Core Image blocks in FSE / block themes via `render_block_core/image`. WCAG 1.1.1.
- High-contrast focus CSS - injects a `focus-visible` rule (3px solid #0056b3 + glow) that overrides theme resets without touching any theme file. WCAG 2.4.11.
- Menu accessibility helper - adds `aria-haspopup` and `aria-expanded` to nav items with sub-menus; Vanilla JS toggles open/close state on click, Enter, Space, and Escape. WCAG 4.1.2.

**PHP Output Buffer**
- Global output buffer on `template_redirect` / `shutdown` intercepts the entire rendered page HTML, fixing `target="_blank"` links regardless of their origin (theme PHP, social share plugins, widget areas, shortcodes).
- Page Builder safety guards: buffer is skipped automatically for Divi (`et_fb`, `et_bfb`), Elementor (`elementor-preview`), Beaver Builder (`fl_builder`), Bricks (`bricks=run`), Oxygen / Breakdance (`ct_builder`, `breakdance_editor`).
- Non-HTML request guards: REST API, AJAX, XML-RPC, WP-Cron responses are excluded from buffering.
- Idempotency: double-injection is prevented by checking for an existing `.screen-reader-text` span before modifying a link.

**HTML Output Remediations (EAA)**
- Nameless link fix - detects icon/image links without accessible text and derives `aria-label` from img title, link title, recognised social domain (Facebook, Instagram, YouTube, LinkedIn, and more), or the capitalised hostname as a fallback. WCAG 2.4.4 / 4.1.2.
- Iframe title fix - adds a `title` attribute to every untitled `<iframe>`, matching src domain to a descriptive title (YouTube, Vimeo, Google Maps, Calendly, iubenda, Spotify, PayPal, Google Forms, and others). WCAG 4.1.2.
- Form input label fix - adds `aria-label` to `<input>`, `<textarea>`, and `<select>` elements without an associated `<label>`, deriving the label from placeholder or name attribute. WCAG 1.3.1 / 3.3.2.

**WooCommerce Accessibility**
- ARIA labels on quantity increment/decrement buttons (`Decrease quantity` / `Increase quantity` with product name).
- Accessible label on "Add to cart" buttons with product name interpolation.
- `aria-label` on product gallery open trigger.
- Live region (`role="status"`, `aria-live="polite"`) for cart update announcements. WCAG 4.1.2 / 4.1.3.

**Content Analysis**
- Heading hierarchy checker - on `save_post`, scans H1–H6 structure and persists detected level-skips as `_livqacea_a11y_issues` post meta (JSON with type, WCAG criterion, timestamp). Shows a dismissible admin notice on the edit screen. WCAG 1.3.1.
- Gutenberg pre-publish panel - real-time checklist in the block editor pre-publish drawer: (A) Core Image blocks missing alt text, (B) links using a raw URL as visible text. No AJAX required. WCAG 1.1.1 / 2.4.4.

**Accessibility Scanner**
- Scans key page types on demand: Homepage, Blog Index, Single Post, Page, Category Archive, Search Results, 404.
- WooCommerce page types auto-detected when active: Shop, Product, Cart, Checkout, My Account, Product Category.
- Each issue scored by severity: Critical, High, Warning.
- Results cached per page type; cleared with a single button.
- Checks include: missing skip link, missing page language (`lang` attribute), multiple H1 elements, heading hierarchy skips, and more.

**Contrast Checker**
- Real-time WCAG 2.1 contrast ratio calculation from hex/rgb foreground and background colour inputs.
- Evaluates three criteria: Normal text AA (4.5:1), Large text AA (3:1), UI components & graphics WCAG 1.4.11 (3:1).
- Live text preview with selected colours.
- Quick-test palette of common colour pairs; click any pair to load it.
- Foreground and background colour pickers with manual hex input.

**Accessibility Issues Log**
- Admin page (Settings → A11y Issues Log) listing all posts with a persisted accessibility issue.
- CSV export (nonce-protected AJAX) with BOM for Excel UTF-8 compatibility - suitable as an EAA compliance audit trail.

**Accessibility Statement Generator**
- Admin page (Settings → A11y Statement) to configure: organisation name, accessibility contact email, contact form URL, date of last evaluation, conformance status (full / partial / non), known limitations.
- Statement text is auto-populated from live plugin data: all active modules and their WCAG criteria are listed automatically (including HTML Output Remediations, WooCommerce enhancements, and the Gutenberg pre-publish panel).
- Confirmation checkbox stores a timestamped declaration record (`livqacea_statement_confirmed` option) with user name, email, date, plugin version, and site URL - legal evidence of operator responsibility under EAA 2025.
- Public statement footer shows plugin name, version, and confirmation attribution.
- Shortcode `[livqacea_accessibility_statement]` embeds the statement on any page.
- One-click "Create Statement Page" helper auto-creates a draft WordPress page with the shortcode pre-inserted.
- Schema.org `itemscope itemtype="WebPage"` markup for search engines.
- Locale-aware enforcement authority section (Italy: AgID / Difensore Civico per il Digitale by default; generic EU fallback for other locales).
- Satisfies the EAA 2025 / Web Accessibility Directive (2016/2102) disclosure requirement.

**Settings & Architecture**
- Settings page (Settings → LivQ AccessFix) with per-module toggles using the native WordPress Settings API - CSRF-safe, no manual nonce handling.
- Skip link target auto-detect via AJAX (server-side homepage fetch with candidate ID matching).
- Singleton bootstrap (`LIVQACEA_Main`) with options loaded once and passed to sub-modules - zero extra DB queries.
- Translation loading via `load_textdomain()` on `init` priority 0 - works on local installs without WP.org auto-download, no-op when WP.org loads community translations from translate.wordpress.org.
- Zero external dependencies: no CDN, no SaaS, no account required.
- Review card (banner) in admin with yellow ★★★★★ rating prompt.
