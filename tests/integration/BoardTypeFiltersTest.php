<?php
/**
 * The board's product-type filter row.
 *
 * It used to be built from get_terms( hide_empty => true ), which means "has
 * a post assigned" and not "is on this board" — so the board offered filters
 * that emptied it when clicked, and counted posts rather than items. The
 * trait filter row beside it has always been derived from the items shown;
 * these two now agree about what they mean.
 */

declare(strict_types=1);

use function ProducerKit\Core\Availability\upsert;

final class BoardTypeFiltersTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		\ProducerKit\Core\Post_Types\register();
		\ProducerKit\Core\Taxonomies\register();
		\ProducerKit\Core\Meta_Fields\register();
	}

	private function product( string $title, string $type ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'pkit_product',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);

		wp_set_object_terms( $id, [ $type ], 'pkit_product_type' );

		return $id;
	}

	private function stock( int $product_id, string $status ): void {
		upsert(
			[
				'product_id'     => $product_id,
				'location_id'    => 0,
				'status'         => $status,
				'effective_date' => current_time( 'Y-m-d' ),
			]
		);
	}

	/**
	 * @return array<int, array{slug: string, label: string, count: int}>
	 */
	private function filters(): array {
		$response = rest_do_request( new WP_REST_Request( 'GET', '/producerkit/v1/board' ) );

		return (array) ( $response->get_data()['filter_types'] ?? [] );
	}

	public function test_a_type_with_nothing_on_the_board_is_not_offered(): void {
		// The bug: the product exists and carries the term, so the term is
		// not empty — but with no availability row it never reaches the board.
		$this->product( 'Nucleus Colony', 'Nucleus Colony' );

		$shown = $this->product( 'Wildflower Honey', 'Honey' );
		$this->stock( $shown, 'available' );

		$labels = wp_list_pluck( $this->filters(), 'label' );

		$this->assertContains( 'Honey', $labels );
		$this->assertNotContains( 'Nucleus Colony', $labels, 'Clicking it would empty the board.' );
	}

	public function test_the_count_is_items_on_the_board_not_posts(): void {
		// Four products of a type, one of them in stock, used to read "4".
		foreach ( [ 'Wildflower', 'Clover', 'Buckwheat', 'Goldenrod' ] as $name ) {
			$this->product( $name . ' Honey', 'Honey' );
		}

		$this->stock( $this->product( 'Comb Honey', 'Honey' ), 'available' );

		$honey = null;
		foreach ( $this->filters() as $filter ) {
			if ( 'Honey' === $filter['label'] ) {
				$honey = $filter;
			}
		}

		$this->assertNotNull( $honey );
		$this->assertSame( 1, $honey['count'] );
	}

	public function test_a_sold_out_item_still_puts_its_type_on_the_list(): void {
		// Deliberate, and worth stating. The endpoint returns sold-out rows —
		// the "Show:" toggles hide them in the browser rather than the query
		// omitting them — so a sold-out item genuinely is on the board and
		// its type genuinely has something to filter to. Turning SOLD OUT on
		// reveals it.
		//
		// The residual wart is client-side: with SOLD OUT off, choosing this
		// type shows nothing. That is two filters interacting, and the fix
		// belongs in the block, which would have to recompute the type row
		// from the currently visible items.
		$this->stock( $this->product( 'Lip Balm', 'Beeswax' ), 'sold_out' );
		$this->stock( $this->product( 'Wildflower Honey', 'Honey' ), 'available' );

		$this->assertSame( [ 'Beeswax', 'Honey' ], wp_list_pluck( $this->filters(), 'label' ) );
	}

	public function test_filters_are_ordered_by_the_word_a_person_reads(): void {
		foreach ( [
			'Wildflower Honey' => 'Honey',
			'Beeswax Block'    => 'Beeswax',
			'Comb Honey'       => 'Comb Honey',
		] as $name => $type ) {
			$this->stock( $this->product( $name, $type ), 'available' );
		}

		$this->assertSame(
			[ 'Beeswax', 'Comb Honey', 'Honey' ],
			wp_list_pluck( $this->filters(), 'label' )
		);
	}

	public function test_an_empty_board_offers_no_filters(): void {
		$this->assertSame( [], $this->filters() );
	}
}
