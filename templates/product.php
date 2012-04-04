<?php

/**
 * Display a single product component of the gdgt Databox
 *
 * @since 1.0
 */
class GDGT_Databox_Product {
	public $tab_class_names = array( 'two', 'three', 'four' );

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
			if ( isset( $product->tabs->answers ) && (bool) get_option( 'gdgt_answers_tab', true ) ) {
				if ( isset( $product->tabs->answers->selected ) && $product->tabs->answers->selected === true )
					$this->selected_tab = 'answers';
				unset( $product->tabs->answers->selected );

				if ( isset( $product->tabs->answers->all_answers_url ) ) {
					$product->tabs->answers->url = $product->tabs->answers->all_answers_url;
					unset( $product->tabs->answers->all_answers_url );
				}

				if ( isset( $product->tabs->answers->ask_a_question_url ) ) {
					$product->tabs->answers->write_url = $product->tabs->answers->ask_a_question_url;
					unset( $product->tabs->answers->ask_a_question_url );
				}

				$this->tabs['answers'] = $product->tabs->answers;
			}
			if ( isset( $product->tabs->discussions ) && (bool) get_option( 'gdgt_discussions_tab', true ) ) {
				if ( isset( $product->tabs->discussions->selected ) && $product->tabs->discussions->selected === true )
					$this->selected_tab = 'discussions';
				unset( $product->tabs->discussions->selected );

				if ( isset( $product->tabs->discussions->all_discussions_url ) ) {
					$product->tabs->discussions->url = $product->tabs->discussions->all_discussions_url;
					unset( $product->tabs->discussions->all_discussions_url );
				}
				if ( isset( $product->tabs->discussions->start_a_discussion_url ) ) {
					$product->tabs->discussions->write_url = $product->tabs->discussions->start_a_discussion_url;
					unset( $product->tabs->discussions->start_a_discussion_url );
				}

				$this->tabs['discussions'] = $product->tabs->discussions;
			}

			/**
			 * Make it easy to test by selected tab
			 *
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true && array_key_exists( 'tab', $_GET ) && in_array( $_GET['tab'], array( 'key_specs','user_reviews', 'answers', 'discussions' ), true ) )
				$this->selected_tab = $_GET['tab']; */

