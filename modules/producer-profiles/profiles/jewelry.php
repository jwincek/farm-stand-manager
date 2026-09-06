<?php
/**
 * Producer profile: Jewelry.
 *
 * Ported from WC Artisan Tools' craft profiles.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'label'          => __( 'Jewelry', 'producerkit' ),
	'description'    => __( 'Fabricated and cast jewellery in precious and base metals.', 'producerkit' ),
	'taxonomies'     => [ 'pkit_material', 'pkit_finish', 'pkit_component' ],
	'names'          => [
		'pkit_material'  => [ __( 'Metal', 'producerkit' ), __( 'Metals', 'producerkit' ) ],
		'pkit_component' => [ __( 'Stone / Setting', 'producerkit' ), __( 'Stones / Settings', 'producerkit' ) ],
	],
	'producer_names' => [
		'byline'     => __( 'Made by', 'producerkit' ),
		'name_label' => __( 'Studio name', 'producerkit' ),
	],
	'meta_labels'    => [
		'_pkit_source_name'             => [ __( 'Supplier', 'producerkit' ), __( 'Where the metal or stone came from. Falls back to the post title on the front end if left empty.', 'producerkit' ) ],
		'_pkit_source_history'          => [ __( 'Provenance', 'producerkit' ), __( 'The story behind this stone or material.', 'producerkit' ) ],
		'_pkit_source_processing_notes' => [ __( 'Preparation Notes', 'producerkit' ), __( 'How it was cut, refined or prepared.', 'producerkit' ) ],
		'_pkit_production_notes'        => [ __( 'Making Notes', 'producerkit' ), __( 'Shown on the product page. Free-form.', 'producerkit' ) ],
	],
	'sample'         => [
		'unit'        => 'each',
		'price'       => '$120',
		'place'       => __( 'The Bench', 'producerkit' ),
		'place_blurb' => __( 'Where the work is fabricated and set.', 'producerkit' ),
	],
	'terms'          => [
		'pkit_product_type' => [ 'Ring', 'Necklace', 'Bracelet', 'Earrings', 'Pendant', 'Brooch', 'Cuff', 'Anklet', 'Tie Clip', 'Money Clip' ],
		'pkit_event_type'   => [ 'Trunk Show', 'Open Studio', 'Craft Fair', 'Workshop' ],
		'pkit_material'     => [ 'Sterling Silver', 'Gold Fill', '14K Gold', 'Brass', 'Copper', 'Titanium', 'Stainless Steel', 'Bronze' ],
		'pkit_finish'       => [ 'Polished', 'Brushed', 'Hammered', 'Oxidized', 'Patina', 'Matte', 'Mirror' ],
		'pkit_component'    => [ 'Bezel Set', 'Prong Set', 'Channel Set', 'Turquoise', 'Garnet', 'Amethyst', 'Moonstone', 'Opal', 'Pearl', 'Lab-Grown Diamond', 'No Stone' ],
	],
];
