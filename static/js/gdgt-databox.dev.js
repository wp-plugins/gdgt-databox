var gdgt = gdgt || {};
gdgt.databox = {
	// Plugin/JS version
	version: "1.0",

	// override me with translated strings
	labels: { collapse: "collapse", expand: "expand" },

	lazy_load_images: function( container ) {
		if ( container.length === 0 ) {
			return;
		}
		container.find( "noscript.img" ).each( function() {
			jQuery( this ).parent().html( jQuery( jQuery(this).text() ) );
		} );
	},

	// change visual display of tab to "selected" CSS class on click event
	// only one "selected" tab in tab group
	// hide the content of the previously selected tab
	// display the content of the newly selected tab
	tab_onclick_handler: function() {
		// store a reference to the current li element
		var tab = jQuery(this);

		// is the tab already selected? if so nothing to do here
		if ( tab.hasClass( "selected" ) ) {
			return;
		}

		// remove selected class from the previously selected tab; apply to current tab selection
		tab.closest( ".gdgt-tabs" ).find( "li.selected" ).removeClass( "selected" ).removeAttr( "aria-selected" );
		tab.addClass( "selected" ).attr( "aria-selected", "true" );

		// store the tab type in a data attribute to correlate with its appropriate panel
		var tab_datatype = tab.data( "gdgtDatatype" );
		if ( typeof tab_datatype !== "string" || tab_datatype === "" ) {
			return;
		}

		var tab_data_class = "gdgt-content-" + jQuery.trim( tab_datatype );
		tab.closest( ".gdgt-product-wrapper" ).find( ".gdgt-content" ).each( function() {
			var content_div = jQuery(this);
			if ( content_div.hasClass( tab_data_class ) ) {
				content_div.show();
				content_div.attr( "aria-hidden", "false" );
				if ( content_div.data( "loaded" ) !== true ) {
					gdgt.databox.lazy_load_images( content_div );
					content_div.data( "loaded", true );
					// don't let an analytics fail interrupt navigation
					try {
						// track the event
						gdgt.databox.analytics.track_tab_change( tab_datatype );
					} catch(e){}
				}
			} else {
				content_div.hide();
				content_div.attr( "aria-hidden", "true" );
			}
		} );
	},

	// expand product view
	product_expand: function() {
		var product = jQuery(this).closest( ".gdgt-product" );
		product.find( ".gdgt-product-collapsed-name" ).hide();
		product.find( ".gdgt-product-wrapper" ).show().attr( "aria-hidden", "false" );
		product.removeClass( "collapsed" );
		product.addClass( "expanded" );
		product.attr( "aria-expanded", "true" );
		if ( product.data( "loaded" ) !== true ) {
			gdgt.databox.lazy_load_images( product.find( ".gdgt-product-head" ) );
			product.data( "loaded", true );
		}
		try {
			gdgt.databox.analytics.track_product_view( product );
		} catch(e) {}
	},

	// collapse product into label
	product_collapse: function() {
		var product = jQuery(this).closest( ".gdgt-product" );
		product.find( ".gdgt-product-wrapper" ).hide().attr( "aria-hidden", "true" );
		product.find( ".gdgt-product-collapsed-name" ).show();
		product.removeClass( "expanded" );
		product.addClass( "collapsed" );
		product.removeAttr( "aria-expanded" );
	},

	// I love the way you turn me on
	enable: function() {
		var databoxes = jQuery( ".gdgt-wrapper" );
		if ( databoxes.length === 0 ) {
			// nothing to work with
			return;
		}

		// note how many databox instances on the page for stats
		gdgt.databox.total = databoxes.length;

		// lazy load product image
		gdgt.databox.lazy_load_images( databoxes.find( ".gdgt-product.expanded .gdgt-product-head" ) );

		// lazy load images in visible content panels
		databoxes.children().find( ".gdgt-content:visible" ).each( function() {
			var panel = jQuery(this);
			gdgt.databox.lazy_load_images( panel );
			panel.data( "loaded", true );
		});

		// enable tab navigation
		databoxes.find( ".gdgt-tabs li" ).click( gdgt.databox.tab_onclick_handler );

		// expand/collapse capability for all products but the first
		databoxes.children().not( ":first-child" ).each( function() {
			var product = jQuery(this);
			product.find( ".gdgt-product-collapsed-name" ).click( gdgt.databox.product_expand ).append( jQuery( '<span class="gdgt-product-expand-icon" />' ).attr( "title", gdgt.databox.labels.expand ).click( gdgt.databox.product_expand ) );
			product.find( ".gdgt-branding" ).html( jQuery( '<span class="gdgt-product-collapse-icon" />' ).attr( "title", gdgt.databox.labels.collapse ).click( gdgt.databox.product_collapse ) );
		} );
		gdgt.databox.analytics.init();
	},

	analytics: {
		// track if page is visible. do not fire tracking events if prefetch or preview
		page_visible: false,

		// store the offset of the databox wrapper
		databox_offset_top: 0,

		// track if databox is visible. do not fire stats events until visible
		databox_visible: false,

		// track viewed products for unique product views
		viewed_products: [],

		// set variables, listen for databox visible state
		init: function() {
			gdgt.databox.analytics.set_content_width();
			gdgt.databox.analytics.set_page_url();
			gdgt.databox.analytics.visibility_init();
		},

		// test if current browsing context supports Web Visibility API
		// tap into visibility events if supported, else assume visible
		visibility_init: function() {
			// check for standard and vendor prefix Visibility APIs
			var hidden = null;
			var event = null;
			if ( document.hidden !== undefined ) { // W3C draft standard
				hidden = "hidden";
				event = "visibilitychange";
			} else if ( document.webkitHidden !== undefined ) {
				hidden = "webkitHidden";
				event = "webkitvisibilitychange";
			} else if ( document.msHidden !== undefined ) {
				hidden = "msHidden";
				event = "msvisibilitychange";
			}

			// store the position of the first databox on the page
			var databox = jQuery( ".gdgt-wrapper" ).first();
			if ( databox.length > 0 ) {
				databox_offset = databox.offset();
				if ( databox_offset.top > 0 ) {
					gdgt.databox.analytics.databox_offset_top = databox_offset.top;
				}
				databox_offset = null;
			}
			databox = null;

			if ( hidden === null || document[hidden] === false ) {
				gdgt.databox.analytics.page_visible = true;
				gdgt.databox.analytics.google.load();
				if ( gdgt.databox.analytics.viewport_test() === false ) {
					jQuery( window ).scroll( gdgt.databox.analytics.viewport_test );
				} else {
					gdgt.databox.analytics.on_visible();
				}
			} else {
				jQuery( document ).bind( event, {hidden:hidden}, gdgt.databox.analytics.visiblity_change );
			}
		},

		// event handler checks for visibility change from hidden to non-hidden
		visibility_change: function( event ) {
			if ( gdgt.databox.analytics.page_visible === true ) {
				return;
			}
			if ( document[event.data.hidden] === false ) {
				gdgt.databox.analytics.page_visible = true;
				gdgt.databox.analytics.google.load();
				jQuery( document ).unbind( event );
				if ( gdgt.databox.analytics.viewport_test() === false ) {
					jQuery( window ).scroll( gdgt.databox.analytics.viewport_test );
				} else {
					gdgt.databox.analytics.on_visible();
				}
			}
		},

		// test if top of databox is at or above the last vertical pixel in the current window
		viewport_test: function() {
			if ( gdgt.databox.analytics.databox_visible === true ) {
				return true;
			}
			var jwindow = jQuery(window);
			if ( ( jwindow.height() + jwindow.scrollTop() ) >= gdgt.databox.analytics.databox_offset_top ) {
				jQuery( window ).unbind( "scroll", gdgt.databox.analytics.viewport_test );
				gdgt.databox.analytics.databox_visible = true;
				gdgt.databox.analytics.on_visible();
				return true;
			}
			return false;
		},

		// actions once the databox appears in viewport
		on_visible: function() {
			jQuery( ".gdgt-product.expanded" ).each( function( index ) {
				// track active tabs on first product
				if ( index === 0 ) {
					var tabs = [];
					jQuery(this).find( ".gdgt-tabs li" ).each( function() {
						var tab = jQuery(this);

						// did the publisher remove a tab by CSS instead of through settings?
						if ( tab.is( ":hidden" ) ) {
							return;
						}

						var tab_name = tab.data( "gdgtDatatype" );
						if ( typeof tab_name === "string" ) {
							tabs.push( tab_name );
						}
					} );
					if ( tabs.length > 0 ) {
						// track available tabs as a custom variable at the page level
						_gaq.push( [ "gdgt._setCustomVar", 1, "Tabs", tabs.join(","), 3 ] );
					}
				}
				gdgt.databox.analytics.track_product_view( jQuery(this) );
			} );
		},

		// functionality specific to Google Analytics
		google: {
			// Google Analytics account
			account: "UA-818999-9",

			// load Google Analytics JavaScript and initialize its async object if not already present
			load: function() {
				if ( typeof _gat === "undefined" ) {
					// create our own getScript with cache
					jQuery.ajax({
						cache: true, // script by default appends timestamp to cache bust
						url: ( "https:" == document.location.protocol ? "https://ssl" : "http://www") + ".google-analytics.com/ga.js",
						dataType: "script"
					});
				}

				if ( typeof _gaq === "undefined" ) {
					_gaq = [];
				}
				_gaq.push( function() {
					var tracker = _gat._createTracker( gdgt.databox.analytics.google.account, 'gdgt' );
					tracker._getLinkerUrl( "http://gdgt.com/" );
					tracker._setDomainName( "gdgt.com" );
					tracker._setAllowLinker( true );
					tracker._setSampleRate( "100" ); // override host site's sampling. 100% coverage
					if ( gdgt.databox.analytics.page_url !== undefined ) {
						tracker._setReferrerOverride( gdgt.databox.analytics.page_url );
					}
				} );
				_gaq.push( [ "gdgt._trackPageview", "http://gdgt.com/databox/" ] );
				if ( gdgt.databox.analytics.content_width !== undefined ) {
					// track the width of the parent container
					_gaq.push( ["gdgt._setCustomVar", 2, "Container width", gdgt.databox.analytics.content_width, 3] );
				}
			},

			// treat product view like a pageview
			track_pageview: function( url ) {
				_gaq.push( ["gdgt._trackPageview", url] );
			},

			// fire in-page events when tab click occurs
			track_tab_change: function( tabname ) {
					_gaq.push( ["gdgt._trackEvent", "Tabs", "view", tabname ] );
			}
		},

		// measure the width of the container to track display environments
		set_content_width: function() {
			if ( gdgt.databox.analytics.content_width === undefined ) {
				var width = jQuery( ".gdgt-wrapper" ).first().parent().width();
				if ( typeof width === "number" ) {
					gdgt.databox.analytics.content_width = width;
				}
			}
		},

		// reference a canonical URL if defined, else grab document URL
		// used to set referer / host page
		set_page_url: function(){
			// use canonical if defined and absolute
			var canonical = jQuery( 'link[rel="canonical"]' ).first().attr( "href" );
			if ( typeof canonical === "string" && canonical.length > 12 && canonical.substring( 0, 4 ) === "http" ) {
				gdgt.databox.analytics.page_url = canonical;
			} else if ( jQuery.type( document.URL ) === "string" ) { // DOM 2
				gdgt.databox.analytics.page_url = document.URL;
			} else if ( document.location !== undefined ) { // DOM 0
				gdgt.databox.analytics.page_url = document.location.toString();
			}
		},

		// act on a tab click
		track_tab_change: function( tabname ) {
			if ( typeof tabname !== "string" ) {
				return;
			}
			gdgt.databox.analytics.google.track_tab_change( tabname );
		},

		// record each product on the page as if it was its own pageview
		track_product_view: function( product ) {
			if ( product.length === 0 ) {
				return;
			}
			var gdgt_url = product.find( ".gdgt-product-name a" ).attr( "href" );
			if ( typeof gdgt_url !== "string" || gdgt_url.length < 16 || jQuery.inArray( gdgt_url, gdgt.databox.analytics.viewed_products ) !== -1 ) {
				return;
			}
			gdgt.databox.analytics.viewed_products.push( gdgt_url );
			gdgt.databox.analytics.google.track_pageview( gdgt_url );
		}
	}
};

jQuery(function() {
	gdgt.databox.enable();
});