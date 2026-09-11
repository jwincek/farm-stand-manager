/**
 * Build the in-dashboard guide from the one source both renderings share.
 *
 * docs/getting-started.tpl.md is the source of truth. It carries {{tokens}}
 * wherever a word belongs to a trade rather than to the plugin, and this
 * produces two things from it:
 *
 *   GETTING-STARTED.md        the repo's copy, tokens resolved to a farm's
 *                             words, because that is the plugin's default and
 *                             a reader on GitHub has no site to ask.
 *   includes/guide-content.php  the shipped copy, converted to HTML with the
 *                             tokens left standing so the dashboard can
 *                             resolve them against whatever trade the site
 *                             actually chose.
 *
 * Markdown is converted here rather than in PHP so the plugin ships no parser
 * and CI needs no Markdown tooling: bin/validate-config.php compares a hash of
 * the template instead of re-running this.
 *
 * Usage:
 *   node bin/make-guide.js            write both outputs
 *   node bin/make-guide.js --check    exit 1 if either is stale
 */

const fs = require( 'fs' );
const path = require( 'path' );
const crypto = require( 'crypto' );
const MarkdownIt = require( 'markdown-it' );

const root = path.dirname( __dirname );
const tplPath = path.join( root, 'docs', 'getting-started.tpl.md' );
const mdPath = path.join( root, 'GETTING-STARTED.md' );
const phpPath = path.join( root, 'includes', 'guide-content.php' );
const check = process.argv.includes( '--check' );

/*
 * The farm's words, used for the repo copy. These mirror what
 * ProducerKit\Guide\tokens() resolves at runtime for a site with the farm
 * profile active — the test suite asserts the two agree, so this cannot drift
 * into telling GitHub readers something the plugin would not say.
 */
const FARM = {
	trade: 'Farm',
	products: 'Products',
	product: 'Product',
	events: 'Events',
	event: 'Event',
	place: 'Farm Stand',
	place_lower: 'farm stand',
	requests: 'Special Orders',
	request_action: 'Request a special order',
	notes_field: 'Growing / Baking Notes',
	source_field: 'Farm / Origin Name',
	product_types_label: 'Product Types',
	event_types_label: 'Event Types',
	product_types: 'Produce, Bread, Baked Good, Pantry Good, Seedling',
	event_types:
		'Pizza Night, Potluck, Farm Dinner, Workshop, Farm Tour, Seed Exchange, Mini Market',
};

function resolve( text, values ) {
	return text.replace( /\{\{([a-z_]+)\}\}/g, ( whole, key ) => {
		if ( ! ( key in values ) ) {
			throw new Error( `Unknown token {{${ key }}} in the guide template.` );
		}
		return values[ key ];
	} );
}

const template = fs.readFileSync( tplPath, 'utf8' );
const hash = crypto.createHash( 'sha256' ).update( template ).digest( 'hex' );

/* ── The repo copy ─────────────────────────────────────────────── */

const markdown =
	'<!-- Generated from docs/getting-started.tpl.md by bin/make-guide.js — do not edit by hand. -->\n\n' +
	resolve( template, FARM );

/* ── The shipped copy ──────────────────────────────────────────── */

const md = new MarkdownIt( { html: false, linkify: false, typographer: false } );
const html = md.render( template ); // tokens survive: they are plain text.

const php =
	"<?php\n" +
	"/**\n" +
	" * Generated from docs/getting-started.tpl.md by bin/make-guide.js.\n" +
	" * Do not edit by hand — edit the template and re-run the generator.\n" +
	" *\n" +
	" * The {{tokens}} are left unresolved on purpose. ProducerKit\\Guide\\render()\n" +
	" * substitutes them against the trade the site actually chose, which is the\n" +
	" * whole reason this is generated rather than written as a static page.\n" +
	" *\n" +
	" * @package ProducerKit\n" +
	" */\n\n" +
	"declare(strict_types=1);\n\n" +
	"defined( 'ABSPATH' ) || exit;\n\n" +
	"return [\n" +
	"\t'source_hash' => '" + hash + "',\n" +
	"\t'html'        => <<<'PKITGUIDE'\n" +
	html.trimEnd() + "\n" +
	"PKITGUIDE,\n" +
	"];\n";

if ( check ) {
	const stale = [];
	if ( ! fs.existsSync( mdPath ) || fs.readFileSync( mdPath, 'utf8' ) !== markdown ) {
		stale.push( 'GETTING-STARTED.md' );
	}
	if ( ! fs.existsSync( phpPath ) || fs.readFileSync( phpPath, 'utf8' ) !== php ) {
		stale.push( 'includes/guide-content.php' );
	}
	if ( stale.length ) {
		process.stderr.write(
			'The guide is out of date with docs/getting-started.tpl.md:\n' +
				stale.map( ( s ) => `  - ${ s }\n` ).join( '' ) +
				'Run: npm run make:guide\n'
		);
		process.exit( 1 );
	}
	process.stdout.write( 'Guide is up to date.\n' );
	process.exit( 0 );
}

fs.writeFileSync( mdPath, markdown );
fs.writeFileSync( phpPath, php );
process.stdout.write(
	`Wrote GETTING-STARTED.md and includes/guide-content.php from the template.\n`
);
