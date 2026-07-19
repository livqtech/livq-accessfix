<?php
/**
 * Frontend accessibility remediation.
 *
 * Applies WCAG 2.2 AA / EAA fixes at render time without touching the database
 * content - all corrections are ephemeral output filters/actions.
 *
 * @package LivQ_AccessFix
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LIVQACEA_Frontend
 */
class LIVQACEA_Frontend {

	/**
	 * Plugin settings passed from LIVQACEA_Main.
	 *
	 * @var array<string, bool>
	 */
	private $options;

	/**
	 * Constructor - registers hooks according to active options.
	 *
	 * @param array<string, bool> $options Sanitised plugin options.
	 */
	public function __construct( array $options ) {
		$this->options = $options;

		// Only run on the public-facing side.
		if ( is_admin() ) {
			return;
		}

		$this->register_hooks();
	}

	/**
	 * Attaches WordPress actions/filters based on which modules are enabled.
	 *
	 * @return void
	 */
	private function register_hooks(): void {

		// Start the output buffer if any buffer-dependent module is active.
		// Centralising the condition here avoids starting multiple buffers.
		$needs_buffer = ! empty( $this->options['fix_external_links'] )
			|| ! empty( $this->options['fix_nameless_links'] )
			|| ! empty( $this->options['fix_iframe_titles'] )
			|| ! empty( $this->options['fix_input_labels'] );

		if ( $needs_buffer ) {
			add_action( 'template_redirect', array( $this, 'start_buffer' ), 1 );
			add_action( 'shutdown', array( $this, 'end_buffer' ), 0 );
		}

		// WCAG 2.4.1 - Skip navigation link for keyboard users.
		if ( ! empty( $this->options['inject_skip_link'] ) ) {
			add_action( 'wp_body_open', array( $this, 'inject_skip_link' ), 1 );
		}

		// WCAG 1.1.1 - Decorative images must have empty alt text, not file names.
		if ( ! empty( $this->options['fix_image_alt'] ) ) {
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'fix_image_alt' ), 10, 2 );
			// Also intercept Core Image blocks rendered in FSE / block themes.
			add_filter( 'render_block_core/image', array( $this, 'fix_block_image' ), 10, 2 );
		}

		// WCAG 2.4.11 - Focus styles.
		if ( ! empty( $this->options['inject_focus_css'] ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_inline_styles' ) );
		}

		// WCAG 4.1.2 - Menu items with sub-menus need aria-haspopup + aria-expanded.
		if ( ! empty( $this->options['menu_aria_helper'] ) ) {
			add_filter( 'nav_menu_link_attributes', array( $this, 'add_menu_aria_attrs' ), 10, 4 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_menu_aria_script' ) );
		}

		// WCAG 3.1.1 - <html lang> must be present. Silent always-on guard.
		add_filter( 'language_attributes', array( $this, 'fix_html_lang' ), 10, 2 );
	}

	// -----------------------------------------------------------------------
	// Output Buffer - global page interception
	// -----------------------------------------------------------------------

	/**
	 * Starts PHP output buffering.
	 *
	 * @return void
	 */
	public function start_buffer(): void {
		if ( is_admin() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( wp_doing_ajax() ) {
			return;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return;
		}

		// ── Page Builder guards ────────────────────────────────────────────────
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['et_fb'] ) || isset( $_GET['et_bfb'] ) ) {
			return; // Divi.
		}
		if ( isset( $_GET['elementor-preview'] ) ) {
			return; // Elementor.
		}
		if ( isset( $_GET['fl_builder'] ) ) {
			return; // Beaver Builder.
		}
		if ( isset( $_GET['bricks'] ) && 'run' === $_GET['bricks'] ) {
			return; // Bricks.
		}
		if ( isset( $_GET['ct_builder'] ) || isset( $_GET['breakdance_editor'] ) ) {
			return; // Oxygen / Breakdance.
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		ob_start( array( $this, 'sanitize_entire_page' ) );
	}

	/**
	 * Closes the output buffer and flushes it through the sanitize callback.
	 *
	 * @return void
	 */
	public function end_buffer(): void {
		if ( false !== ob_get_length() ) {
			ob_end_flush();
		}
	}

	/**
	 * Coordinator - routes the buffered HTML through each enabled fixer.
	 *
	 * Each fixer is a private method that receives and returns the full HTML
	 * string. Order matters: external links runs before nameless links so the
	 * screen-reader notice is already present when the nameless-link check
	 * evaluates whether a _blank link needs an aria-label.
	 *
	 * @param string $html Full rendered HTML of the page.
	 * @return string Modified HTML.
	 */
	public function sanitize_entire_page( string $html ): string {
		if ( empty( $html ) ) {
			return $html;
		}

		if ( ! empty( $this->options['fix_external_links'] ) ) {
			$html = $this->fix_external_links( $html );
		}
		if ( ! empty( $this->options['fix_nameless_links'] ) ) {
			$html = $this->fix_nameless_links( $html );
		}
		if ( ! empty( $this->options['fix_iframe_titles'] ) ) {
			$html = $this->fix_iframe_titles( $html );
		}
		if ( ! empty( $this->options['fix_input_labels'] ) ) {
			$html = $this->fix_input_labels( $html );
		}

		// Allow other modules (e.g. WooCommerce) to add their own HTML fixes.
		return apply_filters( 'livqacea_sanitized_html', $html );
	}

	// -----------------------------------------------------------------------
	// Buffer fixers (private)
	// -----------------------------------------------------------------------

	/**
	 * Adds screen-reader notice and noopener to every target="_blank" link.
	 *
	 * WCAG 2.4.4 (Link Purpose) / Technique G201.
	 *
	 * @param string $html Full page HTML.
	 * @return string Modified HTML.
	 */
	private function fix_external_links( string $html ): string {
		if ( false === strpos( $html, '_blank' ) ) {
			return $html;
		}

		$notice_text = __( '(opens in a new tab)', 'livq-accessfix' );
		$pattern     = '/<a\b([^>]*?target=["\']_blank["\'][^>]*?)>(.*?)<\/a>/is';

		return (string) preg_replace_callback(
			$pattern,
			function ( array $matches ) use ( $notice_text ): string {
				$attrs = $matches[1];
				$inner = $matches[2];

				// Idempotency - skip if notice already injected.
				if ( false !== strpos( $inner, 'screen-reader-text' ) ) {
					return $matches[0];
				}

				// Merge rel="noopener noreferrer".
				if ( preg_match( '/\brel=(["\'])([^"\']*)\1/i', $attrs, $rel_match ) ) {
					$rel_values = array_filter( preg_split( '/\s+/', $rel_match[2] ) );
					if ( ! in_array( 'noopener', $rel_values, true ) ) {
						$rel_values[] = 'noopener';
					}
					if ( ! in_array( 'noreferrer', $rel_values, true ) ) {
						$rel_values[] = 'noreferrer';
					}
					$new_rel = 'rel=' . $rel_match[1] . implode( ' ', $rel_values ) . $rel_match[1];
					$attrs   = preg_replace( '/\brel=(["\'])[^"\']*\1/i', $new_rel, $attrs );
				} else {
					$attrs .= ' rel="noopener noreferrer"';
				}

				$span = sprintf(
					' <span class="screen-reader-text">%s</span>',
					esc_html( $notice_text )
				);

				return '<a' . $attrs . '>' . $inner . $span . '</a>';
			},
			$html
		);
	}

	/**
	 * Adds aria-label to links whose accessible name is empty.
	 *
	 * Covers two common patterns:
	 *  - <a href="…"><img alt=""></a>   (sponsor / partner logos)
	 *  - <a href="…"><svg aria-hidden="true">…</svg></a>  (social icon links)
	 *
	 * Label derivation priority:
	 *  1. img[title]        - intentional tooltip set in media library
	 *  2. img[alt] non-empty - meaningful alt already present
	 *  3. a[title]          - tooltip on the link itself
	 *  4. Social domain map - deterministic brand name from href
	 *  5. href hostname     - capitalised first segment (generic fallback)
	 *
	 * WCAG 2.4.4 / 4.1.2
	 *
	 * @param string $html Full page HTML.
	 * @return string Modified HTML.
	 */
	private function fix_nameless_links( string $html ): string {
		if ( false === strpos( $html, '<a' ) ) {
			return $html;
		}

		$new_tab_notice = __( '(opens in a new tab)', 'livq-accessfix' );

		return (string) preg_replace_callback(
			'/<a\b([^>]*)>(.*?)<\/a>/is',
			function ( array $m ) use ( $new_tab_notice ): string {
				$attrs = $m[1];
				$inner = $m[2];

				// Skip anchors without href (in-page anchors, JS hooks).
				if ( ! preg_match( '/\bhref=["\']([^"\']*)["\']/', $attrs, $href_m ) ) {
					return $m[0];
				}

				// Skip if an accessible name attribute already exists.
				if ( preg_match( '/\baria-(?:label|labelledby)=/i', $attrs ) ) {
					return $m[0];
				}

				// Skip if the link contains non-empty visible text.
				$visible_text = trim( wp_strip_all_tags( $inner ) );
				if ( '' !== $visible_text ) {
					return $m[0];
				}

				// Derive the best available label.
				$label = '';

				if ( preg_match( '/<img\b[^>]*\btitle=["\']([^"\']+)["\']/', $inner, $img_t ) ) {
					$label = $img_t[1];
				} elseif ( preg_match( '/<img\b[^>]*\balt=["\']([^"\']+)["\']/', $inner, $img_a ) ) {
					$label = $img_a[1];
				} elseif ( preg_match( '/\btitle=["\']([^"\']+)["\']/', $attrs, $a_t ) ) {
					$label = $a_t[1];
				} else {
					$label = self::label_from_href( $href_m[1] );
				}

				if ( '' === $label ) {
					return $m[0];
				}

				// Append "opens in a new tab" for _blank links (aria-label
				// overrides inner content, so the notice span won't be read).
				if ( preg_match( '/\btarget=["\']_blank["\']/', $attrs ) ) {
					$label .= ' ' . $new_tab_notice;
				}

				return '<a' . $attrs . ' aria-label="' . esc_attr( $label ) . '">' . $inner . '</a>';
			},
			$html
		);
	}

	/**
	 * Adds a title attribute to every <iframe> that is missing one.
	 *
	 * Screen readers announce untitled iframes as "frame" with no context.
	 * Title derivation uses a src-domain map (YouTube, Maps, Calendly, etc.)
	 * with a generic translatable fallback.
	 *
	 * WCAG 4.1.2
	 *
	 * @param string $html Full page HTML.
	 * @return string Modified HTML.
	 */
	private function fix_iframe_titles( string $html ): string {
		if ( false === strpos( $html, '<iframe' ) ) {
			return $html;
		}

		$src_map = array(
			'youtube.com'     => __( 'YouTube Video', 'livq-accessfix' ),
			'youtu.be'        => __( 'YouTube Video', 'livq-accessfix' ),
			'vimeo.com'       => __( 'Vimeo Video', 'livq-accessfix' ),
			'google.com/maps' => __( 'Interactive Map', 'livq-accessfix' ),
			'maps.google'     => __( 'Interactive Map', 'livq-accessfix' ),
			'calendly.com'    => __( 'Appointment Calendar', 'livq-accessfix' ),
			'iubenda.com'     => __( 'Cookie Policy', 'livq-accessfix' ),
			'facebook.com'    => __( 'Facebook Content', 'livq-accessfix' ),
			'instagram.com'   => __( 'Instagram Content', 'livq-accessfix' ),
			'twitter.com'     => __( 'Twitter / X Content', 'livq-accessfix' ),
			'x.com'           => __( 'Twitter / X Content', 'livq-accessfix' ),
			'spotify.com'     => __( 'Spotify Player', 'livq-accessfix' ),
			'soundcloud.com'  => __( 'SoundCloud Player', 'livq-accessfix' ),
			'paypal.com'      => __( 'PayPal Payment', 'livq-accessfix' ),
			'typeform.com'    => __( 'Form', 'livq-accessfix' ),
			'forms.google'    => __( 'Google Form', 'livq-accessfix' ),
			'docs.google'     => __( 'Google Document', 'livq-accessfix' ),
		);

		$fallback = __( 'Embedded content', 'livq-accessfix' );

		return (string) preg_replace_callback(
			'/<iframe\b([^>]*)>/i',
			function ( array $m ) use ( $src_map, $fallback ): string {
				$attrs = $m[1];

				// Already has a title - respect it.
				if ( preg_match( '/\btitle=["\'][^"\']+["\']/', $attrs ) ) {
					return $m[0];
				}

				$title = $fallback;

				if ( preg_match( '/\bsrc=["\']([^"\']*)["\']/', $attrs, $src_m ) ) {
					foreach ( $src_map as $domain => $label ) {
						if ( false !== strpos( $src_m[1], $domain ) ) {
							$title = $label;
							break;
						}
					}
				}

				return '<iframe' . $attrs . ' title="' . esc_attr( $title ) . '">';
			},
			$html
		);
	}

	/**
	 * Adds aria-label to form fields that lack an accessible label.
	 *
	 * Only fires when ALL of these conditions hold:
	 *  - The field has no aria-label or aria-labelledby attribute.
	 *  - The field's id is not referenced by any <label for="…"> on the page.
	 *  - The field is an interactive type (not hidden/submit/button/reset/image).
	 *
	 * Label derivation: placeholder → name attribute (humanised) → nothing.
	 * If no source is available the field is left untouched.
	 *
	 * WCAG 1.3.1 / 3.3.2
	 *
	 * @param string $html Full page HTML.
	 * @return string Modified HTML.
	 */
	private function fix_input_labels( string $html ): string {
		$has_input    = false !== strpos( $html, '<input' );
		$has_textarea = false !== strpos( $html, '<textarea' );
		$has_select   = false !== strpos( $html, '<select' );

		if ( ! $has_input && ! $has_textarea && ! $has_select ) {
			return $html;
		}

		// Build set of IDs already covered by <label for="…">.
		$labeled_ids = array();
		if ( preg_match_all( '/<label\b[^>]*\bfor=["\']([^"\']+)["\'][^>]*>/i', $html, $lm ) ) {
			$labeled_ids = array_flip( $lm[1] );
		}

		// Fix <input> elements.
		if ( $has_input ) {
			$html = (string) preg_replace_callback(
				'/<input\b([^>]*)>/i',
				function ( array $m ) use ( $labeled_ids ): string {
					return $this->add_aria_label_to_field( $m[0], $m[1], $labeled_ids, 'input' );
				},
				$html
			);
		}

		// Fix <textarea> elements.
		if ( $has_textarea ) {
			$html = (string) preg_replace_callback(
				'/<textarea\b([^>]*)>/i',
				function ( array $m ) use ( $labeled_ids ): string {
					return $this->add_aria_label_to_field( $m[0], $m[1], $labeled_ids, 'textarea' );
				},
				$html
			);
		}

		// Fix <select> elements.
		if ( $has_select ) {
			$html = (string) preg_replace_callback(
				'/<select\b([^>]*)>/i',
				function ( array $m ) use ( $labeled_ids ): string {
					return $this->add_aria_label_to_field( $m[0], $m[1], $labeled_ids, 'select' );
				},
				$html
			);
		}

		return $html;
	}

	/**
	 * Helper - evaluates one field tag and injects aria-label if warranted.
	 *
	 * @param string             $full_tag   The full original tag string.
	 * @param string             $attrs      The attribute string inside the tag.
	 * @param array<string, int> $labeled_ids Set of IDs already covered by a <label>.
	 * @param string             $tag_name   'input', 'textarea', or 'select'.
	 * @return string Modified or original tag.
	 */
	private function add_aria_label_to_field( string $full_tag, string $attrs, array $labeled_ids, string $tag_name ): string {
		// Skip if already has an accessible name.
		if ( preg_match( '/\baria-(?:label|labelledby)=/i', $attrs ) ) {
			return $full_tag;
		}

		// For <input> skip non-interactive types.
		if ( 'input' === $tag_name && preg_match( '/\btype=["\'](?:hidden|submit|button|reset|image)["\']/', $attrs ) ) {
			return $full_tag;
		}

		// Skip if already associated with a <label>.
		if ( preg_match( '/\bid=["\']([^"\']+)["\']/', $attrs, $id_m ) ) {
			if ( isset( $labeled_ids[ $id_m[1] ] ) ) {
				return $full_tag;
			}
		}

		// Derive label from placeholder first, then name.
		$label = '';

		if ( preg_match( '/\bplaceholder=["\']([^"\']+)["\']/', $attrs, $ph_m ) ) {
			$label = $ph_m[1];
		} elseif ( preg_match( '/\bname=["\']([^"\']+)["\']/', $attrs, $nm_m ) ) {
			$label = ucfirst(
				trim( str_replace( array( '_', '-', '[', ']' ), array( ' ', ' ', '', '' ), $nm_m[1] ) )
			);
		}

		if ( '' === $label ) {
			return $full_tag;
		}

		// Inject aria-label before the closing > of the tag.
		return rtrim( $full_tag, '>' ) . ' aria-label="' . esc_attr( $label ) . '">';
	}

	/**
	 * Derives a human-readable label from a URL.
	 *
	 * Checks a curated map of social / service domains first, then falls back
	 * to the capitalised first segment of the hostname.
	 *
	 * @param string $href The href value of the link.
	 * @return string Label string, or empty string if nothing useful is found.
	 */
	private static function label_from_href( string $href ): string {
		static $social_map = array(
			'facebook.com'  => 'Facebook',
			'instagram.com' => 'Instagram',
			'twitter.com'   => 'Twitter',
			'x.com'         => 'X',
			'youtube.com'   => 'YouTube',
			'youtu.be'      => 'YouTube',
			'linkedin.com'  => 'LinkedIn',
			'tiktok.com'    => 'TikTok',
			'pinterest.com' => 'Pinterest',
			'threads.net'   => 'Threads',
			'whatsapp.com'  => 'WhatsApp',
			'telegram.org'  => 'Telegram',
			't.me'          => 'Telegram',
		);

		$host = (string) wp_parse_url( $href, PHP_URL_HOST );
		if ( '' === $host ) {
			return '';
		}

		// Strip leading www.
		$host = preg_replace( '/^www\./i', '', $host );

		foreach ( $social_map as $domain => $name ) {
			if ( false !== strpos( $host, $domain ) ) {
				return $name;
			}
		}

		// Generic fallback: capitalise the first hostname segment.
		$parts = explode( '.', $host );
		return ucfirst( $parts[0] ?? '' );
	}

	// -----------------------------------------------------------------------
	// Skip Link
	// -----------------------------------------------------------------------

	/**
	 * Outputs a skip-navigation link immediately after <body>.
	 *
	 * WCAG 2.4.1 (Bypass Blocks).
	 *
	 * @return void
	 */
	public function inject_skip_link(): void {
		$saved  = ! empty( $this->options['skip_link_target'] ) ? $this->options['skip_link_target'] : '';
		$target = apply_filters( 'livqacea_skip_link_target', $saved ? $saved : '#primary' );

		printf(
			'<a class="skip-link screen-reader-text" href="%s">%s</a>' . "\n",
			esc_url( $target ),
			esc_html__( 'Skip to main content', 'livq-accessfix' )
		);
	}

	// -----------------------------------------------------------------------
	// Image Alt
	// -----------------------------------------------------------------------

	/**
	 * Ensures decorative images carry an explicit empty alt attribute.
	 *
	 * WCAG 1.1.1 / Technique H67.
	 *
	 * @param array<string, string> $attr       Current image attributes.
	 * @param WP_Post               $attachment The attachment post object.
	 * @return array<string, string> Modified attributes.
	 */
	public function fix_image_alt( array $attr, WP_Post $attachment ): array {
		if ( isset( $attr['alt'] ) && '' !== $attr['alt'] ) {
			return $attr;
		}

		$stored_alt = trim( (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) );

		if ( '' !== $stored_alt ) {
			$attr['alt'] = $stored_alt;
		} else {
			$attr['alt'] = '';
		}

		return $attr;
	}

	/**
	 * Fixes alt attribute on Core Image blocks in FSE / block themes.
	 *
	 * WCAG 1.1.1 - wp_get_attachment_image_attributes does not fire for the
	 * block renderer.
	 *
	 * @param string               $block_content Rendered HTML of the block.
	 * @param array<string, mixed> $block         Block data.
	 * @return string Modified block HTML.
	 */
	public function fix_block_image( string $block_content, array $block ): string {
		if ( empty( $block_content ) ) {
			return $block_content;
		}

		$block_alt = isset( $block['attrs']['alt'] ) ? trim( (string) $block['attrs']['alt'] ) : null;

		if ( null !== $block_alt && '' !== $block_alt ) {
			return $block_content;
		}

		if ( false !== strpos( $block_content, '<img' ) && false === strpos( $block_content, ' alt=' ) ) {
			$block_content = (string) preg_replace(
				'/(<img\b[^>]*?)(\s*\/?>)/i',
				'$1 alt=""$2',
				$block_content,
				1
			);
		}

		return $block_content;
	}

	// -----------------------------------------------------------------------
	// Focus CSS
	// -----------------------------------------------------------------------

	/**
	 * Enqueues inline CSS for screen-reader-text and focus-visible styles.
	 *
	 * @return void
	 */
	public function enqueue_inline_styles(): void {
		$handle = wp_style_is( 'wp-block-library', 'enqueued' ) ? 'wp-block-library' : 'livqacea-a11y';

		if ( 'livqacea-a11y' === $handle ) {
			wp_register_style( 'livqacea-a11y', false, array(), LIVQACEA_VERSION );
			wp_enqueue_style( 'livqacea-a11y' );
		}

		wp_add_inline_style( $handle, $this->get_inline_css() );
	}

	// -----------------------------------------------------------------------
	// HTML lang guard (WCAG 3.1.1)
	// -----------------------------------------------------------------------

	/**
	 * Ensures the <html> tag carries a lang attribute.
	 *
	 * WordPress's language_attributes() already outputs lang for properly
	 * configured sites. This filter is a safety net for themes or setups that
	 * inadvertently strip the attribute.
	 *
	 * @param string $output  Existing language_attributes() output.
	 * @param string $doctype The doctype ('html' or 'xhtml').
	 * @return string Output with lang guaranteed present.
	 */
	public function fix_html_lang( string $output, string $doctype ): string {
		if ( 'html' !== $doctype ) {
			return $output;
		}
		if ( false !== strpos( $output, 'lang=' ) ) {
			return $output;
		}

		$locale = str_replace( '_', '-', get_locale() );
		return $output . ' lang="' . esc_attr( $locale ) . '"';
	}

	// -----------------------------------------------------------------------
	// Menu ARIA Helper
	// -----------------------------------------------------------------------

	/**
	 * Adds aria-haspopup and aria-expanded to menu items that have children.
	 *
	 * WCAG 4.1.2.
	 *
	 * @param array<string, string> $atts   Current link attributes.
	 * @param \WP_Post              $item   The menu item post object.
	 * @param \stdClass             $args   wp_nav_menu() arguments object.
	 * @param int                   $depth  Depth of the current menu item.
	 * @return array<string, string> Modified attributes.
	 */
	public function add_menu_aria_attrs( array $atts, WP_Post $item, stdClass $args, int $depth ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
			$atts['aria-haspopup'] = 'true';
			$atts['aria-expanded'] = 'false';
		}
		return $atts;
	}

	/**
	 * Enqueues the Vanilla JS that toggles aria-expanded on sub-menu triggers.
	 *
	 * @return void
	 */
	public function enqueue_menu_aria_script(): void {
		wp_enqueue_script( 'livqacea-menu-aria', LIVQACEA_PLUGIN_URL . 'assets/js/livqacea-menu-aria.js', array(), LIVQACEA_VERSION, true );
	}

	// -----------------------------------------------------------------------
	// Inline CSS
	// -----------------------------------------------------------------------

	/**
	 * Returns the inline CSS string.
	 *
	 * @return string
	 */
	private function get_inline_css(): string {
		return '
/* EAA Developer Guard - Accessibility Styles v' . LIVQACEA_VERSION . ' */

/* WCAG 2.4.1 skip link & 1.3.1 screen-reader-text utility */
.screen-reader-text {
	border: 0;
	clip: rect(1px, 1px, 1px, 1px);
	clip-path: inset(50%);
	height: 1px;
	margin: -1px;
	overflow: hidden;
	padding: 0;
	position: absolute;
	width: 1px;
	word-wrap: normal !important;
}

.screen-reader-text:focus {
	background-color: #fff;
	border-radius: 4px;
	box-shadow: 0 0 2px 2px rgba(0, 0, 0, 0.6);
	clip: auto !important;
	clip-path: none;
	color: #21759b;
	display: block;
	font-size: 0.875rem;
	font-weight: 700;
	height: auto;
	left: 8px;
	line-height: normal;
	padding: 12px 24px;
	text-decoration: none;
	top: 8px;
	width: auto;
	z-index: 100000;
}

/* WCAG 2.4.11 Focus Appearance - high-contrast visible focus indicator.
   !important overrides aggressive theme resets (outline:none on input, etc.).
   3px solid outline + 5px glow meets the 3:1 contrast ratio requirement. */
a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible,
[tabindex]:focus-visible {
	outline: 3px solid #0056b3 !important;
	outline-offset: 3px !important;
	box-shadow: 0 0 0 5px rgba(0, 86, 179, 0.3) !important;
}
';
	}
}
