<?php
/**
 * The "Producer name" field on a user's profile screen.
 *
 * Sits beside the producer-profile chooser this module's sibling adds, and is
 * labelled by that same profile — a baker is asked for a bakery name, a
 * musician for an artist or band name.
 *
 * Left blank, attribution falls back to the display name, so this is a
 * refinement rather than a requirement.
 */

declare(strict_types=1);

namespace ProducerKit\Core\Producers\Admin;

use ProducerKit\Core\Producers;

defined( 'ABSPATH' ) || exit;

add_action( 'show_user_profile', __NAMESPACE__ . '\\render_field' );
add_action( 'edit_user_profile', __NAMESPACE__ . '\\render_field' );
add_action( 'personal_options_update', __NAMESPACE__ . '\\save_field' );
add_action( 'edit_user_profile_update', __NAMESPACE__ . '\\save_field' );

/**
 * Render the field.
 */
function render_field( \WP_User $user ): void {
	$words   = Producers\words_for_user( (int) $user->ID );
	$current = (string) get_user_meta( (int) $user->ID, Producers\USER_META, true );
	?>
	<h2><?php esc_html_e( 'ProducerKit', 'producerkit' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th>
				<label for="pkit_producer_name"><?php echo esc_html( $words['name_label'] ); ?></label>
			</th>
			<td>
				<input
					type="text"
					name="pkit_producer_name"
					id="pkit_producer_name"
					value="<?php echo esc_attr( $current ); ?>"
					class="regular-text"
				/>
				<p class="description">
					<?php
					printf(
						/* translators: %s: the user's display name, used when the field is left empty. */
						esc_html__( 'Shown on the products and events you publish, when more than one producer shares this site. Leave blank to use %s.', 'producerkit' ),
						'<strong>' . esc_html( $user->display_name ) . '</strong>'
					);
					?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Save it.
 *
 * A user may always edit their own; editing somebody else's needs the same
 * capability WordPress requires to edit that user at all.
 */
function save_field( int $user_id ): void {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	// The profile screen's own nonce. Checked rather than assumed, because
	// these hooks also fire for programmatic updates.
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id ) ) {
		return;
	}

	if ( ! isset( $_POST['pkit_producer_name'] ) ) {
		return;
	}

	$name = sanitize_text_field( wp_unslash( $_POST['pkit_producer_name'] ) );

	if ( '' === $name ) {
		delete_user_meta( $user_id, Producers\USER_META );
		return;
	}

	update_user_meta( $user_id, Producers\USER_META, $name );
}
