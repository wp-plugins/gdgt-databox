<?php
/**
 * Display price comparisons for a product, its instances, and instance options
 *
 * @since 1.2
 */
class GDGT_Databox_Prices {

	/**
	 * Parse and store pricing data by instance configuration
	 *
	 * @since 1.2
	 * @param stdClass $prices_data prices
	 * @param int $instance_count (optional) used to compare total instances against available prices to possibly provide more information if only one instance has pricing data
	 */
	public function __construct( $prices_data, $instance_count = 0 ) {
		// store available configuration options
		if ( isset( $prices_data->list ) && ! empty( $prices_data->list ) && is_array( $prices_data->list ) ) {
			$options = array();
			foreach( $prices_data->list as $option ) {
				if ( ! isset( $option->name ) || empty( $option->name ) || ! isset( $option->slug ) || empty( $option->slug ) || array_key_exists( $option->slug, $options ) )
					continue;
				$new_option = new stdClass();
				$new_option->name = $option->name;
				if ( isset( $option->selected ) && $option->selected === true ) {
					$new_option->selected = true;
					$this->selected_configuration = $option->slug;
				} else {
					$new_option->selected = false;
				}
				$options[$option->slug] = $new_option;
				unset( $new_option );
			}
			if ( ! empty( $options ) )
				$this->configurations = $options;
		}

		if ( isset( $prices_data->merchants ) && ! empty( $prices_data->merchants ) ) {
			if ( ! class_exists( 'GDGT_Databox_Offer' ) )
				require_once( dirname( __FILE__ ) . '/class-offer.php' );
			foreach ( $prices_data->merchants as $slug => $offers ) {
				if ( ! array_key_exists( $slug, $this->configurations ) )
					continue;
				$new_offers = array();
				if ( ! empty( $offers ) ) {
					foreach ( $offers as $offer ) {
						$new_offer = new GDGT_Databox_Offer( $offer );
						if ( isset( $new_offer->url ) )
							$new_offers[] = $new_offer;
						unset( $new_offer );
					}
				}

				// account for a configuration having no prices and therefore nothing to select
				if ( empty( $new_offers ) ) {
					unset( $this->configurations[$slug] );
					if ( empty( $this->configurations ) )
						unset( $this->configurations );
					if ( $this->selected_configuration === $slug )
						unset( $this->selected_configuration );
				} else {
					$this->configurations[$slug]->offers = $new_offers;
				}
				unset( $new_offers );
			}
		}

		if ( ! isset( $this->selected_configuration ) && isset( $this->configurations ) && ! empty( $this->configurations ) )
			$this->selected_configuration = key( $this->configurations );

		if ( is_int( $instance_count ) )
			$this->instance_count = absint( $instance_count );
		else
			$this->instance_count = 0;
	}

