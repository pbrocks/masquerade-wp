=== Masquerade ===
Contributors: JR King
Plugin URI: http://castle-creative.com/
Author URI: http://castle-creative.com/
Tags: admin login, login as user, masquerade as user, user login, admin login as user
Requires at least: 2.8
Tested up to: 3.3.2
Stable tag: 1.01
License: General Public License version 2

Adds a link to users.php that allows an administrator to login as that user without knowing the password.

== Description ==

This plugin adds an option to the User List in the admin area where you can click "Masquerade as User", you will be automatically logged in as that user and redirected to the home page.

== Installation ==

1. Download the plugin and extract the files
2. Upload `masquerade` directory to your WordPress Plugin directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Goto the 'Users' menu in Wordpress to see Masquerade Link.

== Frequently Asked Questions ==
Q: Why doesn't the Masquerade as User" link appear in the user list?
A: For security reasons, the link only appears to users that have the 'delete_users' capability.

== Change Log ==

= 1.01 =
* Added nonce security check to POST request

= 1.0 =
* First stable release

== Upgrade Notice ==
None

== Screenshots ==

1. Showing the Masquerade Link in the User List
