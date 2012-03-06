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
	 */
	public function __construct( $product ) {
		if ( isset( $product->title ) )
			$this->full_name = $product->title;
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
			foreach( $this->instances as $instance ) {
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
			foreach( array(
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
		$html .= '</div>'; // gdgt-product
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
	private function render_tabs( $self_contained, $schema_org, $anchor_target ) {
		$tabs_html = '';

		// selected tab appears first in markup
		if ( $this->selected_tab === 'key_specs' ) {
			$tabs_html = $this->render_specs_tab( $anchor_target );
			unset( $this->tabs['key_specs'] );
		} else if ( $this->selected_tab === 'user_reviews' ) {
			$tabs_html = $this->render_reviews_tab( $schema_org, $anchor_target );
			unset( $this->tabs['user_reviews'] );
		} else if ( $this->selected_tab === 'discussions' ) {
			$tabs_html = $this->render_discussions_tab( $anchor_target );
			unset( $this->tabs['discussions'] );
		} else if ( $this->selected_tab === 'answers' ) {
			$tabs_html = $this->render_answers_tab( $anchor_target );
			unset( $this->tabs['answers'] );
		} else {
			$tabs_html = '';
		}

		// only include additional tabs if reachable
		if ( ! $self_contained ) {
			$tabs_html .= $this->render_specs_tab( $anchor_target );
			$tabs_html .= $this->render_reviews_tab( $schema_org, $anchor_target );
			$tabs_html .= $this->render_discussions_tab( $anchor_target );
			$tabs_html .= $this->render_answers_tab( $anchor_target );
		}

		return $tabs_html;
	}

	/**
	 * Generate HTML for the specs tab
	 *
	 * @since 1.0
	 * @param string $anchor_target anchor element custom attributes
	 * @return string HTML string for tab or blank
	 */
	private function render_specs_tab( $anchor_target = '' ) {
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
		return $specs->render( $selected, $anchor_target );
	}

	/**
	 * Generate HTML for the reviews tab
	 *
	 * @since 1.0
	 * @param bool $schema_org include schema.org markup
	 * @param string $anchor_target anchor element custom attributes
	 * @return string HTML string for tab or blank
	 */
	private function render_reviews_tab( $schema_org = true, $anchor_target = '' ) {
		if ( ! array_key_exists( 'user_reviews', $this->tabs ) )
			return '';

		$selected = false;
		if ( $this->selected_tab === 'user_reviews' )
			$selected = true;
		if ( ! class_exists( 'GDGT_Databox_Ratings' ) )
			include_once( dirname(__FILE__) . '/ratings.php' );
		$ratings = new GDGT_Databox_Ratings( $this->tabs['user_reviews'], $this->full_name );
		return $ratings->render( $selected, $schema_org, $anchor_target );
	}

	/**
	 * Generate HTML for the discussions tab
	 *
	 * @since 1.0
	 * @param string $anchor_target anchor element custom attributes
	 * @return string HTML string for tab or blank
	 */
	private function render_discussions_tab( $anchor_target = '' ) {
		$key = 'discussions';
		if ( ! array_key_exists( $key, $this->tabs ) )
			return '';

		$selected = false;
		if ( $this->selected_tab === $key )
			$selected = true;
		if ( ! class_exists( 'GDGT_Databox_Discussions' ) )
			include_once( dirname( __FILE__ ) . '/discussions.php' );
		$discussions = new GDGT_Databox_Discussions( $this->tabs[$key], $this->full_name );
		return $discussions->render( $selected, $anchor_target );
	}

	/**
	 * Generate HTML for the answers tab
	 *
	 * @since 1.0
	 * @param string $anchor_target anchor element custom attributes
	 * @return string HTML string for tab or blank
	 */
	private function render_answers_tab( $anchor_target = '' ) {
		$key = 'answers';
		if ( ! array_key_exists( $key, $this->tabs ) )
			return '';

		$selected = false;
		if ( $this->selected_tab === $key )
			$selected = true;
		if ( ! class_exists( 'GDGT_Databox_Answers' ) )
			include_once( dirname( __FILE__ ) . '/answers.php' );
		$answers = new GDGT_Databox_Answers( $this->tabs[$key], $this->full_name );
		return $answers->render( $selected, $anchor_target );
	}
}
?>