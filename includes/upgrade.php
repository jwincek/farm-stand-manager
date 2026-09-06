<?php
/**
 * One-time data migrations.
 *
 * Meta keys are permanent in a way code is not: they sit in the database of
 * every install, in exported spreadsheets, and in whatever a site owner has
 * written against them. This file exists so the set can be corrected once,
 * while the installed base is small enough that correcting it is free.
 *
 * META_KEY_RENAMES is the single source of truth. The migration reads it, the
 * tests read it, and bin/validate-config.php checks that nothing in the tree
 * still mentions a left-hand side. Nothing derives the map a second time, so
 * the copies cannot drift apart.
 */

declare(strict_types=1);

namespace ProducerKit\Upgrade;

defined( 'ABSPATH' ) || exit;

/**
 * Option holding the meta-key generation this site has been migrated to.
 */
const META_VERSION_OPTION = 'pkit_meta_key_version';

/**
 * Current meta-key generation. Bump when META_KEY_RENAMES grows.
 */
const META_VERSION = 2;

/**
 * old meta key => new meta key.
 *
 * Two corrections, made together in 2.6.0:
 *
 * 1. The `_pkit_em_` and `_pkit_ss_` infixes were documented as collision
 *    avoidance between modules. There is nothing to collide with inside one
 *    plugin, and the convention was not followed anyway — core registered
 *    _pkit_rsvp_cap while event-manager registered _pkit_em_rsvp_enabled,
 *    splitting one feature's configuration across two prefixes on one post
 *    type. An infix by module also freezes a code boundary into storage, and
 *    code boundaries move: making a field universal should not rename it.
 *
 * 2. `growing`, `milling` and `farm` named one trade in keys every trade
 *    stores. The meta-labels layer already re-labels all three per profile —
 *    a potter's _pkit_milling_notes is "Preparation Notes", a musician's is
 *    "Mastering Notes" — but a label filter cannot reach storage, REST, or a
 *    CSV header.
 *
 * _pkit_ss_schedule becomes _pkit_weekly_schedule rather than a plain infix
 * drop: _pkit_schedule sitting next to _pkit_hours says nothing about which
 * one is the structured weekly array.
 */
const META_KEY_RENAMES = [
	// event-manager module infix.
	'_pkit_em_rsvp_enabled'    => '_pkit_rsvp_enabled',
	'_pkit_em_rsvp_label'      => '_pkit_rsvp_label',
	'_pkit_em_rsvp_closed'     => '_pkit_rsvp_closed',
	'_pkit_em_what_to_bring'   => '_pkit_what_to_bring',
	'_pkit_em_cost_note'       => '_pkit_cost_note',
	'_pkit_em_cancelled'       => '_pkit_cancelled',
	'_pkit_em_also_appearing'  => '_pkit_also_appearing',
	'_pkit_em_doors_datetime'  => '_pkit_doors_datetime',
	'_pkit_em_age_restriction' => '_pkit_age_restriction',
	'_pkit_em_ticket_url'      => '_pkit_ticket_url',

	// stand-status module infix.
	'_pkit_ss_status_message'  => '_pkit_status_message',
	'_pkit_ss_last_toggled'    => '_pkit_last_toggled',
	'_pkit_ss_schedule'        => '_pkit_weekly_schedule',
	'_pkit_ss_season_start'    => '_pkit_season_start',
	'_pkit_ss_season_end'      => '_pkit_season_end',
	'_pkit_ss_auto_toggle'     => '_pkit_auto_toggle',

	// One trade's vocabulary in every trade's storage.
	'_pkit_growing_notes'      => '_pkit_production_notes',
	'_pkit_milling_notes'      => '_pkit_source_processing_notes',
	'_pkit_source_farm_name'   => '_pkit_source_name',
];

/**
 * Run pending migrations.
 *
 * Priority 20 on plugins_loaded, matching the availability table's self-heal:
 * late enough that the plugin is loaded, early enough to be done before init
 * registers meta or any request reads it.
 */
add_action(
	'plugins_loaded',
	function (): void {
		if ( (int) get_option( META_VERSION_OPTION, 0 ) >= META_VERSION ) {
			return;
		}

		migrate_meta_keys();

		update_option( META_VERSION_OPTION, META_VERSION, true );
	},
	20
);

/**
 * Rename every key in META_KEY_RENAMES across postmeta.
 *
 * A plain UPDATE is correct here because the right-hand sides are new names
 * that no released version ever wrote, so old and new cannot both be present
 * on the same post. The version option makes this run once regardless.
 *
 * Returns the number of rows renamed, which the tests assert on.
 */
function migrate_meta_keys(): int {
	global $wpdb;

	$renamed = 0;

	foreach ( META_KEY_RENAMES as $old => $new ) {
		// Collect the affected posts before the rename so their meta caches
		// can be dropped after it. A persistent object cache keys post meta by
		// post ID, so without this a cached read would keep serving the old
		// shape for the rest of the request.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				$old
			)
		);

		if ( ! $post_ids ) {
			continue;
		}

		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				$new,
				$old
			)
		);

		if ( is_int( $rows ) ) {
			$renamed += $rows;
		}

		foreach ( $post_ids as $post_id ) {
			wp_cache_delete( (int) $post_id, 'post_meta' );
		}
	}

	return $renamed;
}
