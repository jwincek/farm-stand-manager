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

		get footerText() {
			const items = state.allItems;
			let count = 0;
			for ( let i = 0; i < items.length; i++ ) {
				const statusMatch =
					! state.anyStatusActive ||
					state.activeStatuses[ items[ i ].status ] === true;
				const typeMatch =
					! state.activeType || items[ i ].type === state.activeType;
				if ( statusMatch && typeMatch ) {
					count++;
				}
			}

			if ( count === state.totalItems ) {
				return `Showing ${ count } items`;
			}
			return `Showing ${ count } of ${ state.totalItems } items`;
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
	},
} );
