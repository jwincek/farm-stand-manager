<?php
/**
 * The board's filters say what kind of control they are (#80).
 *
 * Status is an include-set and type is a choose-one, and they used to be the
 * same button carrying the same aria-pressed, two inches apart, doing opposite
 * things — so clicking "Abundant" hid the abundant items. These assert the
 * wiring that makes the two models distinguishable, to a screen reader and to
 * the eye, plus the translated summary that replaced the English-only footer
 * (#79).
 *
 * The behaviour lives in the Interactivity store and is verified in a browser;
 * what breaks silently is a directive naming a getter that no longer exists,
 * so that pairing is what these check.
 */

declare(strict_types=1);

final class BoardFilterSemanticsTest extends WP_UnitTestCase {

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

	private function styles(): string {
		return (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/blocks/availability-board/style.css'
		);
	}

	/* ── The two models are distinguishable ──────────────────── */

	public function test_status_is_a_checkbox_group(): void {
		$render = $this->render();

		$this->assertStringContainsString( 'role="checkbox"', $render );
		$this->assertStringContainsString( 'data-wp-bind--aria-checked="state.isCurrentStatusActive"', $render );
	}

	public function test_type_and_traits_are_radio_groups(): void {
		$render = $this->render();

		$this->assertStringContainsString( 'role="radiogroup"', $render );
		$this->assertStringContainsString( 'role="radio"', $render );
		$this->assertStringContainsString( 'data-wp-bind--aria-checked="state.isProductTypeActive"', $render );
	}

	/**
	 * aria-pressed is for toggle buttons. Using it on a choose-one group told
	 * a screen reader all three rows were the same kind of control, which is
	 * the confusion this issue is about.
	 */
	public function test_no_filter_still_uses_aria_pressed(): void {
		// The attribute, not the word — the file explains in a comment why it
		// is gone, and that sentence is not a regression.
		$this->assertStringNotContainsString( 'aria-pressed="', $this->render() );
		$this->assertStringNotContainsString( 'aria-pressed"', $this->render() );
	}

	/**
	 * Only the selected radio is tabbable, and arrows move between them.
	 */
	public function test_the_radio_groups_use_a_roving_tabindex(): void {
		$this->assertStringContainsString( 'data-wp-bind--tabindex="state.radioTabIndex"', $this->render() );
		$this->assertStringContainsString( 'get radioTabIndex()', $this->store() );

		$this->assertStringContainsString( 'data-wp-on--keydown="actions.moveWithinRadioGroup"', $this->render() );
		$this->assertStringContainsString( 'moveWithinRadioGroup(', $this->store() );
	}

	/* ── State is legible without colour ─────────────────────── */

	/**
	 * WCAG 1.4.1: an excluded status is struck through, not merely faded.
	 */
	public function test_an_excluded_status_is_not_signalled_by_colour_alone(): void {
		$this->assertMatchesRegularExpression(
			'/aria-checked="false"\][^{]*\{[^}]*line-through/s',
			$this->styles(),
			'Excluded statuses need a treatment that survives greyscale.'
		);
	}

	public function test_each_status_carries_its_count(): void {
		$this->assertStringContainsString( 'data-wp-text="state.currentStatusCount"', $this->render() );
		$this->assertStringContainsString( 'get currentStatusCount()', $this->store() );
	}

	/* ── The sentence, and the drawer it fronts ──────────────── */

	public function test_the_summary_sentence_is_a_live_region(): void {
		$this->assertMatchesRegularExpression(
			'/class="pkit-avail-board__state"\s+aria-live="polite"/',
			$this->render()
		);
		$this->assertStringContainsString( 'data-wp-text="state.summaryText"', $this->render() );
		$this->assertStringContainsString( 'get summaryText()', $this->store() );
	}

