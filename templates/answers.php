<?php

if ( ! class_exists( 'GDGT_Databox_Discussions' ) )
	require_once dirname( __FILE__ ) . '/discussions.php';

/**
 * gdgt template for answers tab data
 *
 * @since 1.0
 */
class GDGT_Databox_Answers extends GDGT_Databox_Discussions {
	const content_class = 'gdgt-content-answers';

	/**
	 * Inner text for a answer action button
	 *
	 * @since 1.0
	 * @param int $total total number of replies. zero or greater
	 * @return string inner text for a link
	 */
	public static function single_discussion_action_text( $total ) {
		$total = absint( $total );
		if ( $total === 0 )
			return __( 'answer this', 'gdgt-databox' );
		else
			return sprintf( _n( '%d answer', '%d answers', $total, 'gdgt-databox' ), $total );
	}

	/**
	 * Solicit viewer activity when no discussions exist
	 *
	 * @since 1.0
	 * @return string HTML markup
	 */
	public static function render_no_content() {
		return '<div class="gdgt-content-left gdgt-no-content"><p><strong>' . esc_html( __( 'No one has asked a question about this product yet.', 'gdgt-databox' ) ) . '</strong><br />' . esc_html( __( 'Why not be the first?', 'gdgt-databox' ) ) . '</p></div>';
	}

	/**
	 * Render right content area soliciting viewer action
	 *
	 * @since 1.0
	 * @param string $anchor_target anchor element extra attributes
	 * @return string HTML markup for answers tab sidebar
	 */
	private function render_content_right( $anchor_target = '' ) {
		$html = '<div class="gdgt-content-right"><p>' . esc_html( __( 'Get better answers and support from people who actually have this product!', 'gdgt-databox' ) ) . '</p>';

		if ( isset( $this->write_url ) ) {
			$html .= '<a rel="nofollow" class="gdgt-button gdgt-ask-question" data-ga="Ask question button" href="' . esc_url( $this->write_url, array( 'http', 'https' ) ) . '"' . $anchor_target;
			if ( isset( $this->product_name ) )
				$html .= ' title="' . esc_attr( sprintf( __( 'Ask a question about the %s', 'gdgt-databox' ), $this->product_name ) ) . '"';
			$html .= '>' . esc_html( __( 'ask a question', 'gdgt-databox' ) ) . '</a>';
		}

		if ( isset( $this->url ) && isset( $this->discussions ) && ! empty( $this->discussions ) ) {
			$html .= '<a class="gdgt-link-right gdgt-all-answers" data-ga="See all answers" href="' . esc_url( $this->url, array( 'http', 'https' ) ) .  '"' . $anchor_target;
			if ( isset( $this->product_name ) )
				$html .= ' title="' . esc_attr( sprintf( __( '%s answers', 'gdgt-databox' ), $this->product_name ) ) . '"';
			$html .= '>' . esc_html( __( 'see all answers', 'gdgt-databox' ) ) . ' &#8594;</a>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Build HTML for the answers tab
	 *
	 * @since 1.0
	 * @param bool $displayed is the current tab displayed on initial view
	 * @param string $anchor_target extra anchor element attributes
	 * @return string HTML markup
	 */
	public function render( $displayed = false, $anchor_target = '' ) {
		$html = '<div class="gdgt-content ' . static::content_class . '" role="tabpanel" ';
		if ( $displayed === true )
			$html .= 'aria-hidden="false"';
		else
			$html .= 'aria-hidden="true" style="display:none"';
		$html .= '>';
		if ( isset( $this->discussions ) && ! empty( $this->discussions ) ) {
			$html .= '<ol class="gdgt-content-left">';
			foreach( $this->discussions as $discussion ) {
				$html .= $this->render_single_discussion( $discussion, $anchor_target );
			}
			$html .= '</ol>';
		} else {
			$html .= static::render_no_content();
		}
		$html .= $this->render_content_right( $anchor_target );
		$html .= '</div>';
		return $html;
	}
}
?>