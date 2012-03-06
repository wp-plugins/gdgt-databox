<?php
/**
 * Search by keyword HTTP request frontend
 *
 * @since 1.0
 */

if ( array_key_exists( 'REQUEST_METHOD', $_SERVER ) && $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
	header( 'HTTP/1.1 405 Method Not Allowed', true, 405 );
	header( 'Allow: GET' );
	exit();
}
	

// WordPress bootstrap
if ( ! function_exists( 'current_user_can' ) )
	require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

// override HTML default Content-Type with JSON
header( 'Content-Type: application/json; charset=UTF-8', true );

/**
 * Echo a JSON error message, set a HTTP status, and exit
 *
 * @since 1.0
 * @param WP_Error $error error code of HTTP status int. error message echoed in JSON
 */
function gdgt_reject_message( WP_Error $error ) {
	status_header( $error->get_error_code() );
	echo json_encode( array( 'error' => $error->get_error_message() ) );
	exit();
}

if ( ! current_user_can( 'edit_posts' ) )
	gdgt_reject_message( new WP_Error( 403, __( 'Cheatin\' uh?' ) ) );

if ( ! array_key_exists( 'q', $_GET ) )
	gdgt_reject_message( new WP_Error( 400, 'Search string needed. Use q query parameter.' ) );


$__search_term = trim( $_GET['q'] );
if ( empty( $__search_term ) )
	gdgt_reject_message( new WP_Error( 400, 'No search string provided.' ) );

if ( ! class_exists( 'GDGT_Product_Search' ) )
	require_once( dirname(__FILE__) . '/product-search.php' );

$__products = GDGT_Product_Search::search_by_keyword( $__search_term, true );
if ( is_wp_error( $__products  ) )
	gdgt_reject_message( $__products );
else
	echo json_encode( $__products );
?>