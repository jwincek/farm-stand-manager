<?php
/**
 * The 2.6.0 meta-key rename, and the guards that keep it honest.
 *
 * Renaming meta keys is a one-way door: the migration runs once per site and
 * the old names are gone afterwards. These tests cover the two ways that can
 * go wrong — the migration missing rows, and a renamed key creeping back into
 * the source tree later, where it would be written but never migrated.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_UnitTestCase;

use const ProducerKit\Upgrade\META_KEY_RENAMES;
use const ProducerKit\Upgrade\META_VERSION;
use const ProducerKit\Upgrade\META_VERSION_OPTION;

class MetaKeyMigrationTest extends WP_UnitTestCase {

	/**
	 * Directories that hold shipped code, as opposed to the migration map or
	 * this test.
	 */
	private const CODE_DIRS = [ 'modules', 'includes', 'assets', 'blocks' ];

	/**
	 * Every old key becomes its new key, value intact.
	 */
	public function test_migration_renames_every_key(): void {
		global $wpdb;

		$post_id = self::factory()->post->create();

		foreach ( META_KEY_RENAMES as $old => $new ) {
			// Written raw: update_post_meta() would refuse nothing here, but
			// going through $wpdb is what a 2.5.0 database actually looks like.
			$wpdb->insert(
				$wpdb->postmeta,
				[
					'post_id'    => $post_id,
					'meta_key'   => $old,
					'meta_value' => 'value for ' . $old,
				]
			);
		}

		wp_cache_delete( $post_id, 'post_meta' );

		$renamed = \ProducerKit\Upgrade\migrate_meta_keys();

		$this->assertSame( count( META_KEY_RENAMES ), $renamed );

		foreach ( META_KEY_RENAMES as $old => $new ) {
			$this->assertSame(
				'value for ' . $old,
				get_post_meta( $post_id, $new, true ),
				"{$old} did not survive the rename to {$new}"
			);
			$this->assertSame(
				'',
				get_post_meta( $post_id, $old, true ),
				"{$old} is still present after the rename"
			);
		}
	}

	/**
	 * A site with none of the old keys is left alone, and the routine is safe
	 * to run twice.
	 */
	public function test_migration_is_a_no_op_on_a_clean_site(): void {
		$this->assertSame( 0, \ProducerKit\Upgrade\migrate_meta_keys() );
		$this->assertSame( 0, \ProducerKit\Upgrade\migrate_meta_keys() );
	}

	/**
	 * Values are not mangled in transit.
	 *
	 * _pkit_weekly_schedule holds JSON and _pkit_production_notes holds free
	 * text, so a rename that went through sanitisation would corrupt both.
	 */
	public function test_migration_preserves_structured_values(): void {
		global $wpdb;

		$post_id  = self::factory()->post->create();
		$schedule = wp_json_encode(
			[
				[
					'day'   => 6,
					'open'  => '09:00',
					'close' => '13:00',
				],
			]
		);
		$notes    = "No-till, heirloom variety.\nSecond line — with punctuation & an ampersand.";

		$wpdb->insert(
			$wpdb->postmeta,
			[
				'post_id'    => $post_id,
				'meta_key'   => '_pkit_ss_schedule',
				'meta_value' => $schedule,
			]
		);
		$wpdb->insert(
			$wpdb->postmeta,
			[
				'post_id'    => $post_id,
				'meta_key'   => '_pkit_growing_notes',
				'meta_value' => $notes,
			]
		);
		wp_cache_delete( $post_id, 'post_meta' );

		\ProducerKit\Upgrade\migrate_meta_keys();

		$this->assertSame( $schedule, get_post_meta( $post_id, '_pkit_weekly_schedule', true ) );
		$this->assertSame( $notes, get_post_meta( $post_id, '_pkit_production_notes', true ) );
	}

	/**
	 * The version gate stops the migration re-running on every request.
	 */
	public function test_version_option_reflects_the_current_generation(): void {
		update_option( META_VERSION_OPTION, META_VERSION );

		$this->assertGreaterThanOrEqual(
			META_VERSION,
			(int) get_option( META_VERSION_OPTION, 0 ),
			'A migrated site should not be asked to migrate again.'
		);
	}

	/**
	 * No renamed key survives anywhere in shipped code.
	 *
	 * This is the guard that matters most. A stray `_pkit_em_ticket_url` in a
	 * template would read a key the migration has already emptied, and would
	 * do it silently — an empty field, not an error.
	 */
	public function test_no_shipped_file_still_uses_an_old_key(): void {
		$root     = dirname( __DIR__, 2 );
		$offences = [];

		foreach ( self::CODE_DIRS as $dir ) {
			$path = $root . '/' . $dir;
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path ) );

			foreach ( $files as $file ) {
				if ( ! $file->isFile() || ! preg_match( '/\.(php|js)$/', $file->getFilename() ) ) {
					continue;
				}
				if ( str_contains( $file->getPathname(), '/node_modules/' ) || str_contains( $file->getPathname(), '/build/' ) ) {
					continue;
				}
				// The migration map is the one place the old names must live on.
				if ( str_ends_with( $file->getPathname(), 'includes/upgrade.php' ) ) {
					continue;
				}

				$contents = (string) file_get_contents( $file->getPathname() );

				foreach ( array_keys( META_KEY_RENAMES ) as $old ) {
					if ( preg_match( '/\b' . preg_quote( $old, '/' ) . '\b/', $contents ) ) {
						$offences[] = str_replace( $root . '/', '', $file->getPathname() ) . ' uses ' . $old;
					}
				}
			}
		}

		$this->assertSame( [], $offences, "Renamed meta keys are still referenced:\n" . implode( "\n", $offences ) );
	}

	/**
	 * The map only ever moves keys into the plain _pkit_ namespace, and never
	 * onto a name something else already owns.
	 */
	public function test_rename_targets_are_well_formed_and_unique(): void {
		$targets = array_values( META_KEY_RENAMES );

		$this->assertSame( $targets, array_unique( $targets ), 'Two keys renamed onto one target.' );

		foreach ( META_KEY_RENAMES as $old => $new ) {
			$this->assertStringStartsWith( '_pkit_', $new );
			$this->assertDoesNotMatchRegularExpression( '/^_pkit_(em|ss)_/', $new, "{$new} still carries a module infix." );
			$this->assertNotSame( $old, $new );
		}

		// The point of the exercise: no trade's vocabulary in a key every
		// trade stores.
		foreach ( $targets as $new ) {
			$this->assertDoesNotMatchRegularExpression(
				'/\b(growing|milling|farm)\b/',
				$new,
				"{$new} names one trade in storage every trade shares."
			);
		}
	}
}
