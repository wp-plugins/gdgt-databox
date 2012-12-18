<?php

/**
 * Display a single product component of the gdgt Databox
 *
 * @since 1.0
 */
class GDGT_Databox_Product {
	public $tab_class_names = array( 'two', 'three' );

	public $currency_symbols = array( 'USD' => '$', 'EUR' => '&#8364;' );

	/**
	 * Process the stdClass result from json_decode into class variables we will use to build our template
	 *
	 * @since 1.0
	 * @param stdClass $product Product component of the gdgt API JSON response
	 * @param bool $collapsed_only process only properties needed for a collapsed product view
	 */
	public function __construct( $product, $collapsed_only = false ) {
		if ( isset( $product->title_url ) )
			$this->url = $product->title_url;

		if ( isset( $product->product_uuid ) )
			$this->id = $product->product_uuid;
		if ( isset( $product->product_name ) )
			$this->name = $product->product_name;
		if ( isset( $product->company_name ) ) {
			$company = new stdClass();
			$company->name = $product->company_name;
			if ( isset( $product->company_url ) )
				$company->url = $product->company_url;
			$this->company = $company;
			unset( $company );
		}

		if ( $collapsed_only === true ) {
			$this->collapsed = true;
			return;
		} else {
			$this->collapsed = false;
		}

		if ( isset( $product->title ) )
			$this->full_name = $product->title;

		// we only use the 50x50 image
		if ( isset( $product->product_images ) ) {
			$property = '50x50'; // property name starts with digit. work around direct reference issues
			if ( property_exists( $product->product_images, $property ) ) {
				$image = new stdClass();
				$image->width = $image->height = 50;
				$image->src = $product->product_images->$property;
				$this->image = $image;
				unset( $image );
			}
			unset( $property );
		}

		/*
		 * only store tabs we know about and the publisher has accepted
		 * note the selected tab for initial view state
		 */
		$this->tabs = array();
		if ( isset( $product->tabs ) ) {
			if ( isset( $product->tabs->key_specs ) && (bool) get_option( 'gdgt_specs_tab', true ) ) {
				if ( isset( $product->tabs->key_specs->selected ) && $product->tabs->key_specs->selected === true )
					$this->selected_tab = 'key_specs';
				unset( $product->tabs->key_specs->selected );

				if ( isset( $product->tabs->key_specs->see_all_specs_url ) ) {
					$product->tabs->key_specs->url = $product->tabs->key_specs->see_all_specs_url;
					unset( $product->tabs->key_specs->see_all_specs_url );
				}

				$this->tabs['key_specs'] = $product->tabs->key_specs;
			}
			if ( isset( $product->tabs->user_reviews ) && (bool) get_option( 'gdgt_reviews_tab', true ) ) {
				if ( isset( $product->tabs->user_reviews->selected ) && $product->tabs->user_reviews->selected === true )
					$this->selected_tab = 'user_reviews';
				unset( $product->tabs->user_reviews->selected );

				if ( isset( $product->tabs->user_reviews->review_landing_url ) ) {
					$product->tabs->user_reviews->url = $product->tabs->user_reviews->review_landing_url;
					unset( $product->tabs->user_reviews->review_landing_url );
				}

				if ( isset( $product->tabs->user_reviews->write_review_url ) ) {
					$product->tabs->user_reviews->write_url = $product->tabs->user_reviews->write_review_url;
					unset( $product->tabs->user_reviews->write_review_url );
				}

				$this->tabs['user_reviews'] = $product->tabs->user_reviews;
			}
			if ( isset( $product->tabs->prices ) && (bool) get_option( 'gdgt_prices_tab', true ) && isset( $product->tabs->prices->merchants ) && ! empty( $product->tabs->prices->merchants ) ) {
				if ( isset( $product->tabs->prices->selected ) && $product->tabs->prices->selected === true )
					$this->selected_tab = 'prices';
				unset( $product->tabs->prices->selected );

				// point prices tab data to gdgt product page as a backup URL
				if ( ! isset( $product->tabs->prices->url ) && isset( $this->url ) )
					$product->tabs->prices->url = $this->url;

				$this->tabs['prices'] = $product->tabs->prices;

				if ( isset( $product->lowest_price ) && isset( $product->lowest_price->amount ) && isset( $product->lowest_price->currency ) ) {
					$price = new stdClass();
					$lowest_price = null;
					try {
						$lowest_price = absint( $product->lowest_price->amount );
					} catch ( Exception $e ) {}
			
					if ( is_numeric( $lowest_price ) ) {
						$price->amount = $lowest_price;
						if ( is_string( $product->lowest_price->currency ) && array_key_exists( $product->lowest_price->currency, $this->currency_symbols ) ) {
							$price->currency = $product->lowest_price->currency;
							if ( isset( $product->lowest_price->is_on_contract ) && $product->lowest_price->is_on_contract === true )
								$price->is_on_contract = true;
							else
								$price->is_on_contract = false;
							$this->price = $price;
						}
					}
					unset( $lowest_price );
					unset( $price );
				}
			} else {
				$this->tabs['prices'] = false;
			}

			/**
			 * Make it easy to test by selected tab
			 *
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true && array_key_exists( 'tab', $_GET ) && in_array( $_GET['tab'], array( 'key_specs','user_reviews', 'prices' ), true ) )
				$this->selected_tab = $_GET['tab']; */

			if ( ! isset( $this->selected_tab ) && ! empty( $this->tabs ) ) {
				foreach ( array_keys( $this->tabs ) as $tab_name ) {
					if ( ! empty( $this->tabs[$tab_name] ) ) {
						$this->selected_tab = $tab_name;
						break;
					}
				}

				// if all empty then choose first
				if ( ! isset( $this->selected_tab ) )
					$this->selected_tab = key( $this->tabs );
			}
		}

		/*
		 * a product may have sub-instances describing unique configurations
		 * the instance key appears for products with no instances with a null title
		 */
		if ( isset( $product->instances ) && is_array( $product->instances ) ) {
			$instances = array();
			foreach( $product->instances as $instance ) {
				/* 
				 * default instance can be a single instance in a list of instances or a product without instances
				 * used to store specs
				 */
				$is_default_instance = false;
				if ( isset( $instance->selected ) && $instance->selected === true ) {
					$is_default_instance = true;
					if ( array_key_exists( 'key_specs', $this->tabs ) && isset( $instance->key_specs ) && is_array( $instance->key_specs ) )
						$this->tabs['key_specs']->specs = $instance->key_specs;
				}

				// products with instances should name the instance and link to instance specs
				if ( isset( $instance->title ) && ! empty( $instance->title ) && isset( $instance->url ) && ! empty( $instance->url ) ) {
					$i = new stdClass();
					$i->name = trim( $instance->title );
					$i->url = trim( $instance->url );
					if ( $is_default_instance )
						$i->selected = true;
					$instances[] = $i;
					unset( $i );
				}
				unset( $is_default_instance );
			}
			if ( ! empty( $instances ) )
				$this->instances = $instances;
		}
	}

