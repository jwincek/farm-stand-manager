/**
 * Availability Board — Interactivity API view module.
 *
 * CRITICAL: Do NOT declare default values for state properties that are
 * initialized server-side via wp_interactivity_state(). The server values
 * are injected into the store BEFORE this module runs. If we declare
 * state: { activeStatuses: {} }, it OVERWRITES the server values.
 *
 * Only declare computed getters here. The server provides:
 *   - state.activeStatuses  (object map)
 *   - state.allStatuses     (array of status keys)
 *   - state.activeType      (string)
 *   - state.totalItems      (number)
 *
 * Registers into the shared producerkit store; see ../store.js.
 */

import { store, getContext } from '@wordpress/interactivity';
import { NAMESPACE } from '../store.js';

/*
 * Script modules cannot import @wordpress/i18n — core registers only
 * @wordpress/interactivity and @wordpress/block-library as modules. WP prints
 * this module's translations by calling wp.i18n.setLocaleData() on the global,
 * so that is where these come from.
 *
 * Bound to names rather than wrapped in helpers on purpose: `wp i18n make-pot`
 * and the i18n lint rule both read literal __( '…', 'producerkit' ) calls, and
 * a wrapper taking a variable hides every string from the extractor.
 *
 * The fallbacks keep the board working if wp-i18n somehow did not load. They
 * return the English source strings, which is exactly what shipped before.
 */
const wpI18n =
	( typeof window !== 'undefined' && window.wp && window.wp.i18n ) || {};

const __ = wpI18n.__ || ( ( text ) => text );
const _n =
	wpI18n._n ||
	( ( single, plural, number ) => ( number === 1 ? single : plural ) );
const sprintf =
	wpI18n.sprintf ||
	( ( format, ...args ) => {
		let next = 0;
		return format
			.replace( /%(\d)\$d/g, ( _match, position ) =>
				String( args[ position - 1 ] )
			)
			.replace( /%d/g, () => String( args[ next++ ] ) );
	} );

/**
 * Does one item survive the current filters?
 *
 * The same three rules isCurrentItemHidden() applies per element, in one
 * place, so the count in the sentence and what is actually on screen cannot
 * disagree.
 *
 * @param {Object} item One entry from state.allItems.
 * @return {boolean} Whether it is showing.
 */
function itemMatches( item ) {
	if (
		state.anyStatusActive &&
		state.activeStatuses[ item.status ] !== true
	) {
		return false;
	}

	if ( state.activeType && item.type !== state.activeType ) {
		return false;
	}

	const traits = state.activeTraits || {};
	for ( const taxonomy in traits ) {
		const wanted = traits[ taxonomy ];
		if ( ! wanted ) {
			continue;
		}
		const on = ( item.traits && item.traits[ taxonomy ] ) || [];
		if ( ! on.includes( wanted ) ) {
			return false;
		}
	}

	return true;
}

