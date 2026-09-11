<?php
/**
 * The getting-started guide, as a page in the dashboard.
 *
 * Until now this guide shipped nowhere: .distignore excluded it, so the only
 * people who could read it were those browsing the repository. Everyone who
 * actually installed the plugin got no guide at all.
 *
 * It sits directly under the dashboard, because the moment it is wanted is the
 * moment after activation, and because the first-run prompt can point at it.
 */

declare(strict_types=1);

namespace ProducerKit\Guide\Admin;

use ProducerKit\Guide;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', __NAMESPACE__ . '\\register_page', 11 );

/**
 * Priority 11: after the dashboard registers at 9 and core appends the post
 * type submenus at 10, so this lands below them rather than displacing the
 * dashboard from the top of the list — which is what makes the parent menu
 * open the dashboard at all. See #67.
 */
function register_page(): void {
	add_submenu_page(
		'producerkit',
		__( 'Getting Started', 'producerkit' ),
		__( 'Getting Started', 'producerkit' ),
		'edit_posts',
		'producerkit-guide',
		__NAMESPACE__ . '\\render_page'
	);
}

/**
 * Render it.
 */
function render_page(): void {
	$html = Guide\html();
	?>
	<div class="wrap pkit-guide">
		<h1><?php esc_html_e( 'Getting Started', 'producerkit' ); ?></h1>

		<?php if ( '' === $html ) : ?>
			<div class="notice notice-error inline">
				<p><?php esc_html_e( 'The guide could not be loaded. Reinstalling the plugin should restore it.', 'producerkit' ); ?></p>
			</div>
		<?php else : ?>
			<div class="pkit-guide__body">
				<?php
				/*
				 * The HTML is generated at build time from a Markdown file in
				 * this repository, and every token substituted into it is
				 * passed through esc_html() by Guide\html(). wp_kses_post()
				 * anyway, so that the page cannot become an injection point if
				 * the generator is ever pointed at something less trusted.
				 */
				echo wp_kses_post( $html );
				?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Keep the guide readable: WordPress admin pages are full-width, and a 200
 * character line of prose is not something anyone reads.
 */
add_action(
	'admin_head',
	function (): void {
		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || ! str_contains( (string) $screen->id, 'producerkit-guide' ) ) {
			return;
		}
		?>
		<style>
			.pkit-guide__body { max-width: 46rem; }
			.pkit-guide__body h2 { margin-top: 2em; padding-top: .6em; border-top: 1px solid #dcdcde; }
			.pkit-guide__body h3 { margin-top: 1.6em; }
			.pkit-guide__body li { margin-bottom: .35em; }
			.pkit-guide__body table { border-collapse: collapse; margin: 1em 0; }
			.pkit-guide__body th,
			.pkit-guide__body td { border: 1px solid #dcdcde; padding: .4em .7em; text-align: left; }
			.pkit-guide__body th { background: #f6f7f7; }
			.pkit-guide__body code { padding: .1em .4em; background: #f0f0f1; border-radius: 3px; }
			.pkit-guide__body hr { margin: 2em 0; border: 0; border-top: 1px solid #dcdcde; }
		</style>
		<?php
	}
);
