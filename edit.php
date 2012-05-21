<?php

if ( ! class_exists( 'GDGT_Base' ) )
	require_once( dirname( __FILE__ ) . '/base.php' );

/**
 * Add a gdgt product editor to the WordPress edit post screen
 *
 * @since 1.0
 */
class GDGT_Post_Meta_Box extends GDGT_Base {

	/**
	 * HTML ID of the meta box
	 *
	 * @var string
	 * @since 1.0
	 */
	const BASE_ID = 'gdgt-product-selector';

	/**
	 * Share a hashed secret between the post page and the save page to prove source
	 *
	 * @var string
	 * @since 1.0
	 */
	const NONCE = 'gdgt-postmeta-nonce';

	/**
	 * All post meta keys used by the postbox.
	 * Used for uninstall cleanup
	 *
	 * @var array
	 * @since 1.0
	 */
	public static $all_meta_keys = array( 'gdgt-disabled', 'gdgt-products-include', 'gdgt-products-exclude' );

	/**
	 * Load product selector meta box on post edit screen load if no stop tags present
	 *
	 * @since 1.0
	 */
	public function __construct() {
		add_action( 'load-post-new.php', array( &$this, 'load' ) );
		add_action( 'load-post.php', array( &$this, 'maybe_load' ) );
	}

	/**
	 * Attach actions when post loaded
	 *
	 * @since 1.3
	 */
	public function load() {
		add_action( 'add_meta_boxes', array( &$this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( &$this, 'process_saved_data' ) );
		foreach ( array( 'post-new.php', 'post.php' ) as $page ) {
			add_action( 'admin_print_scripts-' . $page, array( &$this, 'enqueue_scripts' ) );
			add_action( 'admin_print_styles-' . $page, array( &$this, 'enqueue_styles') );
			add_action( 'admin_head-' . $page, array( &$this, 'add_help_tab' ) );
		}
	}

	/**
	 * Check if stop tags are present for a post before displaying a product selector metabox
	 *
	 * @since 1.3
	 */
	public function maybe_load() {
		if ( isset( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] );
			if ( $post_id ) {
				if ( ! class_exists( 'GDGT_Databox' ) )
					include_once( dirname( __FILE__ ) . '/databox.php' );
				if ( GDGT_Databox::stop_tag_exists( $post_id ) )
					return;
			}
		}
		$this->load();
	}

	/**
	 * Display help documentation in edit and add post screens
	 * Hide help documentation if user has hidden the referenced metabox at the time of pageload
	 *
	 * @since 1.3
	 */
	public function add_help_tab() {
		$screen = get_current_screen();
		if ( ! method_exists( $screen, 'add_help_tab' ) )
			return;
		$hidden_post_boxes = maybe_unserialize( get_user_option( 'metaboxhidden_post' ) );
		if ( ! empty( $hidden_post_boxes ) && is_array( $hidden_post_boxes ) && in_array( GDGT_Post_Meta_Box::BASE_ID, $hidden_post_boxes, true ) )
			return;
		unset( $hidden_post_boxes );
		$max_products = absint( get_option( 'gdgt_max_products', 10 ) );
		$screen->add_help_tab( array(
			'id' => GDGT_Post_Meta_Box::BASE_ID . '-help',
			'title' => GDGT_Post_Meta_Box::PLUGIN_NAME,
			'content' => '<p>' . esc_html( sprintf( __( 'The %1$s builds a list of up to %2$u products by matching products based on your post tags or when you manually include a product in the post editor.', GDGT_Post_Meta_Box::PLUGIN_SLUG ), GDGT_Post_Meta_Box::PLUGIN_NAME, $max_products ) ) . '</p><p>' . esc_html( sprintf( __( 'You can manually add products by entering text into keyword search tool located at the bottom of the %s editor and selecting a product from the list of results. If your post is related to a specific product configuration or special edition you may wish to select that model from the list (e.g. tablet with mobile broadband vs. tablet with only Wi-Fi).', GDGT_Post_Meta_Box::PLUGIN_SLUG ), GDGT_Post_Meta_Box::PLUGIN_NAME ) ) . '</p><p>' . esc_html( sprintf( __( 'Products may also be manually excluded from automatic matching by manually adding that product to your %1$s list, then clicking the %2$s button.', GDGT_Post_Meta_Box::PLUGIN_SLUG ), __( 'Displayed', GDGT_Post_Meta_Box::PLUGIN_SLUG ), __( 'Delete', GDGT_Post_Meta_Box::PLUGIN_SLUG ) ) ) . '</p>'
		) );
	}

