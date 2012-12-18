<?php

/**
 * Display product information inside the "gdgt databox"
 *
 * @since 1.0
 */
class GDGT_Databox {

	/**
	 * HTML placeholder used when priority < 10
	 *
	 * @since 1.2
	 * @var string
	 */
	const placeholder = '<div id="gdgt-placeholder"></div>';

	/**
	 * Track the total number of placeholders in the current view
	 *
	 * @since 1.2
	 * @var int
	 */	
	public static $placeholder_count = 1;

	/**
	 * Track if Databox JavaScript files have been loaded for the current page
	 *
	 * @since 1.3
	 * @var bool
	 */
	public $js_loaded = false;

	/**
	 * Track if Databox CSS files have been loaded for the current page
	 *
	 * @since 1.3
	 * @var bool
	 */
	public $css_loaded = false;

	/**
	 * Google Analytics account + site identifier
	 *
	 * @since 1.2
	 * @var string
	 */
	const google_analytics_id = 'UA-818999-9';

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
	 * Current view is a single post web or feed view
	 * An admin user has not disabled the databox for this post
	 * No stop tags exist in the post's tags
	 *
	 * @since 1.0
	 */
	public function on_wp_load() {
		global $post, $content_width;

		if ( ! isset( $post ) )
			return;

		// databox on single posts only
		if ( ! ( is_feed() || is_single() ) )
			return;

		// do not display on mobile themes or known overflow cases
		if ( ! is_feed() && ( isset( $content_width ) && $content_width < 550 ) )
			return;

		$post_id = absint( $post->ID );
		if ( $post_id < 1 )
			return;

		if ( get_post_meta( $post_id, 'gdgt-disabled', '1' ) == '1' )
			return;

		if ( GDGT_Databox::stop_tag_exists() )
			return;

		$this->content_priority = absint( get_option( 'gdgt_content_filter_priority', 1 ) );
		if ( is_feed() ) {
			if ( (bool) get_option( 'gdgt_feed_include', true ) ) {
				if ( $this->content_priority < 10 ) { // if before wpautop
					add_filter( 'the_content_feed', array( &$this, 'add_placeholder' ), $this->content_priority );
					$this->content_priority = 12; // after shortcode
				}
				add_filter( 'the_content_feed', array( &$this, 'after_content' ), $this->content_priority );
			}
		} else {
			add_action( 'wp_enqueue_scripts', array( &$this, 'maybe_enqueue_scripts' ) );
			// possibly output content at the end of the post
			if ( $this->content_priority < 10 ) {
				add_filter( 'the_content', array( &$this, 'add_placeholder' ), $this->content_priority );
				$this->content_priority = 12; // after shortcode
			}
			add_filter( 'the_content', array( &$this, 'after_content' ), $this->content_priority, 1 );
		}

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
	public static function stop_tag_exists( $post_id = 0 ) {
		$stop_tags = explode( ',', get_option( 'gdgt_stop_tags', '' ) );
		if ( empty( $stop_tags ) )
			return false;

		$post_tags = get_the_tags( absint( $post_id ) );
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

		if ( ! is_feed() && ( isset( $content_width ) && $content_width < 650 ) )
			return 'mini';
		return '';
	}

	/**
	 * Load scripts and stylesheet if products_include specified, else wait to see if we get an API response first
	 *
	 * @since 1.3
	 */
	public function maybe_enqueue_scripts() {
		global $post;

		if ( ! is_single() || ! isset( $post->ID ) )
			return;

		// use products include as a strong signal the post will contain Databox content
		$products_include = get_post_meta( $post->ID, 'gdgt-products-include', true );
		if ( ! empty( $products_include ) ) {
			$this->enqueue_styles();
			$this->enqueue_scripts();
		}
	}

	/**
	 * Load Databox CSS in page head
	 *
	 * @since 1.3
	 * @uses wp_enqueue_style()
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'gdgt-databox', plugins_url( 'static/css/databox.css', __FILE__ ), array(), '1.31' );
		$this->css_loaded = true;
	}

	/**
	 * Load the gdgt Databox JavaScript
	 *
	 * @since 1.0
	 * @uses wp_enqueue_script()
	 */
	public function enqueue_scripts() {
		// no need to load twice
		if ( $this->js_loaded === true )
			return false;

		wp_enqueue_script( 'jquery', is_ssl() ? 'https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' : 'http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js', array(), null, true );

		$js_filename = 'gdgt-databox.js';
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG === true )
			$js_filename = 'gdgt-databox.dev.js';
		wp_enqueue_script( 'gdgt-databox', plugins_url( 'static/js/' . $js_filename, __FILE__ ), array( 'jquery' ), '1.3', true );
		$this->js_loaded = true;
		return true;
	}

