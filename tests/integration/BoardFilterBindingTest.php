<?php
/**
 * The board's type buttons say when they lead nowhere.
 *
 * The type row and the status row filter independently, and the type buttons
 * are rendered once from the server payload. So a type whose only item is
 * sold out gets a button — correctly, the item is on the board — and with
 * SOLD OUT off, pressing it empties the board.
 *
 * The behaviour itself lives in the Interactivity store and is verified in a
 * browser; there is no JavaScript suite here. What these assert is the wiring
 * that would silently stop working: the directive on the markup and the
 * getter it names.
 */

declare(strict_types=1);

final class BoardFilterBindingTest extends WP_UnitTestCase {

	private function render(): string {
		return (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/blocks/availability-board/render.php'
		);
	}

	private function store(): string {
		return (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/assets/js/interactivity/availability-board.js'
		);
	}

	public function test_the_type_buttons_bind_the_empty_class(): void {
		$this->assertStringContainsString(
			'data-wp-class--pkit-avail-board__radio--empty="state.isCurrentTypeEmpty"',
			$this->render()
		);
	}

	public function test_the_getter_the_directive_names_exists(): void {
		// A directive pointing at a getter that is not there fails silently:
		// the class is simply never applied and nothing reports it.
		$this->assertStringContainsString( 'get isCurrentTypeEmpty()', $this->store() );
	}

	public function test_the_faded_class_is_styled(): void {
		$this->assertStringContainsString(
			'.pkit-avail-board__radio--empty',
			(string) file_get_contents( dirname( __DIR__, 2 ) . '/blocks/availability-board/style.css' )
		);
	}

	public function test_the_button_is_never_disabled(): void {
		// Faded, not disabled. Disabling takes it out of the tab order, and
		// pressing it stays legitimate — the board is allowed to be empty.
		$this->assertStringNotContainsString(
			'data-wp-bind--disabled="state.isCurrentTypeEmpty"',
			$this->render()
		);
	}

	public function test_the_all_button_has_no_type_slug(): void {
		// isCurrentTypeEmpty() short-circuits on an empty filterType, which is
		// how "All" is never faded. If the All button ever gained a slug that
		// reasoning would quietly stop holding.
		$this->assertStringContainsString( "'filterType' => ''", $this->render() );
	}
}
