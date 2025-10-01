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
 * Class MasqueradeWP
 *
 * Provides admin masquerade functionality for WordPress.
 * Allows admins to log in as other users and return to their own account.
 */
if ( ! class_exists( 'MasqueradeWP' ) ) {

    class MasqueradeWP {

        /**
         * Singleton instance
         *
         * @var MasqueradeWP
         */
        private static $instance = null;

        /**
         * Get the singleton instance
         *
         * @return MasqueradeWP
         */
        public static function instance() {
            if ( self::$instance === null ) {
                self::$instance = new self();
                self::$instance->init_hooks();
            }
            return self::$instance;
        }

        /**
         * Initialize WordPress hooks
         */
        private function init_hooks() {
            add_action( 'admin_init', [ $this, 'admin_init' ] );
            add_action( 'admin_footer', [ $this, 'admin_footer_js' ], 30 );
            add_action( 'wp_ajax_masq_wp_user', [ $this, 'ajax_login_as_user' ] );
            add_action( 'admin_post_masq_return', [ $this, 'return_to_admin' ] );
            add_action( 'admin_bar_menu', [ $this, 'admin_bar_link' ], 999 );
            add_action( 'admin_notices', [ $this, 'admin_masquerade_banner' ] );
        }

        /**
         * Add masquerade link to user row actions
         */
        public function admin_init() {
            if ( is_admin() ) {
                add_filter( 'user_row_actions', [ $this, 'user_row_actions' ], 99, 2 );
            }
        }

        /**
         * Add "Masquerade As User" link
         *
         * @param array   $actions
         * @param WP_User $user
         * @return array
         */
        public function user_row_actions( $actions, $user ) {
            if ( current_user_can( 'delete_users' ) && wp_get_current_user()->ID !== $user->ID ) {
                $actions['masqueradewp'] = '<a onclick="MasqueradeWP.switchUser(' . esc_attr( $user->ID ) . ' ); return false;" href="#" title="Masquerade As User">View as User</a>';
            }
            return $actions;
        }

        /**
         * Output JavaScript for AJAX masquerade
         */
        public function admin_footer_js() {
            if ( is_admin() ) {
                ?>
                <script type="text/javascript">
                    var MasqueradeWP = {
                        switchUser: function(uid) {
                            jQuery.post(ajaxurl, {
                                action: 'masq_wp_user',
                                wponce: '<?php echo esc_js( wp_create_nonce( "masq_wp_once" ) ); ?>',
                                uid: uid
                            }, function(response) {
                                if(response == '1') {
                                    window.location.href = '<?php echo esc_url( home_url() ); ?>';
                                } else {
                                    alert('Error logging in as user.');
                                }
                            });
                        }
                    };
                </script>
                <?php
            }
        }

        /**
         * Handle AJAX request to masquerade as user
         */
        public function ajax_login_as_user() {
            $wponce = $_POST['wponce'] ?? '';
            if ( ! wp_verify_nonce( $wponce, 'masq_wp_once' ) ) {
                wp_die( 'Security check' );
            }

            $uid = isset( $_POST['uid'] ) ? (int) $_POST['uid'] : 0;
            $user_info = get_userdata( $uid );
            if ( ! $user_info ) {
                wp_die( 'Invalid user' );
            }

            $current_admin = wp_get_current_user();
            set_transient( 'masq_original_user_' . $uid, $current_admin->ID, HOUR_IN_SECONDS );

            if ( current_user_can( 'delete_users' ) ) {
                wp_set_current_user( $uid, $user_info->user_login );
                wp_set_auth_cookie( $uid );
                do_action( 'wp_login', $user_info->user_login );

                if ( wp_get_current_user()->ID === $uid ) {
                    echo 1;
                    exit();
                }
            }

            wp_die( 'Failed to switch user.' );
        }

        /**
         * Return to the original admin user
         */
        public function return_to_admin() {
            $current_user = wp_get_current_user();
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

        /**
         * Add admin bar link for masquerade actions
         *
         * @param WP_Admin_Bar $wp_admin_bar
         */
        public function admin_bar_link( $wp_admin_bar ) {
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

        /**
         * Display admin notice banner when masquerading
         */
        public function admin_masquerade_banner() {
            $current_user = wp_get_current_user();
            $admin_id = get_transient( 'masq_original_user_' . $current_user->ID );

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
    }

    // Initialize the masquerade functionality
    MasqueradeWP::instance();
}
