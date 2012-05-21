<?php

/**
 * Build a Google Analytics beacon
 * Used for views without JavaScript such as feed or noscript
 *
 * @since 1.1
 */
class GDGT_Google_Analytics {
	/**
	 * Create a unique instance for a given Google Analytics account identifier
	 *
	 * @since 1.1
	 * @param string Google Analytics account identifier
	 */
	public function __construct( $account ) {
		$account = trim( $account );
		if ( ! empty( $account ) )
			$this->account = $account;
	}

	/**
	 * Identify
	 *
	 * @since 1.1
	 * @return string
	 */
	public function __toString() {
		$id = 'gdgt Google Analytics builder';
		if ( isset( $this->account ) )
			return $id . ': ' . $this->account;
		else
			return $id;
	}

	/**
	 * Build a URL for the Google Analytics beacon GIF. Vary scheme based on parent.
	 *
	 * @since 1.3
	 * @return string
	 */
	public static function beacon_url( array $params ) {
		return ( is_ssl() ? 'https' : 'http' ) . '://www.google-analytics.com/__utm.gif?' . http_build_query( $params, '', '&' );
	}

	/**
	 * Cache-busting random number
	 * Minimum and maximum values taken directly from the Google Analytics for Mobile PHP library, which also generates a tracker GIF
	 *
	 * @since 1.1
	 * @return int random positive number
	 */
	public static function get_random_number() {
		return rand( 0, 0x7fffffff );
	}

	/**
	 * The title of the page.
	 * The gdgt Databox uses the displayed gadget title as the page title
	 *
	 * @since 1.1
	 * @param string $title page title
	 */
	public function setPageTitle( $title ) {
		$title = trim( $title );
		if ( ! empty( $title ) )
			$this->page_title = $title;
	}

	/**
	 * Set the hostname of the originating request
	 *
	 * @since 1.1
	 * @param string $hostname custom
	 */
	public function setHostname( $hostname='' ) {
		$hostname = trim( $hostname );
		if ( ! empty( $hostname ) )
			$hostname = parse_url( 'http://' . $hostname, PHP_URL_HOST ); // just the good stuff
		if ( empty( $hostname ) ) {
			$hostname = parse_url( get_permalink(), PHP_URL_HOST ); // we should be in post context. permalink may be a different host than site
			if ( empty( $hostname ) )
				$hostname = parse_url( get_site_url(), PHP_URL_HOST ); // use site as fallback
		}
		if ( ! empty( $hostname ) )
			$this->hostname = $hostname;
	}

	/**
	 * Set the page URL relative to the hostname
	 *
	 * @since 1.1
	 * @param string $url full URL
	 */
	public function setPageURL( $url ) {
		$url = trim( $url );
		if ( empty( $url ) )
			return;
		$url_parts = parse_url( $url );
		if ( ! empty( $url_parts ) && array_key_exists( 'path', $url_parts ) && ! empty( $url_parts['path'] ) ) {
			if ( array_key_exists( 'query', $url_parts ) && ! empty( $url_parts['query'] ) )
				$this->page_url = $url_parts['path'] . '?' . $url_parts['query'];
			else
				$this->page_url = $url_parts['path'];
		}
	}

	/**
	 * Set a referrer URL for the tracked page
	 *
	 * @since 1.1
	 * @param string pass a referrer or one will be generated from post permalink
	 */
	public function setReferrer( $referrer_url = '' ) {
		$referrer_url = trim( $referrer_url );
		if ( empty( $referrer_url ) )
			$referrer_url = get_permalink();
		if ( ! empty( $referrer_url ) )
			$this->referrer_url = $referrer_url;
	}

	/**
	 * Build an image URL for use in an img[src]
	 *
	 * @since 1.1
	 * @link http://code.google.com/apis/analytics/docs/tracking/gaTrackingTroubleshooting.html#gifParameters GIF parameters
	 * @return string absolute image URL or empty string if requirements not met
	 */
	public function get_image_url() {
		// check for minimum tracking components
		if ( ! isset( $this->account ) || ! isset( $this->hostname ) || ! isset( $this->page_url ) )
			return '';
		$params = array(
			'utmac' => $this->account,
			'utmhn' => $this->hostname,
			'utmp'  => $this->page_url, // path + optional query params
			'utmn'  => GDGT_Google_analytics::get_random_number()
		);
		if ( isset( $this->referrer_url ) )
			$params['utmr'] = $this->referrer_url;
		if ( isset( $this->page_title ) )
			$params['utmdt'] = $this->page_title;

		return GDGT_Google_Analytics::beacon_url( $params );
	}
}
?>