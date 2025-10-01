<?php
/**
 * Plugin Name: Masquerade in WP
 * Plugin URI: http://castle-creative.com/
 * Description: Adds a link to users.php that allows an administrator to login as that user without knowing the password.
 * Version: 1.0.3
 * Author: JR King
 * Author URI: http://castle-creative.com/
 * License: General Public License version 2
 *
 * Copyright 2012 JR King
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published by
 * the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/.
 */

/**
 * Add masquerade functionality to WordPress admin user list.
 *
 * Allows administrators with the `delete_users` capability to log in
 * as another user via the "Masquerade As User" link in the user table.
 */

add_action( 'admin_init', 'masq_wp_init' );
/**
 * Initialize masquerade hooks on admin init.
 *
 * @return void
 */
function masq_wp_init() {
	if ( is_admin() ) {
		add_filter( 'user_row_actions', 'masq_wp_user_link', 99, 2 );
	}
}

/**
 * Add a "Masquerade As User" link to user row actions.
 *
 * @param array   $actions     Existing row action links.
 * @param WP_User $user_object The user object for the row.
 *
 * @return array Modified actions with masquerade option if allowed.
 */
function masq_wp_user_link( $actions, $user_object ) {
	if ( current_user_can( 'delete_users' ) ) {
		$current_user = wp_get_current_user();
		if ( $current_user->ID !== $user_object->ID ) {
			$actions['masquerade'] = '<a onclick="masq_wp_as_user(' . $user_object->ID . '); return false;" href="#" title="Masquerade As User">Masquerade As User</a>';
		}
	}
	return $actions;
}

add_action( 'admin_footer', 'masq_wp_as_user_js', 30 );
/**
 * Output JavaScript to handle masquerading via AJAX.
 *
 * Injected into the admin footer to allow admins to switch users.
 *
 * @return void
 */
function masq_wp_as_user_js() {
	if ( is_admin() ) {
		?>
		<script type="text/javascript">
		function masq_wp_as_user(uid) {
			var ajax_url = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
			var data = {
				action: 'masq_wp_user',
				wponce: '<?php echo wp_create_nonce( 'masq_wp_once' ); ?>',
				uid: uid
			};
			jQuery.post(ajax_url, data, function(response) {
				if (response == '1') {
					window.location = "<?php echo site_url(); ?>";
				}
			});
		}
		</script>
		<?php
	}
}

add_action( 'wp_ajax_masq_wp_user', 'ajax_masq_wp_login' );
/**
 * Handle AJAX request to masquerade as a different user.
 *
 * Validates nonce, checks permissions, and logs in as the selected user.
 *
 * @return void
 */
function ajax_masq_wp_login() {
	$wponce = $_POST['wponce'] ?? '';

	if ( ! wp_verify_nonce( $wponce, 'masq_wp_once' ) ) {
		wp_die( 'Security check' );
	}

	$uid       = isset( $_POST['uid'] ) ? (int) $_POST['uid'] : 0;
	$user_info = get_userdata( $uid );

	if ( ! $user_info ) {
		wp_die( 'Invalid user' );
	}

	$uname = $user_info->user_login;

	if ( current_user_can( 'delete_users' ) ) {
		wp_set_current_user( $uid, $uname );
		wp_set_auth_cookie( $uid );
		do_action( 'wp_login', $uname );

		$new_user = wp_get_current_user();
		if ( $new_user->ID === $uid ) {
			echo 1;
			exit();
		}
	}
}
