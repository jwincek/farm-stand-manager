<?php
/**
 * The CSV column rename, and the compatibility it has to keep.
 *
 * `growing_notes` was an export column header until 2.6.0, which means it is
 * sitting in spreadsheets on people's machines. The migration can reach a
 * database; it cannot reach those. So import accepts both headers, and this
 * test is what stops the old one being tidied away later.
 */

declare(strict_types=1);

namespace ProducerKit\Tests\Integration;

use WP_UnitTestCase;

use function ProducerKit\Core\ProductIO\import_rows;

class CsvColumnRenameTest extends WP_UnitTestCase {

	/**
	 * Turn a CSV string into the row shape import_rows() expects.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function rows_from( string $csv ): array {
		$lines  = array_map( 'str_getcsv', explode( "\n", trim( $csv ) ) );
		$header = array_shift( $lines );
		$rows   = [];

		foreach ( $lines as $line ) {
			$rows[] = array_combine( $header, $line );
		}

		return $rows;
	}

	/**
	 * A spreadsheet exported by 2.5.0 still imports.
	 */
	public function test_old_growing_notes_header_still_imports(): void {
		$rows = $this->rows_from(
			"title,status,price,unit,growing_notes\n" .
			"Old Header Row,publish,4.00,bunch,\"Heirloom variety, cold-hardy.\"\n"
		);

		$result = import_rows( $rows, false );

		$this->assertSame( 1, $result['created'] );
		$this->assertSame( [], $result['errors'] );

		$post = get_page_by_path( 'old-header-row', OBJECT, 'pkit_product' );
		$this->assertNotNull( $post );
		$this->assertSame(
			'Heirloom variety, cold-hardy.',
			get_post_meta( $post->ID, '_pkit_production_notes', true )
		);
	}

	/**
	 * The current header does what it says.
	 */
	public function test_new_production_notes_header_imports(): void {
		$rows = $this->rows_from(
			"title,status,price,unit,production_notes\n" .
			"New Header Row,publish,4.00,bunch,\"Stone-ground, slow and cool.\"\n"
		);

		$result = import_rows( $rows, false );

		$this->assertSame( 1, $result['created'] );

		$post = get_page_by_path( 'new-header-row', OBJECT, 'pkit_product' );
		$this->assertNotNull( $post );
		$this->assertSame(
			'Stone-ground, slow and cool.',
			get_post_meta( $post->ID, '_pkit_production_notes', true )
		);
	}

	/**
	 * When a file somehow carries both, the current name wins.
	 */
	public function test_new_header_takes_precedence_over_the_old_one(): void {
		$rows = $this->rows_from(
			"title,status,production_notes,growing_notes\n" .
			"Both Headers Row,publish,current,legacy\n"
		);

		import_rows( $rows, false );

		$post = get_page_by_path( 'both-headers-row', OBJECT, 'pkit_product' );
		$this->assertNotNull( $post );
		$this->assertSame( 'current', get_post_meta( $post->ID, '_pkit_production_notes', true ) );
	}

	/**
	 * Export writes the current header, and no longer the old one.
	 */
	public function test_export_header_uses_the_current_name(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/modules/core/includes/product-import-export.php'
		);

		// The export header list, as opposed to the import fallback which must
		// keep mentioning the old name.
		$this->assertStringContainsString( "'production_notes',", $source );
		$this->assertStringNotContainsString( "'growing_notes',", $source );
	}
}
