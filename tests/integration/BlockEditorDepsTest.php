<?php
/**
 * Blocks have to register in the editor on a site with nothing else installed.
 *
 * Each block's index.js reads window.wp.blocks directly, and block.json's
 * editorScript is registered by core from an index.asset.php beside it. With no
 * such file the dependency list is empty, nothing guarantees wp-blocks has
 * loaded, and on a clean WordPress every registerBlockType() throws on
 * undefined — so none of the eleven blocks appear in the editor at all.
 *
 * It stayed hidden through 2.7.0 because every test and screenshot ran on a
 * development site with Gutenberg and WooCommerce, which load wp-blocks first.
 *
 * bin/validate-config.php checks the files. These check the thing that actually
 * broke: what core ends up registering.
 */

declare(strict_types=1);

final class BlockEditorDepsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// The blocks are registered on init, which has already run for the
		// suite; re-register so the script handles exist in this request.
		foreach ( glob( dirname( __DIR__, 2 ) . '/blocks/*/block.json' ) ?: [] as $block_json ) {
			$name = 'producerkit/' . basename( dirname( $block_json ) );
			if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				register_block_type( dirname( $block_json ) );
			}
		}
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function blocks(): array {
		$cases = [];

		foreach ( glob( dirname( __DIR__, 2 ) . '/blocks/*/block.json' ) ?: [] as $block_json ) {
			$slug           = basename( dirname( $block_json ) );
			$cases[ $slug ] = [ 'producerkit/' . $slug ];
		}

		return $cases;
	}

	/**
	 * The regression: an editor script with no dependencies.
	 *
	 * @dataProvider blocks
	 */
	public function test_the_editor_script_depends_on_wp_blocks( string $block_name ): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		$this->assertInstanceOf( WP_Block_Type::class, $type, "{$block_name} is not registered." );

		$handles = (array) ( $type->editor_script_handles ?? [] );

		$this->assertNotEmpty( $handles, "{$block_name} registered no editor script." );

		foreach ( $handles as $handle ) {
			$script = wp_scripts()->query( $handle, 'registered' );

			$this->assertNotFalse( $script, "{$handle} is not registered with wp_scripts." );

			$this->assertContains(
				'wp-blocks',
				$script->deps,
				"{$block_name}'s editor script does not depend on wp-blocks, so it can run before "
					. 'window.wp.blocks exists and the block will not register at all.'
			);

			$this->assertContains( 'wp-element', $script->deps, "{$block_name} needs wp-element." );
		}
	}

	/**
	 * Core only sets up a block's script translations when wp-i18n is declared,
	 * so an empty dependency list silently costs those too — on every site,
	 * not just a clean one.
	 *
	 * @dataProvider blocks
	 */
	public function test_the_editor_script_can_be_translated( string $block_name ): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		foreach ( (array) ( $type->editor_script_handles ?? [] ) as $handle ) {
			$script = wp_scripts()->query( $handle, 'registered' );

			$this->assertContains(
				'wp-i18n',
				$script->deps,
				"{$block_name} declares a textdomain but not wp-i18n, so core never registers its "
					. 'script translations and every __() in it stays English.'
			);

			$this->assertSame(
				'producerkit',
				$script->textdomain ?? '',
				"{$block_name}'s editor script has no translation domain attached."
			);
		}
	}
}
