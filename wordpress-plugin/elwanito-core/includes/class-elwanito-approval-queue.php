<?php
/**
 * Owner review queue: every AI-generated lesson lands here as a draft
 * (unless auto-publish is on) so the owner can approve or reject it from
 * any phone browser at /wp-admin/admin.php?page=elwanito-review.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elwanito_Approval_Queue {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_elwanito_approve', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_elwanito_reject', array( $this, 'handle_reject' ) );
	}

	public function add_menu() {
		add_submenu_page(
			'elwanito-settings',
			'Review Queue',
			'Review Queue',
			'manage_options',
			'elwanito-review',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pending = get_posts(
			array(
				'post_type'      => 'elwanito_lesson',
				'post_status'    => 'draft',
				'posts_per_page' => 20,
				'meta_key'       => '_elwanito_status',
				'meta_value'     => 'pending_review',
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="wrap" style="max-width:640px;">
			<h1>Review Queue<?php echo $pending ? ' (' . count( $pending ) . ')' : ''; ?></h1>

			<?php if ( empty( $pending ) ) : ?>
				<p>Nothing waiting for review right now.</p>
			<?php endif; ?>

			<?php foreach ( $pending as $post ) : ?>
				<?php
				$module  = get_post_meta( $post->ID, '_elwanito_module', true );
				$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
				?>
				<div style="border:1px solid #ccd0d4;border-radius:6px;padding:16px;margin-bottom:16px;background:#fff;">
					<div style="font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.03em;">
						<?php echo esc_html( $module ); ?>
					</div>
					<h2 style="margin:4px 0 8px;font-size:18px;">
						<?php echo esc_html( $post->post_title ); ?>
					</h2>
					<p style="color:#444;"><?php echo esc_html( $excerpt ); ?></p>
					<p>
						<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">Edit before deciding →</a>
					</p>
					<div style="display:flex;gap:10px;flex-wrap:wrap;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'elwanito_approve_' . $post->ID ); ?>
							<input type="hidden" name="action" value="elwanito_approve">
							<input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
							<button type="submit" class="button button-primary">Approve &amp; Publish</button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'elwanito_reject_' . $post->ID ); ?>
							<input type="hidden" name="action" value="elwanito_reject">
							<input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
							<button type="submit" class="button">Reject</button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function handle_approve() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $post_id ) {
			wp_die( 'Invalid request.' );
		}
		check_admin_referer( 'elwanito_approve_' . $post_id );

		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		update_post_meta( $post_id, '_elwanito_status', 'approved' );

		wp_safe_redirect( admin_url( 'admin.php?page=elwanito-review' ) );
		exit;
	}

	public function handle_reject() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $post_id ) {
			wp_die( 'Invalid request.' );
		}
		check_admin_referer( 'elwanito_reject_' . $post_id );

		update_post_meta( $post_id, '_elwanito_status', 'rejected' );
		wp_trash_post( $post_id );

		wp_safe_redirect( admin_url( 'admin.php?page=elwanito-review' ) );
		exit;
	}
}