	/**
	 * Delete stored post metadata related to the plugin
	 *
	 * @since 1.0
	 * @param int $post_id post identifier
	 */
	public static function delete_post_meta( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 )
			return;

		foreach( GDGT_Post_Meta_Box::$all_meta_keys as $meta_key ) {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	/**
	 * Add gdgt meta box to edit post
	 *
	 * @since 1.0
	 * @uses add_meta_box()
	 */
	public function add_meta_boxes() {
		add_meta_box( GDGT_Post_Meta_Box::BASE_ID, GDGT_Post_Meta_Box::PLUGIN_NAME, array( &$this, 'product_selector' ), 'post', 'side' );
	}

	/**
	 * Queue up a script for use in the post meta box
	 *
	 * @since 1.0
	 * @uses wp_enqueue_script()
	 * @param string hook name. scope the enqueue to just the admin pages we care about
	 */
	public function enqueue_scripts() {
		// if jQuery not present load from Google CDN
		wp_enqueue_script( 'jquery', is_ssl() ? 'https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' : 'http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js', array(), null );
		// handle case of no jQuery UI autocomplete in WordPress 3.2. load jQuery UI autocomplete 3.2.1 to match 3.2 version
		wp_enqueue_script( 'jquery-ui-autocomplete', plugins_url( 'static/js/jquery/ui/jquery.ui.autocomplete.min.js', __FILE__ ), array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position' ), '1.8.12' );

		$js_filename = 'gdgt-product-selector.js';
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG === true )
			$js_filename = 'gdgt-product-selector.dev.js';
		wp_enqueue_script( GDGT_Post_Meta_Box::BASE_ID . '-js', plugins_url( 'static/js/' . $js_filename, __FILE__ ), array( 'jquery-ui-widget', 'jquery-ui-autocomplete', 'jquery-ui-sortable' ), GDGT_Post_Meta_Box::PLUGIN_VERSION, true );
	}

	/**
	 * Queue a CSS stylesheet to load with the post page.
	 *
	 * @since 1.0
	 * @uses wp_enqueue_style()
	 */
	public function enqueue_styles() {
		wp_enqueue_style( GDGT_Post_Meta_Box::BASE_ID . '-css', plugins_url( 'static/css/gdgt-product-selector.css', __FILE__ ), array(), GDGT_Post_Meta_Box::PLUGIN_VERSION );
	}

	/**
	 * Build the markup used in the post box
	 *
	 * @since 1.0
	 * @uses wp_nonce_field()
	 */
	public function product_selector() {
		global $post;

		// verify request
		wp_nonce_field( plugin_basename( __FILE__ ), GDGT_Post_Meta_Box::NONCE );

		if ( ! empty( $post ) && isset( $post->ID ) )
			$post_id = absint( $post->ID );
		else
			$post_id = 0;

		// it's possible the module is disabled, triggering a read-only mode
		$readonly = false;

		if ( $post_id > 0 && get_post_meta( $post_id, 'gdgt-disabled', true ) == '1' )
			$readonly = true;

		/* Restrict disable module to users who can at least publish a post.
		 * Contributors who submit a post for approval won't see this option.
		 */
		if ( current_user_can( get_option( 'gdgt_min_disable_capability', 'edit_posts') ) ) {
			$disable_id = 'gdgt-products-readonly';
			echo '<div class="' . GDGT_Post_Meta_Box::BASE_ID . '-disable-module' . '">';
			echo '<input type="checkbox" id="' . $disable_id . '" name="' . $disable_id . '" value="1" ';
			checked( $readonly );
			echo ' /> <label for="' . $disable_id . '">' . __( 'Disable Databox on this post', GDGT_Post_Meta_Box::PLUGIN_SLUG ) .  '</label>';
			echo '</div>';
			unset( $disable_id );
		}

		$labels = array(
			'display' => __( 'Display', GDGT_Post_Meta_Box::PLUGIN_SLUG ),
			'displayed' => __( 'Displayed', GDGT_Post_Meta_Box::PLUGIN_SLUG ),
			'remove' => __( 'Delete', GDGT_Post_Meta_Box::PLUGIN_SLUG ),
			'removed' => __( 'Deleted', GDGT_Post_Meta_Box::PLUGIN_SLUG ),
			'typeahead' => __( 'Add a product manually:', GDGT_Post_Meta_Box::PLUGIN_SLUG ),
			'typeahead_placeholder' => __( 'Start typing...', GDGT_Post_Meta_Box::PLUGIN_SLUG ),
			'invalid_key' => __( 'Invalid API key.', GDGT_Post_Meta_Box::PLUGIN_SLUG ),
			'no_results' => __( 'No results found.', GDGT_Post_Meta_Box::PLUGIN_SLUG )
		);
		$displayed_product_slugs = array();
		$deleted_product_slugs = array();
		$tags = array();
		$products_body = '';

		if ( $post_id > 0 ) {

			// pass post tag strings into JS
			// why not do this on the client side? post meta box might not be there but might be useful to update product selector based on tags
			$post_tags = get_the_tags();
			if ( ! empty( $post_tags ) ) {
				foreach( $post_tags as $tag ) {
					if ( ! isset( $tag->name ) )
						continue;
					$tag_name = trim( $tag->name );
					if ( ! empty( $tag_name ) && ! in_array( $tag_name, $tags, true ) ) {
						$tags[] = $tag_name;
					}
					unset( $tag_name );
				}
			}
			unset( $post_tags );

			$displayed_products = maybe_unserialize( get_post_meta( $post_id, 'gdgt-products-include', true ) );
			if ( ! empty( $displayed_products ) && is_array( $displayed_products ) ) {
				$num_products = count( $displayed_products );
				$products_body .= '<div>';
				$products_body .= '<h4>' . esc_html( $labels['displayed'] ) . '</h4><ol id="' . GDGT_Post_Meta_Box::BASE_ID . '-displayed-products">';
				for ( $i=0; $i < $num_products; $i++ ) {
					$product = $displayed_products[$i];
					if ( empty( $product ) || ! array_key_exists( 'slug', $product ) || ! array_key_exists( 'name', $product ) )
						continue;
					$displayed_product_slugs[] = $product['slug'];
					$products_body .= '<li class="gdgt-product"><span class="gdgt-drag-handle"><span class="gdgt-product-name">' . esc_html( $product['name'] ) . '</span></span>';
					$products_body .= '<input type="hidden" class="gdgt-product-slug" name="gdgt-product[' . $i . '][slug]" value="' . esc_attr( $product['slug'] ) . '" />';
					$products_body .= '<input type="hidden" class="gdgt-product-name" name="gdgt-product[' . $i . '][name]" value="' . esc_attr( $product['name'] ) . '" />';
					if ( isset( $product['instances'] ) ) {
						$products_body .= '<input type="hidden" class="gdgt-product-instances" name="gdgt-product[' . $i . '][instances]" value="' . esc_attr( $product['instances'] ) . '" />';
						$products_body .= '<ul class="gdgt-product-instances">';
						foreach( explode( ',', $product['instances'] ) as $instance_name ) {
							$products_body .= '<li>' . esc_html( $instance_name ) . '</li>';
						}
						$products_body .= '</ul>';
					} else if ( isset( $product['parent'] ) ) {
						$products_body .= '<input type="hidden" class="gdgt-product-parent" name="gdgt-product[' . $i . ']"[parent]" value="' . esc_attr( $product['parent'] ) . '" />';
					}
					$products_body .= '</li>';
					unset( $product );
				}
				unset( $num_products );
				$products_body .= '</ol></div>';
			}
			unset( $displayed_products );

			$deleted_products = maybe_unserialize( get_post_meta( $post_id, 'gdgt-products-exclude', true ) );
			if ( ! empty( $deleted_products ) && is_array( $deleted_products ) ) {
				$num_deleted = count( $deleted_products );
				$products_body .= '<div>';
				$products_body .= '<h4>'. __( 'Deleted', GDGT_Post_Meta_Box::PLUGIN_SLUG ) . '</h4><ul id="' . GDGT_Post_Meta_Box::BASE_ID . '-deleted-products">';
				for ( $i=0; $i < $num_deleted; $i++ ) {
					$product = $deleted_products[$i];
					if ( empty( $product ) || ! array_key_exists( 'slug', $product ) || ! array_key_exists( 'name', $product ) )
						continue;
					$deleted_product_slugs[] = $product['slug'];
					$products_body .= '<li class="gdgt-product"><span class="gdgt-product-name">' . esc_html( $product['name'] ) . '</span>';
					$products_body .= '<input type="hidden" class="gdgt-product-slug" name="gdgt-product-deleted[' . $i . '][slug]" value="' . esc_attr( $product['slug'] ) . '" />';
					$products_body .= '<input type="hidden" class="gdgt-product-name" name="gdgt-product-deleted[' . $i . '][name]" value="' . esc_attr( $product['name'] ) . '" />';
					if ( isset( $product['instances'] ) ) {
						$products_body .= '<input type="hidden" class="gdgt-product-instances" name="gdgt-product-deleted[' . $i . '][instances]" value="' . esc_attr( $product['instances'] ) . '" />';
						$products_body .= '<ul class="gdgt-product-instances">';
						foreach( explode( ',', $product['instances'] ) as $instance_name ) {
							$products_body .= '<li>' . esc_html( $instance_name ) . '</li>';
						}
						$products_body .= '</ul>';
					} else if ( isset( $product['parent'] ) ) {
						$products_body .= '<input type="hidden" class="gdgt-product-parent" name="gdgt-product-deleted[' . $i . '][parent]" value="' . esc_attr( $product['parent'] ) . '" />';
					}
					$products_body .= '</li>';
					unset( $product );
				}
				unset( $num_deleted );
				$products_body .= '</ul></div>';
			}
			unset( $deleted_products );
		}

		echo '<div id="' . GDGT_Post_Meta_Box::BASE_ID . '-results">';
		if ( empty( $products_body ) )
			echo '<p id="' . GDGT_Post_Meta_Box::BASE_ID . '-results-placeholder">' . esc_html( __( 'No results found.', GDGT_Post_Meta_Box::PLUGIN_SLUG ) ) . '</p>';
		else
			echo $products_body;
		echo '</div>';
		unset( $products_body );

		echo '<script type="text/javascript">jQuery( "#gdgt-product-selector" ).one( "gdgt-product-selector-onload", function(){';
		foreach( array(
			'readonly' => $readonly,
			'search_by_keyword_endpoint' => plugins_url( 'search-by-keyword.php', __FILE__ ),
			'search_by_tag_endpoint' => plugins_url( 'search-by-tag.php', __FILE__ ),
			'loading_spinner_image' => array( 'url' => admin_url( 'images/loading.gif' ), 'width' => 16, 'height' => 16 ),
			'displayed_products' => $displayed_product_slugs,
			'deleted_products' => $deleted_product_slugs,
			'post_tags' => $tags, // sort on client-side for consistency
			'labels' => $labels
		) as $var => $value ) {
			echo 'gdgt.product_selector.' . $var . '=' . json_encode( $value ) . ';';
		}
		echo '});</script>';
	}

	/**
	 * Return a list of products based on post tags
	 *
	 * @since 1.0
	 * @param array $tags tag list
	 * @return array products
	 */
	private function products_search_by_tags( array $tags ) {
		if ( empty( $tags ) )
			return array();

		if ( ! class_exists( 'GDGT_Product_Search' ) )
			include_once( dirname( __FILE__ ) . '/product-search.php' );
		$products = GDGT_Product_Search::search_by_tags( $tags );
		if ( ! is_array( $products ) || empty( $products ) )
			return array();

		return $this->clean_product_array( $products );
	}

	/**
	 * Test if a slug meets some minimal conformance
	 * Check only for the existence of a forward slash in the slug. Current slugs are {company}/{product}
	 *
	 * @since 1.0
	 * @param string $slug product or instance slug
	 * @return bool true if is string with a slash
	 */
	public static function is_valid_slug( $slug ) {
		if ( is_string( $slug ) && ! empty( $slug ) && strpos( $slug, '/' ) !== false )
			return true;
		return false;
	}

	/**
	 * Iterate through a list of products while checking for slug uniqueness
	 * Create a new array with only the associative array keys we care about
	 * Return list of cleaned products with keys: slug, name, (optional) instances, (optional) parent
	 *
	 * @since 1.0
	 * @param array $products list of products
	 * @param bool $allow_instances true if instances allowed as part of array. default false
	 * @return array $products list of products
	 */
	private function clean_product_array( array $products, $allow_instances = false ) {
		$clean_products = array();
		foreach( $products as $product ) {
			// need at least a slug for products_include and a display name
			if ( ! isset( $product['slug'] ) || ! GDGT_Post_Meta_Box::is_valid_slug( $product['slug'] ) || ! isset( $product['name'] ) || ( $allow_instances === false && isset( $product['parent'] ) ) )
				continue;

			$clean_product = array(
				'slug' => trim( $product['slug'] ),
				'name' => trim( $product['name'] )
			);

			// use parent slug instead of product slug for comparisons between product instances and a parent
			if ( isset( $product['parent'] ) ) {
				$product['parent'] = trim( $product['parent'] );
				if ( empty( $product['parent'] ) || ! GDGT_Post_Meta_Box::is_valid_slug( $product['parent'] ) || in_array( $product['parent'], $this->processed_product_slugs ) )
					continue;
				$this->processed_product_slugs[] = $product['parent'];
				$clean_product['parent'] = $product['parent'];
				
			} else {
				if ( in_array( $clean_product['slug'], $this->processed_product_slugs ) )
					continue;
				$this->processed_product_slugs[] = $clean_product['slug'];
			}
			if ( isset( $product['instances'] ) )
				$clean_product['instances'] = trim( $product['instances'] );

			$clean_products[] = $clean_product;
			unset( $clean_product );
		}
		return $clean_products;
	}

	/**
	 * Create a Tag URI based on the site URL, blog ID, and post ID
	 *
	 * @since 1.0
	 * @link http://tools.ietf.org/html/rfc4151 RFC 4151 - Tag URI
	 * @return string Tag URI
	 */
	private function post_tag_uri() {
		$authority = parse_url( get_site_url(), PHP_URL_HOST );
		if ( empty( $authority ) || in_array( $authority, array( 'localhost', '127.0.0.1' ), true ) ) {
			$admin_email = get_bloginfo( 'admin_email' );
			if ( empty( $admin_email ) )
				$authority = 'postbox-nodata.gdgt.com'; // fake it. flag it serverside
			else
				$authority = $admin_email;
			unset( $admin_email );
		}
		return 'tag:' . $authority . ',2012:site-' . absint( get_current_blog_id() ) . ':gdgt-post-' . absint( get_the_ID() );
	}

	/**
	 * Build up a post data array for use in the gdgt API product/module call
	 *
	 * @since 1.0
	 * @return array post data
	 */
	private function product_module_individual_post_data() {
		global $post;

		$post_data = array(
			'post_uuid' => $this->post_tag_uri()
		);

		$post_title = get_the_title();
		if ( ! empty( $post_title ) )
			$post_data['post_title'] = trim( $post_title );
		unset( $post_title );

		$post_url = get_permalink();
		if ( ! empty( $post_url ) )
			$post_data['post_url'] = $post_url;

		$pubdate = get_the_date( 'c' );
		if ( ! empty( $pubdate ) )
			$post_data['post_pub_date'] = $pubdate;

		if ( function_exists( 'has_post_thumbnail' ) && function_exists( 'wp_get_attachment_image_src' ) && function_exists( 'get_post_thumbnail_id' ) && has_post_thumbnail() ) {
			$image_url = esc_url_raw( wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'full' ), array( 'http', 'https' ) );
			if ( ! empty( $image_url ) )
				$post_data['post_image'] = $image_url;
			unset( $image_url );
		}

		$content = trim( get_the_content() );
		if ( ! empty( $content ) )
			$post_data['post_content'] = $content;
		else if ( isset( $post ) && isset( $post->post_content ) && ! empty( $post->post_content ) )
			$post_data['post_content'] = trim( $post->post_content );
		unset( $content );

		return $post_data;
	}

