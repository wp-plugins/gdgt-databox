<?php

/**
 * Reviews tab of gdgt Databox
 *
 * @since 1.0
 */
class GDGT_Databox_Ratings {

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
		else if ( $rating >= 8 )
			return 'green';
		else if ( $rating >= 6 )
			return 'yellow';
		else
			return 'red';
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
		$ratings_exist = true;
		if ( ! isset( $this->ratings ) || empty( $this->ratings ) || ( isset( $this->average_rating ) && $this->average_rating < 1 ) || ( isset( $this->total ) && $this->total === 0 ) )
			$ratings_exist = false;

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
			$html .= '<a rel="nofollow" class="gdgt-button gdgt-write-review" data-ga="Write review button" href="' . esc_url( $this->write_url, array( 'http', 'https' ) ) . '"';
			if ( isset( $this->product_name ) )
				$html .= ' title="' . esc_attr( sprintf( __( 'Review the %s', 'gdgt-databox' ), $this->product_name ) ) . '"';
			$html .= $anchor_target . '>' . esc_html( __( 'write a review', 'gdgt-databox' ) ) . '</a>';
		}
		if ( $ratings_exist && isset( $this->url ) )
			$html .= '<a class="gdgt-link-right gdgt-all-reviews" data-ga="See all reviews" href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '"' . $anchor_target . '>' . __( 'see all reviews', 'gdgt-databox' ) .  ' &#8594;</a>';
		$html .= '</div></div>';
		return $html;
	}
}

?>