<?php
/**
 * Generate the default page set for this site's trade.
 *
 * Every post type registers with has_archive => false, so there is no
 * /products/ or /events/ index — the blocks are the index, which is what makes
 * them configurable in ways an archive template is not. The cost is that a
 * fresh install has nothing to navigate to, and nothing tells a producer the
 * pages are theirs to build. Someone who adds "Products" to their menu gets a
 * 404.
 *
 * This places the pages with their blocks already pointed at the site's own
 * location, because a block rendering an empty box teaches nothing.
 *
 * Three rules it does not break:
 *
 * 1. Pages are drafts. The sample-data button published to a live site without
 *    saying so (#68); a generator that publishes a whole page set would repeat
 *    that at a larger size. The producer reviews and publishes.
 * 2. Nothing is overwritten. A slug that exists is skipped and reported, so
 *    re-running fills gaps instead of duplicating.
 * 3. The nav menu and front page are left alone. Both are opinionated and
 *    awkward to undo; the producer gets edit links and wires them up.
 */

declare(strict_types=1);

namespace ProducerKit\DefaultPages;

use ProducerKit\Commissions\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * Marks a page this generator created.
 */
const GENERATED_META = '_pkit_generated_page';

/**
 * Handle the generate action from the dashboard.
 */
add_action(
	'admin_init',
	function (): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! isset( $_GET['pkit_pages_action'] ) || ! wp_verify_nonce( $nonce, 'pkit_pages_action' ) ) {
			return;
		}

		if ( 'generate' !== sanitize_key( wp_unslash( $_GET['pkit_pages_action'] ) ) ) {
			return;
		}

		$result = generate();

		set_transient( 'pkit_generated_pages', $result, 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=producerkit&pkit_pages=done' ) );
		exit;
	}
);

/**
 * The location a page's blocks should point at.
 *
 * The producer's own stand comes first — a retailer that carries your goods is
 * not the place to send someone to visit you.
 */
function primary_location_id(): int {
	$own = get_posts(
		[
			'post_type'     => 'pkit_location',
			'post_status'   => 'publish',
			'numberposts'   => 1,
			'fields'        => 'ids',
			'no_found_rows' => true,
			'meta_query'    => [
				'relation' => 'OR',
				[
					'key'     => '_pkit_location_type',
					'value'   => 'retailer',
					'compare' => '!=',
				],
				[
					'key'     => '_pkit_location_type',
					'compare' => 'NOT EXISTS',
				],
			],
		]
	);

	if ( $own ) {
		return (int) $own[0];
	}

	$any = get_posts(
		[
			'post_type'     => 'pkit_location',
			'post_status'   => 'publish',
			'numberposts'   => 1,
			'fields'        => 'ids',
			'no_found_rows' => true,
		]
	);

	return $any ? (int) $any[0] : 0;
}

/**
 * Whether anything exists to order yet.
 */
function has_any_product(): bool {
	return (bool) get_posts(
		[
			'post_type'     => 'pkit_product',
			'post_status'   => 'publish',
			'numberposts'   => 1,
			'fields'        => 'ids',
			'no_found_rows' => true,
		]
	);
}

/**
 * Serialise one dynamic block. All of these render server-side, so they are
 * self-closing and carry no inner content.
 *
 * @param array<string, mixed> $attrs
 */
function block( string $name, array $attrs = [] ): string {
	if ( ! $attrs ) {
		return sprintf( '<!-- wp:producerkit/%s /-->', $name );
	}

	return sprintf(
		'<!-- wp:producerkit/%s %s /-->',
		$name,
		wp_json_encode( $attrs )
	);
}

/**
 * The pages this trade should have.
 *
 * Titles come from the registered post type labels and the commissions
 * vocabulary, both of which already vary by producer profile — a musician's
 * events are "Shows" and their requests are "Bookings" — so this needs no new
 * profile key.
 *
 * @return array<int, array{slug: string, title: string, content: string}>
 */