	/**
	 * Build a tab link dependent on whether a link exists for the tab, the tab has a count, and the markup is self-contained
	 *
	 * @since 1.0
	 * @param stdClass $tab_data data for the tab
	 * @param string $text tab label, inner text
	 * @param string $anchor_target extra attributes added to the end of an anchor such as targets
	 * @param bool $self_contained wrap links in <noscript> or assume no JS present and directly output links
	 */
	private function tab_link( $tab_data, $text, $selected = false, $anchor_target = '', $self_contained = false ) {
		if ( empty( $text ) )
			return '';

		$anchor = '';
		if ( $selected === false && isset( $tab_data->url ) )
			$anchor = '<a href="' . esc_url( $tab_data->url, array( 'http' ) ) . '" title="' . esc_attr( $this->full_name . ' ' . strip_tags( $text ) ) . '"' . $anchor_target . '>';

		$html = $text;
		if ( isset( $tab_data->total_count ) )
			$html .= ' <span class="dot">&#8226;</span> <span class="count">' . number_format_i18n( absint( $tab_data->total_count ), 0 ) . '</span>';

		if ( ! empty( $anchor ) ) {
			if ( $self_contained === false )
				return '<noscript>' . $anchor . '</noscript>' . $html . '<noscript></a></noscript>';
			else
				return $anchor . $html . '</a>';
		}

		return $html;
	}

