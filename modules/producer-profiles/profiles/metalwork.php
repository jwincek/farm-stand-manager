<?php
/**
 * Producer profile: Metalwork.
 *
 * Ported from WC Artisan Tools' craft profiles.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'       => __( 'Metalwork', 'producerkit' ),
	'description' => __( 'Forged and fabricated steel, iron and non-ferrous metalwork.', 'producerkit' ),
	'taxonomies'  => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'       => [
		'pkit_material'  => [ __( 'Steel / Metal', 'producerkit' ), __( 'Steels / Metals', 'producerkit' ) ],
		'pkit_component' => [ __( 'Handle / Accessory', 'producerkit' ), __( 'Handles / Accessories', 'producerkit' ) ],
	],
	'meta_labels' => [
		'_pkit_source_name' => [ __( 'Supplier', 'producerkit' ), __( 'Where the stock came from. Falls back to the post title on the front end if left empty.', 'producerkit' ) ],
		'_pkit_source_history'   => [ __( 'Background', 'producerkit' ), __( 'The story behind this material.', 'producerkit' ) ],
		'_pkit_source_processing_notes'    => [ __( 'Working Notes', 'producerkit' ), __( 'How it was worked — forged, cast, annealed, finished.', 'producerkit' ) ],
		'_pkit_production_notes'    => [ __( 'Making Notes', 'producerkit' ), __( 'Shown on the product page. Free-form.', 'producerkit' ) ],
	],
	'sample'      => [
		'unit'        => 'each',
		'price'       => '$85',
		'place'       => __( 'The Forge', 'producerkit' ),
		'place_blurb' => __( 'Where the work is forged and finished.', 'producerkit' ),
	],
	'terms'       => [
		'pkit_product_type' => [ 'Knife', 'Bottle Opener', 'Hook', 'Fire Poker', 'Candle Holder', 'Shelf Bracket', 'Letter Opener', 'Keychain', 'Sculpture', 'Tool' ],
		'pkit_event_type'   => [ 'Open Forge', 'Demonstration', 'Craft Fair', 'Workshop' ],
		'pkit_material'     => [ '1095 Carbon Steel', 'Damascus Steel', 'O1 Tool Steel', 'Wrought Iron', 'Mild Steel', 'Stainless Steel', 'Copper', 'Bronze', 'Brass' ],
		'pkit_finish'       => [ 'Mirror Polish', 'Satin', 'Acid Etch', 'Patina', 'Beeswax', 'Clear Coat', 'Blued', 'Forge Scale' ],
		'pkit_component'    => [ 'Walnut Handle', 'Micarta Handle', 'G10 Handle', 'Antler Handle', 'Leather Wrap', 'Paracord Wrap', 'Leather Sheath', 'Kydex Sheath', 'No Handle' ],
	],
];
