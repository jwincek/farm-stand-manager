<?php
/**
 * Producer profile: Farm.
 *
 * The plugin's original vocabulary, and the default. Mirrors the terms core
 * seeds on its own so that a site with this profile active behaves exactly as
 * it did before profiles existed.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'          => __( 'Farm', 'producerkit' ),
	'description'    => __( 'Produce, bread and pantry goods for a farm stand or market garden.', 'producerkit' ),
	// A farm sells what it grew; material/finish/component add nothing.
	'taxonomies'     => [],
	'names'          => [],
	'request_names'  => [
		'singular' => __( 'Special Order', 'producerkit' ),
		'plural'   => __( 'Special Orders', 'producerkit' ),
		'menu'     => __( 'Special Orders', 'producerkit' ),
		'action'   => __( 'Request a special order', 'producerkit' ),
	],
	'producer_names' => [
		'byline'     => __( 'Grown by', 'producerkit' ),
		'name_label' => __( 'Farm name', 'producerkit' ),
	],
	'meta_labels'    => [
		'_pkit_also_appearing' => [ __( 'Sharing the booth', 'producerkit' ), __( 'Another grower or maker selling alongside you.', 'producerkit' ) ],
		'_pkit_doors_datetime' => [ __( 'Gates open', 'producerkit' ), __( 'When people can arrive, if that is earlier than the start.', 'producerkit' ) ],
	],
	'sample'         => [
		'unit'        => 'bunch',
		'price'       => '$4',
		'place'       => __( 'Farm Stand', 'producerkit' ),
		// [ name, product type ]. A farm's product types are categories, so
		// generating names from them would give "Produce" and "Bread"; these
		// are the plugin's original sample products, kept.
		'products'    => [
			[ 'Arugula', 'Produce' ],
			[ 'Cherry Tomatoes', 'Produce' ],
			[ 'Salad Mix', 'Produce' ],
			[ 'Sugar Snap Peas', 'Produce' ],
			[ 'Country Sourdough', 'Bread' ],
			[ 'Cornmeal Cookies', 'Baked Good' ],
			[ 'Garlic Dill Pickles', 'Pantry Good' ],
			[ 'Tomato Seedlings', 'Seedling' ],
		],
		'place_blurb' => __( 'Our honor-system roadside stand. Cash and Venmo accepted.', 'producerkit' ),
	],
	'terms'          => [
		'pkit_product_type' => [ 'Produce', 'Bread', 'Baked Good', 'Pantry Good', 'Seedling' ],
		'pkit_season'       => [ 'Spring', 'Summer', 'Fall', 'Winter' ],
		'pkit_event_type'   => [ 'Pizza Night', 'Potluck', 'Farm Dinner', 'Workshop', 'Farm Tour', 'Seed Exchange', 'Mini Market' ],
	],
];