	/**
	 * Generate HTML markup for the current product
	 *
	 * @since 1.0
	 * @param bool $is_expanded output HTML for a product in an "expanded" state in its initial view
	 * @param int $position product position. currently used to exclude collapsed name output from first, non-collapsible
	 * @return string HTML markup
	 */
	public function render( $is_expanded = false, $position = 1 ) {
		$position = absint( $position );
		$schema_org = (bool) get_option( 'gdgt_schema_org', true );
		$self_contained = false;
		$is_expanded = (bool) $is_expanded;
		$collapsed_summary_exists = true;
		if ( $position === 1 && $is_expanded === true )
			$collapsed_summary_exists = false;

		// allow override of default browsing context
		$browsing_context = apply_filters( 'gdgt_databox_browsing_context', '_blank' );
		// limit browsing context to special keywords
		if ( ! in_array( $browsing_context, array( '', '_blank', '_self', '_parent', '_top' ), true ) )
			$browsing_context = '_blank';
		if ( $browsing_context === '' )
			$anchor_target = '';
		else
			$anchor_target = ' target="' . $browsing_context . '"';
		unset( $browsing_context );

		$html = '<div class="gdgt-product ';
		if ( isset( $this->id ) )
			$html .= sanitize_html_class( 'gdgt-product-' . $this->id ) . ' ';
		if ( $is_expanded === true )
			$html .= 'expanded" aria-expanded="true"';
		else
			$html .= 'collapsed"';
		if ( $schema_org === true )
			$html .= ' itemscope itemtype="http://schema.org/Product"';
		$html .= ' role="tab">';
		if ( $collapsed_summary_exists === true ) {
			$collapsed_name = '<p class="gdgt-product-collapsed-name"';
			if ( $is_expanded === true )
				$collapsed_name .= ' style="display:none"';
			$collapsed_name .= ' aria-label="' . esc_attr( $this->company->name ) . ' ' . esc_attr( $this->name ) . '">';
			$collapsed_name .= '<noscript><a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '"></noscript>';
			if ( $schema_org === true ) {
				if ( isset( $this->company->url ) ) {
					$html .= '<link itemprop="brand" href="' . esc_url( $this->company->url , array( 'http', 'https' ) ) . '" title="' . esc_attr( $this->company->name ) . '" />';
					$collapsed_name .= esc_html( $this->company->name ) . ' ';
				} else {
					$collapsed_name .= '<span itemprop="brand" itemscope itemtype="http://schema.org/Corporation"><span itemprop="name">' . esc_html( $this->company->name ) . '</span></span> ';
				}
				$collapsed_name .= '<strong itemprop="model">' . esc_html( $this->name ) . '</strong>';
			} else {
				$collapsed_name .= esc_html( $this->company->name ) . ' <strong>' . esc_html( $this->name ) . '</strong>';
			}
			$collapsed_name .= '<noscript></a></noscript></p>';
			$html .= $collapsed_name;
			unset( $collapsed_name );
		} else if ( $schema_org === true ) {
			if ( isset( $this->company->url ) )
				$html .= '<link itemprop="brand" href="' . esc_url( $this->company->url , array( 'http', 'https' ) ) . '" title="' . esc_attr( $this->company->name ) . '" />';
		}
		$html .= '<div class="gdgt-product-wrapper" role="tabpanel" ';
		if ( $is_expanded === true )
			$html .= 'aria-hidden="false"';
		else
			$html .= 'aria-hidden="true" style="display:none"';
		$html .= '><div class="gdgt-product-head ';
		if ( isset( $this->price ) && isset( $this->price->amount ) )
			$html .= 'gdgt-price-' . strlen( (string) absint( $this->price->amount ) ) . 'digits';
		else
			$html .= 'gdgt-no-price';
		$html .= '"><div class="gdgt-product-name">';
		if ( isset( $this->image ) ) {
			$html .= '<a class="gdgt-product-image" href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '" title="' . esc_attr( $this->full_name ) . '"' . $anchor_target . '>';
			$img = '<img alt="' . esc_attr( sprintf( __( '%s thumbnail image', 'gdgt-databox' ), $this->full_name ) ) . '" src="' . esc_url( $this->image->src, array( 'http', 'https' ) ) . '" width="' . $this->image->width . '" height="' . $this->image->height . '"';
			if ( $schema_org === true )
				$img .= ' itemprop="image"';
			$img .= ' />';
			if ( $self_contained === false )
				$html .= '<noscript class="img" data-html="' . esc_attr( $img ) . '">' . $img . '</noscript>';
			else
				$html .= $img;
			unset( $img );
			$html .= '</a>';
		}
		$html .= '<h2><a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '"';
		if ( $schema_org === true )
			$html .= ' itemprop="url"';
		$html .= $anchor_target . '>' . esc_html( $this->company->name ) . ' <strong';
		if ( $collapsed_summary_exists !== true && $schema_org === true )
			$html .= ' itemprop="model"';
		$html .= '>' . esc_html( esc_html( $this->name ) ) . '</strong></a></h2>';
		if ( $schema_org === true )
			$html .= '<meta itemprop="name" content="' . esc_attr( $this->full_name ) .  '" />';
		if ( isset( $this->instances ) ) {
			$html .= '<ul class="instances">';
			foreach ( $this->instances as $instance ) {
				$html .= '<li><a href="' . esc_url( $instance->url, array( 'http', 'https' ) ) . '" class="gdgt-product-instance';
				if ( isset( $instance->selected ) && $instance->selected )
					$html .= ' selected';
				$html .= '" title="' . esc_html( $this->full_name . ' ' . $instance->name ) . '"' . $anchor_target . '>' . esc_html( $instance->name ) . '</a></li>';
			}
			$html .= '</ul>';
		}
		$html .= '</div>';
		if ( isset( $this->price ) ) {
			$html .= '<div class="gdgt-product-price"';
			if ( $schema_org )
				$html .= ' itemprop="offers" itemscope itemtype="http://schema.org/AggregateOffer"><link itemprop="itemCondition" href="http://schema.org/NewCondition" /><meta itemprop="priceCurrency" content="' . $this->price->currency . '" />';
			else
				$html .= '>';
			$html .= '<span class="gdgt-price-label">';
			if ( $this->price->amount === 0 )
				$html .= esc_html( __( 'Get it for', 'gdgt-databox' ) );
			else
				$html .= esc_html( __( 'Buy from', 'gdgt-databox' ) );
			$html .= '</span>';
			$html .= '<span class="gdgt-price"';
			if ( $schema_org )
				$html .= ' itemprop="url">';
			else
				$html .= '>';
			if ( $this->price->amount === 0 ) {
				$html .= 'FREE';
			} else {
				if ( array_key_exists( $this->price->currency, $this->currency_symbols ) )
					$html .= $this->currency_symbols[$this->price->currency];
				if ( $schema_org )
					$html .= '<span itemprop="price lowPrice">' . $this->price->amount . '</span>';
				else
					$html .= $this->price->amount;
			}
			$html .= '</span>';
			if ( $this->price->is_on_contract )
				$html .= '<span class="gdgt-price-asterisk">*</span>';
			$html .= '</div>';
		}
		$html .= '<div class="gdgt-branding"><p>' . esc_html( _x( 'powered by', 'site credits', 'gdgt-databox' ) ) . '<a role="img" aria-label="gdgt logo" href="http://gdgt.com/" class="gdgt-logo"' . $anchor_target . '>gdgt</a></p></div></div>'; // gdgt-product-name, gdgt-branding, gdgt-product-head

		if ( isset( $this->tabs ) && ! empty( $this->tabs ) ) {
			$tabs = array();
			foreach ( array(
				'key_specs' => array( 'class' => 'specs', 'text' => __( 'key <abbr title="specifications">specs</abbr>', 'gdgt-databox' ) ),
				'user_reviews' => array( 'class' => 'reviews', 'text' => __( 'reviews', 'gdgt-databox' ) ),
				'prices' => array( 'class' => 'prices', 'text' => __( 'prices', 'gdgt-databox' ) )
			) as $key => $attributes ) {
				if ( ! array_key_exists( $key, $this->tabs ) )
					continue;
				$selected = false;
				if ( $this->selected_tab === $key )
					$selected = true;
				$li = $this->tab_link( $this->tabs[$key], $attributes['text'], $selected, $anchor_target, $self_contained );
				if ( empty( $li ) )
					continue;
				$tab = '<li class="' . $attributes['class'];
				if ( empty( $this->tabs[$key] ) )
					$tab .= ' disabled';
				if ( $selected )
					$tab .= ' selected" aria-selected="true';
				$tab .= '" data-gdgt-datatype="' . $attributes['class'] .  '" role="tab">' . $li . '<span class="gdgt-tab-divider"></span>';
				unset( $li );
				unset( $selected );
				$tabs[] = $tab;
				unset( $tab );
			}
			if ( ! empty( $tabs ) ) {
				$html .= '<ul class="gdgt-tabs ' . $this->tab_class_names[ count($this->tabs) - 2 ] . '" role="tablist">';
				$tabs_html = implode( '</li>', $tabs );
				$tabs_html = trim( substr( $tabs_html, 0, -38 ) ); // take off the last tab divider
				$html .= $tabs_html . '</li>';
				$html .= '</ul>';
				unset( $tabs_html );
			}
			unset( $tabs );

			$html .= $this->render_tabs( $self_contained, $schema_org, $anchor_target );
		}

		$html .= '</div>'; // gdgt-product-wrapper
		if ( $is_expanded === true && isset( $this->name ) ) {
			if ( ! class_exists( 'GDGT_Databox' ) )
				require_once( dirname( dirname( __FILE__ ) ) . '/databox.php' );
			$html .= GDGT_Databox::google_analytics_beacon( $this->name, $this->url, 'noscript' );
		}
		$html .= '</div>'; // gdgt-product
		return $html;
	}

