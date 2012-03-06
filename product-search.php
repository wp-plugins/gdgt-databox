<?php
/**
 * Centralize some sanity checks and prerequisites for search by keyword and search by tag
 */

if ( ! class_exists('GDGT_Base') )
	require_once( dirname( __FILE__ ) . '/base.php' );

/**
 * Proxy a keyword search to gdgt API
 *
 * @since 1.0
 */
class GDGT_Product_Search extends GDGT_Base {

	/**
	 * Send a search request to gdgt servers, interpret the response
	 *
	 * @since 1.0
	 * @param array $request_body parameters to be serialized as JSON
	 * @param bool $autocomplete format extra parameters for use in an autocomplete display
	 * @return array product array
	 */
	public static function search( array $request_body, $autocomplete ) {
		if ( empty( $request_body ) )
			return;

		$args = GDGT_Product_Search::base_request_args();
		$args['body'] = json_encode( $request_body );
		$response = wp_remote_post( GDGT_Product_Search::BASE_URL . 'v3/search/product/', $args );
		unset( $args );
		if ( is_wp_error( $response ) )
			return new WP_Error( 500, 'gdgt has issues.' );
		if ( absint( wp_remote_retrieve_response_code( $response ) ) === 401 )
			return new WP_Error( 401, 'Unauthorized. Likely a bad API key.' );

		$response_body = wp_remote_retrieve_body( $response );
		if ( empty( $response_body ) )
			return new WP_Error( 404, 'No matching products found.' );
		$response = json_decode( $response_body, true );
		unset( $response_body );
		if ( empty( $response ) || ! array_key_exists( 'results', $response ) || ! is_array( $response['results'] ) || empty( $response['results'] ) )
			return new WP_Error( 404, 'No matching products found.' );

		$products = $response['results'];
		unset( $response );

		$retval = array();
		foreach ( $products as $product ) {
			// test if we received the minimum key and type of value expected
			if ( array_key_exists( 'product_slug', $product ) && is_string( $product['product_slug'] ) && ! empty( $product['product_slug'] ) && array_key_exists( 'product_fullname', $product ) && is_string( $product['product_fullname'] ) && ! empty( $product['product_fullname'] ) ) {

				// display a separate autocomplete name vs. selected name to aid in differentiation between products and instances
				$p = array(
					'slug' => $product['product_slug'],
					'name' => trim( $product['product_fullname'] )
				);
				if ( $autocomplete )
					$p['autocomplete_name'] = trim( $product['product_fullname'] );

				$instances = array();
				if ( array_key_exists( 'instances', $product ) && ! empty( $product['instances'] ) && is_array( $product['instances'] ) ) {
					$instance_names = array();
					foreach ( $product['instances'] as $instance ) {
						if ( ! array_key_exists( 'instance_name', $instance ) || ! is_string( $instance['instance_name'] ) )
							continue;
						if ( $autocomplete && array_key_exists( 'product_slug', $instance ) && is_string( $instance['product_slug'] ) && array_key_exists( 'product_fullname', $instance ) && is_string( $instance['product_fullname'] ) ) {
							if ( $instance['product_slug'] !== $product['product_slug'] ) {
								$instances[] = array(
									'slug' => $instance['product_slug'],
									'name' => $instance['product_fullname'],
									'autocomplete_name' => $instance['instance_name'],
									'parent' => $product['product_slug']
								);
							}
							unset( $instance_slug );
						}
						$instance_names[] = trim( $instance['instance_name'] );
					}
					if ( ! empty( $instance_names ) ) {
						sort( $instance_names );
						$p['instances'] = implode( ',', $instance_names );
					}
					unset( $instance_names );
					if ( $autocomplete )
						$p['autocomplete_name'] .= __( ' (all models)', GDGT_Product_Search::PLUGIN_SLUG );
				}

				$retval[] = $p;

				if ( $autocomplete && ! empty( $instances ) ) {
					// sort by autocomplete name for easy scanning
					usort( $instances, create_function( '$a,$b', 'return strnatcmp( $a[\'autocomplete_name\'], $b[\'autocomplete_name\'] );' ) );
					foreach ( $instances as $instance ) {
						$retval[] = $instance;
					}
				}
				unset( $instances );
				unset( $p );
				unset( $slug );
			}
		}

		if ( empty( $retval ) )
			return new WP_Error( 404, 'No matching products found.' );
		return $retval;
	}

	/**
	 * Submit search to gdgt
	 *
	 * @since 1.0
	 * @param string $search_query freeform search string
	 * @param bool $autocomplete format labels for use in an autocomplete dropdown
	 * @param string $api_key explicitly provide an API key or rely on the stored key
	 */
	public static function search_by_keyword( $search_query, $autocomplete = false, $api_key = '' ) {
		if ( ! is_string( $search_query ) || empty( $search_query ) )
			return new WP_Error( 400, 'No search query provided.' );
		if ( ! is_string( $api_key ) || empty( $api_key ) ) {
			$api_key = get_option( 'gdgt_apikey' );
			if ( empty( $api_key ) )
				return new WP_Error( 401, 'No API key set.' );
		}
		return GDGT_Product_Search::search( array( 'api_key' => $api_key, 'keyword' => $search_query ), (bool) $autocomplete );
	}

	/**
	 * Search gdgt for one or more tags
	 *
	 * @since 1.0
	 * @param array $tags post tags
	 * @param string $api_key (optional) API key. if not provided API key will be pulled from options
	 * @return array product array
	 */
	public static function search_by_tags( array $tags, $autocomplete = false, $api_key = '' ) {
		if ( ! is_array( $tags ) || empty( $tags) )
			return new WP_Error( 400, 'No tags provided.' );
		if ( ! is_string( $api_key ) || empty( $api_key ) ) {
			$api_key = get_option('gdgt_apikey');
			if ( empty( $api_key ) )
				return new WP_Error( 401, 'No API key set.' );
		}

		return GDGT_Product_Search::search( array( 'api_key' => $api_key, 'tags' => $tags ), (bool) $autocomplete );
	}
}
?>