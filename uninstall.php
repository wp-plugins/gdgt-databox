<?php

/**
 * Remove all stored settings and post data
 *
 * @since 1.0
 */
function gdgt_databox_uninstall() {
	// delete options
	if ( ! class_exists( 'GDGT_Settings' ) )
		require_once( dirname(__FILE__) . '/settings.php' );
	if ( ! isset( GDGT_Settings::$all_options ) || ! is_array( GDGT_Settings::$all_options ) )
		return;
	foreach ( GDGT_Settings::$all_options as $option_name ) {
		delete_option( $option_name );
	}

	// delete post meta
	if ( ! class_exists( 'GDGT_Post_Meta_Box' ) )
		require_once( dirname(__FILE__) . '/edit.php' );
	if ( ! isset( GDGT_Post_Meta_Box::$all_meta_keys ) || ! is_array( GDGT_Post_Meta_Box::$all_meta_keys ) )
		return;
	$meta_query = array();
	foreach ( GDGT_Post_Meta_Box::$all_meta_keys as $meta_key ) {
		$meta_query[] = array( 'key' => $meta_key );
	}
	$all_posts = get_posts( array(
		'numberposts' => -1, // everything
		'post_status' => 'any', // draft, waiting, published, anything
		'post_type' => 'post', // just posts
		'orderby' => 'none', // don't care
		'meta_query' => $meta_query, // restrict to posts with stored data
		'cache_results' => false, // uninstalling. don't expect reuse
		'fields' => 'ids' // just the post ID
	) );
	unset( $meta_query );
	if ( empty( $all_posts ) )
		return;
	foreach ( $all_posts as $post_id ) {
		GDGT_Post_Meta_Box::delete_post_meta( absint( $post_id ) );
	}
}
gdgt_databox_uninstall();
?>