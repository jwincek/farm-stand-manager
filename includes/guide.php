<?php
/**
 * The getting-started guide, in this site's own words.
 *
 * The guide is authored once, in docs/getting-started.tpl.md, with {{tokens}}
 * wherever a word belongs to a trade rather than to the plugin.
 * bin/make-guide.js turns that into two things: GETTING-STARTED.md for the
 * repo, with a farm's words resolved in, and includes/guide-content.php for
 * the plugin, converted to HTML with the tokens left standing.
 *
 * This resolves them. A beekeeper reading the guide is told to set up The Home
 * Yard and to fill in an Apiary; a potter, The Studio and a Clay Supplier. A
 * plugin whose entire argument is that a potter should not be shown a farm's
 * words should not hand them a farm's manual either.
 *
 * Every value comes from vocabulary that already exists — registered post type
 * labels, the commissions vocabulary, the meta labels, the profile's own
 * sample place and seeded terms. No profile gained a key for this.
 */

declare(strict_types=1);

namespace ProducerKit\Guide;

use ProducerKit\Commissions\Vocabulary;
use ProducerKit\Core\MetaLabels;
use ProducerKit\ProducerProfiles\Profiles;

defined( 'ABSPATH' ) || exit;

/**
 * Words this site would use, keyed by the template's tokens.
 *
 * @return array<string, string>
 */
function tokens(): array {
	$product = get_post_type_object( 'pkit_product' );
	$event   = get_post_type_object( 'pkit_event' );
	$profile = function_exists( '\\ProducerKit\\ProducerProfiles\\Profiles\\labelling_profile' )
		? Profiles\labelling_profile()
		: null;

	$place = $profile['sample']['place'] ?? __( 'The Farm Stand', 'producerkit' );

	$tokens = [
		'trade'               => (string) ( $profile['label'] ?? __( 'Farm', 'producerkit' ) ),
		'products'            => $product ? (string) $product->labels->name : __( 'Products', 'producerkit' ),
		'product'             => $product ? (string) $product->labels->singular_name : __( 'Product', 'producerkit' ),
		'events'              => $event ? (string) $event->labels->name : __( 'Events', 'producerkit' ),
		'event'               => $event ? (string) $event->labels->singular_name : __( 'Event', 'producerkit' ),
		'place'               => (string) $place,
		// Lower-cased and stripped of a leading article, for mid-sentence use
		// ("the morning sign for your home yard"), which is where most of
		// these land. Fifteen of the sixteen profiles name their place "The
		// Something", which is right for a location title — sample data
		// creates a location called The Home Yard — and wrong after "your".
		// The data stays as it is; this token is the one that has to bend.
		'place_lower'         => place_lower( (string) $place ),
		'requests'            => Vocabulary\words()['plural'],
		'request_action'      => Vocabulary\words()['action'],
		'notes_field'         => MetaLabels\label( '_pkit_production_notes' ),
		'source_field'        => MetaLabels\label( '_pkit_source_name' ),
		'product_types_label' => taxonomy_label( 'pkit_product_type', __( 'Product Types', 'producerkit' ) ),
		'event_types_label'   => taxonomy_label( 'pkit_event_type', __( 'Event Types', 'producerkit' ) ),
		'product_types'       => seeded_terms( 'pkit_product_type', $profile ),
		'event_types'         => seeded_terms( 'pkit_event_type', $profile ),
	];

	/**
	 * Filters the words the getting-started guide is written in.
	 *
	 * @param array<string, string> $tokens Token => word.
	 */
	return (array) apply_filters( 'pkit_guide_tokens', $tokens );
}

/**
 * A place name that reads correctly after "your".
 *
 * The article is English, which is as far as this goes: a translator giving a
 * profile a place name in another language should supply one that works in
 * their own possessive construction, since no amount of prefix-stripping here
 * would help them.
 */
function place_lower( string $place ): string {
	$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $place ) : strtolower( $place );

	return (string) preg_replace( '/^the\s+/i', '', $lower );
}

/**
 * What this site calls one of its taxonomies.
 */
function taxonomy_label( string $taxonomy, string $fallback ): string {
	$object = get_taxonomy( $taxonomy );

	return $object ? (string) $object->labels->name : $fallback;
}

/**
 * The terms this trade seeds, as a readable list.
 *
 * Read from the profile rather than from the database: the guide describes
 * what the plugin sets up for you, and a producer who has since renamed or
 * deleted them should still be able to read what the sentence is about.
 *
 * @param array<string, mixed>|null $profile
 */
function seeded_terms( string $taxonomy, ?array $profile ): string {
	$terms = $profile['terms'][ $taxonomy ] ?? [];

	if ( ! $terms ) {
		return __( 'none by default', 'producerkit' );
	}

	return implode( ', ', array_map( 'strval', (array) $terms ) );
}

/**
 * The guide as HTML, in this site's words.
 *
 * Returns '' when the generated content is missing, which only happens if the
 * build was assembled without running bin/make-guide.js — the validator fails
 * on that, so it should never reach a user.
 */
function html(): string {
	$file = \ProducerKit\PLUGIN_DIR . '/includes/guide-content.php';

	if ( ! file_exists( $file ) ) {
		return '';
	}

	$content = require $file;
	$html    = (string) ( $content['html'] ?? '' );

	if ( '' === $html ) {
		return '';
	}

	$tokens  = tokens();
	$escaped = [];

	foreach ( $tokens as $key => $value ) {
		$escaped[ '{{' . $key . '}}' ] = esc_html( (string) $value );
	}

	return strtr( $html, $escaped );
}
