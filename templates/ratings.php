<?php

/**
 * Reviews tab of gdgt Databox
 *
 * @since 1.0
 */
class GDGT_Databox_Ratings {
	/**
	 * Background color hex mappings for inline use
	 *
	 * @since 1.1
	 */
	public static $color_hex = array( 'green' => '#009900', 'light-green' => '#99cc00', 'yellow' => '#ffac00', 'red' => '#d50000' );

	/**
	 * @since 1.0
	 * @param stdClass $reviews_data user_reviews tab data
	 * @param string $product_name full product name
	 */
	public function __construct( $reviews_data, $product_name='' ) {

		if ( ! empty( $product_name ) )
			$this->product_name = trim( $product_name );

		if ( isset( $reviews_data->url ) )
			$this->url = $reviews_data->url;
		if ( isset( $reviews_data->write_url ) )
			$this->write_url = $reviews_data->write_url;
		if ( isset( $reviews_data->total_count ) )
			$this->total = absint( $reviews_data->total_count );
		if ( isset( $reviews_data->average_rating ) )
			$this->average_rating = (float) $reviews_data->average_rating;

		$this->ratings = array();
		if ( isset( $reviews_data->criteria ) && is_array( $reviews_data->criteria ) ) {
			foreach ( $reviews_data->criteria as $criteria ) {
				$criterion = trim( html_entity_decode( $criteria->criterion, ENT_QUOTES, 'UTF-8' ) );
				if ( ! empty( $criterion ) )
					$this->ratings[ $criterion ] = (float) $criteria->rating;
				unset( $criterion );
			}
		}
	}

	/**
	 * Associate a color class with a rating value
	 *
	 * @since 1.0
	 * @param float $rating rating value between 0 and 10
	 * @return string color name or empty string
	 */
	public static function rating_color( $rating ) {
		if ( ! is_float( $rating ) || $rating <= 0 )
			return '';
		else if ( $rating >= 9 )
			return 'green';
		else if ( $rating >= 8 )
			return 'light-green';
		else if ( $rating >= 6 )
			return 'yellow';
		else
			return 'red';
	}

	/**
	 * Is enough data present to display ratings?
	 *
	 * @since 1.1
	 * @return bool true if ratings, average rating, and total ratings count found
	 */
	private function ratings_exist() {
		if ( ! isset( $this->ratings ) || empty( $this->ratings ) || ( isset( $this->average_rating ) && $this->average_rating < 1 ) || ( isset( $this->total ) && $this->total === 0 ) )
			return false;
		else
			return true;
	}

