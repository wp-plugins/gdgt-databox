<?php
/**
 * A single merchant's offer for a product
 *
 * @since 1.2
 */
class GDGT_Databox_Offer {
	/**
	 * Allowed currencies and their HTML symbol
	 *
	 * @since 1.2
	 * @var array
	 */
	public $currency_symbols = array( 'USD' => '$', 'EUR' => '&#8364;' );

	/**
	 * Build an offer based on gdgt API data
	 *
	 * @since 1.2
	 * @param stdClass $data gdgt API data for a single offer
	 */
	public function __construct( stdClass $data ) {
		if ( ! isset( $data->price ) || ! isset( $data->price->amount ) || ! isset( $data->price->currency ) || ! array_key_exists( $data->price->currency, $this->currency_symbols ) )
			return;
		$this->price = $data->price->amount;
		$this->currency = $data->price->currency;

		$author = new stdClass();
		$author->name = $data->retailer_name;
		if ( isset( $data->is_featured ) && $data->is_featured === true )
			$author->featured = true;
		else
			$author->featured = false;

		if ( isset( $data->retailer_image ) && ! empty( $data->retailer_image ) ) {
			// IAB micro bar image
			$image = new stdClass();
			$image->src = $data->retailer_image;
			$image->width = 88;
			$image->height = 31;
			$author->image = $image;
			unset( $image );
		}

		$this->author = $author;
		unset( $author );

		if ( isset( $data->is_in_stock ) && $data->is_in_stock === true )
			$this->availability = 'inStock';
		else
			$this->availability = 'outOfStock';

		if ( isset( $data->is_on_contract ) && $data->is_on_contract === true )
			$this->is_on_contract = true;
		else
			$this->is_on_contract = false;

		$this->url = urldecode( $data->link );
	}

	/**
	 * Generate HTML for a single merchant offer
	 *
	 * @since 1.2
	 * @param bool $schema_org include Schema.org markup
	 * @param string $anchor_target possible custom anchor attributes including target
	 * @param bool $noscript prepare for a possible noscript state such as image lazyload
	 * @return string HTML list item (li)
	 */
	public function render( $schema_org = true, $anchor_target = '', $noscript = true ) {
		if ( ! isset( $this->url ) )
			return '';
		$link = esc_url( $this->url, array( 'http', 'https' ) );

		$html = '<li';
		if ( $this->author->featured === true )
			$html .= ' class="gdgt-featured-seller-highlight"';
		if ( $schema_org === true )
			$html .= ' itemprop="offers" itemscope itemtype="http://schema.org/Offer"><link itemprop="itemCondition" href="http://schema.org/NewCondition" /><meta itemprop="priceCurrency" content="' . $this->currency . '" /><meta itemprop="price" content="' . $this->price . '" /><link itemprop="availability" href="http://schema.org/' . ucfirst( $this->availability ) . '" />';
		else
			$html .= '>';

		// who
		$html .= '<span class="gdgt-price-seller"><a rel="nofollow" href="' . $link . '"' . $anchor_target;
		if ( $schema_org === true )
			$html .= ' itemprop="seller" itemscope itemtype="http://schema.org/Organization">';
		else
			$html .= '>';
		if ( $this->author->featured === true )
			$html .= '<span class="gdgt-featured-seller-label">' . esc_html( __( 'featured seller', 'gdgt-databox' ) ) . '</span>';
		if ( isset( $this->author->image ) ) {
			if ( $schema_org === true )
				$html .= '<meta itemprop="name" content="' . esc_attr( $this->author->name ) . '" />';
			$image = '<img alt="' . esc_attr( $this->author->name ) . '" src="' . esc_url( $this->author->image->src, array( 'http', 'https' ) ) . '" width="' . $this->author->image->width . '" height="' . $this->author->image->height . '"';
			if ( $schema_org === true )
				$image .= ' itemprop="image"';
			$image .= ' />';
			if ( $noscript === true )
				$html .= '<noscript class="img" data-html="' . esc_attr( $image ) . '">' . $image . '</noscript>';
			else
				$html .= $image;
			unset( $image );
		} else {
			$html .= '<span class="gdgt-price-seller-name" itemprop="name">' . esc_html( $this->author->name ) . '</span>';
		}
		$html .= '</a></span>';

		// how much?
		$html .= '<span class="gdgt-price-details"><a rel="nofollow" href="' . $link . '"' . $anchor_target;
		if ( $schema_org === true )
			$html .= ' itemprop="url"';
		$html .= '>';
		if ( floor($this->price) == 0 ) {
			$html .= 'FREE';
		} else {
			$html .= $this->currency_symbols[$this->currency];
			$html .= number_format_i18n( $this->price, 2 );
		}
    if ( $this->is_on_contract === true )
      $html .= '</a> <span class="gdgt-aside">' . esc_html( __( 'on contract', 'gdgt-databox' ) ) . '</span></span>';
    else
      $html .= '</a> <span class="gdgt-aside">' . esc_html( __( '+ tax & shipping', 'gdgt-databox' ) ) . '</span></span>';

		// get some
		$html .= '<span><a rel="nofollow" href="' . $link . '"' . $anchor_target . ' class="gdgt-button gdgt-' . esc_attr( strtolower( $this->availability ) );
		if ( $this->availability === 'inStock' )
			$html .= ' blue">' . esc_html( _x( 'buy now', 'purchase this item immediately', 'gdgt-databox' ) );
		else if ( $this->availability === 'outOfStock' )
			$html .= '">' . esc_html( _x( 'out of stock', 'not one of this item is currently available', 'gdgt-databox' ) );
		$html .= '</a></span>';

		$html .= '</li>';
		return $html;
	}

