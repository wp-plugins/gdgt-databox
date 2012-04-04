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
	 * Inline CSS version of render_single_discussion
	 *
	 * @since 1.1
	 * @return string HTML
	 */
	public function render_single_discussion_inline( $discussion ) {
		$url = esc_url( $discussion->url, array( 'http', 'https' ) );
		$item = '<li style="height:25px; margin-top:4px; margin-bottom: 20px; margin-left:0; margin-right:0; font-size:12px; text-align:left"><span style="">';

		$action = GDGT_Databox_Discussions::single_discussion_action_text( $discussion->total_replies );
		$item .= '<a href="' . $url . '" title="' . esc_attr( $action . ': ' . $discussion->subject ) . '" style="display:block; float:right; width:74px; height:23px; margin-bottom:6px; margin-top:0; margin-left:0; margin-right:0; background-color:#FFF; border-width:1px; border-style:solid; border-color:#CCC; font-size:11px; text-align:center; line-height:24px; text-decoration:none; color:#00BDF6; vertical-align:middle">' . esc_html( $action ) . '</a></span>';
		unset( $action );

		// user profile image
		$item .= '<a href="' . $url . '"><img alt="';
		if ( isset( $discussion->author->username ) )
			$item .= esc_attr( sprintf( __( 'gdgt user %s', 'gdgt-databox' ), $discussion->author->username ) );
		$item .= '" src="' . esc_url( $discussion->author->image->url, array( 'http', 'https' ) ) . '" width="' . $discussion->author->image->width . '" height="' . $discussion->author->image->height . '" style="border:0; display:inline-block; margin-right:8px; margin-left:0; margin-top:0; margin-bottom:0; vertical-align:middle" /></a>';

		$item .= '<a href="' . $url . '" style="display:inline-block; width:75%; max-height:40px; line-height:13px; overflow:hidden; text-decoration:none; border-bottom-width:0; color:#00BDF6; vertical-align:middle">' . esc_html( $discussion->subject ) . '</a></li>';
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
	 * No discussions exist
	 *
	 * @since 1.1
	 * @return string HTML markup
	 */
	public static function render_no_content_inline() {
		return '<div style="float:left; width:75%; padding-right:15px; padding-left:0; padding-top:40px; padding-bottom:3px; margin:0; border-right-color:#EEE; border-right-width:1px; border-right-style:solid; min-height:95px; line-height:20px; color:#333; text-align:center"><p><strong>' . esc_html( __( 'No one has started a discussion about this product yet.', 'gdgt-databox' ) ) . '</strong><br />' . esc_html( __( 'Why not be the first?', 'gdgt-databox' ) ) . '</p></div>';
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
			$html .= '<a rel="nofollow" class="gdgt-button gdgt-start-discussion" href="' . esc_url( $this->write_url, array( 'http', 'https' ) ) . '"' . $anchor_target;
			if ( isset( $this->product_name ) )
				$html .= ' title="' . esc_attr( sprintf( __( 'Start a discussion about the %s', 'gdgt-databox' ), $this->product_name ) ) . '"';
			$html .= '>'. esc_html( __( 'start a discussion', 'gdgt-databox' ) ) . '</a>';
		}

		if ( isset( $this->url ) && isset( $this->discussions ) && ! empty( $this->discussions ) ) {
			$html .= '<a class="gdgt-link-right gdgt-all-discussions" href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '"' . $anchor_target;
			if ( isset( $this->product_name ) )
				$html .= ' title="' . esc_attr( sprintf( _x( '%s discussions', 'product discussions', 'gdgt-databox' ), $this->product_name ) ) . '"';
			$html .= '>' . esc_html( __( 'see all discussions', 'gdgt-databox' ) ) . ' &#8594;</a>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Render right content area soliciting viewer action with inline styles
	 *
	 * @since 1.1
	 * @return string HTML markup for discussion tab sidebar
	 */
	private function render_content_right_inline() {
		$html = '<div style="float:right; width:20%; padding:0; margin:0"><p style="padding:0; margin-top:3px; margin-bottom:12px; margin-left:0; margin-right:0; font-size:12px; line-height:16px; text-align:left">' . esc_html( __( 'Talk about this product with other people who own it!', 'gdgt-databox' ) ) . '</p>';

		if ( isset( $this->write_url ) ) {
			$html .= '<a href="' . esc_url( $this->write_url, array( 'http', 'https' ) ) . '"';
			if ( isset( $this->product_name ) )
				$html .= ' title="' . esc_attr( sprintf( __( 'Start a discussion about the %s', 'gdgt-databox' ), $this->product_name ) ) . '"';
			$html .= ' style="display:inline-block; padding-top:5px; padding-bottom:5px; padding-left:12px; padding-right:12px; background-color:#d7d7d7; border-color:#999; border-style:solid; border-width:1px; font-size:12px; color:#333; text-align:center; text-decoration: none; width:81%; margin-top:0; margin-bottom:10px; margin-left:0; margin-right:0; white-space:nowrap">' . esc_html( __( 'start a discussion', 'gdgt-databox' ) ) . '</a>';
		}

		if ( isset( $this->url ) && isset( $this->discussions ) && ! empty( $this->discussions ) ) {
			$html .= '<a href="' . esc_url( $this->url, array( 'http', 'https' ) ) . '"';
			if ( isset( $this->product_name ) )
				$html .= ' title="' . esc_attr( sprintf( __( 'Start a discussion about the %s', 'gdgt-databox' ), $this->product_name ) ) . '"';
			$html .= ' style="clear:both; float:right; margin-top:3px; margin-bottom:0; margin-left:0; margin-right:0; font-size:11px; font-weight:bold; color:#00BDF6; cursor:pointer; white-space:nowrap; text-decoration:none; border-bottom-width:0">' . esc_html( __( 'see all discussions', 'gdgt-databox' ) ) . ' &#8594;</a>';
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

	/**
	 * Build HTML for the discussions tab with inline CSS
	 *
	 * @since 1.1
	 * @return string HTML
	 */
	public function render_inline() {
		$html = '<div style="clear:both; min-height:135px; padding-top:8px; padding-bottom:8px; padding-left:15px; padding-right:15px; margin:0; background-color:#FFF; border-color:#CCC; border-top-width:0; border-bottom-width:1px; border-left-width:1px; border-right-width:1px; border-style:solid; color:#333; overflow:hidden">';
		if ( isset( $this->discussions ) && ! empty( $this->discussions ) ) {
			$html .= '<ol style="list-style:none; padding-top:3px; padding-right:15px; padding-bottom:3px; padding-left:0; min-height:129px; float:left; width:75%; min-height:135px; padding-right:15px; padding-left:0; padding-top:0; padding-bottom:0; margin:0; border-right-color: #EEE; border-right-width:1px; border-right-style:solid">';
			foreach ( $this->discussions as $discussion ) {
				$html .= $this->render_single_discussion_inline( $discussion );
			}
			$html .= '</ol>';
		} else {
			$html .= GDGT_Databox_Discussions::render_no_content_inline();
		}

		$html .= $this->render_content_right_inline();
		$html .= '</div>';
		return $html;
	}
}

?>