	/**
	 * Render a no JavaScript, inline CSS databox suitable for use in a stand-alone environment such as a syndicated feed and feed reeder
	 *
	 * @since 1.1
	 * @return string HTML markup of an inline, standalone Databox product
	 */
	public function render_inline() {
		/*
		 * Link to the product and optional tab within the post so the partner may capture the visit and potential revenue
		 * If no product ID present in API response include a URL fragment equal to the gdgt Databox wrapper ID to take advantage of default browser behaviors for internal page links.
		 * Do not alter the URL fragment if a fragment already exists as this may break existing behaviors such as default internal page links.
		 *
		 * @todo expand use of URL fragments more comfortable with non-interference
		 */
		$post_product_url = esc_url( apply_filters( 'the_permalink_rss', get_permalink() ) );
		$post_product_url_has_fragment = false;
		if ( $post_product_url ) {
			try { // catch malformed URL errors in PHP < 5.3.3
				$fragment = parse_url( $post_product_url, PHP_URL_FRAGMENT );
				// only add if we are the only fragment
				if ( empty( $fragment ) ) {
					$post_product_url .= '#';
					if ( isset( $this->id ) ) {
						$post_product_url .= rawurlencode( sanitize_html_class( 'gdgt-product-' . $this->id ) );
						$post_product_url_has_fragment = true;
					} else {
						$post_product_url .= 'gdgt-wrapper';
					}
				}
				unset( $fragment );
			} catch ( Exception $e ) {
				$post_product_url = ''; // invalid URL. override
			}
		}

		if ( $this->collapsed === true ) {
			$html = '<li style="height:39px; padding-top:0; padding-bottom: 0; padding-left:15px; padding-right:15px; margin:0; border-top-width:0; border-bottom-width:1px; border-right-width:0; border-left-width:0; border-style:solid; border-color: #CCC; background-color:#ededed"><a href="';
			if ( $post_product_url )
				$html .= $post_product_url;
			else
				$html .= esc_url( $this->url, array( 'http', 'https' ) );
			$html .= '" style="padding:0; margin:0; font-size:18px; color:#333; line-height:40px; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; color:inherit; text-decoration:none; border-bottom-width:0">' . esc_html( $this->company->name ) . ' <strong style="font-weight:bold">' . esc_html( $this->name ) . '</strong></a></li>';
			return $html;
		}

		// product
		$html = '<li style="display:block; margin:0; padding:15px; background:none; border-color:#CCC; border-style:solid; border-top-width:0; border-bottom-width:1px; border-right-width:0; border-left-width:0; min-height:243px">';

		
		$html .= '<div style="min-height:50px">'; // product-head

		// product-name
		$html .= '<div style="float:left; margin:0; padding:0; width:';
		if ( isset( $this->price ) )
			$html .= '58';
		else
			$html .= '87';
		$html .= '%">';
		if ( isset( $this->image ) ) // product-image
			$html .= '<a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '" title="' . esc_attr( $this->full_name ). '" style="float:left; margin-top: 0; margin-bottom:0; margin-left:0; margin-right:15px; text-decoration:none; border-bottom-width:0"><img alt="' . esc_attr( sprintf( __( '%s thumbnail image', 'gdgt-databox' ), $this->full_name ) ) . '" src="' . esc_url( $this->image->src, array( 'http', 'https' ) ) . '" width="50" height="50" style="border:0" /></a>';
		$html .= '<h2 style="clear:none; padding:0; background:none; border:none; display:block; margin-top:3px; margin-left:65px; margin-right:0; font-size:24px; font-weight:normal; line-height:26px; margin-bottom:';
		if ( isset( $this->instances ) ) // no negative values in Google Reader. calculate instead
			$html .= '3';
		else
			$html .= '10';
		$html .= 'px"><a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '" style="padding-top:0; padding-bottom:0; padding-left:1px; padding-right:1px; color:#3399CC; text-decoration:none; border-bottom-width:0">' . esc_html( $this->company->name ) . ' <strong style="color:#3399CC; font-weight:bold">' . esc_html( $this->name ) .  '</strong></a></h2>';
		if ( isset( $this->instances ) ) {
			$html .= '<p style="color:#888; padding:0; margin-bottom:12px; margin-top:0; margin-left:65px; margin-right:0; font-size:12px; font-weight:normal; line-height:18px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">';
			$instances = array();
			foreach( $this->instances as $instance ) {
				$instance_str = '<a href="' . esc_url( $instance->url, array( 'http', 'https' ) ) . '" style="text-decoration:none; border-bottom-width:0; color:#888';
				if ( isset( $instance->selected ) && $instance->selected )
					$instance_str .= '; font-weight:bold';
				$instance_str .= '" title="' . esc_attr( $this->full_name . ' ' . $instance->name ) . '">' . esc_html( $instance->name ) . '</a>';
				$instances[] = $instance_str;
				unset( $instance_str );
			}
			$html .= implode( ', ', $instances );
			unset( $instances );
			$html .= '</p>';
		}
		$html .= '</div>';

		// branding
		$html .= '<div style="float:right; width:66px; padding-top:2px; padding-bottom:0; padding-left:0; padding-right:0; margin:0; text-align:right"><p style="padding:0; margin:0; text-align:right; overflow:hidden"><a href="http://gdgt.com/" style="color:#666; font-size:10px; display:block; padding:0; margin-top:0; margin-bottom:0; margin-left:10px; margin-right:0; text-align:left; vertical-align:middle; text-decoration:none; border-bottom-width:0">' . _x( 'powered by', 'site credits', 'gdgt_databox' ) . ' <img alt="gdgt logo" src="' . esc_url( plugins_url( '/static/css/images/gdgt-logo.png', dirname( __FILE__ ) ), array( 'http', 'https' ) ) . '" width="49" height="23" style="vertical-align:middle; border:0" /></a></p></div>';

		// product-price
		if ( isset( $this->price ) ) {
			$price_url = $post_product_url;
			if ( $post_product_url_has_fragment ) {
				$price_url .= '=prices';
			} else if ( ! $price_url && array_key_exists( 'prices', $this->tabs ) ) {
				$price_data = $this->tabs['prices'];
				if ( isset( $price_data->url ) )
					$price_url = esc_url( $price_data->url, array( 'http', 'https' ) );
				unset( $price_data );
			}
			$html .= '<div style="float:right; width:29%; height:48px; padding-top:2px; padding-bottom:0; padding-left:0; padding-right:8px; margin-top:0; margin-bottom:0; margin-left:0; text-align:right; text-transform:uppercase; border-right-width:1px; border-right-style:solid; border-right-color:#ddd">';
			if ( $price_url )
				$html .= '<a href="' . $price_url . '" style="cursor:pointer; text-decoration:none; border-bottom-width:0">';
			$html .= '<span style="display:inline-block; width:46px; margin-top:7px; margin-bottom:0; margin-left:0; margin-right:2px; color:#666; line-height:16px; text-align:right; vertical-align:top">' . esc_html( __( 'Buy from', 'gdgt-databox' ) ) . '</span>';
			$html .= '<span style="padding-top:0; padding-bottom:0; padding-left:3px; padding-right:3px; font-size:45px; color:#3399CC; line-height:45px">';
			if ( array_key_exists( $this->price->currency, $this->currency_symbols ) )
				$html .= $this->currency_symbols[$this->price->currency];
			$html .= $this->price->amount;
			$html .= '</span>';
			if ( $price_url )
				$html .= '</a>';
			$html .= '</div>';
			unset( $price_url );
		}

		$html .= '</div>';

		// tabs
		if ( isset( $this->tabs ) && ! empty( $this->tabs ) ) {
			$html .= '<ul style="clear:both; padding:0; width:auto; height:30px; list-style:none; margin-top:15px; margin-bottom:10px; margin-left:0; margin-right:0">';
			$num_tabs = count( $this->tabs );
			$tab_width_percent = absint( 100 / $num_tabs );
			$tab_widths = array_fill( 0, $num_tabs, $tab_width_percent );
			// fill out odd number of tabs with remainder width
			if ( ( $num_tabs % 2 ) === 1 )
				$tab_widths[$num_tabs-1] = 100 - ( $tab_width_percent * ( $num_tabs - 1 ) );
			unset( $tab_width_percent );
			unset( $num_tabs );
			$position = 0;
			foreach( array(
				'key_specs' => __( 'key <abbr title="specifications" style="border-bottom:0">specs</abbr>', 'gdgt-databox' ),
				'user_reviews' => __( 'reviews', 'gdgt-databox' ),
				'prices' => __( 'prices', 'gdgt-databox' )
			) as $key => $label ) {
				if ( ! array_key_exists( $key, $this->tabs ) )
					continue;

				$selected = false;
				if ( $this->selected_tab === $key )
					$selected = true;

				$tab_data = $this->tabs[$key];

				// allow selected, disabled, and other states to easily override a previously set value
				$item_style_rules = array(
					'float' => 'left',
					'width' => $tab_widths[$position] . '%',
					'height' => '30px',
					'padding' => '0',
					'margin' => '0',
					'background-color' => '#F4F4F4',
					'font-size' => '13px',
					'font-weight' => 'bold',
					'color' => '#333',
					'text-align' => 'center',
					'text-transform' => 'uppercase',
					'line-height' => '31px',
					'cursor' => 'pointer'
				);
				if ( empty( $tab_data ) ) {
					$item_style_rules['font-weight'] = 'normal';
					$item_style_rules['color'] = '#CCC';
					$item_style_rules['cursor'] = 'default';
				}
				if ( $selected ) {
					$item_style_rules['cursor'] = 'default';
					$item_style_rules['background-color'] = '#333';
					$item_style_rules['color'] = '#FFF';
				}

				$item_inner = '';
				// check for disabled. no link if no data
				if ( empty( $tab_data ) ) {
					$item_inner .= $label;
					$item_style_rules['cursor'] = 'default';
				} else {
					// use one URL for all tabs for now: link to gdgt Databox on permalink or fall back to gdgt.com tab link
					$url = $post_product_url;
					if ( $post_product_url_has_fragment )
						$url .= '=' . ltrim( substr( $key, strpos( $key, '_' ) ), '_' );
					else if ( ! $url && isset( $tab_data->url ) )
						$url = esc_url( $tab_data->url, array( 'http', 'https' ) );
					if ( $url )
						$item_inner .= '<a href="' . $url . '" title="' . esc_attr( $this->full_name . ' ' . strip_tags( $label ) ) . '" style="text-decoration:none; border-bottom-width:0; color:inherit">';
					else
						$item_style_rules['cursor'] = 'default'; // don't be a tease
					$item_inner .= $label;
					if ( isset( $tab_data->total_count ) )
						$item_inner .= ' <span style="color:#CCC">&#8226;</span> <span style="padding:0; margin-left:2px; margin-right:0; margin-top:0; margin-bottom:0; color:#3399CC">' . number_format_i18n( absint( $tab_data->total_count ), 0 ) . '</span>';
					if ( $url )
						$item_inner .= '</a>';
					unset( $url );
				}

				$item_style = '';
				foreach ( $item_style_rules as $rule => $value ) {
					$item_style .= $rule . ':' . $value . ';';
				}
				unset( $item_style_rules );
				$html .= '<li style="' . esc_attr( rtrim( $item_style, ';' ) ) . '">' . $item_inner . '</li>';
				unset( $item_style );
				unset( $item_inner );
				$position++;
			}
			unset( $position );
			unset( $post_product_url_has_fragment );
			unset( $post_product_url );
			$html .= '</ul>';

			// tab content
			$html .= $this->render_tabs( true, false );
		}

		if ( ! class_exists( 'GDGT_Databox' ) )
			require_once( dirname( dirname( __FILE__ ) ) . '/databox.php' );
		$html .= GDGT_Databox::google_analytics_beacon( $this->name, $this->url, 'img' );

		$html .= '</li>';
		return $html;
	}