	/**
	 * Generate HTML for a single merchant offer with all CSS inline
	 * Used for web feeds (RSS & Atom)
	 *
	 * @since 1.2
	 */
	public function render_inline() {
		$link = esc_url( $this->url, array( 'http', 'https' ) );

		$html = '<li style="clear:both; border-bottom-width:1px; border-bottom-style:solid; border-bottom-color: #EEE; line-height:1em; padding-left:8px; padding-right:8px;';
		// featured-seller-highlight
		if ( $this->author->featured )
			$html .= 'min-height:50px; padding-top:2px; padding-bottom:2px; background-color:#FFFBD9';
		else
			$html .= 'min-height:30px; padding-top:10px; padding-bottom:10px';
		$html .= '">';

		// price-seller
		$html .= '<span style="float:left; width:25%; text-align:left; ';
		if ( ! $this->author->featured )
			$html .= 'height:31px';
		$html .= '"><a rel="nofollow" href="' . $link . '" style="color:#3399CC; font-size:16px; text-decoration:none; border-bottom-width:0">';
		if ( $this->author->featured === true )
			$html .= '<span style="display:block; margin-top:0; margin-bottom:2px; font-size:10px; color:#CC0000; text-indent:8px">' . esc_html( __( 'featured seller', 'gdgt-databox' ) ) . '</span>'; // featured-seller-label
		if ( isset( $this->author->image ) )
			$html .= '<img alt="' . esc_attr( $this->author->name ) . '" src="' . esc_url( $this->author->image->src, array( 'http', 'https' ) ) . '" width="' . $this->author->image->width . '" height="' . $this->author->image->height . '" style="border:0" />';
		else
			$html .= '<span style="line-height:31px">' . esc_html( $this->author->name ) . '</span>'; // price-seller-name
		$html .= '</a></span>';

		// price-details
		$html .= '<span style="float:left; width:50%; padding-bottom:0; padding-left:0; padding-right:0; font-size:11px; color:#666; text-align:center; padding-top:';
		if ( $this->author->featured )
			$html .= '18';
		else
			$html .= '9';
		$html .= 'px"><a rel="nofollow" href="' . $link . '" style="padding-top:1px; padding-bottom:1px; padding-left:3px; padding-right:3px; font-size:24px; color:#3399CC; text-decoration:none; border-bottom-width:0">' . $this->currency_symbols[$this->currency] . number_format_i18n( $this->price, 2 ) . '</a> ';
		// aside
		$html .= '<span style="color:#898989">' . esc_html( __( '+ tax & shipping', 'gdgt-databox' ) ) . '</span>';
		$html .= '</span>';

		// button
		$style = array(
			'float' => 'right',
			'display' => 'inline-block',
			'cursor' => 'pointer',
			'padding-top' => '5px',
			'padding-bottom' => '5px',
			'padding-left' => '10px',
			'padding-right' => '10px',
			'background-color' => '#EEE',
			'color' => '#333',
			'border-color' => '#CCC',
			'border-style' => 'solid',
			'border-width' => '1px',
			'font-size' => '12px',
			'font-weight' => 'bold',
			'line-weight' => 'normal',
			'text-align' => 'center',
			'text-decoration' => 'none',
			'text-transform' => 'uppercase'
		);
		if ( $this->availability === 'inStock' ) {
			$style['padding-top'] = '6px';
			$style['padding-bottom'] = '6px';
			$style['padding-left'] = '20px';
			$style['padding-right'] = '20px';
			$style['margin-top'] = '2px';
			$style['margin-bottom'] = '0';
			$style['margin-left'] = '0';
			$style['margin-right'] = '0';
			$style['background-color'] = '#3399CC';
			$style['color'] = '#FFF';
			$style['border'] = '0';
			$style['font-size'] = '12px';
		}
		if ( $this->author->featured )
			$style['margin-top'] = '11px';
		$html .= '<span><a rel="nofollow" href="' . $link . '"';
		$style_attribute = '';
		foreach ( $style as $property => $value ) {
			$style_attribute .= $property . ':' . $value . ';';
		}
		$html .= ' style="' . rtrim( $style_attribute, ';' ) . '">';
		if ( $this->availability === 'inStock' )
			$html .= esc_html( _x( 'buy now', 'purchase this item immediately', 'gdgt-databox' ) );
		else if ( $this->availability === 'outOfStock' )
			$html .= esc_html( _x( 'out of stock', 'not one of this item is currently available', 'gdgt-databox' ) );
		$html .= '</a></span>';
		unset( $style_attribute );
		unset( $style );

		$html .= '</li>';
		return $html;
	}
}
?>
