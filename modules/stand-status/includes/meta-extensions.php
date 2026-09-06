<?php
/**
 * Stand-status-specific meta extensions on pkit_location.
 *
 * These extend the core location meta without duplicating it.
 *
 * Plain _pkit_ prefix, like core's location fields. The _pkit_ss_ infix these
 * carried until 2.6.0 recorded which module registered a field, which is not
 * something a reader of the database can predict and not something that stays
 * true when a field moves. See includes/upgrade.php.
 */

declare(strict_types=1);

namespace ProducerKit\StandStatus\Meta;

defined( 'ABSPATH' ) || exit;

add_action( 'init', __NAMESPACE__ . '\\register' );

function register(): void {
	$fields = [
		'_pkit_status_message' => [
			'type'        => 'string',
			'description' => 'Custom status message shown alongside the badge, e.g. "Back at 2 PM" or "Sold out for today".',
			'default'     => '',
		],
		'_pkit_last_toggled'   => [
			'type'        => 'string',
			'description' => 'ISO 8601 timestamp of the last open/closed toggle.',
			'default'     => '',
		],
		'_pkit_weekly_schedule'       => [
			'type'        => 'string',
			'description' => 'JSON-encoded weekly schedule array. Each entry: { day: 0-6, open: "HH:MM", close: "HH:MM" }.',
			'default'     => '',
		],
		'_pkit_season_start'   => [
			'type'        => 'string',
			'description' => 'Season opening date (YYYY-MM-DD).',
			'default'     => '',
		],
		'_pkit_season_end'     => [
			'type'        => 'string',
			'description' => 'Season closing date (YYYY-MM-DD).',
			'default'     => '',
		],
		'_pkit_auto_toggle'    => [
			'type'        => 'boolean',
			'description' => 'Whether to auto-toggle open/closed based on the weekly schedule.',
			'default'     => false,
		],
	];

	foreach ( $fields as $key => $args ) {
		register_post_meta(
			'pkit_location',
			$key,
			[
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => $args['type'],
				'description'       => $args['description'],
				'default'           => $args['default'],
				'sanitize_callback' => match ( $args['type'] ) {
					'boolean' => 'rest_sanitize_boolean',
					default   => 'sanitize_text_field',
				},
				'auth_callback'     => fn () => current_user_can( 'edit_posts' ),
			]
		);
	}
}
