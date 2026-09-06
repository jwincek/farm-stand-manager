<?php
/**
 * Sample content that matches the trade you chose.
 *
 * Choosing a profile already seeded that trade's vocabulary. The sample
 * content did not follow — it was eight farm products and a Pizza Night
 * whoever you were, which is what somebody sees immediately after the
 * first-run prompt asks what they make.
 *
 * Names are generated from the terms the profile already declares rather
 * than authored sixteen times over, so the list stays right when a profile's
 * vocabulary changes.
 */

declare(strict_types=1);

use ProducerKit\ProducerProfiles\SampleHints;

final class SampleHintsTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'pkit_producer_profile' );
		parent::tear_down();
	}

	private function use_profile( string $slug ): void {
		update_option( 'pkit_producer_profile', [ $slug ] );
	}

	/**
	 * @return string[]
	 */
	private function names( int $count = 8 ): array {
		return array_column( SampleHints\product_names( $count ), 'name' );
	}

	public function test_a_material_in_front_of_a_type_reads_as_a_product(): void {
		$this->use_profile( 'beekeeping' );

		$this->assertContains( 'Wildflower Honey', $this->names() );

		$this->use_profile( 'pottery' );
		$this->assertContains( 'Stoneware Mug', $this->names() );
	}

	public function test_the_place_is_named_for_the_trade(): void {
		// A beekeeper's stand should not be called Farm Stand.
		$this->use_profile( 'beekeeping' );
		$this->assertSame( 'The Home Yard', SampleHints\hints()['place'] );

		$this->use_profile( 'pottery' );
		$this->assertSame( 'The Studio', SampleHints\hints()['place'] );
	}

	public function test_events_come_from_the_trades_own_event_types(): void {
		$this->use_profile( 'pottery' );
		$this->assertContains( 'Open Studio', SampleHints\event_names( 5 ) );

		$this->use_profile( 'beekeeping' );
		$this->assertContains( 'Hive Tour', SampleHints\event_names( 5 ) );
	}

	public function test_every_profile_can_name_an_event(): void {
		// Seven trades seeded no event types at all, so generated events
		// would have been blank for them — a hole in those profiles, not
		// only in the sample data.
		foreach ( \ProducerKit\ProducerProfiles\Profiles\get_slugs() as $slug ) {
			$this->use_profile( $slug );

			$this->assertNotEmpty(
				SampleHints\event_names( 1 ),
				$slug . ' has no event types to name a sample event after.'
			);
		}
	}

	public function test_every_profile_produces_some_products(): void {
		foreach ( \ProducerKit\ProducerProfiles\Profiles\get_slugs() as $slug ) {
			$this->use_profile( $slug );

			$this->assertNotEmpty( $this->names( 4 ), $slug . ' produces no sample products.' );
		}
	}

	/* ── The three that cannot be generated ─────────────── */

	public function test_a_farm_keeps_its_own_names(): void {
		// A farm's product types are categories — Produce, Bread — so
		// generating from them would give "Produce" as a product name.
		$this->use_profile( 'farm' );

		$this->assertSame( 'Arugula', $this->names()[0] );
		$this->assertNotContains( 'Produce', $this->names() );
	}

	public function test_authored_names_carry_their_own_type(): void {
		// Assigning types by position gave "Country Sourdough" the type
		// "Seedling", which is the kind of thing nobody reads twice.
		$this->use_profile( 'farm' );

		$by_name = [];
		foreach ( SampleHints\product_names() as $product ) {
			$by_name[ $product['name'] ] = $product['type'];
		}

		$this->assertSame( 'Produce', $by_name['Arugula'] );
		$this->assertSame( 'Bread', $by_name['Country Sourdough'] );
		$this->assertSame( 'Seedling', $by_name['Tomato Seedlings'] );
	}

	public function test_a_musician_does_not_get_a_colored_vinyl_cassette(): void {
		// Format and product type are the same axis for a musician, so
		// pairing them produces nonsense.
		$this->use_profile( 'musician' );

		$names = $this->names();

		$this->assertContains( 'Cassette', $names );

		foreach ( $names as $name ) {
			$this->assertStringNotContainsString( 'Vinyl Cassette', $name );
			$this->assertStringNotContainsString( 'Vinyl Vinyl', $name );
		}
	}

	public function test_a_blank_slate_still_produces_something(): void {
		// The general profile seeds no vocabulary, so there is nothing to
		// generate from.
		$this->use_profile( 'general' );

		$this->assertNotEmpty( $this->names() );
	}

	public function test_a_name_is_never_the_material_twice(): void {
		// "Cassette Cassette" — where a term is both a material and a type.
		foreach ( \ProducerKit\ProducerProfiles\Profiles\get_slugs() as $slug ) {
			$this->use_profile( $slug );

			foreach ( SampleHints\product_names() as $product ) {
				$words = explode( ' ', $product['name'] );
				$this->assertSame(
					count( $words ),
					count( array_unique( $words ) ),
					$slug . ' produced "' . $product['name'] . '".'
				);
			}
		}
	}

	public function test_hints_fall_back_when_a_profile_says_nothing(): void {
		$this->use_profile( 'general' );
		$hints = SampleHints\hints();

		foreach ( [ 'unit', 'price', 'place', 'place_blurb' ] as $key ) {
			$this->assertNotSame( '', $hints[ $key ], $key . ' should never be blank.' );
		}
	}
}