	public function test_the_drawer_is_wired_to_its_disclosure(): void {
		$render = $this->render();

		$this->assertStringContainsString( 'data-wp-bind--aria-expanded="state.filtersOpen"', $render );
		$this->assertStringContainsString( 'data-wp-bind--hidden="!state.filtersOpen"', $render );
		$this->assertStringContainsString( 'aria-controls=', $render );
		// Pattern, not a literal: phpcbf aligns the => in this array, so the
		// padding shifts whenever a longer key is added.
		$this->assertMatchesRegularExpression(
			"/'filtersOpen'\s*=> false/",
			$render,
			'The drawer should start shut.'
		);
	}

	/**
	 * Each pill removes exactly one constraint; one button puts it all back.
	 */
	public function test_every_constraint_can_be_dismissed(): void {
		$render = $this->render();
		$store  = $this->store();

		foreach ( [ 'restoreStatus', 'clearProductType', 'clearTrait', 'clearAllFilters' ] as $action ) {
			$this->assertStringContainsString( 'actions.' . $action, $render, "{$action} is not wired up." );
			$this->assertStringContainsString( $action . '(', $store, "{$action} is not in the store." );
		}

		$this->assertStringContainsString( 'data-wp-bind--disabled="!state.isFiltered"', $render );
	}

	/**
	 * Clear restores the board's configuration, not all-statuses-on.
	 *
	 * defaultStatusFilter is the producer's decision about what a customer
	 * should see. A first pass had "clear" turning everything on, which
	 * quietly overruled it — press clear on a board set up to hide sold-out
	 * items and you saw more than it was configured to show.
	 */
	public function test_clear_restores_the_configured_default(): void {
		$this->assertMatchesRegularExpression(
			"/'defaultStatuses'\s*=> clone \\\$status_map/",
			$this->render()
		);
		$this->assertMatchesRegularExpression(
			'/state\.activeStatuses\[ keys\[ i \] \] =\s*state\.defaultStatuses\[ keys\[ i \] \] === true;/s',
			$this->store()
		);
	}

	/**
	 * "Everything on the board" and "you have filtered" are different
	 * questions, and a board configured to hide sold-out items answers them
	 * differently. Sharing one getter made the sentence contradict its own
	 * pills.
	 */
	public function test_the_sentence_separates_hidden_from_filtered(): void {
		$store = $this->store();

		$this->assertStringContainsString( 'get isShowingEverything()', $store );
		$this->assertStringContainsString( 'get isFiltered()', $store );
		$this->assertStringContainsString(
			'data-wp-bind--hidden="!state.isShowingEverything"',
			$this->render()
		);
	}

	/* ── #79: the summary is translated on every update ──────── */

	/**
	 * The old footer was server-rendered translated and then overwritten by a
	 * template literal, so it reverted to English the moment anyone filtered.
	 */
	public function test_the_summary_is_built_from_translated_strings(): void {
		$store = $this->store();

		$this->assertStringNotContainsString( '`Showing ${', $store, 'The untranslated template literal is back.' );
		// Matched as a pattern: the formatter is free to wrap the call across
		// lines, and asserting the one-line form would fail on style alone.
		$this->assertMatchesRegularExpression(
			"/_n\(\s*'Showing %d item',\s*'Showing %d items',\s*count,\s*'producerkit'\s*\)/s",
			$store
		);
		$this->assertMatchesRegularExpression(
			"/_n\(\s*'Showing %1\\\$d of %2\\\$d item',/s",
			$store,
			'The filtered form needs translating too.'
		);
	}

	/**
	 * Script modules do not get strings from wp_set_script_translations().
	 */
	public function test_module_translations_are_registered(): void {
		$plugin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/producerkit.php' );

		$this->assertStringContainsString( 'wp_set_script_module_translations(', $plugin );
		$this->assertStringContainsString( 'view_script_module_ids', $plugin );
	}

	/**
	 * The board's own module id has to be the one that gets them.
	 */
	public function test_the_board_module_id_resolves(): void {
		$this->assertSame(
			'producerkit-availability-board-view-script-module',
			generate_block_asset_handle( 'producerkit/availability-board', 'viewScriptModule' )
		);
	}
}