const { state } = store( NAMESPACE, {
	state: {
		// NO default values here — they come from wp_interactivity_state().

		/**
		 * Whether any status filter is currently active.
		 *
		 * Iterates ALL status keys so the Interactivity API proxy
		 * registers dependencies on every property — required for
		 * reactive updates when any status toggles.
		 */
		get anyStatusActive() {
			let active = false;
			const keys = state.allStatuses;
			for ( let i = 0; i < keys.length; i++ ) {
				if ( state.activeStatuses[ keys[ i ] ] === true ) {
					active = true;
				}
			}
			return active;
		},

		get isCurrentStatusActive() {
			const ctx = getContext();
			return state.activeStatuses[ ctx.filterStatus ] === true;
		},

		get isProductTypeActive() {
			const ctx = getContext();
			return state.activeType === ctx.filterType;
		},

		/**
		 * Whether this trade-field button is the selected one for its field.
		 *
		 * Each field filters independently — a potter narrowing to Stoneware
		 * has not also chosen a glaze — so the selection is a map keyed by
		 * taxonomy rather than one active value.
		 */
		get isCurrentTraitActive() {
			const ctx = getContext();
			const selected = state.activeTraits[ ctx.filterTaxonomy ] || '';
			return selected === ctx.filterTraitSlug;
		},

		get isCurrentItemHidden() {
			const ctx = getContext();

			if (
				state.anyStatusActive &&
				state.activeStatuses[ ctx.itemStatus ] !== true
			) {
				return true;
			}

			if ( state.activeType && ctx.itemType !== state.activeType ) {
				return true;
			}

			// Trade fields narrow together: choosing Stoneware AND Ash Glaze
			// shows only what is both, which is what a person picking through
			// a shelf means by it.
			const traits = state.activeTraits;
			for ( const taxonomy in traits ) {
				const wanted = traits[ taxonomy ];
				if ( ! wanted ) {
					continue;
				}

				const on =
					( ctx.itemTraits && ctx.itemTraits[ taxonomy ] ) || [];
				if ( ! on.includes( wanted ) ) {
					return true;
				}
			}

			return false;
		},

		get isCurrentGroupHidden() {
			const ctx = getContext();

			if ( state.activeType && state.activeType !== ctx.groupSlug ) {
				return true;
			}

			if ( ! state.anyStatusActive ) {
				return false;
			}

			const statuses = ctx.itemStatuses || [];
			for ( let i = 0; i < statuses.length; i++ ) {
				if ( state.activeStatuses[ statuses[ i ] ] === true ) {
					return false;
				}
			}

			return true;
		},

		get currentGroupCount() {
			const ctx = getContext();

			if ( state.activeType && state.activeType !== ctx.groupSlug ) {
				return '0';
			}

			if ( ! state.anyStatusActive ) {
				return String( ctx.itemCount || 0 );
			}

			const statuses = ctx.itemStatuses || [];
			let count = 0;
			for ( let i = 0; i < statuses.length; i++ ) {
				if ( state.activeStatuses[ statuses[ i ] ] === true ) {
					count++;
				}
			}
			return String( count );
		},

		/**
		 * Would choosing this type show anything, given the statuses on?
		 *
		 * The type row and the status row filter independently, and the type
		 * buttons are rendered once from the server. So a type whose only item
		 * is sold out gets a button — correctly, the item is on the board —
		 * and with SOLD OUT off, pressing it empties the board.
		 *
		 * Only the status filter is applied here. Narrowing the type row by
		 * the active *type* would leave a single button and no way back.
		 *
		 * The button is greyed rather than disabled. Disabling takes it out of
		 * the tab order, and it stays a legitimate thing to press — the board
		 * is allowed to be empty. Sighted readers get a shortcut; everyone
		 * gets the real answer from the footer, which already says how many of
		 * how many are showing.
		 */
		get isCurrentTypeEmpty() {
			const ctx = getContext();

			// "All" always has the board behind it.
			if ( ! ctx.filterType ) {
				return false;
			}

			// The one already chosen is never greyed: a selected button that
			// looks unavailable reads as a fault rather than as information.
			if ( state.activeType === ctx.filterType ) {
				return false;
			}

			// No status narrowing means everything on the board is showing.
			if ( ! state.anyStatusActive ) {
				return false;
			}

			const items = state.allItems;

			for ( let i = 0; i < items.length; i++ ) {
				if (
					items[ i ].type === ctx.filterType &&
					state.activeStatuses[ items[ i ].status ] === true
				) {
					return false;
				}
			}

			return true;
		},

		/**
		 * How many items the current filters leave showing.
		 */
		get visibleCount() {
			const items = state.allItems;
			let count = 0;
			for ( let i = 0; i < items.length; i++ ) {
				if ( itemMatches( items[ i ] ) ) {
					count++;
				}
			}
			return count;
		},

		/**
		 * The sentence above the board.
		 *
		 * Translated through the global wp.i18n rather than a template
		 * literal. Script modules do not get their strings from
		 * wp_set_script_translations() — that only covers classic scripts —
		 * which is why this line used to render correctly on load and revert
		 * to English the moment anyone touched a filter. WP 7.0's
		 * wp_set_script_module_translations() loads the module's .json and
		 * calls wp.i18n.setLocaleData(), so _n() here has the locale's real
		 * plural rules rather than an English guess at them.
		 */
		get summaryText() {
			const count = state.visibleCount;

			if ( count === state.totalItems ) {
				return sprintf(
					/* translators: %d: number of items shown on the availability board. */
					_n(
						'Showing %d item',
						'Showing %d items',
						count,
						'producerkit'
					),
					count
				);
			}

			return sprintf(
				/* translators: 1: items currently showing. 2: items on the board in total. */
				_n(
					'Showing %1$d of %2$d item',
					'Showing %1$d of %2$d items',
					state.totalItems,
					'producerkit'
				),
				count,
				state.totalItems
			);
		},

		get filterToggleLabel() {
			return state.filtersOpen
				? __( 'Hide filters', 'producerkit' )
				: __( 'Filter', 'producerkit' );
		},

		/**
		 * Whether anything is narrowed. Drives the "everything on the board"
		 * note and whether Clear everything can be pressed.
		 */
		get isFiltered() {
			if ( state.activeType ) {
				return true;
			}

			// Against the producer's default, not against all-on: a board
			// that opens hiding sold-out items has not been filtered by the
			// person looking at it.
			const keys = state.allStatuses;
			for ( let i = 0; i < keys.length; i++ ) {
				const wanted = state.defaultStatuses[ keys[ i ] ] === true;
				if ( state.activeStatuses[ keys[ i ] ] !== wanted ) {
					return true;
				}
			}

			const traits = state.activeTraits || {};
			return Object.keys( traits ).some( ( k ) => traits[ k ] );
		},

		/**
		 * Is literally nothing hidden?
		 *
		 * Distinct from isFiltered(), which asks whether the visitor has moved
		 * away from the producer's default. A board configured to hide sold-out
		 * items is unfiltered and is not showing everything, and the sentence
		 * has to be able to say so without contradicting its own pills.
		 */
		get isShowingEverything() {
			if ( state.activeType ) {
				return false;
			}

			const keys = state.allStatuses;
			for ( let i = 0; i < keys.length; i++ ) {
				if ( state.activeStatuses[ keys[ i ] ] !== true ) {
					return false;
				}
			}

			const traits = state.activeTraits || {};
			return ! Object.keys( traits ).some( ( k ) => traits[ k ] );
		},

		/**
		 * Items in this status, respecting the other filters — what a checkbox
		 * is actually worth right now, shown on the checkbox itself.
		 */
		get currentStatusCount() {
			const ctx = getContext();
			const items = state.allItems;
			let count = 0;
			for ( let i = 0; i < items.length; i++ ) {
				if (
					items[ i ].status === ctx.filterStatus &&
					( ! state.activeType ||
						items[ i ].type === state.activeType )
				) {
					count++;
				}
			}
			return String( count );
		},

		/**
		 * Only the selected radio is tabbable; arrow keys move between them.
		 * That is the radiogroup pattern, and it is why these are radios now
		 * rather than buttons wearing aria-pressed.
		 */
		get radioTabIndex() {
			return state.isProductTypeActive || state.isCurrentTraitActive
				? 0
				: -1;
		},
	},

	actions: {
		toggleStatus() {
			const ctx = getContext();
			const status = ctx.filterStatus;
			if ( ! status ) {
				return;
			}
			state.activeStatuses[ status ] = ! state.activeStatuses[ status ];
		},

		/**
		 * Select a term within one trade field, or clear it by re-clicking.
		 */
		setTraitFilter() {
			const ctx = getContext();
			const taxonomy = ctx.filterTaxonomy;
			const slug = ctx.filterTraitSlug;

			if ( ! taxonomy ) {
				return;
			}

			// A fresh object rather than a mutation, so the proxy sees it.
			state.activeTraits = {
				...state.activeTraits,
				[ taxonomy ]:
					state.activeTraits[ taxonomy ] === slug ? '' : slug,
			};
		},

		setProductTypeFilter() {
			const ctx = getContext();
			state.activeType = ctx.filterType;
		},

		toggleFilters() {
			state.filtersOpen = ! state.filtersOpen;
		},

		/**
		 * Put a status back from its pill in the sentence.
		 *
		 * Deliberately not toggleStatus: a pill only ever exists for a status
		 * that is currently hidden, and its X should never be able to hide
		 * something instead.
		 */
		restoreStatus() {
			const ctx = getContext();
			if ( ctx.filterStatus ) {
				state.activeStatuses[ ctx.filterStatus ] = true;
			}
		},

		clearProductType() {
			state.activeType = '';
		},

		clearTrait() {
			const ctx = getContext();
			if ( ! ctx.filterTaxonomy ) {
				return;
			}
			state.activeTraits = {
				...state.activeTraits,
				[ ctx.filterTaxonomy ]: '',
			};
		},

		clearAllFilters() {
			// Back to what the producer configured, not to everything on. The
			// board's defaultStatusFilter is a decision about what customers
			// should see, and "clear" should not overrule it.
			const keys = state.allStatuses;
			for ( let i = 0; i < keys.length; i++ ) {
				state.activeStatuses[ keys[ i ] ] =
					state.defaultStatuses[ keys[ i ] ] === true;
			}

			state.activeType = '';

			const cleared = {};
			Object.keys( state.activeTraits || {} ).forEach( ( k ) => {
				cleared[ k ] = '';
			} );
			state.activeTraits = cleared;
		},

		/**
		 * Arrow keys move the selection within a radiogroup, which is what a
		 * radiogroup is expected to do and what a row of aria-pressed buttons
		 * never did.
		 *
		 * @param {KeyboardEvent} event The keydown.
		 */
		moveWithinRadioGroup( event ) {
			const keys = [ 'ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp' ];
			if ( ! keys.includes( event.key ) ) {
				return;
			}

			const group = event.target.closest( '[role="radiogroup"]' );
			if ( ! group ) {
				return;
			}

			const radios = Array.prototype.filter.call(
				group.querySelectorAll( '[role="radio"]' ),
				( el ) => ! el.disabled
			);
			const here = radios.indexOf( event.target );
			if ( here === -1 ) {
				return;
			}

			event.preventDefault();

			const forward =
				event.key === 'ArrowRight' || event.key === 'ArrowDown';
			const next =
				radios[
					( here + ( forward ? 1 : -1 ) + radios.length ) %
						radios.length
				];

			next.focus();
			next.click();
		},
	},
} );
