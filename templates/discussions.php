<?php

/**
 * Discussions tab data template
 *
 * @since 1.0
 */
class GDGT_Databox_Discussions {
	const content_class = 'gdgt-content-discussions';

	/**
	 * Parse and clean tab data returned by gdgt API
	 *
	 * @since 1.0
	 * @param stdClass $discussion_data tab data returned by the gdgt API
	 * @param string $product_name full product name
	 */
	public function __construct( $discussion_data, $product_name = '' ) {

		if ( ! empty( $product_name ) )
			$this->product_name = trim( $product_name );
		if ( isset( $discussion_data->url ) )
			$this->url = $discussion_data->url;
		if ( isset( $discussion_data->write_url ) )
			$this->write_url = $discussion_data->write_url;
		if ( isset( $discussion_data->total_count ) )
			$this->total = absint( $discussion_data->total_count );

		if ( isset( $discussion_data->posts ) && is_array( $discussion_data->posts ) ) {
			$this->discussions = array();
			foreach( $discussion_data->posts as $post ) {
				$subject = trim( html_entity_decode( $post->subject, ENT_QUOTES ) );
				if ( empty( $subject ) )
					continue;
				$discussion = new stdClass();
				$discussion->subject = $subject;
				$discussion->url = $post->post_url;
				unset( $subject );

				$author = new stdClass();
				$image = new stdClass();
				if ( isset( $post->image_url ) )
					$image->url = $post->image_url;
				else
					$image->url = 'http://media.gdgt.com/assets/img/site/blank-user-25.gif';
				$image->width = $image->height = 25;
				$author->image = $image;
				unset( $image );
				if ( isset( $post->username ) )
					$author->username = trim( $post->username );
				$discussion->author = $author;
				unset( $author );

				if ( isset( $post->total_replies ) )
					$discussion->total_replies = absint( $post->total_replies );
				else
					$discussion->total_replies = 0;
				$this->discussions[] = $discussion;
				unset( $discussion );
			}
		}
	}

	/**
	 * Inner text for a discussion action button
	 * Override in subclass for different types of discussions
	 *
	 * @since 1.0
	 * @param int $total total number of replies. zero or greater
	 * @return string inner text for a link
	 */
	public static function single_discussion_action_text( $total ) {
		$total = absint( $total );
		if ( $total === 0 )
			return __( 'add reply', 'gdgt-databox' );
		else
			return sprintf( _n( '%d reply', '%d replies', $total, 'gdgt-databox' ), $total );
	}

	/**
	 * Build HTML for a single discussion
	 *
	 * @since 1.0
	 * @param stdClass $discussion single discussion object built in class init
	 * @param string $anchor_target anchor attribute string
	 */
	public function render_single_discussion( $discussion, $anchor_target = '' ) {
		$url = esc_url( $discussion->url, array( 'http', 'https' ) );
		$item = '<li class="gdgt-thread-row"><span class="gdgt-thread-replies">';
		$item .= '<a class="gdgt-reply-count" href="' . $url . '"' . $anchor_target;
		$action = GDGT_Databox_Discussions::single_discussion_action_text( $discussion->total_replies );
		$item .= ' title="' . esc_attr( $action . ': ' . $discussion->subject ) . '"';
		$item .= '>' . esc_html( $action ) . '</a></span>';
		$item .= '<a class="gdgt-user-image" href="' . $url . '"' . $anchor_target . '><noscript class="img"><img alt="';
		if ( isset( $discussion->author->username ) )
			$item .= esc_attr( sprintf( __( 'gdgt user %s', 'gdgt-databox' ), $discussion->author->username ) );
		$item .= '" src="' . esc_url( $discussion->author->image->url, array( 'http', 'https' ) ) . '" width="' . $discussion->author->image->width . '" height="' . $discussion->author->image->height . '" /></noscript></a>';
		$item .= '<a class="gdgt-thread-title" href="' . $url . '"' . $anchor_target . '>' . esc_html( $discussion->subject ) . '</a>';
		$item .= '</li>';
		return $item;
	}

	/**
	 * Solicit viewer activity when no discussions exist
	 *
	 * @since 1.0
	 * @return string HTML markup
	 */
	public static function render_no_content() {
		return '<div class="gdgt-content-left gdgt-no-content"><p><strong>' . esc_html( __( 'No one has started a discussion about this product yet.', 'gdgt-databox' ) ) . '</strong><br />' . esc_html( __( 'Why not be the first?', 'gdgt-databox' ) ) . '</p></div>';
	}

	/**
	 * Render right content area soliciting viewer action
	 *
	 * @since 1.0
	 * @param string $anchor_target anchor element extra attributes
	 * @return string HTML markup for discussion tab sidebar
	 */
	private function render_content_right( $anchor_target = '' ) {
		$html = '<div class="gdgt-content-right"><p>' . esc_html( __( 'Talk about this product with other people who own it!', 'gdgt-databox' ) ) . '</p>';
		
		if ( isset( $this->write_url ) ) {
			$html .= '<a rel="nofollow" class="gdgt-button gdgt-start-discussion" data-ga="Start discussion button" href="' . esc_url( $this->write_url, array( 'http', 'https' ) ) . '"' . $anchor_target;
			if ( isset( $this->product_name ) )
				$html .= ' title="' . esc_attr( sprintf( __( 'Start a discussion about the %s', 'gdgt-databox' ), $this->product_name ) ) . '"';
			$html .= '>'. esc_html( __( 'start a discussion', 'gdgt-databox' ) ) . '</a>';
		}

		if ( isset( $this->url ) && isset( $this->discussions ) && ! empty( $this->discussions ) ) {
			$html .= '<a class="gdgt-link-right gdgt-all-discussions" data-ga="See all discussions"' . $anchor_target;
			if ( isset( $this->product_name ) )
				$html .= ' title="' . esc_attr( sprintf( _x( '%s discussions', 'product discussions', 'gdgt-databox' ), $this->product_name ) ) . '"';
			$html .= '>' . esc_html( __( 'see all discussions', 'gdgt-databox' ) ) . ' &#8594;</a>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Build HTML for the discussions tab
	 *
	 * @since 1.0
	 * @param bool $displayed is the current tab displayed on initial view
	 * @param string $anchor_target extra anchor element attributes
	 * @return string HTML markup
	 */
	public function render( $displayed = false, $anchor_target = '' ) {
		$html = '<div class="gdgt-content ' . GDGT_Databox_Discussions::content_class . '" role="tabpanel" ';
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
			$html .= GDGT_Databox_Discussions::render_no_content();
		}
		$html .= $this->render_content_right( $anchor_target );
		$html .= '</div>';
		return $html;
	}
}

?>