			if ( ! isset( $this->selected_tab ) && ! empty( $this->tabs ) )
				$this->selected_tab = key( $this->tabs[0] );
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
	private function tab_link( stdClass $tab_data, $text, $selected = false, $anchor_target = '', $self_contained = false ) {
		if ( empty( $text ) )
			return '';

		$anchor = '';
		if ( $selected === false && isset( $tab_data->url ) )
			$anchor = '<a href="' . esc_url( $tab_data->url, array( 'http' ) ) . '" title="' . esc_attr( $this->full_name . ' ' . strip_tags( $text ) ) . '"' . $anchor_target . '>';

		$html = $text;
		if ( isset( $tab_data->total_count ) )
			$html .= ' <span>' . number_format_i18n( absint( $tab_data->total_count ), 0 ) . '</span>';

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
	 * @return string HTML markup
	 */
	public function render( $is_expanded = false ) {
		$schema_org = (bool) get_option( 'gdgt_schema_org', true );
		$self_contained = false;
		$is_expanded = (bool) $is_expanded;

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
		if ( $is_expanded === true )
			$html .= 'expanded" aria-expanded="true"';
		else
			$html .= 'collapsed"';
		if ( $schema_org )
			$html .= ' itemscope itemtype="http://schema.org/Product"';
		$html .= ' role="tab"><p class="gdgt-product-collapsed-name"';
		if ( $is_expanded === true )
			$html .= ' style="display:none"';
		$html .= ' aria-label="' . esc_attr( $this->company->name ) . ' ' . esc_attr( $this->name ) . '"><noscript><a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '"></noscript>';
		if ( $schema_org ) {
			$html .= '<span itemprop="brand" itemscope itemtype="http://schema.org/Corporation"><span itemprop="name">' . esc_html( $this->company->name ) . '</span>';
			if ( isset( $this->company->url ) )
				$html .= '<meta itemprop="url" content="' . esc_url( $this->company->url , array( 'http', 'https' ) ) . '">';
			$html .= '</span> <strong itemprop="model">' . esc_html( $this->name ) . '</strong>';
		} else {
			$html .= esc_html( $this->company->name ) . ' <strong>' . esc_html( $this->name ) . '</strong>';
		}
		$html .= '<noscript></a></noscript></p><div class="gdgt-product-wrapper" role="tabpanel" ';
		if ( $is_expanded === true )
			$html .= 'aria-hidden="false"';
		else
			$html .= 'aria-hidden="true" style="display:none"';
		$html .= '><div class="gdgt-product-head">';
		if ( isset( $this->image ) ) {
			$html .= '<a class="gdgt-product-image" href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '" title="' . esc_attr( $this->full_name ) . '"' . $anchor_target . '>';
			$img = '<img alt="' . esc_attr( sprintf( __( '%s thumbnail image', 'gdgt-databox' ), $this->full_name ) ) . '" src="' . esc_url( $this->image->src, array( 'http', 'https' ) ) . '" width="' . $this->image->width . '" height="' . $this->image->height . '"';
			if ( $schema_org )
				$img .= ' itemprop="image"';
			if ( $self_contained === false )
				$html .= '<noscript class="img">' . $img . ' /></noscript>';
			unset( $img );
			$html .= '</a>';
		}
		$html .= '<div class="gdgt-product-name">';
		$html .= '<h2><a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '"';
		if ( $schema_org )
			$html .= ' itemprop="url"';
		$html .= $anchor_target . '>' . esc_html( $this->company->name ) . ' <strong>' . esc_html( esc_html( $this->name ) ) . '</strong></a></h2>';
		if ( $schema_org )
			$html .= '<meta itemprop="name" content="' . esc_attr( $this->full_name ) .  '">';
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
		$html .= '</div><div class="gdgt-branding"><p>' . esc_html( _x( 'powered by', 'site credits', 'gdgt-databox' ) ) . '<a role="img" aria-label="gdgt logo" href="http://gdgt.com/" class="gdgt-logo"' . $anchor_target . '>gdgt</a></p></div></div>'; // gdgt-product-name, gdgt-branding, gdgt-product-head

		if ( isset( $this->tabs ) && ! empty( $this->tabs ) ) {
			$html .= '<ul class="gdgt-tabs ' . $this->tab_class_names[ count($this->tabs) - 2 ] . '" role="tablist">';
			foreach ( array(
				'key_specs' => array( 'class' => 'specs', 'text' => __( 'key <abbr title="specifications">specs</abbr>', 'gdgt-databox' ) ),
				'user_reviews' => array( 'class' => 'reviews', 'text' => __( 'user reviews', 'gdgt-databox' ) ),
				'answers' => array( 'class' => 'answers', 'text' => __( 'answers', 'gdgt-databox' ) ),
				'discussions' => array( 'class' => 'discussions', 'text' => __( 'discussions', 'gdgt-databox' ) )
			) as $key => $attributes ) {
				if ( ! array_key_exists( $key, $this->tabs ) )
					continue;
				$selected = false;
				if ( $this->selected_tab === $key )
					$selected = true;
				$li = $this->tab_link( $this->tabs[$key], $attributes['text'], $selected, $anchor_target, $self_contained );
				if ( empty( $li ) )
					continue;
				$html .= '<li class="' . $attributes['class'];
				if ( $selected )
					$html .= ' selected" aria-selected="true';
				$html .= '" data-gdgt-datatype="' . $attributes['class'] .  '" role="tab">' . $li . '</li>';
				unset( $li );
				unset( $selected );
			}
			$html .= '</ul>';

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
		if ( $this->collapsed === true ) {
			return '<li style="height:39px; padding-top:0; padding-bottom: 0; padding-left:15px; padding-right:15px; margin:0; border-top-width:1px; border-bottom-width:1px; border-right-width:0; border-left-width:0; border-style:solid; border-color: #CCC; border-top-width:0; background-color:#ededed"><a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '" style="padding:0; margin:0; font-size:18px; color:#333; line-height:40px; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; color:inherit; text-decoration:none; border-bottom-width:0">' . esc_html( $this->company->name ) . ' <strong style="font-weight:bold">' . esc_html( $this->name ) . '</strong></a></li>';
		}

		$html = '<li style="display:block; margin:0; padding:15px; border-color:#CCC; border-style:solid; border-top-width:1px; border-bottom-width:1px; border-right-width:0; border-left-width:0; min-height: 243px"><div>';

		// product header
		$html .= '<a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '" title="' . esc_attr( $this->full_name ). '" style="text-decoration:none; border-bottom-width:0; margin-right:15px; float:left"><img alt="' . esc_attr( sprintf( __( '%s thumbnail image', 'gdgt-databox' ), $this->full_name ) ) . '" src="' . esc_url( $this->image->src, array( 'http', 'https' ) ) . '" width="50" height="50" style="border:0" /></a>';
		$html .= '<div style="float:left; margin:0; padding:0">';
		$html .= '<h2 style="display:block; float:left; padding:0; margin-top:3px; margin-bottom:';
		if ( isset( $this->instances ) )
			$html .= '3';
		else
			$html .= '10';
		$html .= 'px; margin-left:0; margin-right:0; max-width:420px; font-size:24px; font-weight:normal; line-height:26px"><a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '" style="color:#00BDF6; text-decoration:none; border-bottom-width:0">' . esc_html( $this->company->name ) . ' <strong style="color:#00BDF6;font-weight:bold">' . esc_html( $this->name ) .  '</strong></a></h2>';
		if ( isset( $this->instances ) ) {
			$html .= '<p style="clear:both; min-width:1px; max-width:330px; padding:0; margin-bottom:12px; margin-top:0; margin-left:0; margin-right:0; font-size:12px; font-weight:normal; line-height:18px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">';
			$instances = array();
			foreach( $this->instances as $instance ) {
				$instance_str = '<a href="' . esc_url( $instance->url, array( 'http', 'https' ) ) . '" style="text-decoration:none; border-bottom-width:0; color:#888';
				if ( isset( $instance->selected ) && $instance->selected )
					$instance_str .= ';font-weight:bold';
				$instance_str .= '" title="' . esc_html( $this->full_name . ' ' . $instance->name ) . '">' . esc_html( $instance->name ) . '</a>';
				$instances[] = $instance_str;
				unset( $instance_str );
			}
			$html .= implode( ', ', $instances );
			unset( $instances );
			$html .= '</p>';
		}
		$html .= '</div>';
		$html .= '<div style="position:relative; float:right"><p style="text-align:right; font-size:11px; color:#666; margin:0; padding:0; overflow:hidden"><a href="http://gdgt.com/" style="text-decoration:none; border-bottom-width:0; color:inherit">' . __( 'powered by', 'gdgt_databox' ) . ' <img alt="gdgt logo" src="' . esc_url( plugins_url( '/static/css/images/gdgt-logo.png', dirname( __FILE__ ) ), array( 'http', 'https' ) ) . '" width="49" height="23" style="margin-left:4px; border:0; vertical-align:middle" /></a></p></div>';

		// tabs
		if ( isset( $this->tabs ) && ! empty( $this->tabs ) ) {
			$html .= '<ul style="clear:both; height:26px; list-style:none; padding:0; margin-top:15px; margin-bottom:0; margin-left:0; margin-right:0; border-bottom-width:2px; border-bottom-style:solid; border-bottom-color:#333">';
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
				'user_reviews' => __( 'user reviews', 'gdgt-databox' ),
				'answers' => __( 'answers', 'gdgt-databox' ),
				'discussions' => __( 'discussions', 'gdgt-databox' )
			) as $key => $label ) {
				if ( ! array_key_exists( $key, $this->tabs ) )
					continue;

				$selected = false;
				if ( $this->selected_tab === $key )
					$selected = true;

				$tab_data = $this->tabs[$key];
				if ( empty( $tab_data ) )
					continue;
				$html .= '<li style="list-style:none; float:left; padding:0; margin:0; background-color:#F4F4F4; font-size:12px; color:#333; text-align:center; line-height: 26px; width:' . $tab_widths[$position] . '%';
				if ( $selected )
					$html .= ';background-color: #333; color:#FFF';
				$html .= '">';
				if ( isset( $tab_data->url ) )
					$html .= '<a href="' . esc_url( $tab_data->url, array( 'http', 'https' ) ) . '" title="' . esc_attr( $this->full_name . ' ' . strip_tags( $label ) ) . '" style="text-decoration:none; border-bottom-width:0; color:inherit">' . $label;
				if ( isset( $tab_data->total_count ) ) {
					$html .= ' <span style="padding-top:1px; padding-bottom:1px; padding-left:4px; padding-right:4px; margin-left:3px; margin-right:0; margin-top:0; margin-bottom:0; font-size:11px; vertical-align:top;';
					if ( $selected )
						$html .= 'background-color:#F4F4F4; color:#333';
					else
						$html .= 'background-color:#333; color:#F4F4F4';
					$html .= '">' . number_format_i18n( absint( $tab_data->total_count ), 0 ) . '</span>';
				}
				$html .= '</a></li>';
				$position++;
			}
			unset( $position );
			$html .= '</ul>';

			// tab content
			$html .= $this->render_tabs( true, false );
		}

		if ( ! class_exists( 'GDGT_Databox' ) )
			require_once( dirname( dirname( __FILE__ ) ) . '/databox.php' );
		$html .= GDGT_Databox::google_analytics_beacon( $this->name, $this->url, 'img' );
		$html .= '</div>';
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
		} else if ( $this->selected_tab === 'discussions' ) {
			$tabs_html = $this->render_discussions_tab( $self_contained, $anchor_target );
			unset( $this->tabs['discussions'] );
		} else if ( $this->selected_tab === 'answers' ) {
			$tabs_html = $this->render_answers_tab( $self_contained, $anchor_target );
			unset( $this->tabs['answers'] );
		} else {
			$tabs_html = '';
		}