function planned( ?int $location_id = null ): array {
	$location_id = null === $location_id ? primary_location_id() : $location_id;

	$event_type = get_post_type_object( 'pkit_event' );
	$events     = $event_type ? (string) $event_type->labels->menu_name : __( 'Events', 'producerkit' );

	$loc_attr = $location_id > 0 ? [ 'locationId' => $location_id ] : [];

	$pages = [];

	// A starter rather than a front page: something to copy blocks from, not
	// something that takes over the site.
	$pages[] = [
		'slug'    => 'home-starter',
		'title'   => __( 'Home (starter)', 'producerkit' ),
		'content' => implode(
			"\n\n",
			[
				block( 'stand-status-banner', $loc_attr ),
				block(
					'availability-board',
					[
						'showFilters' => true,
						'showPrices'  => true,
					]
				),
				block(
					'event-list',
					[
						'perPage'  => 3,
						'showRsvp' => false,
					]
				),
			]
		),
	];

	if ( $location_id > 0 ) {
		$pages[] = [
			'slug'    => 'visit',
			'title'   => __( 'Visit', 'producerkit' ),
			'content' => implode(
				"\n\n",
				[
					block( 'location-info', $loc_attr ),
					block( 'stand-hours-schedule', $loc_attr ),
				]
			),
		];
	}

	$pages[] = [
		'slug'    => sanitize_title( $events ),
		'title'   => $events,
		'content' => block(
			'event-list',
			[
				'showRsvp'        => true,
				'showTypeFilters' => true,
			]
		),
	];

	// The pre-order form renders nothing at all when no product is orderable
	// (blocks/preorder-form/render.php bails early), which is right on a live
	// site and wrong on a fresh install — exactly when this button is pressed.
	// So the page waits for something to order, the way Visit waits for
	// somewhere to visit. The board and event list are not gated the same way:
	// they have empty states and say so.
	if ( \ProducerKit\is_module_active( 'pre-order' ) && has_any_product() ) {
		$pages[] = [
			'slug'    => 'pre-orders',
			'title'   => __( 'Pre-orders', 'producerkit' ),
			'content' => block( 'preorder-form', $loc_attr ),
		];
	}

	if ( \ProducerKit\is_module_active( 'commissions' ) ) {
		$requests = Vocabulary\words()['plural'];

		$pages[] = [
			'slug'    => sanitize_title( $requests ),
			'title'   => $requests,
			'content' => block( 'commission-form' ),
		];
	}

	return $pages;
}

/**
 * Create the pages that are missing.
 *
 * @return array{created: array<int, array{id: int, title: string}>, skipped: array<int, string>}
 */
function generate(): array {
	$created = [];
	$skipped = [];

	foreach ( planned() as $page ) {
		$existing = get_page_by_path( $page['slug'], OBJECT, 'page' );

		if ( $existing instanceof \WP_Post ) {
			$skipped[] = $page['title'];
			continue;
		}

		$id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => $page['title'],
				'post_name'    => $page['slug'],
				'post_content' => $page['content'],
			],
			true
		);

		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}

		update_post_meta( (int) $id, GENERATED_META, '1' );

		$created[] = [
			'id'    => (int) $id,
			'title' => $page['title'],
		];
	}

	return [
		'created' => $created,
		'skipped' => $skipped,
	];
}

/**
 * The dashboard section (called from admin-dashboard.php).
 */
function get_dashboard_html(): string {
	$url = wp_nonce_url(
		admin_url( 'admin.php?page=producerkit&pkit_pages_action=generate' ),
		'pkit_pages_action'
	);

	$notice = '';

	// Display-only flag, set by our own redirect after the nonce-checked action
	// above already ran. Nothing here changes state.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['pkit_pages'] ) ) {
		$result = get_transient( 'pkit_generated_pages' );
		delete_transient( 'pkit_generated_pages' );

		$lines = [];

		if ( ! empty( $result['created'] ) ) {
			$links = [];
			foreach ( $result['created'] as $page ) {
				$links[] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( (string) get_edit_post_link( $page['id'] ) ),
					esc_html( $page['title'] )
				);
			}

			$lines[] = sprintf(
				/* translators: %s: list of links to the pages that were created. */
				esc_html__( 'Created as drafts: %s. Review each one, then publish.', 'producerkit' ),
				implode( ', ', $links )
			);
		}

		if ( ! empty( $result['skipped'] ) ) {
			$lines[] = sprintf(
				/* translators: %s: list of page titles that already existed. */
				esc_html__( 'Left alone, because a page with that address already exists: %s.', 'producerkit' ),
				esc_html( implode( ', ', $result['skipped'] ) )
			);
		}

		if ( ! $lines ) {
			$lines[] = esc_html__( 'Nothing to create — every default page already exists.', 'producerkit' );
		}

		$notice = sprintf(
			'<div class="notice notice-success inline" style="margin-bottom:0.75rem;"><p>%s</p></div>',
			implode( '<br />', $lines )
		);
	}

	$titles = wp_list_pluck( planned(), 'title' );

	return sprintf(
		'<div class="pkit-dashboard__section">
            <h2>%s</h2>
            %s
            <p class="description">%s</p>
            <p class="description"><strong>%s</strong></p>
            <a href="%s" class="button">%s</a>
        </div>',
		esc_html__( 'Default Pages', 'producerkit' ),
		$notice,
		esc_html__( 'This plugin has no automatic archive pages — the blocks are the listing, which is what makes them configurable. That means the pages are yours to build, so this builds them for you, with the blocks already pointed at your location.', 'producerkit' ),
		sprintf(
			/* translators: %s: comma-separated list of page titles. */
			esc_html__( 'Creates as drafts, and never touches a page you already have: %s', 'producerkit' ),
			esc_html( implode( ', ', $titles ) )
		),
		esc_url( $url ),
		esc_html__( 'Generate Default Pages', 'producerkit' )
	);
}