	/**
	 * Build a HTML string for the prices tab from prices data
	 *
	 * @since 1.2
	 * @param bool $displayed should the tab appear hidden on initial view?
	 * @param bool $schema_org include Schema.org markup for offers
	 * @param string $anchor_target possible custom anchor attributes including target
	 * @return string HTML markup for the prices tab
	 */
	public function render( $displayed = false, $schema_org = true, $anchor_target = '' ) {
		// even a single configuration product should have data
		if ( ! isset( $this->configurations ) )
			return '';

		$html = '<div class="robots-nofollow gdgt-content gdgt-content-prices" role="tabpanel" ';
		if ( $displayed === true )
			$html .= 'aria-hidden="false">';
		else
			$html .= 'aria-hidden="true" style="display:none">';

		/* @todo if one config but an expectation of more than one config based on instance count display .gdgt-price-instances with a model name in the <p>
		 */

		$configurations_count = count( $this->configurations );
		if ( $configurations_count === 1 && $this->instance_count > 1 ) {
			$configuration = $this->configurations[key($this->configurations)];
			if ( is_object( $configuration ) && isset( $configuration->name ) )
				$html .= '<div class="gdgt-price-instances"><p>' . _x( 'Displaying prices for model:', 'model as in product variation such as by color', 'gdgt-databox' ) . ' <strong>' . esc_html( $configuration->name ) . '</strong></p></div>';
			unset( $configuration );
		} else if ( $configurations_count > 1 ) {
			$html .= '<div class="gdgt-price-instances"><p>' . _x( 'Displaying prices for model:', 'model as in product variation such as by color', 'gdgt-databox' ) . '</p><select class="gdgt-prices-configs">';
			foreach ( $this->configurations as $slug => $configuration ) {
				$html .= '<option value="' . esc_attr( $slug ) . '"';
				if ( $configuration->selected === true )
					$html .= ' selected';
				/*
				 * add offers HTML as data attached to selector option for dynamic insertion on selection
				 * Considered moving this list to dynamic insertion via JS only
				 * The list is informative on its own even if dynamic elements are not triggered without JS
				 */
				if ( isset( $configuration->offers ) ) {
					$offers_html = '';
					foreach( $configuration->offers as $offer ) {
						$offers_html .= $offer->render( $schema_org, $anchor_target, false );
					}
					if ( ! empty( $offers_html ) )
						$html .= ' data-gdgt-offers="' . esc_attr( $offers_html ) . '"';
					unset( $offers_html );
				}
				$html .= '>' . esc_html( $configuration->name ) . '</option>';
			}
			$html .= '</select></div>';
		}
		unset( $configurations_count );

		if ( isset( $this->selected_configuration ) && array_key_exists( $this->selected_configuration, $this->configurations ) ) {
			$selected_configuration = $this->configurations[$this->selected_configuration];
			if ( isset( $selected_configuration->offers ) ) {
				$offers_html = '';
				foreach ( $selected_configuration->offers as $offer ) {
					$offers_html .= $offer->render( $schema_org, $anchor_target );
				}
				if ( ! empty( $offers_html ) )
					$html .= '<ol class="gdgt-price-retailers">' . $offers_html . '</ol>';
				unset( $offers_html );
			}
			unset( $selected_configuration );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Build HTML for a specs tab with all CSS inline.
	 * Used for web feeds (RSS & Atom)
	 *
	 * @since 1.2
	 * @return string HTML markup for the specs tab with inline CSS
	 */
	public function render_inline() {
		if ( ! isset( $this->configurations ) )
			return '';

		// content, content-prices
		$html = '<div style="clear:both; min-height:135px; padding:0; margin:0; background-color:#FFF; color:#333; overflow:hidden; text-align:center">';
		if ( isset( $this->selected_configuration ) && array_key_exists( $this->selected_configuration, $this->configurations ) ) {
			$configuration = $this->configurations[$this->selected_configuration];
			// gdgt-price-instances
			if ( count( $this->configurations ) > 1 )
				$html .= '<div style="height:30px; text-align:left; border-bottom-width:1px; border-bottom-style:solid; border-bottom-color:#CCC"><p style="margin-top:3px; margin-bottom:0; margin-left:0; margin-right:8px; font-size:12px; text-align:left; vertical-align:middle">' . _x( 'Displaying prices for model:', 'model as in product variation such as by color', 'gdgt-databox' ) . ' <strong>' . esc_html( $configuration->name ) . '</strong></p></div>';
			if ( isset( $configuration->offers ) ) {
				$offers_html = '';
				foreach ( $configuration->offers as $offer ) {
					$offers_html .= $offer->render_inline();
				}
				// price-retailers
				if ( ! empty( $offers_html ) )
					$html .= '<ol style="list-style:none; margin-top:0; margin-bottom:8px; margin-left:0; margin-right:0; padding:0">' . $offers_html . '</ol>';
				unset( $offers_html );
			}
		}
		$html .= '</div>';
		return $html;
	}
}
?>