		// only include additional tabs if reachable
		if ( ! $self_contained ) {
			$tabs_html .= $this->render_specs_tab( false, $anchor_target );
			$tabs_html .= $this->render_reviews_tab( false, $schema_org, $anchor_target );
			$tabs_html .= $this->render_discussions_tab( false, $anchor_target );
			$tabs_html .= $this->render_answers_tab( false, $anchor_target );
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
	 * Generate HTML for the discussions tab
	 *
	 * @since 1.0
	 * @param bool $self_contained render inline CSS, no JS
	 * @param string $anchor_target anchor element custom attributes
	 * @return string HTML string for tab or blank
	 */
	private function render_discussions_tab( $self_contained = false, $anchor_target = '' ) {
		$key = 'discussions';
		if ( ! array_key_exists( $key, $this->tabs ) )
			return '';

		$selected = false;
		if ( $this->selected_tab === $key )
			$selected = true;
		if ( ! class_exists( 'GDGT_Databox_Discussions' ) )
			include_once( dirname( __FILE__ ) . '/discussions.php' );
		$discussions = new GDGT_Databox_Discussions( $this->tabs[$key], $this->full_name );
		if ( $selected === true && $self_contained === true )
			return $discussions->render_inline();
		else
			return $discussions->render( $selected, $anchor_target );
	}

	/**
	 * Generate HTML for the answers tab
	 *
	 * @since 1.0
	 * @param bool $self_contained render inline CSS, no JS
	 * @param string $anchor_target anchor element custom attributes
	 * @return string HTML string for tab or blank
	 */
	private function render_answers_tab( $self_contained = false, $anchor_target = '' ) {
		$key = 'answers';
		if ( ! array_key_exists( $key, $this->tabs ) )
			return '';

		$selected = false;
		if ( $this->selected_tab === $key )
			$selected = true;
		if ( ! class_exists( 'GDGT_Databox_Answers' ) )
			include_once( dirname( __FILE__ ) . '/answers.php' );
		$answers = new GDGT_Databox_Answers( $this->tabs[$key], $this->full_name );
		if ( $selected === true && $self_contained === true )
			return $answers->render_inline();
		else
			return $answers->render( $selected, $anchor_target );
	}
}
?>