	/**
	 * Send full post data to gdgt API
	 *
	 * @since 1.0
	 */
	private function ping_gdgt() {
		global $post;

		if ( ! isset( $post ) || ! empty( $post->post_password ) || $post->post_type !== 'post' )
			return;

		$post_id = absint( $post->ID );
		if ( $post_id < 1 )
			return;

		$post_status = get_post_status_object( $post->post_status );
		if ( empty( $post_status ) || ! isset( $post_status->public ) || $post_status->public !== true )
			return;
		unset( $post_status );

		if ( ! class_exists( 'GDGT_Databox' ) )
			require_once( dirname(__FILE__) . '/databox.php' );
		if ( GDGT_Databox::stop_tag_exists() )
			return;

		$params = $this->product_module_individual_post_data();
		if ( empty( $params ) )
			return;

		$cache_key = GDGT_Databox::cache_key( $post_id );
		delete_transient( $cache_key );
		GDGT_Databox::generate_databox( $params, false, $cache_key );
	}

	/**
	 * Process manual product actions from the gdgt post meta box and append products based on active tags
	 * If gdgt meta box is not present then gdgt nonce will not be present and the function will return.
	 * Only store products returned by tag search if gdgt module is on the page. Otherwise rely on tags passed to product/module API.
	 *
	 * @since 1.0
	 * @param int post_id post identifier
	 */
	public function process_saved_data( $post_id ) {
		// do not process on autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		$post_id = absint( $post_id );
		if ( $post_id < 1 || wp_is_post_revision( $post_id ) != false )
			return;

		// verify the request came from the meta box by checking for our nonce
		if ( ! array_key_exists( GDGT_Post_Meta_Box::NONCE, $_POST ) || ! wp_verify_nonce( $_POST[GDGT_Post_Meta_Box::NONCE], plugin_basename(__FILE__) ) )
			return;

		// check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) )
			return;

		/* Is the post box hidden?
		 * A bit tricky since we might want to populate data so it's visible and not disabled when they remove the post box from their hidden list
		 * If we are able to detect the box was never shown then stop processing. Especially if we were thinking about processing tags separately.
		 */
		$hidden_post_boxes = maybe_unserialize( get_user_option( 'metaboxhidden_post' ) );
		if ( ! empty( $hidden_post_boxes ) && is_array( $hidden_post_boxes ) && in_array( GDGT_Post_Meta_Box::BASE_ID, $hidden_post_boxes, true ) ) {
			return;
		}

		if ( ! class_exists( 'GDGT_Databox' ) )
			require_once( dirname(__FILE__) . '/databox.php' );
		if ( GDGT_Databox::stop_tag_exists() )
			return;

		// Is the plugin explicitly disabled for this post?
		if ( array_key_exists( 'gdgt-products-readonly', $_POST ) && $_POST['gdgt-products-readonly'] == '1' ) {
			update_post_meta( $post_id, 'gdgt-disabled', '1' );
			return;
		} else {
			update_post_meta( $post_id, 'gdgt-disabled', '0' );
		}

		// set property for comparison between include and exclude product lists
		$this->processed_product_slugs = array();

		// process deleted products (excludes)
		// process first to block any other products
		$deleted_products = array();
		if ( array_key_exists( 'gdgt-product-deleted', $_POST ) && is_array( $_POST['gdgt-product-deleted'] ) ) {
			$deleted_products = $this->clean_product_array( $_POST['gdgt-product-deleted'] );
			if ( empty( $deleted_products ) )
				$deleted_products = array();
		}
		update_post_meta( $post_id, 'gdgt-products-exclude', $deleted_products );
		unset( $deleted_products );

		// Process our display fields (includes)
		$displayed_products = array();
		if ( array_key_exists( 'gdgt-product', $_POST ) && is_array( $_POST['gdgt-product'] ) ) {
			$displayed_products = $this->clean_product_array( $_POST['gdgt-product'], true );

			if ( empty( $displayed_products ) )
				$displayed_products = array();
		}
		update_post_meta( $post_id, 'gdgt-products-include', $displayed_products );
		unset( $displayed_products );

		$this->ping_gdgt();
	}
}

?>