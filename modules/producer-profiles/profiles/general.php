<?php
/**
 * Producer profile: General.
 *
 * Ported from WC Artisan Tools' craft profiles.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'General', 'producerkit' ),
	'description' => __( 'A blank slate: the full field set with no vocabulary seeded.', 'producerkit' ),
	'taxonomies'  => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'       => [],
	'sample'      => [
		'unit'        => 'each',
		'price'       => '$20',
		'place'       => __( 'The Workshop', 'producerkit' ),
		// A blank slate seeds no vocabulary, so there is nothing to generate
		// from and nothing to type these as.
		'products'    => [ 'Small Item', 'Medium Item', 'Large Item', 'Gift Set' ],
		'place_blurb' => __( 'Where the work is made.', 'producerkit' ),
	],
	'terms'       => [
		'pkit_product_type' => [],
		'pkit_event_type'   => [ 'Open Studio', 'Market', 'Workshop', 'Sale' ],
		'pkit_material'     => [],
		'pkit_finish'       => [],
		'pkit_component'    => [],
	],
];