	/**
	 * Generate tab HTML
	 *
	 * @since 1.0
	 * @param bool $self_contained assume no JS such as a feed view
	 * @param bool @schema_org include schema.org markup
	 * @param string $anchor_target anchor element custom attributes
	 * @return string HTML string for all tabs or blank
	 */
	private function render_tabs( $self_contained = false, $schema_org = true, $anchor_target = '' ) {
		$tabs_html = '';

		// selected tab appears first in markup
		if ( $this->selected_tab === 'key_specs' ) {
			$tabs_html = $this->render_specs_tab( $self_contained, $anchor_target );
			unset( $this->tabs['key_specs'] );
		} else if ( $this->selected_tab === 'user_reviews' ) {
			$tabs_html = $this->render_reviews_tab( $self_contained, $schema_org, $anchor_target );
			unset( $this->tabs['user_reviews'] );
		} else if ( $this->selected_tab === 'prices' ) {
			$tabs_html = $this->render_prices_tab( $self_contained, $schema_org, $anchor_target );
		} else {
			$tabs_html = '';
		}

		// only include additional tabs if reachable
		if ( ! $self_contained ) {
			$tabs_html .= $this->render_specs_tab( false, $anchor_target );
			$tabs_html .= $this->render_reviews_tab( false, $schema_org, $anchor_target );
			$tabs_html .= $this->render_prices_tab( false, $schema_org, $anchor_target );
		}

		return $tabs_html;
	}

