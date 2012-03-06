<?php

/**
 * Specs tab of gdgt databox
 *
 * @since 1.0
 */
class GDGT_Databox_Specs {

	/**
	 * Parse and store specs, product name, and more info URL
	 *
	 * @since 1.0
	 * @param array $specs_data Specifications
	 * @param string $product_name (optional) adds a title attribute to link
	 * @param string $url link to gdgt.com to view all specs
	 */
	public function __construct( $specs_data, $product_name='', $url = '' ) {
		if ( empty( $specs_data ) || ! is_array( $specs_data ) )
			return;
		if ( ! empty( $product_name ) )
			$this->product_name = trim( $product_name );
		if ( ! empty( $url ) )
			$this->url = $url;
		$this->specs = array();
		foreach ( $specs_data as $spec_data ) {
			// account for bad data
			if ( ! isset( $spec_data->spec )  || ! isset( $spec_data->value ) || ! isset( $spec_data->value->value ) )
				continue;
			$spec = trim( html_entity_decode( $spec_data->spec, ENT_QUOTES ) );
			if ( empty( $spec ) )
				continue;
			if ( isset( $spec_data->value->type ) && $spec_data->value->type === 'date' ) {
				// respect the site's date formatting preferences
				if ( ! isset( $this->date_format ) )
					$this->date_format = get_option( 'date_format', 'F j, Y' );
				$value = GDGT_Databox_Specs::string_to_datetime( $spec_data->value->value );
				if ( $value instanceOf DateTime )
					$this->specs[ $spec ] = $value;
				unset( $value );
			} else {
				$value = trim( strip_tags( html_entity_decode( $spec_data->value->value, ENT_QUOTES, 'UTF-8' ) ) );
				if ( ! empty( $value ) )
					$this->specs[ $spec ] = $value;
				unset( $value );
			}
			unset( $spec );
		}
	}

	/**
	 * Convert a date expressed as YYYY-MM-DD into a DateTime object for later formatting
	 *
	 * @since 1.0
	 * @uses DateTime::__construct()
	 * @uses gmmktime()
	 * @param string $date_str date string in the format YYYY-MM-DD, YYYY-MM, or YYYY
	 * @return DateTime object representation of the passed-in date at UTC
	 */
	public static function string_to_datetime( $date_str ) {
		$date_pieces = explode( '-', $date_str );
		$formatted_date = array();
		if ( isset( $date_pieces[0] ) ) {
			$year = absint( $date_pieces[0] );
			if ( $year > 1900 )
				$formatted_date['year'] = $year;
			else
				return false;
			unset( $year );
		}
		if ( isset( $date_pieces[1] ) ) {
			$month = absint( $date_pieces[1] );
			if ( $month > 0 && $month < 13 )
				$formatted_date['month'] = $month;
			else
				$formatted_date['month'] = 1;
			unset( $month );
		} else {
			$formatted_date['month'] = 1;
		}
		if ( isset( $date_pieces[2] ) ) {
			$day = absint( $date_pieces[2] );
			if ( $day > 0 && $day < 32 )
				$formatted_date['day'] = $day;
			else
				$formatted_date['day'] = 1;
			unset( $day );
		} else {
			$formatted_date['day'] = 1;
		}
		unset( $date_pieces );

		try {
			return new DateTime( '@' . gmmktime( 0, 0, 0, $formatted_date['month'], $formatted_date['day'], $formatted_date['year'] ) );
		} catch( Exception $e ) {
			return false;
		}
	}

	/**
	 * Build a HTML string from specs data
	 *
	 * @since 1.0
	 * @param bool $displayed should the tab appear hidden on initial view?
	 * @param string $anchor_target possible custom anchor attributes including target
	 * @return string HTML markup for the specs tab
	 */
	public function render( $displayed = false, $anchor_target = '' ) {
		$html = '<div class="gdgt-content gdgt-content-specs" role="tabpanel" ';
		if ( $displayed === true )
			$html .= 'aria-hidden="false">';
		else
			$html .= 'aria-hidden="true" style="display:none">';
		if ( empty( $this->specs ) ) {
			$html .= '<div class="gdgt-no-content"><p><strong>' . esc_html( __( 'There aren\'t any specs for this product yet.', 'gdgt-databox' ) ) . '</strong><br />' . esc_html( __( 'Check back soon!', 'gdgt-databox' ) ) . '</p></div>';
		} else {
			$html .= '<ul class="gdgt-specs">';
			foreach( $this->specs as $label => $value ) {
				$html .= '<li><span class="gdgt-specs-label">' . esc_html( $label ) . '</span><span class="gdgt-specs-value">';
				if ( $value instanceOf DateTime )
					$html .= $value->format( $this->date_format );
				else
					$html .= esc_html( $value );
				$html .= '</span></li>';
			}
			$html .= '</ul>';
			if ( isset( $this->url ) ) {
				$html .= '<a class="gdgt-link-right gdgt-all-specs" href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '" data-ga="See all specs"' . $anchor_target;
				if ( isset( $this->product_name ) )
					$html .= ' title="' . esc_attr( sprintf( __( '%s specifications', 'gdgt-databox' ), $this->product_name ) ) . '"';
				$html .= '>' . _x( 'see all <abbr>specs</abbr>', 'product specifications', 'gdgt-databox' ) . ' &#8594;</a>';
			}
		}
		$html .= '<div class="gdgt-clear"></div></div>';
		return $html;
	}
}

?>