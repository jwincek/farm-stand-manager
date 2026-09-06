<?php
/**
 * What the sample content should look like for this trade.
 *
 * Choosing a profile already seeds the trade's vocabulary — a beekeeper gets
 * Honey and Wildflower, a potter gets Mug and Stoneware. The sample content
 * did not follow: it was eight farm products and a Pizza Night, whoever you
 * were. Somebody picks Beekeeping at the first-run prompt, clicks the toggle
 * to see what the plugin does, and gets a farm.
 *
 * The names are generated from those terms rather than authored per trade.
 * Sixteen profiles of invented content would be two hundred items in a file
 * that grows with every profile and goes stale; a material term in front of a
 * product-type term reads well enough across trades and stays correct when a
 * profile's vocabulary changes:
 *
 *   Wildflower Honey · Stoneware Mug · Black Vinyl LP
 *
 * What terms cannot supply is a unit, a price and a name for a place —
 * locations are not term-driven, and a beekeeper's stand should not be called
 * Farm Stand. That is four short strings per profile.
 */

declare(strict_types=1);

namespace ProducerKit\ProducerProfiles\SampleHints;

use ProducerKit\ProducerProfiles\Profiles;

defined( 'ABSPATH' ) || exit;

/**
 * The hints for the trade whose wording is in force.
 *
 * @return array{unit: string, price: string, place: string, place_blurb: string}
 */
function hints(): array {
	$defaults = [
		'unit'          => 'each',
		'price'         => '$20',
		'place'         => __( 'The Workshop', 'producerkit' ),
		'place_blurb'   => __( 'Where the work is made.', 'producerkit' ),
		// Names to use instead of generating any. A farm's product types are
		// categories — Produce, Bread — so its own list reads better, and a
		// blank-slate profile seeds no vocabulary to generate from at all.
		'products'      => [],
		// Whether to put a material in front of the type. False where the two
		// describe the same axis: for a musician, format and product type
		// both mean vinyl, and pairing them gives "Colored Vinyl Cassette".
		'pair_material' => true,
	];

	$profile = Profiles\labelling_profile();
	$given   = ( null !== $profile && isset( $profile['sample'] ) ) ? (array) $profile['sample'] : [];

	$out = [];
	foreach ( $defaults as $key => $fallback ) {
		if ( is_bool( $fallback ) ) {
			$out[ $key ] = array_key_exists( $key, $given ) ? (bool) $given[ $key ] : $fallback;
			continue;
		}

		if ( is_array( $fallback ) ) {
			$out[ $key ] = isset( $given[ $key ] ) ? array_values( (array) $given[ $key ] ) : $fallback;
			continue;
		}

		$value = isset( $given[ $key ] ) ? trim( (string) $given[ $key ] ) : '';

		$out[ $key ] = '' !== $value ? $value : $fallback;
	}

	return $out;
}

/**
 * Sample product names, built from the trade's own vocabulary.
 *
 * A material in front of a product type where both exist, the type alone
 * where they do not. Duplicates are impossible because each pairing is used
 * once, and the list is trimmed to $count so a trade with forty terms does
 * not seed forty products.
 *
 * @return array<int, array{name: string, type: string, material: string}>
 */
function product_names( int $count = 8 ): array {
	$hints = hints();

	// A trade that supplies its own names uses them and generates nothing.
	// An entry is either a plain name or [ name, product type ] — assigning
	// types by position instead gave "Country Sourdough" the type "Seedling".
	if ( $hints['products'] ) {
		$out = [];

		foreach ( array_slice( $hints['products'], 0, $count ) as $entry ) {
			$entry = (array) $entry;

			$out[] = [
				'name'     => (string) ( $entry[0] ?? '' ),
				'type'     => (string) ( $entry[1] ?? '' ),
				'material' => '',
			];
		}

		return $out;
	}

	$types     = terms_for( 'pkit_product_type' );
	$materials = $hints['pair_material'] ? terms_for( 'pkit_material' ) : [];

	if ( ! $types ) {
		return [];
	}

	$out = [];

	// Pair each type with a different material, so the list reads as a range
	// of things rather than five variations of one.
	foreach ( $types as $i => $type ) {
		if ( count( $out ) >= $count ) {
			break;
		}

		$material = $materials ? (string) $materials[ $i % count( $materials ) ] : '';

		$out[] = [
			// A material that is already the whole name — "Cassette" as both
			// a format and a product type — would read as "Cassette Cassette".
			'name'     => ( '' !== $material && $material !== $type ) ? $material . ' ' . $type : $type,
			'type'     => $type,
			'material' => $material,
		];
	}

	return $out;
}

/**
 * Sample event names, from the trade's own event types.
 *
 * @return string[]
 */
function event_names( int $count = 3 ): array {
	return array_slice( terms_for( 'pkit_event_type' ), 0, $count );
}

/**
 * The seeded term names for a taxonomy, in the order the profile declares
 * them, whether or not they have reached the database yet.
 *
 * Read from the profile rather than with get_terms() because sample data is
 * loaded from the dashboard, and on a fresh site the seeding may not have
 * run for a taxonomy nobody has opened yet.
 *
 * @return string[]
 */
function terms_for( string $taxonomy ): array {
	$names = (array) apply_filters( 'pkit_taxonomy_default_terms', [], $taxonomy );

	return array_values(
		array_filter(
			array_map( 'strval', $names ),
			static fn ( string $name ): bool => '' !== trim( $name )
		)
	);
}