	/**
	 * Print the gdgt Databox JavaScript if not already enqueued
	 *
	 * @since 1.3
	 * @uses wp_print_scripts()
	 */
	public function print_scripts() {
		if ( $this->enqueue_scripts() === true )
			wp_print_scripts( 'gdgt-databox' );
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
		if ( defined( 'DOING_CRON' ) && DOING_CRON )
			$args['timeout'] = 30;
		$args['body'] = json_encode( $params );
		unset( $params );
		$response = wp_remote_post( GDGT_Base::BASE_URL . 'v3/product/module', $args );
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
	 * Wrap a string in newline characters to separate from other content
	 * Helps maintain content such as oEmbed and shortcodes needing to appear on their own line in the post content
	 *
	 * @since 1.22
	 * @param string $content content you would like to wrap
	 * @return string newline wrapped content
	 */
	public static function wrap_in_newlines( $content ) {
		if ( empty( $content ) )
			return $content;
		if ( defined( 'PHP_EOL' ) )
			return PHP_EOL . $content . PHP_EOL;
		else
			return "\n" . $content . "\n";
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

		$expand_all = false;
		if ( get_option( 'gdgt_expand_products', false ) == '1' )
			$expand_all = true;

		if ( ! class_exists( 'GDGT_Databox_Product' ) )
			require_once dirname(__FILE__) . '/templates/product.php';

		if ( is_feed() ) {
			$databox = '<div lang="en" dir="ltr" style="display:block; width:650px; padding:0; margin-top:20px; margin-bottom:20px; margin-left:0; margin-right:0; background-color:#FFF; border-color:#CCC; border-style:solid; border-top-width:1px; border-bottom-width:0; border-left-width:1px; border-right-width:1px; font-family:Arial, Helvetica, sans-serif; font-size:13px; font-weight:normal; text-align:left; line-height:1em; vertical-align:baseline"><ol style="list-style:none; margin:0; padding:0">';
			$expanded = true;
			foreach ( $products as $product ) {
				$product_template = new GDGT_Databox_Product( $product, ! $expanded );
				if ( ! isset( $product_template->url ) )
					continue;
				$databox .= $product_template->render_inline();
				if ( ! $expand_all && $expanded )
					$expanded = false;
			}
			$databox .= '</ol></div>';
		} else {
			$databox = '<div id="gdgt-wrapper"';
			if ( GDGT_Databox::databox_type() === 'mini' )
				$databox .= ' class="mini"';
			if ( isset( $content_width ) ) { // match the specified width preference, not parent computed
				$databox_width = absint( $content_width );
				if ( $databox_width > 1000 )
					$databox_width = 1000;
				$databox .= ' style="width:' . $databox_width . 'px"';
				unset( $databox_width );
			}
			$databox .= ' lang="en" dir="ltr" role="complementary tablist" aria-multiselectable="true">';
			$expanded = true;
			$position = 1;
			foreach ( $products as $product ) {
				$product_template = new GDGT_Databox_Product( $product );
				if ( ! isset( $product_template->url ) )
					continue;
				$databox .= $product_template->render( $expanded, $position );
				if ( ! $expand_all && $expanded )
					$expanded = false;
				$position++;
			}
			$databox .= GDGT_Databox::google_analytics_beacon( 'gdgt Databox', 'http://gdgt.com/databox/', 'noscript' );
			$databox .= '</div>';
		}
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
		global $content_width;

		$cache_key_parts = array( 'gdgt-databox', 'v1.3' );
		if ( is_multisite() ) {
			$blog_id = absint( get_current_blog_id() );
			if ( $blog_id > 0 )
				$cache_key_parts[] = 's' . $blog_id;
			unset( $blog_id );
		}

		$cache_key_parts[] = 'p' . $post_id;

		// separate cache by content width. busts cache when switching themes
		$width = 650;
		if ( isset( $content_width ) ) {
			if ( $content_width > 1000 )
				$width = 1000;
			else
				$width = $content_width;
		}
		$cache_key_parts[] = 'w' . $width;
		unset( $width );

		$cache_key_parts[] = 'n' . absint( get_option( 'gdgt_max_products', 10 ) );

		// display all products in fully expanded state
		if ( (bool) get_option( 'gdgt_expand_products', false ) )
			$cache_key_parts[] = 'e';
		if ( ! (bool) get_option( 'gdgt_schema_org', true ) )
			$cache_key_parts[] = 'ns';
		if ( is_feed() )
			$cache_key_parts[] = 'f';

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
	 * Google Analytics image beacon for noscript environments
	 *
	 * @param string $title page title
	 * @param string $url gdgt URL
	 * @param string $element noscript or img
	 * @return string HTML markup for a GA image beacon
	 */
	public static function google_analytics_beacon( $title, $url, $element = '' ) {
		if ( ! class_exists( 'GDGT_Google_Analytics' ) )
			include_once( dirname( __FILE__ ) . '/google-analytics.php' );

		$ga = new GDGT_Google_Analytics( GDGT_Databox::google_analytics_id );
		$ga->setHostname( 'gdgt.com' );
		$ga->setPageTitle( $title );
		$ga->setPageURL( $url );
		$ga->setReferrer( get_permalink() );
		$ga_url = $ga->get_image_url();
		if ( empty( $ga_url ) ) {
			return '';
		} else if ( in_array( $element, array( 'img','noscript' ), true ) ) {
			$img = '<img alt=" " src="' . esc_url( $ga_url, array( 'http', 'https' ) ) . '" width="1" height="1" />';
			if ( $element === 'img' )
				return $img;
			else
				return '<noscript>' . $img . '</noscript>';
		}
		return $ga_url;
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

		$tags = GDGT_Databox::tag_names_only( get_the_tags() );

		$products_include = GDGT_Databox::stored_products_slugs_only( maybe_unserialize( get_post_meta( $post_id, 'gdgt-products-include', true ) ) );

		// we need either explicit products to include in the response or tags that could match products
		if ( empty( $tags ) && empty( $products_include ) )
			return '';

		$products_exclude = GDGT_Databox::stored_products_slugs_only( maybe_unserialize( get_post_meta( $post_id, 'gdgt-products-exclude', true ) ) );
		if ( empty( $products_exclude ) )
			$products_exclude = array();

		if ( empty( $cache_key ) || ! is_string( $cache_key ) )
			$cache_key = GDGT_Databox::cache_key( $post_id );

		$products = GDGT_Databox::gdgt_response( $tags, $products_include, $products_exclude, $extra_params );
		if ( is_wp_error( $products ) ) {
			if ( $fallback === true ) {
				// try to fetch last known good result from transient cache if one exists
				// gdgt API fail fallback
				$databox = get_transient( GDGT_Databox::cache_key_last_known_good( $cache_key ) );
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
			$databox = GDGT_Databox::render( $products );
		}

		if ( ! empty( $databox ) ) {
			set_transient( $cache_key, $databox, $expiration );
			if ( $databox !== ' ' )
				set_transient( GDGT_Databox::cache_key_last_known_good( $cache_key ), $databox, 86400 ); // store last known good for 24 hours to account for gdgt API fail
		}

		return $databox;
	}

	/**
	 * Add a wpautop-safe grouping block placeholder in the_content HTML to protect against modification
	 * We will later replace this string with the actual Databox HTML
	 *
	 * @since 1.2
	 * @param string $content the post content
	 * @return string post content with a placeholder appended
	 */
	public function add_placeholder( $content ) {
		$this->has_placeholder = true;
		return $content . GDGT_Databox::wrap_in_newlines( GDGT_Databox::placeholder );
	}

	/**
	 * Remove the placholder if set
	 *
	 * @since 1.2
	 * @param string $content the post content
	 * @return string post content with placeholder removed
	 */
	public function remove_placeholder( $content ) {
		if ( isset( $this->has_placeholder ) && $this->has_placeholder === true )
			return str_replace( GDGT_Databox::placeholder, '', $content, GDGT_Databox::$placeholder_count );
		return $content;
	}

	/**
	 * Possibly display a gdgt Databox after the post content
	 *
	 * @since 1.2
	 * @param string $content the post content
	 * @return string post content with possible databox appended
	 */
	private function databox_content() {
		global $post;

		if ( ! isset( $post ) )
			return '';

		$post_id = absint( $post->ID );
		if ( $post_id === 0 )
			return '';

		$cache_key = GDGT_Databox::cache_key( $post_id );
		$databox = get_transient( $cache_key );
		if ( empty( $databox ) )
			$databox = GDGT_Databox::generate_databox( array(), true, $cache_key );
		unset( $cache_key );

		return trim( $databox );
	}

	/**
	 * Add Databox content in place or replacing a previous placeholder
	 *
	 * @since 1.0
	 * @param string $content the post content
	 * @return string post content with possible databox appended and placeholder removed
	 */
	public function after_content( $content ) {
		$databox = $this->databox_content();
		if ( empty( $databox ) ) {
			$databox = '';
		} else if ( ! is_feed() ) {
			if ( ! $this->js_loaded )
				add_action( 'wp_footer', array( &$this, 'print_scripts' ) );
			if ( ! $this->css_loaded )
				$databox = '<script type="text/javascript">(function(d){var id="gdgt-databox-css";if(d.getElementById(id)){return;}var css=d.createElement("link");css.id=id;css.rel="stylesheet";css.type="text/css";css.href=' . json_encode( plugins_url( 'static/css/databox.css', __FILE__ ) . '?ver=1.31' ) . ';var ref=d.getElementsByTagName("head")[0];ref.appendChild(css);}(document));</script>' . $databox;
		}

		// do we need to replace a placeholder?
		if ( ! empty( $content ) && isset( $this->has_placeholder ) && $this->has_placeholder === true )
			return str_replace( GDGT_Databox::placeholder, $databox, $content, GDGT_Databox::$placeholder_count );

		if ( $databox )
			return $content . GDGT_Databox::wrap_in_newlines( $databox );

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
			$shortcode_priority = 11;
			// prevent double output
			if ( is_feed() ) {
				add_filter( 'the_content_feed', array( &$this, 'remove_placeholder' ), $shortcode_priority + 1 );
				remove_filter( 'the_content_feed', array( &$this, 'after_content' ), $this->content_priority, 1 );
			} else {
				add_filter( 'the_content', array( &$this, 'remove_placeholder' ), $shortcode_priority + 1 );
				remove_filter( 'the_content', array( &$this, 'after_content' ), $this->content_priority, 1 );
			}
			return $this->after_content( '' );
		}

		return '';
	}
}
?>