	/**
	 * Generate HTML for the specs tab
	 *
	 * @since 1.0
	 * @param bool $self_contained render inline CSS, no JS
	 * @param string $anchor_target anchor element custom attributes
	 * @return string HTML string for tab or blank
	 */
	private function render_specs_tab( $self_contained = false, $anchor_target = '' ) {
		if ( ! array_key_exists( 'key_specs', $this->tabs ) )
			return '';

		$selected = false;
		if ( $this->selected_tab === 'key_specs' )
			$selected = true;
		if ( ! class_exists( 'GDGT_Databox_Specs' ) )
			include_once( dirname( __FILE__ ) . '/specs.php' );
		if ( isset( $this->tabs['key_specs']->url ) )
			$specs = new GDGT_Databox_Specs( $this->tabs['key_specs']->specs, $this->full_name, $this->tabs['key_specs']->url );
		else
			$specs = new GDGT_Databox_Specs( $this->tabs['key_specs']->specs, $this->full_name );
		if ( $selected === true && $self_contained === true )
			return $specs->render_inline();
		else
			return $specs->render( $selected, $anchor_target );
	}

	/**
	 * Generate HTML for the reviews tab
	 *
	 * @since 1.0
	 * @param bool $self_contained render inline CSS, no JS
	 * @param bool $schema_org include schema.org markup
	 * @param string $anchor_target anchor element custom attributes
	 * @return string HTML string for tab or blank
	 */
	private function render_reviews_tab( $self_contained = false, $schema_org = true, $anchor_target = '' ) {
		if ( ! array_key_exists( 'user_reviews', $this->tabs ) )
			return '';

		$selected = false;
		if ( $this->selected_tab === 'user_reviews' )
			$selected = true;
		if ( ! class_exists( 'GDGT_Databox_Ratings' ) )
			include_once( dirname(__FILE__) . '/ratings.php' );
		$ratings = new GDGT_Databox_Ratings( $this->tabs['user_reviews'], $this->full_name );
		if ( $selected === true && $self_contained === true )
			return $ratings->render_inline();
		else
			return $ratings->render( $selected, $schema_org, $anchor_target );
	}

	/**
	 * Generate HTML for the prices tab
	 *
	 * @since 1.2
 	 * @param bool $self_contained render inline CSS, no JS
	 * @param bool $schema_org include schema.org markup
	 * @param string $anchor_target anchor element custom attributes
	 * @return string HTML string for tab or empty string
	 */
	private function render_prices_tab( $self_contained = false, $schema_org = true, $anchor_target = '' ) {
		if ( ! array_key_exists( 'prices', $this->tabs ) )
			return '';

		$selected = false;
		if ( $this->selected_tab === 'prices' )
			$selected = true;
		$instance_count = 0;
		if ( isset( $this->instances ) )
			$instance_count = count( $this->instances );
		if ( ! class_exists( 'GDGT_Databox_Prices' ) )
			include_once( dirname(__FILE__) . '/prices.php' );
		$prices = new GDGT_Databox_Prices( $this->tabs['prices'], $instance_count );
		if ( $selected === true && $self_contained === true )
			return $prices->render_inline();
		else
			return $prices->render( $selected, $schema_org, $anchor_target );
	}
}
?>