	/**
	 * Build a HTML string based on ratings data
	 *
	 * @since 1.0
	 * @param bool $displayed should the tab appear hidden on initial view?
	 * @param bool $schema_org include schema.org markup
	 * @param string $anchor_target possible custom anchor attributes including target
	 * @return string HTML markup for the reviews tab
	 */
	public function render( $displayed = false, $schema_org = true, $anchor_target = '' ) {
		$ratings_exist = $this->ratings_exist();

		$html = '<div class="gdgt-content gdgt-content-reviews" role="tabpanel" ';
		if ( $displayed === true )
			$html .= 'aria-hidden="false"';
		else
			$html .= 'aria-hidden="true" style="display:none"';
		$html .= '><div class="gdgt-content-left';

		if ( $ratings_exist ) {
			$html .= '">';
			if ( isset( $this->average_rating ) ) {
				$html .= '<div class="gdgt-reviews-avg-rating-block"';
				if ( $schema_org ) {
					$html .= '  itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating"><meta itemprop="worstRating" content="1" /><meta itemprop="bestRating" content="10" />';
					if ( isset( $this->total ) )
						$html .= '<meta itemprop="ratingCount" content="' . $this->total . '" />';
				} else {
					$html .= '>';
				}
				$html .= '<span class="gdgt-reviews-avg-rating big ' . GDGT_Databox_Ratings::rating_color( $this->average_rating ) . '"';
				if ( $schema_org )
					$html .= ' itemprop="ratingValue"';
				$html .= '>' . number_format_i18n( $this->average_rating, 1 ) . '</span> <span';
				if ( $schema_org )
					$html .= ' itemprop="name"';
				$html .= '>' . esc_html( __( 'average user rating', 'gdgt-databox' ) ) . '</span></div>';
			}

			$html .= '<ul class="gdgt-reviews-criteria">';
			foreach ( $this->ratings as $criteria => $rating ) {
				$html .= '<li><span class="gdgt-criteria-label">' . esc_html( $criteria ) . '</span><span class="gdgt-reviews-avg-rating small ' . GDGT_Databox_Ratings::rating_color( $rating ) . '">';
				if ( $rating < 1 )
					$html .= '&#8212;';
				else
					$html .= number_format_i18n( $rating, 1 );
				$html .= '</span></li>';
			}
			$html .= '</ul>';
		} else {
			$html .= ' gdgt-no-content"><strong>' . esc_html( __( 'There are not any user reviews for this product yet.', 'gdgt-databox' ) ) . '</strong><br />' . esc_html( __( 'Why not be the first to write one?', 'gdgt-databox' ) );
		}
		$html .= '</div><div class="gdgt-content-right"><p>' . esc_html( __( 'Get better reviews from people who actually have this product!', 'gdgt-databox' ) ) . '</p>';
		if ( isset( $this->write_url ) ) {
			$html .= '<a rel="nofollow" class="gdgt-button gdgt-write-review" href="' . esc_url( $this->write_url, array( 'http', 'https' ) ) . '"';
			if ( isset( $this->product_name ) )
				$html .= ' title="' . esc_attr( sprintf( __( 'Review the %s', 'gdgt-databox' ), $this->product_name ) ) . '"';
			$html .= $anchor_target . '>' . esc_html( __( 'write a review', 'gdgt-databox' ) ) . '</a>';
		}
		if ( $ratings_exist && isset( $this->url ) )
			$html .= '<a class="gdgt-link-right gdgt-all-reviews" href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '"' . $anchor_target . '>' . esc_html( __( 'see all reviews', 'gdgt-databox' ) ) .  ' &#8594;</a>';
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Self contained view for use in web feeds
	 * Inline CSS
	 *
	 * @since 1.1
	 * @return string HTML markup
	 */
	public function render_inline() {
		$ratings_exist = $this->ratings_exist();
		$html = '<div style="min-height:135px; padding-top:8px; padding-bottom:8px; padding-left:15px; padding-right:15px; margin:0; background-color:#FFF; border-color: #CCC; border-top-width: 0; border-bottom-width:1px; border-left-width:1px; border-right-width:1px; border-style:solid; color:#333; overflow:hidden"><div style="float:left; width:75%; padding-right:15px; padding-left:0; padding-bottom:0; margin:0; border-right-color:#EEE;border-right-width:1px; border-right-style:solid';
		if ( $ratings_exist ) {
			$html .= ';padding-top:0; min-height:135px">';
			if ( isset( $this->average_rating ) ) {
				$html .= '<div style="display:block; height:30px; border-bottom-color:#EEE; border-bottom-style:solid; border-bottom-width:1px; font-size:13px; font-weight:bold; line-height:25px">';
				$html .= '<span style="padding:0; margin:0; font-weight:bold; color:#FFF; text-align:center; display:block; float:left; width:40px; height:25px; margin-left:0; margin-right:10px; margin-top:0; margin-bottom:0; font-size:20px; line-height:25px;background-color:';
				$color = GDGT_Databox_Ratings::rating_color( $this->average_rating );
				if ( ! empty( $color ) && array_key_exists( $color, GDGT_Databox_Ratings::$color_hex ) )
					$html .= GDGT_Databox_Ratings::$color_hex[ $color ];
				else
					$html .= '#999';
				unset( $color );
				$html .= '">' . number_format_i18n( $this->average_rating, 1 ) . '</span>';
				$html .= '<span>' . esc_html( __( 'average user rating', 'gdgt-databox' ) ) . '</span>';
				$html .= '</div>';
			}
			$html .= '<ul style="clear:both; list-style:none; padding:9px 0 0; margin:0 -15px">';
			foreach ( $this->ratings as $criteria => $rating ) {
				$html .= '<li style="float:left; width:43%; height:14px; padding-top:2px; padding-bottom:3px; padding-left:0; padding-right:0; margin-top:0; margin-bottom:0; margin-left:15px; margin-right:15px; font-size:11px; line-height:normal"><span style="display:inline-block; float:left; width:83%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">' . esc_html( $criteria ) . '</span><span style="padding:0; margin:0; font-weight:bold; color:#FFF; text-align:center; display:block; float:right; width:25px; height:14px; margin-top:-1px; margin-bottom:0; margin-left:5px; margin-right:0; font-size:12px; line-height:14px;background-color:';
				$color = GDGT_Databox_Ratings::rating_color( $rating );
				if ( ! empty( $color ) && array_key_exists( $color, GDGT_Databox_Ratings::$color_hex ) )
					$html .= GDGT_Databox_Ratings::$color_hex[ $color ];
				else
					$html .= '#999';
				unset( $color );
				$html .= '">';
				if ( $rating < 1 )
					$html .= '&#8212;';
				else
					$html .= number_format_i18n( $rating, 1 );
				$html .= '</span></li>';
			}
			$html .= '</ul>';
		} else {
			$html .= 'min-height:95px; padding-top:40px; line-height:20px; color:#333; text-align:center"><strong>' . esc_html( __( 'There are not any user reviews for this product yet.', 'gdgt-databox' ) ) . '</strong><br />' . esc_html( __( 'Why not be the first to write one?', 'gdgt-databox' ) );
		}
		$html .= '</div><div style="float:right; width:20%; padding:0; margin:0"><p style="padding:0; margin-top:3px; margin-bottom:12px; margin-left:0; margin-right:0; font-size:12px; line-height:16px; text-align:left">' . esc_html( __( 'Get better reviews from people who actually have this product!', 'gdgt-databox' ) ) . '</p>';
		if ( isset( $this->write_url ) ) {
			$html .= '<a href="' . esc_url( $this->write_url, array( 'http', 'https' ) ) . '" style="width:81%; margin-top:0; margin-bottom:10px; margin-left:0; margin-right:0; white-space:nowrap; display:inline-block; padding-top:5px; padding-bottom:5px; padding-left:12px; padding-right:12px; background-color:#d7d7d7; border-color:#999; border-style:solid; border-width:1px; font-size:13px; color:#333; text-align:center; text-decoration:none"';
			if ( isset( $this->product_name ) )
				$html .= ' title="' . esc_attr( sprintf( __( 'Review the %s', 'gdgt-databox' ), $this->product_name ) ) . '"';
			$html .= '>' . esc_html( __( 'write a review', 'gdgt-databox' ) ) . '</a>';
		}
		if ( $ratings_exist && isset( $this->url ) )
			$html .= '<a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '" style="clear:both; float:right; margin-top:3px; margin-bottom:0; margin-left:0; margin-right:0; font-size:13px; font-weight:bold; color:#00BDF6; cursor:pointer; white-space:nowrap; text-decoration:none; border-bottom-width:0">' . esc_html( __( 'see all reviews', 'gdgt-databox' ) ) . ' &#8594;</a>';
		$html .= '</div></div>';
		return $html;
	}
}

?>