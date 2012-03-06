<?php

/**
 * Display product information inside the "gdgt databox"
 *
 * @since 1.0
 */
class GDGT_Databox {

	/**
	 * Attach to WP load action
	 *
	 * @since 1.0
	 */
	public function __construct() {
		add_action( 'wp', array( &$this, 'on_wp_load' ) );
	}

	/**
	 * Wait for WordPress to load so we can use query functions
	 *
     * Test for minimum requirements for a databox to appear in the post
	 * Current view is a single post
	 * An admin user has not disabled the databox for this post
	 * No stop tags exist in the post's tags
	 *
	 * @since 1.0
	 */
	public function on_wp_load() {
		global $post, $content_width;

		// databox on single posts only
		if ( ! is_single() || ! isset( $post ) )
			return;

		// do not display on mobile themes or known overflow cases
		if ( isset( $content_width ) && $content_width < 550 )
			return;

		$post_id = absint( $post->ID );
		if ( $post_id < 1 )
			return;

		if ( get_post_meta( $post_id, 'gdgt-disabled', '1' ) == '1' )
			return;

		if ( static::stop_tag_exists() )
			return;

		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );

		// possibly output content at the end of the post
		add_filter( 'the_content', array( &$this, 'after_content' ), 20, 1 );

		// shortcode could happen
		remove_shortcode( 'gdgt' );
		add_shortcode( 'gdgt', array( &$this, 'shortcode' ) );
	}

	/**
	 * Check for stop tags stored for this site and compare against tags for the current post
	 *
	 * @since 1.0
	 * @return bool true if one or more post tags appear in the site's list of stop tags
	 */
	public static function stop_tag_exists() {
		$stop_tags = explode( ',', get_option( 'gdgt_stop_tags', '' ) );
		if ( empty( $stop_tags ) )
			return false;

		$post_tags = get_the_tags();
		if ( empty( $post_tags ) || ! is_array( $post_tags ) )
			return false;
		foreach( $post_tags as $post_tag ) {
			if ( isset( $post_tag->name ) && in_array( trim( strtolower( $post_tag->name ) ), $stop_tags ) )
				return true;
		}
		return false;
	}

	/**
	 * Should a "mini" gdgt Databox be displayed for the current theme?
	 * Add a mini class to the databox if fewer than 650 horizontal pixels
	 *
	 * @since 1.0
	 * @uses content_width
	 * @return bool true if content_width defined and less than 650 pixels
	 */
	public static function databox_type() {
		global $content_width;
		if ( isset( $content_width ) && $content_width < 650 )
			return 'mini';
		return '';
	}

	/**
	 * Load the gdgt Databox JavaScript and CSS with every post even if no databox is displayed
	 *
	 * @since 1.0
	 * @todo only include if post generates a databox
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'gdgt-databox', plugins_url( 'static/css/databox.css', __FILE__ ), array(), '1.0' );
		$js_filename = 'gdgt-databox.js';
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG === true )
			$js_filename = 'gdgt-databox.dev.js';
		wp_enqueue_script( 'gdgt-databox', plugins_url( 'static/js/' . $js_filename, __FILE__ ), array( 'jquery' ), '1.0' );
	}

	/**
	 * Request product module data from gdgt API
	 *
	 * @since 1.0
	 * @param array tags post tags used to supplement product results beyond what is specified by product_include_slugs up to site limit
	 * @param array $products_include_slugs product or instance slugs to explicitly include in the product module response
	 * @param array $products_exclude_slugs product slugs to explicitly exclude from the product response, overriding tags
	 * @return array product data for use in gdgt databox
	 */
	public static function gdgt_response( array $tags, array $products_include_slugs, array $products_exclude_slugs, $extra_params = null ) {
		// must be at least one or the other defined
		if ( empty( $tags ) && empty( $products_include_slugs ) )
			return;

		$api_key = get_option( 'gdgt_apikey' );
		if ( ! is_string( $api_key ) || empty( $api_key ) )
			return;
		if ( ! empty( $extra_params ) && is_array( $extra_params ) )
			$params = $extra_params;
		else
			$params = array();
		unset( $extra_params );

		$params['api_key'] = $api_key;
		unset( $api_key );

		$limit = absint( get_option( 'gdgt_max_products', 10 ) );
		if ( $limit < 1 || $limit > 10 )
			$limit = 10;
		$params['limit'] = $limit;
		unset( $limit );

		if ( ! empty( $tags ) )
			$params['tags'] = $tags;
		unset( $tags );
		if ( ! empty( $products_include_slugs ) )
			$params['products_include'] = $products_include_slugs;
		unset( $products_include_slugs );
		if ( ! empty( $products_exclude_slugs ) )
			$params['products_exclude'] = $products_exclude_slugs;
		unset( $products_exclude_slugs );

		if ( ! class_exists( 'GDGT_Base' ) )
			require_once( dirname( __FILE__ ) . '/base.php' );	
		$args = GDGT_Base::base_request_args();
		$args['body'] = json_encode( $params );
		unset( $params );
		$response = wp_remote_post( GDGT_Base::BASE_URL . 'v2/product/module', $args );
		unset( $args );
		if ( is_wp_error( $response ) || absint( wp_remote_retrieve_response_code( $response ) ) !== 200 )
			return new WP_Error( 'gdgt-fail', 'gdgt API failed' );
		$response = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $response ) || ! isset( $response->results ) || ! is_array( $response->results ) )
			return new WP_Error( 'gdgt-invalid', 'gdgt API invalid response' );
		else
			return $response->results;
	}

	/**
	 * Generate HTML markup for the gdgt Databox based on an array of products returned by the gdgt product module API
	 *
	 * @since 1.0
	 * @param array $products gdgt products
	 * @return string HTML markup for the gdgt Databox or empty string
	 */
	public static function render( $products ) {
		global $content_width;

		if ( empty( $products ) || ! is_array( $products ) )
			return '';

		// should have been enforced by limit parameter in API.
		// do it ourselves if doesn't match stored preference
		$max_products = absint( get_option( 'gdgt_max_products', 10 ) );
		if ( count( $products ) > $max_products )
			$products = array_slice( $products, 0, $max_products );
		unset( $max_products );

		$expand_all = false;
		if ( get_option( 'gdgt_expand_products', false ) == '1' )
			$expand_all = true;

		if ( ! class_exists( 'GDGT_Databox_Product' ) )
			require_once dirname(__FILE__) . '/templates/product.php';

		$databox = '<div class="gdgt-wrapper';
		if ( static::databox_type() === 'mini' )
			$databox .= ' mini';
		$databox .= '" lang="en" dir="ltr" role="complementary tablist" aria-multiselectable="true">';
		$expanded = true;
		foreach( $products as $product ) {
			$product_template = new GDGT_Databox_Product( $product );
			if ( ! isset( $product_template->url ) )
				continue;
			$databox .= $product_template->render( $expanded );
			if ( ! $expand_all && $expanded )
				$expanded = false;
		}
		$databox .= '</div>';
		return $databox;
	}

	/**
	 * Given an array of tag objects return only the names
	 *
	 * @since 1.0
	 * @param array $tags post tag objects
	 * @return array post tag names
	 */
	public static function tag_names_only( $tags ) {
		if ( empty( $tags ) || ! is_array( $tags ) )
			return array();

		$tag_names = array();
		foreach( $tags as $tag ) {
			if ( isset( $tag->name ) && ! empty( $tag->name ) )
				$tag_names[] = trim( $tag->name );
		}
		return $tag_names;
	}

	/**
	 * Given an array of product_include or product_exclude products from post meta return an array of slugs for use by the API
	 *
	 * @since 1.0
	 * @param array $products list of products from post meta
	 * @return array product slugs
	 */
	public static function stored_products_slugs_only( $products ) {
		if ( empty( $products ) || ! is_array( $products ) )
			return array();

		$slugs = array();
		foreach( $products as $product ) {
			if ( array_key_exists( 'slug', $product ) )
				$slugs[] = $product['slug'];
		}
		return $slugs;
	}

	/**
	 * Build a cache key based on site options
	 *
	 * @since 1.0
	 * @param int $post_id post identifier
	 * @return string cache key
	 */
	public static function cache_key( $post_id ) {
		$cache_key_parts = array( 'gdgt-databox', 'v1' );
		if ( is_multisite() ) {
			$blog_id = absint( get_current_blog_id() );
			if ( $blog_id > 0 )
				$cache_key_parts[] = 's' . $blog_id;
			unset( $blog_id );
		}

		$cache_key_parts[] = 'p' . $post_id;

		// separate cache for full vs. mini box
		if ( static::databox_type() === 'mini' )
			$cache_key_parts[] = 'm';

		$cache_key_parts[] = 'n' . absint( get_option( 'gdgt_max_products', 10 ) );

		// store tab preference uniqueness
		$tabs = array( 's','r' );
		if ( (bool) get_option( 'gdgt_answers_tab', true ) )
			$tabs[] = 'a';
		if ( (bool) get_option( 'gdgt_discussions_tab', true ) )
			$tabs[] = 'd';
		$cache_key_parts[] = implode( '', $tabs );
		unset( $tabs );

		// display all products in fully expanded state
		if ( (bool) get_option( 'gdgt_expand_products', false ) )
			$cache_key_parts[] = 'e';
		if ( ! (bool) get_option( 'gdgt_schema_org', true ) )
			$cache_key_parts[] = 'ns';

		// must be 45 characters or fewer http://core.trac.wordpress.org/ticket/15058
		return implode( '-', $cache_key_parts );
	}

	/**
	 * Modify the standard cache key for storage of a last known good value
	 *
	 * @since 1.0
	 * @param string $cache_key unique cache identifier
	 * @return string cache key with a last known good identifier
	 */
	public static function cache_key_last_known_good( $cache_key ) {
		return $cache_key . '-lkg';
	}

	/**
	 * Generate databox HTML
	 * Store result in transient cache
	 *
	 * @since 1.0
	 * @uses set_transient()
	 * @param array $extra_params extra parameters for the request. used by full post update to pass new content
	 * @param bool $fallback attempt to request fallback content if gdgt API errors. should only be true if you plan to output the result.
	 * @param string $cache_key cache key to update on success if already generated. else one will be created
	 * @return string databox HTML or empty string
	 */
	public static function generate_databox( array $extra_params, $fallback = true, $cache_key = '' ) {
		global $post;

		if ( ! isset( $post ) )
			return;

		$post_id = absint( $post->ID );
		if ( $post_id === 0 )
			return '';

		$tags = static::tag_names_only( get_the_tags() );

		$products_include = static::stored_products_slugs_only( maybe_unserialize( get_post_meta( $post_id, 'gdgt-products-include', true ) ) );

		// we need either explicit products to include in the response or tags that could match products
		if ( empty( $tags ) && empty( $products_include ) )
			return '';

		$products_exclude = static::stored_products_slugs_only( maybe_unserialize( get_post_meta( $post_id, 'gdgt-products-exclude', true ) ) );
		if ( empty( $products_exclude ) )
			$products_exclude = array();

		if ( empty( $cache_key ) || ! is_string( $cache_key ) )
			$cache_key = static::cache_key( $post_id );

		$products = static::gdgt_response( $tags, $products_include, $products_exclude, $extra_params );
		if ( is_wp_error( $products ) ) {
			if ( $fallback === true ) {
				// try to fetch last known good result from transient cache if one exists
				// gdgt API fail fallback
				$databox = get_transient( static::cache_key_last_known_good( $cache_key ) );
				if ( empty( $databox ) )
					return '';
				else
					return $databox;
			}
			return '';
		}

		// trust the gdgt API to honor our limit but verify just in case
		$limit = absint( get_option( 'gdgt_max_products', 10 ) );
		if ( $limit < 1 || $limit > 10 )
			$limit = 10;
		if ( count( $products ) > $limit )
			$products = array_slice( $products, 0, $limit, true );	

		// cache the result

		$expiration = 3600; // cache for one hour by default

		if ( empty( $products ) ) {
			// store an empty result
			$databox = ' ';

			// if post less than 24 hours old there is a chance a product will soon be added and match. shorten cache
			if ( isset( $post->post_date_gmt ) ) {
				$published = absint( mysql2date( 'G', $post->post_date_gmt ) );
				if ( $published > 0 && ( time() - $published ) < 86400 )
					$expiration = 900; // 15 minutes
				unset( $published );
			}
		} else {
			// generate HTML from the returned products
			$databox = static::render( $products );
		}

		if ( ! empty( $databox ) ) {
			set_transient( $cache_key, $databox, $expiration );
			if ( $databox !== ' ' )
				set_transient( static::cache_key_last_known_good( $cache_key ), $databox, 86400 ); // store last known good for 24 hours to account for gdgt API fail
		}

		return $databox;
	}

	/**
	 * Possibly display a gdgt Databox after the post content
	 *
	 * @since 1.0
	 * @param string $content the post content
	 * @return string post content with possible databox appended
	 */
	public function after_content( $content ) {
		global $post;

		if ( ! isset( $post ) )
			return $content;

		$post_id = absint( $post->ID );
		if ( $post_id === 0 )
			return $content;

		$cache_key = static::cache_key( $post_id );
		$databox = get_transient( $cache_key );
		if ( empty( $databox ) )
			$databox = static::generate_databox( array(), true, $cache_key );
		unset( $cache_key );

		$databox = trim( $databox );

		if ( ! empty( $databox ) )
			return $content . $databox;

		// don't break the filter
		return $content;
	}

	/**
	 * Add a gdgt Databox using a shortcode
	 * Empty shortcode clears the content filter, outputting at the shortcode point instead
	 *
	 * @since 1.0
	 * @param array $attributes
	 * @return string databox HTML markup
	 */
	public function shortcode( $attributes ) {
		extract( shortcode_atts( array(
			'tags' => '',
			'products_include' => '',
			'products_exclude' => ''
		), $attributes ) );

		// treat an empty shortcode as explicit placement of the databox inside the post instead of appended to the end
		if ( empty( $tags ) && empty( $products_include ) && empty( $products_exclude ) ) {
			// prevent double output
			remove_filter( 'the_content', array( &$this, 'after_content' ), 20, 1 );
			return $this->after_content( '' );
		}

		return '';
	}
}
?>
