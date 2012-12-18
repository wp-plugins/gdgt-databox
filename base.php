<?php

// did someone access this file outside of WordPress? try loading WordPress
if ( ! function_exists('get_bloginfo') )
	require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';

/**
 * gdgt base class to be inherited by other classes.
 * Centralize common tasks such as HTTP back to gdgt API server, plugin slug reference.
 *
 * @since 1.0
 */
class GDGT_Base {

	/**
	 * Plugin version number.
	 * Added to HTTP requests to identify the plugin to gdgt API servers
	 *
	 * @since 1.0
	 * @var string
	 */
	const PLUGIN_VERSION = '1.31';

	/**
	 * Plugin slug used to differentiate this plugin and its message bundles, options, etc. from other WordPress plugins
	 *
	 * @since 1.0
	 * @var string
	 */
	const PLUGIN_SLUG = 'gdgt-databox';

	/**
	 * Name of the plugin and its main object(s)
	 *
	 * @since 1.0
	 * @var string
	 */
	const PLUGIN_NAME = 'gdgt Databox';

	/**
	 * gdgt API endpoint base URL
	 * Consistent for all API calls. Used to build full API URL
	 *
	 * @since 1.0
	 * @var string
	 */
	const BASE_URL = 'http://api.gdgt.com/';

	/**
	 * Build base arguments to be passed to WP_Http request function
	 *
	 * @since 1.0
	 * @link http://core.trac.wordpress.org/browser/trunk/wp-includes/class-http.php#L36 WP_Http request
	 * @return array associative array with default values specific to the gdgt API
	 */
	public static function base_request_args() {
		global $wp_version;

		$charset = get_bloginfo( 'charset' );
		if ( empty( $charset ) )
			$charset = 'utf-8';
		else
			$charset = strtolower( $charset );

		$headers = array( 'Accept' => 'application/json', 'Accept-Charset' => $charset );
		unset( $charset );

		$language = get_bloginfo( 'language' );
		if ( ! empty( $language ) )
			$headers[ 'Accept-Language' ] = $language;
		unset( $language );

		$args = array(
			'httpversion' => '1.1',
			'redirection' => 0,
			'timeout' => 3,
			'user-agent' => apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . site_url('/')  ) . '; ' . GDGT_Base::PLUGIN_SLUG . '/' . GDGT_Base::PLUGIN_VERSION,
			'headers' => $headers
		);
		unset( $headers );
		return $args;
	}
}
?>
