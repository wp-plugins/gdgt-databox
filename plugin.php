<?php
/**
 * @package gdgt-databox
 */
/*
Plugin Name: gdgt Databox
Plugin URI: http://gdgt.com/
Description: Display gadget specifications, reviews, and prices alongside your content. Requires a gdgt API key.
Version: 1.31
Author: gdgt
Author URI: http://gdgt.com/
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/*
Copyright (C) 2012 PastFuture

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// avoid a double load
if ( ! class_exists( 'GDGT_Databox_Plugin' ) ):

/**
 * Configure and initialize actions and hooks for gdgt product box configuration and display
 *
 * @since 1.0
 * @version 1.3
 */
class GDGT_Databox_Plugin {

	/**
	 * Initialize plugin
	 *
	 * @since 1.0
	 */
	public function __construct() {
		$plugin_directory = dirname( __FILE__ );
		$api_key = get_option( 'gdgt_apikey' );
		if ( ! empty( $api_key ) )
			$this->api_key = $api_key;
		unset( $api_key );

		if ( ! is_admin() )
			add_shortcode( 'gdgt', create_function( '','return \'\';' ) );

		if ( is_admin() ) {
			if ( ! class_exists( 'GDGT_Settings' ) )
				require_once( $plugin_directory . '/settings.php' );
			new GDGT_Settings();

			/* API key required for all other functionality */
			if ( ! isset( $this->api_key ) )
				return;

			if ( current_user_can( 'edit_posts' ) ) {
				if ( ! class_exists( 'GDGT_Post_Meta_Box' ) )
					require_once( $plugin_directory . '/edit.php' );
				new GDGT_Post_Meta_Box();
			}
		} else if ( isset( $this->api_key ) ) {
			if ( ! class_exists( 'GDGT_Databox' ) )
				require_once( $plugin_directory . '/databox.php' );
			new GDGT_Databox();
		}
	}
}

/**
 * Hook into the WordPress init action to setup our plugin hooks for later use
 *
 * @since 1.0
 */
function gdgt_init_plugin() {
	new GDGT_Databox_Plugin();
}
add_action( 'init', 'gdgt_init_plugin' );

endif;
?>
