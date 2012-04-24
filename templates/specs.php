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
				$html .= '<a class="gdgt-link-right gdgt-all-specs" href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '"' . $anchor_target;
				if ( isset( $this->product_name ) )
					$html .= ' title="' . esc_attr( sprintf( __( '%s specifications', 'gdgt-databox' ), $this->product_name ) ) . '"';
				$html .= '>' . _x( 'see all <abbr>specs</abbr>', 'product specifications', 'gdgt-databox' ) . ' &#8594;</a>';
			}
		}
		$html .= '<div class="gdgt-clear"></div></div>';
		return $html;
	}

	/**
	 * Build HTML for a specs tab with all CSS inline.
	 * Used for web feeds (RSS & Atom)
	 *
	 * @since 1.1
	 * @return string HTML markup for the specs tab with inline CSS
	 */
	public function render_inline() {
		$html = '<div style="clear:both; min-height:120px; padding-top:10px; padding-bottom:0; padding-left:0; padding-right:0; overflow:hidden">';
		if ( empty( $this->specs ) ) {
			$html .= '<div style="min-height:95px; padding-top:40px; line-height:20px; color:#333; text-align:center"><p><strong style="font-weight:bold">' . esc_html( __( 'There aren\'t any specs for this product yet.', 'gdgt-databox' ) ) . '</strong><br />' . esc_html( __( 'Check back soon!', 'gdgt-databox' ) ) . '</p></div>';
		} else {
			$html .= '<ul style="list-style:none; min-height:105px; margin:0; padding:0">';
			foreach ( $this->specs as $label => $value ) {
				$html .= '<li style="float:left; width:25%; height:47px; padding-bottom:5px; padding-top:0; padding-left:0; padding-right:0; margin:0; line-height:0; font-weight:bold; line-height:0; overflow:hidden">';
				$html .= '<span style="display:block; width:95%; padding:0; margin-bottom:6px; margin-top:0; margin-left:0; margin-right:0; font-size:11px; color:#AAA; line-height:11px; text-transform:uppercase; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">' . esc_html( $label ) . '</span>';
				$html .= '<span style="display:block; width:95%; max-height: 28px; line-height: 15px; color:#333; overflow:hidden">';
				if ( $value instanceOf DateTime )
					$html .= $value->format( $this->date_format );
				else
					$html .= esc_html( $value );
				$html .= '</span>';
				$html .= '</li>';
			}
			$html .= '</ul>';

			// link to all specs
			if ( isset( $this->url ) ) {
				$html .= '<p><a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '" style="clear:both; float:right; margin-top:3px; margin-bottom:0; margin-left:0; margin-right:0; font-size:13px; font-weight:bold; color:#3399CC; cursor:pointer; overflow:hidden; white-space:nowrap; text-decoration:none; border-bottom-width:0"';
				if ( isset( $this->product_name ) )
					$html .= ' title="' . esc_attr( sprintf( __( '%s specifications', 'gdgt-databox' ), $this->product_name ) ) . '"';
				$html .= '>' . _x( 'see all <abbr>specs</abbr>', 'product specifications', 'gdgt-databox' ) . ' &#8594;</a></p>';
			}
		}
		$html .= '</div>';
		return $html;
	}
}

?>