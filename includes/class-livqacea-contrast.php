<?php
/**
 * Contrast Checker - WCAG 1.4.3 / 1.4.11 color contrast tool.
 *
 * Admin page with two color pickers and a real-time ratio display.
 * The contrast ratio is calculated entirely client-side using the WCAG
 * relative luminance formula - no server round-trips needed.
 *
 * @package LivQ_AccessFix
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LIVQACEA_Contrast
 */
class LIVQACEA_Contrast {

	const PAGE_SLUG = 'livqacea-contrast';

	/**
	 * Hook suffix of this page, captured from add_submenu_page().
	 * Used to scope asset enqueueing to this screen only.
	 *
	 * @var string
	 */
	private static $page_hook = '';

	// -----------------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------------

	/**
	 * Registers WordPress hooks for the Contrast Checker module.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Registers the Contrast Checker submenu page.
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		self::$page_hook = (string) add_submenu_page(
			LIVQACEA_Backend::PAGE_SLUG,
			__( 'Contrast Checker', 'livq-accessfix' ),
			__( 'Contrast Checker', 'livq-accessfix' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Enqueues the Contrast Checker CSS/JS - scoped to this screen only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( $hook !== self::$page_hook ) {
			return;
		}

		wp_enqueue_style( 'livqacea-contrast', LIVQACEA_PLUGIN_URL . 'assets/css/livqacea-contrast.css', array(), LIVQACEA_VERSION );

		wp_enqueue_script( 'livqacea-contrast', LIVQACEA_PLUGIN_URL . 'assets/js/livqacea-contrast.js', array(), LIVQACEA_VERSION, true );
		wp_localize_script(
			'livqacea-contrast',
			'livqaceaContrast',
			array(
				'strings' => array(
					'belowThree'  => __( 'Ratio is below 3:1 - fails all levels. Significant contrast improvement needed.', 'livq-accessfix' ),
					'aaLargeOnly' => __( 'Passes AA for large text (≥ 3:1) but fails AA for normal text (needs ≥ 4.5:1).', 'livq-accessfix' ),
				),
			)
		);
	}

	// -----------------------------------------------------------------------
	// Admin page
	// -----------------------------------------------------------------------

	/**
	 * Renders the Contrast Checker admin page HTML.
	 *
	 * @return void
	 */
	public static function render_admin_page(): void {
		?>
<div class="wrap livqacea-contrast-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Contrast Checker', 'livq-accessfix' ); ?></h1>
	<hr class="wp-header-end">
	<p class="description" style="margin-top:8px;">
		<?php esc_html_e( 'Verify color contrast ratios for WCAG 1.4.3 (text contrast) and 1.4.11 (non-text contrast). Required for EAA compliance. Enter any two hex colors below.', 'livq-accessfix' ); ?>
	</p>

	<div class="livqacea-ct-tool">

		<!-- Color inputs -->
		<div class="livqacea-ct-inputs">
			<div class="livqacea-ct-field">
				<label for="livqacea-ct-fg-hex"><?php esc_html_e( 'Foreground (text / element)', 'livq-accessfix' ); ?></label>
				<div class="livqacea-ct-row">
					<input type="color" id="livqacea-ct-fg-picker" value="#000000" aria-label="<?php esc_attr_e( 'Foreground color picker', 'livq-accessfix' ); ?>">
					<input type="text"  id="livqacea-ct-fg-hex"    value="#000000" maxlength="7" class="regular-text" placeholder="#000000">
				</div>
			</div>
			<div class="livqacea-ct-field">
				<label for="livqacea-ct-bg-hex"><?php esc_html_e( 'Background', 'livq-accessfix' ); ?></label>
				<div class="livqacea-ct-row">
					<input type="color" id="livqacea-ct-bg-picker" value="#ffffff" aria-label="<?php esc_attr_e( 'Background color picker', 'livq-accessfix' ); ?>">
					<input type="text"  id="livqacea-ct-bg-hex"    value="#ffffff" maxlength="7" class="regular-text" placeholder="#ffffff">
				</div>
			</div>
			<div class="livqacea-ct-field">
				<label><?php esc_html_e( 'Preview', 'livq-accessfix' ); ?></label>
				<div id="livqacea-ct-preview">
					<span id="livqacea-ct-preview-text"><?php esc_html_e( 'Sample text Aa', 'livq-accessfix' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Ratio badge -->
		<div id="livqacea-ct-ratio" aria-live="polite" aria-atomic="true">
			<span id="livqacea-ct-ratio-num">21</span><span class="livqacea-ct-ratio-unit">: 1</span>
		</div>

		<!-- Results table -->
		<table class="wp-list-table widefat fixed striped livqacea-ct-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Level', 'livq-accessfix' ); ?></th>
					<th><?php esc_html_e( 'Use case', 'livq-accessfix' ); ?></th>
					<th><?php esc_html_e( 'Minimum ratio', 'livq-accessfix' ); ?></th>
					<th><?php esc_html_e( 'Result', 'livq-accessfix' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><strong>AA</strong></td>
					<td><?php esc_html_e( 'Normal text (< 18 pt / < 14 pt bold)', 'livq-accessfix' ); ?></td>
					<td>4.5 : 1</td>
					<td id="res-aa-normal">-</td>
				</tr>
				<tr>
					<td><strong>AA</strong></td>
					<td><?php esc_html_e( 'Large text (≥ 18 pt / ≥ 14 pt bold)', 'livq-accessfix' ); ?></td>
					<td>3 : 1</td>
					<td id="res-aa-large">-</td>
				</tr>
				<tr>
					<td><strong>AA</strong></td>
					<td><?php esc_html_e( 'UI components & graphics (WCAG 1.4.11)', 'livq-accessfix' ); ?></td>
					<td>3 : 1</td>
					<td id="res-aa-ui">-</td>
				</tr>
				<tr>
					<td><strong>AAA</strong></td>
					<td><?php esc_html_e( 'Normal text (enhanced)', 'livq-accessfix' ); ?></td>
					<td>7 : 1</td>
					<td id="res-aaa-normal">-</td>
				</tr>
				<tr>
					<td><strong>AAA</strong></td>
					<td><?php esc_html_e( 'Large text (enhanced)', 'livq-accessfix' ); ?></td>
					<td>4.5 : 1</td>
					<td id="res-aaa-large">-</td>
				</tr>
			</tbody>
		</table>

		<!-- Tip bar -->
		<div id="livqacea-ct-tip" role="status" style="display:none;margin-top:16px;padding:12px 16px;background:#f0f6fc;border-left:4px solid #2271b1;">
			<strong><?php esc_html_e( 'Tip:', 'livq-accessfix' ); ?></strong> <span id="livqacea-ct-tip-text"></span>
		</div>

		<!-- Quick test palette -->
		<div style="margin-top:28px;">
			<h3 style="margin-bottom:4px;"><?php esc_html_e( 'Quick test palette', 'livq-accessfix' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Click a pair to load it into the checker.', 'livq-accessfix' ); ?></p>
			<div id="livqacea-ct-palette" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;"></div>
		</div>

	</div><!-- .livqacea-ct-tool -->
</div><!-- .wrap -->
		<?php
	}
}
