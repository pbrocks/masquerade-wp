<?php
/**
 * Plugin Name: Masquerade in WP
 * Plugin URI: https://github.com/pbrocks/masquerade-wp
 * Description: Forked from JR King -- Adds a link to users.php that allows an administrator to login as that user without knowing the password.
 * Version: 1.0.4
 * Author:  pbrocks
 * Author URI: https://github.com/pbrocks
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
			$actions['masqueradewp'] = '<a onclick="masq_wp_as_user(' . $user_object->ID . '); return false;" href="#" title="Masquerade As User">View as User</a>';
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
			var ajax_url = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			var data = {
				action: 'masq_wp_user',
				wponce: '<?php echo esc_html( wp_create_nonce( 'masq_wp_once' ) ); ?>',
				uid: uid
			};
			jQuery.post(ajax_url, data, function(response) {
				if (response == '1') {
					window.location.href = '<?php echo esc_url( home_url() ); ?>';
				} else {
					alert('Error logging in as user.');
				}
			});
		}
		</script>
		<?php
	}
}

add_action( 'wp_ajax_masq_wp_user', 'ajax_masq_wp_login' );
/**
 * Add masquerade functionality with admin bar link using transients.
 *
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

	// Grab current admin user BEFORE switching.
	$current_admin = wp_get_current_user();

	// Store the original admin ID, keyed by the masquerade UID.
	set_transient(
		'masq_original_user_' . $uid,
		$current_admin->ID,
		HOUR_IN_SECONDS
	);

	// Now actually switch users.
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

add_action( 'admin_post_masq_return', 'masq_wp_return_to_admin' );
/**
 * Return to the original admin user.
 */
function masq_wp_return_to_admin() {
	$current_user = wp_get_current_user();

	// Look up original admin ID stored for this masquerading user.
	$admin_id = get_transient( 'masq_original_user_' . $current_user->ID );
	delete_transient( 'masq_original_user_' . $current_user->ID );

	if ( $admin_id ) {
		$user_info = get_userdata( $admin_id );
		if ( $user_info ) {
			wp_set_current_user( $admin_id, $user_info->user_login );
			wp_set_auth_cookie( $admin_id );
			do_action( 'wp_login', $user_info->user_login );
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	wp_safe_redirect( home_url() );
	exit;
}
add_action( 'admin_bar_menu', 'masq_wp_adminbar_link', 999 );
/**
 * Add "Masquerade As" or "Return to Admin User" link in the admin bar.
 *
 * @param WP_Admin_Bar $wp_admin_bar The admin bar object.
 */
function masq_wp_adminbar_link( $wp_admin_bar ) {
    $current_user = wp_get_current_user();
    $admin_id = get_transient( 'masq_original_user_' . $current_user->ID );

    if ( current_user_can( 'delete_users' ) || $admin_id ) {
        if ( $admin_id ) {
            $wp_admin_bar->add_node([
                'parent' => 'my-account',
                'id'     => 'masquerade_return',
                'title'  => __( 'Return to Admin User', 'textdomain' ),
                'href'   => admin_url( 'admin-post.php?action=masq_return' ),
                'meta'   => ['class' => 'masquerade-return-link'],
            ]);
        } else {
            $wp_admin_bar->add_node([
                'parent' => 'my-account',
                'id'     => 'masquerade_as',
                'title'  => __( 'Masquerade As', 'textdomain' ),
                'href'   => admin_url( 'users.php' ),
                'meta'   => ['class' => 'masquerade-as-link'],
            ]);
        }
    }
}
add_action( 'admin_notices', 'masq_wp_masquerade_banner' );
/**
 * Display an admin notice banner when currently masquerading as another user.
 *
 * @return void
 */
function masq_wp_masquerade_banner() {
    $current_user = wp_get_current_user();
    $admin_id = get_transient( 'masq_original_user_' . $current_user->ID );

    // Show banner if currently masquerading OR current user is admin
    if ( $admin_id ) {
        $admin_info = get_userdata( $admin_id );
        if ( $admin_info ) {
            $admin_name = esc_html( $admin_info->user_login );
            $return_url = esc_url( admin_url( 'admin-post.php?action=masq_return' ) );

            echo '<div class="notice notice-warning is-dismissible" style="border-left: 4px solid #ffba00; background-color:#fff3cd;">';
            echo '<p>';
            echo '⚠️ You are currently masquerading as <strong>' . esc_html( $current_user->user_login ) . '</strong>. ';
            echo '<a href="' . $return_url . '">Return to Admin User (' . $admin_name . ')</a>.';
            echo '</p>';
            echo '</div>';
        }
    }
}
