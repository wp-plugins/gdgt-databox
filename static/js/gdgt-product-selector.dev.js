var gdgt = gdgt || {};
gdgt.product_selector = {
	// Plugin/JS version
	version: "1.2",

	// no interaction. just display existing contents
	readonly: false,

	// English labels for template. Overridden with localized values in later plugin property setter markup
	labels: {display: "Display", displayed: "Displayed", remove: "Delete", removed: "Deleted", invalid_key: "Invalid API key.", no_results: "No results found.", typeahead: "Add a product manually:", typeahead_placeholder: "Start typing..."},

	// Array of product slugs in the displayed list. Used for comparison when adding products.
	displayed_products: [],

	// Array of product slugs in the deleted list. Used for comparison when adding products.
	deleted_products: [],

	// track post tags and possibly request new products on change
	post_tags: [],

	// Maximum number of products displayed per post.
	max_products: 10,

	// wrapper for the post meta box
	post_box: jQuery( "#gdgt-product-selector" ),

	// Shared jQuery element for the displayed product list, if one exist
	displayed_list: jQuery( "#gdgt-product-selector-displayed-products" ),

	// Shared jQuery element for the deleted product list if one exists
	deleted_list: jQuery( "#gdgt-product-selector-deleted-products" ),

	// tags meta box if present. many assumptions based on markup output by post_tags_meta_box() in wp-admin/includes/meta-boxes.php
	tag_box: jQuery( "#post_tag" ),

	// account for various DOM vs. visible states of post box
	is_post_box_active: function() {
		if ( gdgt.product_selector.post_box == null || gdgt.product_selector.post_box.hasClass( "closed" ) || ! gdgt.product_selector.post_box.is( ":visible" ) ) {
			return false;
		} else {
			return true;
		}
	},

	// wrapper for on() vs. delegate() while we have to support versions of jQuery before the 1.7 on() change
	add_event_bubble_handler: function( element, event, selector, handler ) {
		if ( jQuery.isFunction( jQuery.fn.on ) ) {
			// we support WP 3.2+. WP 3.2 bundled jQuery 1.6.1. on() is preferred for 1.7+
			element.on( event, selector, handler );
		} else {
			// delegate for jQuery 1.4.2+
			element.delegate( selector, event, handler );
		}
	},

	// wrapper for off() vs. undelegate while we have to support versions of jQuery before the 1.7 off() change
	remove_event_bubble_handler: function( element, event, selector, handler ) {
		if ( jQuery.isFunction( jQuery.fn.off ) ) {
			// jQuery 1.7+ (WP 3.3+)
			element.off( event, selector, handler );
		} else {
			// undelegate for jQuery 1.4.2+
			element.undelegate( selector, event, handler );
		}
	},

	// typeahead only exists if JS enabled. dynamically add to the DOM, hiding in no JS case
	create_typeahead: function() {
		var typeahead_div = jQuery( '<div id="gdgt-product-selector-typeahead" />' );
		var typeahead_id = "gdgt-typeahead";
		if ( typeof gdgt.product_selector.labels.typeahead === "string" ) {
			typeahead_div.append( jQuery( "<div />" ).append( jQuery( "<label />" ).attr( "for", typeahead_id ).text( gdgt.product_selector.labels.typeahead ) ) );
		}
		var typeahead_input = jQuery( '<input type="search" size="30" autocomplete="on" />' ).attr( "id", typeahead_id );
		if ( typeof gdgt.product_selector.labels.typeahead_placeholder === "string" ) {
			typeahead_input.attr( "placeholder", gdgt.product_selector.labels.typeahead_placeholder );
		}
		typeahead_div.append( typeahead_input );
		gdgt.product_selector.post_box.find( "div.inside" ).append( typeahead_div );
	},

	// Bind a keypress listener to the search input, generate HTML from a JSON server response, and take action when a possible result is selected
	enable_typeahead: function() {
	    var typeahead_div = jQuery( "#gdgt-product-selector-typeahead" );
	    if ( typeahead_div.length === 0 ) {
	    	gdgt.product_selector.create_typeahead();
	    	typeahead_div = jQuery( "#gdgt-product-selector-typeahead" );
	    }
		typeahead_div.show();
		jQuery( "#gdgt-typeahead" ).prop( "disabled", false ).autocomplete({
			appendTo: typeahead_div,
			disabled: false,
			focus: function() {
				return false;
			},
			minLength: 1,
			open: function() {
				var search_term = jQuery.trim( jQuery(this).data( "autocomplete" ).term );
				if ( typeof search_term !== "string" || search_term === "" ) {
					return;
				}

				var results = jQuery( "#gdgt-product-selector-typeahead ul.ui-autocomplete" );
				results.find( "li.ui-menu-item" ).each( function( index, search_result ) {
					var li = jQuery(this);

					// pull in stored data for the result for comparison
					var search_result_data = li.data( "uiAutocompleteItem" );

					// backwards compatibility for before jQuery UI 1.9.2
					if (typeof search_result_data === 'undefined') {
						var search_result_data = li.data( "item.autocomplete" );
					}

					var label = jQuery.trim( search_result_data.label );
					if ( label === gdgt.product_selector.labels.invalid_key ) {
						li.addClass( "gdgt-error-invalid-key" );
						li.empty();
						li.text( gdgt.product_selector.labels.invalid_key );
						return;
					} else if ( label === gdgt.product_selector.labels.no_results ) {
						li.addClass( "gdgt-no-results" );
						li.empty();
						li.text( gdgt.product_selector.labels.no_results );
						return;
					}

					if ( search_result_data.parent == null ) {
						var slug = search_result_data.slug;
						li.addClass( "gdgt-product" );
					} else {
						var slug = search_result_data.parent;
						li.addClass( "gdgt-product-instance" );
					}

					if ( slug == null ) {
						return;
					}

					if ( jQuery.inArray( slug, gdgt.product_selector.displayed_products ) !== -1 || jQuery.inArray( slug, gdgt.product_selector.deleted_products ) !== -1 ) {
						li.remove();
						if ( results.is( ":empty" ) ) {
							results.append( jQuery( '<li class="gdgt-no-results" />' ).text( gdgt.product_selector.labels.no_results ) );
						}
						return;
					}

					if ( typeof label !== "string" || label === "" ) {
						return;
					}

					// create a dummy placeholder, escape the text in our target, get the HTML string of the dummy placeholder back
					li.find( "a" ).html( label.replace( search_term, jQuery( '<div />' ).append( jQuery( '<span class="searchterm" /></span>' ).text( search_term ) ).html() ) );
				});
			},
			source: function( request, response ) {
				var keyword = jQuery.trim( request.term );
				if ( keyword === "" ) {
					return;
				}
				jQuery.ajax({
					url: gdgt.product_selector.search_by_keyword_endpoint + "?" + jQuery.param( {q:keyword} ),
					dataType: "json",
					success: function( products ) {
						response( jQuery.map( products, function( item ) {
							var autocomplete_item = {
								label: item.autocomplete_name,
								value: item.slug,
								slug: item.slug,
								name: item.name
							};
							if ( item.instances !== undefined ) {
								autocomplete_item.instances = item.instances;
							} else if ( item.parent !== undefined ) {
								autocomplete_item.parent = item.parent;
							}
							return autocomplete_item;
						} ) );
					},
					statusCode: {
						401: function() {
							response( [{ label: gdgt.product_selector.labels.invalid_key, value: "" }] );
						},
						404: function() {
							response( [{ label: gdgt.product_selector.labels.no_results, value: "" }] );
						}
					}
				});
			},
			select: function( event, ui ) {
				// clear input field
				jQuery( "#gdgt-typeahead" ).val( "" );
				gdgt.product_selector.add_product( ui.item );
				// don't update the text input with the value
				return false;
			}
		});
	},

	// enable dynamic module
	enable: function() {
		gdgt.product_selector.readonly = false;
		gdgt.product_selector.displayed_list = jQuery( "#gdgt-product-selector-displayed-products" );
		gdgt.product_selector.deleted_list = jQuery( "#gdgt-product-selector-deleted-products" );
		gdgt.product_selector.tag_box = jQuery( "#post_tag" );

		gdgt.product_selector.enable_displayed_product_list();
		gdgt.product_selector.enable_deleted_product_list();
		gdgt.product_selector.enable_refresh_button();
		gdgt.product_selector.enable_typeahead();
		gdgt.product_selector.enable_tag_events();
		jQuery( "#gdgt-product-selector-results" ).removeClass( "disabled" );
	},

	// disable the typeahead element events. mark input element disabled
	disable_typeahead: function() {
		jQuery( "#gdgt-product-selector-typeahead" ).hide();
		var typeahead = jQuery( "#gdgt-typeahead" );
		if ( typeahead.length === 0 ) {
			return;
		}
		typeahead.autocomplete( "option", "disabled", true );
		typeahead.prop( "disabled", true );
	},

	// disable post box event listeners and edit abilities
	disable: function() {
		gdgt.product_selector.readonly = true;
		gdgt.product_selector.disable_refresh_button();
		gdgt.product_selector.disable_typeahead();
		gdgt.product_selector.disable_tag_events();
		gdgt.product_selector.disable_displayed_product_list();
		gdgt.product_selector.disable_deleted_product_list();
		jQuery( "#gdgt-product-selector-results" ).addClass( "disabled" );
	},

	// Create a new displayed products section
	create_displayed_list: function() {
		if ( gdgt.product_selector.displayed_list != null && gdgt.product_selector.displayed_list.length > 0 ) {
			return;
		}
		var container = jQuery( "#gdgt-product-selector-results" );
		// remove the placeholder
		container.find( "#gdgt-product-selector-results-placeholder").remove();
		container.prepend( "<div><h4>" + gdgt.product_selector.labels.displayed + '</h4><ol id="gdgt-product-selector-displayed-products"></ol></div>' );
		gdgt.product_selector.displayed_list = jQuery( "#gdgt-product-selector-displayed-products" );
		gdgt.product_selector.displayed_products = [];
		gdgt.product_selector.enable_displayed_product_list();
	},

	// Create a new deleted products section
	create_deleted_list: function() {
		if ( gdgt.product_selector.deleted_list != null && gdgt.product_selector.deleted_list.length > 0 ) {
			return;
		}
		var container = jQuery( "#gdgt-product-selector-results" );
		// remove the placeholder if present
		container.find( "#gdgt-product-selector-results-placeholder" ).remove();
		container.append( "<div><h4>" + gdgt.product_selector.labels.removed + '</h4><ul id="gdgt-product-selector-deleted-products"></ul></div>' );
		gdgt.product_selector.deleted_list = jQuery( "#gdgt-product-selector-deleted-products" );
		gdgt.product_selector.deleted_products = [];
	},

	// Make products in the displayed list draggable. add drag handle and delete button
	enable_displayed_product_list: function() {
		if ( gdgt.product_selector.displayed_list == null || gdgt.product_selector.displayed_list.length === 0 ) {
			return;
		}
		// resort product list using drag and drop controls
		gdgt.product_selector.displayed_list.sortable({
			axis: "y",
			containment: "parent",
			cursor: "move",
			dropOnEmpty: false,
			handle: ".gdgt-drag-handle",
			items: "li.gdgt-product",
			update: gdgt.product_selector.displayed_product_list_onchange
		}).sortable("enable");
		gdgt.product_selector.displayed_list.find( "li.gdgt-product" ).each( function() {
			var product = jQuery(this);
			var drag_handle = product.find( ".gdgt-drag-handle" );
			if ( drag_handle.length === 0 ) {
				return;
			}
			drag_handle.append( jQuery( '<span class="gdgt-drag-handle-icon">&#8691;</span>' ).hide() );
			drag_handle.before( jQuery( '<button type="button" class="gdgt-delete-product" />' ).text( gdgt.product_selector.labels.remove ).click( gdgt.product_selector.delete_product_handler ).hide() );
			product.hover( gdgt.product_selector.displayed_product_list_mouseenter, gdgt.product_selector.displayed_product_list_mouseleave );
		});
	},

	// display drag icon and delete button on user interaction with product
	displayed_product_list_mouseenter: function() {
		var product = jQuery(this);
		product.find( ".gdgt-drag-handle-icon" ).show();
		product.find( ".gdgt-delete-product" ).show();
	},

	// hide drag icon and delete button on user interaction with product
	displayed_product_list_mouseleave: function() {
		var product = jQuery(this);
		product.find( ".gdgt-drag-handle-icon" ).hide();
		product.find( ".gdgt-delete-product" ).hide();
	},

	// Handle a new drag position from the drag-and-drop sorter
	displayed_product_list_onchange: function() {
		// reorder inputs on changed drag position
		gdgt.product_selector.displayed_list.find( "li.gdgt-product" ).each( function( index ) {
			jQuery(this).find( "input" ).each( function(){
				var input_el = jQuery(this);
				if ( input_el.hasClass( "gdgt-product-slug" ) || input_el.hasClass( "gdgt-product-name" ) || input_el.hasClass( "gdgt-product-parent" ) || input_el.hasClass( "gdgt-product-instances" ) ) {
					// change numeric index
					var name = input_el.attr( "name" );
					// gdgt-product[([0-9]{1,2})][(slug|name)]
					input_el.attr( "name", name.substring( 0, 13 ) + index + name.substring( name.indexOf( "]", 13 ) ) );
				}
			});
		});
	},

	// remove clickable and draggable items from the displayed list
	disable_displayed_product_list: function() {
		if ( gdgt.product_selector.displayed_list == null || gdgt.product_selector.displayed_list.length === 0 ) {
			return;
		}

		gdgt.product_selector.displayed_list.sortable( "disable" );
		gdgt.product_selector.displayed_list.find( "li.gdgt-product" ).each( function(){
			jQuery(this).find( ".gdgt-drag-handle-icon" ).remove();
			jQuery(this).find( ".gdgt-delete-product" ).remove();
		} );
	},

	// add dynamic delete buttons to delete list
	enable_deleted_product_list: function() {
		if ( gdgt.product_selector.deleted_list ==null || gdgt.product_selector.deleted_list.length === 0 ) {
			return;
		}

		gdgt.product_selector.deleted_list.find( "li.gdgt-product" ).each( function() {
			var product = jQuery(this);
			product.find( "span.gdgt-product-name" ).after( jQuery( '<button type="button" class="gdgt-undelete-product" />' ).text( gdgt.product_selector.labels.display ).click( gdgt.product_selector.undelete_product_handler ).hide() );
			product.hover( gdgt.product_selector.deleted_product_list_mouseenter, gdgt.product_selector.deleted_product_list_mouseleave );
		} );
	},

	// show undelete button on user interaction with product element
	deleted_product_list_mouseenter: function() {
		jQuery(this).find( ".gdgt-undelete-product" ).show()
	},

	// hide undelete button when user finished interaction
	deleted_product_list_mouseleave: function() {
		jQuery(this).find( ".gdgt-undelete-product" ).hide()
	},

	// remove undelete buttons
	disable_deleted_product_list: function() {
		if ( gdgt.product_selector.deleted_list == null || gdgt.product_selector.deleted_list.length === 0 ) {
			return;
		}

		gdgt.product_selector.deleted_list.find( "li.gdgt-product" ).each( function(){
			jQuery(this).find( ".gdgt-undelete-product" ).remove();
		} );
	},

	// DHTML for a new displayed product
	add_product: function( product ) {
		// do we have minimum properties
		if ( ! jQuery.isPlainObject( product ) || product.slug === undefined || product.slug === "" || product.name === undefined || product.name === "" ) {
			return;
		}

		// check if we already have the main product or another instance of this product in the displayed list or the full product in the deleted list
		if ( product.parent !== undefined ) {
			if ( jQuery.inArray( product.parent, gdgt.product_selector.displayed_products ) !== -1 || jQuery.inArray( product.parent, gdgt.product_selector.deleted_products ) !== -1 ) {
				return;
			}
		} else if ( jQuery.inArray( product.slug, gdgt.product_selector.displayed_products ) !== -1 || jQuery.inArray( product.slug, gdgt.product_selector.deleted_products ) !== -1 ) {
			return;
		}

		if ( gdgt.product_selector.displayed_list == null || gdgt.product_selector.displayed_list.length === 0 ) {
			gdgt.product_selector.create_displayed_list();
			gdgt.product_selector.displayed_list = jQuery( "#gdgt-product-selector-displayed-products" );
		}

		// build element by element for proper escaping
		var current_position = gdgt.product_selector.displayed_products.length;
		var li = jQuery( '<li class="gdgt-product" />' );
		li.append( jQuery( '<button type="button" class="gdgt-delete-product" />' ).text( gdgt.product_selector.labels.remove ).click( gdgt.product_selector.delete_product_handler ) );
		li.append( jQuery( '<span class="gdgt-drag-handle" />' ).append( jQuery( '<span class="gdgt-product-name" />' ).text( product.name ) ).append( '<span class="gdgt-drag-handle-icon">&#8691;</span>' )  );
		li.append( jQuery( '<input type="hidden" class="gdgt-product-slug" name="gdgt-product[' + current_position + '][slug]" />' ).val( product.slug ) );
		li.append( jQuery( '<input type="hidden" class="gdgt-product-name" name="gdgt-product[' + current_position + '][name]" />' ).val( product.name ) );
		if ( product.parent === undefined ) {
			gdgt.product_selector.displayed_products.push( product.slug );
			if ( product.instances !== undefined ) {
				li.append( jQuery( '<input type="hidden" class="gdgt-product-instances" name="gdgt-product[' + current_position + '][instances]" />' ).val( product.instances ) );
				var ul = jQuery( '<ul class="gdgt-product-instances" />' );
				jQuery.each( product.instances.split( "," ), function( index, instance_name ) {
					ul.append( jQuery( "<li />").text( jQuery.trim( instance_name ) ) );
				} );
				if ( ! ul.is( ":empty" ) ) {
					li.append( ul );
				}
			}
		} else {
			gdgt.product_selector.displayed_products.push( product.parent );
			li.append( jQuery( '<input type="hidden" class="gdgt-product-parent" name="gdgt-product[' + current_position + '][parent]" />' ).val( product.parent ) );
		}

		li.hover( gdgt.product_selector.displayed_product_list_mouseenter, gdgt.product_selector.displayed_product_list_mouseleave );

		gdgt.product_selector.displayed_list.append( li ).fadeIn( 600 );
		gdgt.product_selector.displayed_list.sortable( "refresh" );
	},

	// DHTML for a new deleted product
	add_deleted_product: function( product ) {
		if ( ! jQuery.isPlainObject( product ) || product.slug === undefined || product.slug === "" || product.name === undefined || product.name === "" ) {
			return;
		}

		// do we have anything to work with? does the product slug already exist?
		if ( jQuery.inArray( product.slug, gdgt.product_selector.deleted_products ) !== -1 || jQuery.inArray( product.slug, gdgt.product_selector.displayed_products ) !== -1 ) {
			return;
		}

		if ( gdgt.product_selector.deleted_list == null || gdgt.product_selector.deleted_list.length === 0 ) {
			gdgt.product_selector.create_deleted_list();
			gdgt.product_selector.deleted_list = jQuery( "#gdgt-product-selector-deleted-products" );
		}

		// build element by element for proper escaping
		var current_position = gdgt.product_selector.deleted_products.length;
		var li = jQuery( '<li class="gdgt-product" />' );
		li.append( jQuery( '<span class="gdgt-product-name" />' ).text( product.name ) );
		li.append( jQuery( '<button type="button" class="gdgt-undelete-product" />' ).text( gdgt.product_selector.labels.display ).click( gdgt.product_selector.undelete_product_handler ) );
		li.append( jQuery( '<input type="hidden" class="gdgt-product-slug" name="gdgt-product-deleted[' + current_position + '][slug]" />' ).val( product.slug ) );
		li.append( jQuery( '<input type="hidden" class="gdgt-product-name" name="gdgt-product-deleted[' + current_position + '][name]" />' ).val( product.name ) );
		if ( product.instances !== undefined ) {
			li.append( jQuery( '<input type="hidden" class="gdgt-product-instances" name="gdgt-product-deleted[' + current_position + '][instances]" />' ).val( product.instances ) );
			var ul = jQuery( '<ul class="gdgt-product-instances" />' );
			jQuery.each( product.instances.split( "," ), function( index, instance_name ) {
				ul.append( jQuery( "<li />").text( jQuery.trim( instance_name ) ) );
			} );
			if ( ! ul.is( ":empty" ) ) {
				li.append( ul );
			} 
		}
		li.hover( gdgt.product_selector.deleted_product_list_mouseenter, gdgt.product_selector.deleted_product_list_mouseleave );

		gdgt.product_selector.deleted_list.append( li ).fadeIn( 600 );
		gdgt.product_selector.deleted_products.push( product.slug );
	},

	// Click handler for the delete product action: move a displayed product to the delete list
	delete_product_handler: function( event ) {
		var element = jQuery(this).closest( "li.gdgt-product" );
		var product = {
			slug: element.find( "input.gdgt-product-slug" ).val(),
			name: element.find( "input.gdgt-product-name" ).val()
		};
		if ( product.slug == null || product.slug === "" || product.name == null || product.name === "" ) {
			return;
		}

		var position = -1;

		// if parent exists use parent for comparison against displayed list
		var parent = jQuery.trim( element.find( "input.gdgt-product-parent" ).val() );
		if ( typeof parent === "string" && parent !== "" ) {
			position = jQuery.inArray( parent, gdgt.product_selector.displayed_products );
		} else {
			parent = "";
			position = jQuery.inArray( product.slug, gdgt.product_selector.displayed_products );
		}

		// remove from old list before adding to new one for proper de-duplication compare
		if ( position !== -1 ) {
			gdgt.product_selector.displayed_products.splice( position, 1 );
		}

		// only process additional elements if we plan on doing something with the result
		if ( parent !== "" ) {
			element.remove();
		} else {
			var instances = jQuery.trim( element.find( "input.gdgt-product-instances" ).val() );
			if ( typeof instances === "string" && instances !== "" ) {
				product.instances = instances;
			}
			element.remove();
			gdgt.product_selector.add_deleted_product( product );
		}
		if ( gdgt.product_selector.displayed_products.length === 0 ) {
			// remove the product listing section
			gdgt.product_selector.displayed_list.parent().remove();
			gdgt.product_selector.displayed_list = null;
		} else {
			gdgt.product_selector.displayed_product_list_onchange();
		}
	},

	// Click handler for the undelete action: move a deleted product into the displayed list
	undelete_product_handler: function( event ) {
		var element = jQuery(this).closest( "li.gdgt-product" );
		var product = {
			slug: element.find( "input.gdgt-product-slug" ).val(),
			name: element.find( "input.gdgt-product-name" ).val()
		};
		if ( product.slug == null || product.slug === "" || product.name == null || product.name === "" ) {
			return;
		}

		var instances = jQuery.trim( element.find( "input.gdgt-product-instances" ).val() );
		if ( typeof instances === "string" && instances !== "" ) {
			product.instances = instances;
		}

		// remove from old list before adding to new one for proper de-duplication compare
		var position = jQuery.inArray( product.slug, gdgt.product_selector.deleted_products );
		if ( position !== -1 ) {
			gdgt.product_selector.deleted_products.splice( position, 1 );
		}
		element.remove();
		gdgt.product_selector.add_product( product );
		if ( gdgt.product_selector.deleted_products.length === 0 ) {
			gdgt.product_selector.deleted_list.parent().remove();
			gdgt.product_selector.deleted_list = null;
		}
	},

	// enable or disable module functionality when the disabled checkbox changes
	disable_module_handler: function( event ) {
		if ( jQuery( this ).prop( "checked" ) ) {
			gdgt.product_selector.disable();
		} else {
			gdgt.product_selector.enable();
			if ( gdgt.product_selector.tag_box.length !== 0 ) {
				gdgt.product_selector.tag_search();
			}
		}
	},

	// manually refresh products list based on a tag search
	create_refresh_button: function() {
		gdgt.product_selector.post_box.find("div.inside").prepend( jQuery( '<span id="gdgt-refresh-container" />' ).append( jQuery( '<button type="button" />' ).attr( "id", "gdgt-refresh" ).text( "↺" ) ) );
	},

	// show the refresh button. listen for click
	enable_refresh_button: function() {
		var refresh_container = jQuery( "#gdgt-refresh-container" );
		if ( refresh_container.length === 0 ) {
			gdgt.product_selector.create_refresh_button();
			refresh_container = jQuery( "#gdgt-refresh-container" );
		}
		refresh_container.show();
		var refresh_button = refresh_container.find( "#gdgt-refresh" );
		if ( refresh_button.length > 0 ) {
			refresh_button.click( gdgt.product_selector.refresh_button_handler );
			refresh_button.trigger( "click" ); // refresh when enabled
		}
	},

	// hide the refresh button. stop listening for click
	disable_refresh_button: function() {
		var refresh_container = jQuery( "#gdgt-refresh-container" );
		if ( refresh_container.length === 0  ) {
			return;
		}
		var refresh_button = refresh_container.find( "#gdgt-refresh" );
		if ( refresh_button.length > 0 ) {
			refresh_button.unbind( "click", gdgt.product_selector.refresh_button_handler );
		}
		refresh_container.hide();
	},

	// refresh list of tags and pass to tag search
	refresh_button_handler: function() {
		var refresh_container = "";
		var refresh_button = "";

		// is a loading gif defined?
		if ( jQuery.isPlainObject( gdgt.product_selector.loading_spinner_image ) && gdgt.product_selector.loading_spinner_image.url != null && gdgt.product_selector.loading_spinner_image.width != null && gdgt.product_selector.loading_spinner_image.height != null ) {
			refresh_container = jQuery( "#gdgt-refresh-container" );
			if ( refresh_container.length > 0 ) {
				refresh_button = refresh_container.find( "#gdgt-refresh" );
				// hide the button
				if ( refresh_button.length > 0 ) {
					refresh_button.hide();
				}
				refresh_container.append( jQuery( '<img alt="Loading..." />' ).attr( "src", gdgt.product_selector.loading_spinner_image.url ).attr( "width", gdgt.product_selector.loading_spinner_image.width ).attr( "height", gdgt.product_selector.loading_spinner_image.height ) );
			}
		}
		gdgt.product_selector.refresh_stored_tags();
		gdgt.product_selector.tag_search();
		if ( refresh_container.length > 0 ) {
			refresh_container.find( "img" ).remove();
			if ( refresh_button.length > 0 ) {
				refresh_button.show();
			}
		}
	},

	refresh_stored_tags: function() {
		var tag_checklist = gdgt.product_selector.tag_box.find( ".tagchecklist" );
		if ( tag_checklist.length === 0 ) {
			return;
		}

		gdgt.product_selector.post_tags = [];
		tag_checklist.find( "span" ).each( function() {
			tag = gdgt.product_selector.extract_tag_from_checklist( jQuery(this), jQuery(this).find( ".ntdelbutton" ) );
			if ( typeof tag === "string" && tag !== "" ) {
				gdgt.product_selector.post_tags.push(tag);
			}
		} );
		// sort initial array on client-side for consistency with later sort operations
		gdgt.product_selector.post_tags.sort();
	},

	// listen for changes to the tag list
	enable_tag_events: function() {
		// editor has the ability to pick boxes displayed on the post screen. no tags box is possible
		if ( gdgt.product_selector.tag_box.length === 0 || gdgt.product_selector.search_by_tag_endpoint == null ) {
			return;
		}

		// it's possible the tag events are enabled when the gdgt product module is changed from disabled to enabled.
		// update tags list based on current checklist
		var tag_checklist = gdgt.product_selector.tag_box.find( ".tagchecklist" );
		if ( tag_checklist.length > 0 ) {
			gdgt.product_selector.refresh_stored_tags();
			gdgt.product_selector.add_event_bubble_handler( tag_checklist, "click", ".ntdelbutton", gdgt.product_selector.delete_tag_handler );
		}

		// add tag(s) button
		gdgt.product_selector.tag_box.find( "#new-tag-post_tag" ).parent().find( ".tagadd" ).click( gdgt.product_selector.add_tags_handler );
	},

	// stop listening for changes to the tag list
	disable_tag_events: function() {
		if ( gdgt.product_selector.tag_box.length === 0 ) {
			return;
		}

		gdgt.product_selector.tag_box.find( ".ajaxtag .tagadd" ).unbind( "click", gdgt.product_selector.add_tags_handler );

		var tag_checklist = gdgt.product_selector.tag_box.find( ".tagchecklist" );
		if ( tag_checklist.length > 0 ) {
			gdgt.product_selector.remove_event_bubble_handler( tag_checklist, "click", ".ntdelbutton", gdgt.product_selector.delete_tag_handler );
		}
	},

	// text value of the tag element in the checklist includes both the tag text and the delete button text. remove delete button, purify tag
	extract_tag_from_checklist: function( tag_element, delete_target ) {
		var targettext = jQuery.trim( delete_target.text() );
		var tag = jQuery.trim( tag_element.text() );
		if ( typeof tag !== "string" || tag === "" ) {
			return;
		}

		// delete button is a prepend on the element. remove it
		if ( typeof targettext === "string" && targettext !== "" && tag.substring( 0, targettext.length ) == targettext ) {
			return jQuery.trim( tag.substring( targettext.length + 1 ) );
		}
	},

	// grab fresh tags from JS list. parse it, possibly kick off tag search
	add_tags_handler: function(e) {
		if ( ! gdgt.product_selector.is_post_box_active() ) {
			return;
		}
		var old_tags = gdgt.product_selector.post_tags;
		gdgt.product_selector.refresh_stored_tags();

		if ( old_tags.length !== gdgt.product_selector.post_tags.length ) {
			gdgt.product_selector.tag_search();
			return;
		}

		jQuery.each( old_tags, function( index, tag ) {
			// arrays should be pre-sorted and of equal length, allowing for comparison by position
			if ( tag !== gdgt.product_selector.post_tags[index] ) {
				gdgt.product_selector.tag_search();
				return;
			}
		} );
	},

	// remove a tag from our list on delete
	delete_tag_handler: function( e ) {
		if ( ! gdgt.product_selector.is_post_box_active() ) {
			return;
		}
		var tag = gdgt.product_selector.extract_tag_from_checklist( jQuery(this).parent(), jQuery(this) );
		if ( typeof tag !== "string" || tag === "" ) {
			return;
		}
		var position = jQuery.inArray( tag, gdgt.product_selector.post_tags );
		if ( position !== -1 ) {
			gdgt.product_selector.post_tags.splice( position, 1 );
		}
	},

	// tag search based on post_tags. accepts no args for compatibility with gdgt post box refresh button
	tag_search: function() {
		if ( gdgt.product_selector.readonly || gdgt.product_selector.post_tags == null || ! jQuery.isArray( gdgt.product_selector.post_tags ) || gdgt.product_selector.post_tags.length === 0 || gdgt.product_selector.search_by_tag_endpoint == null ) {
			return;
		}
		jQuery.getJSON( gdgt.product_selector.search_by_tag_endpoint + "?" + jQuery.param( {"tags":gdgt.product_selector.post_tags.join()} ), function( products ) {
			jQuery.each( products, function( index, product ) {
				if ( product.slug != null && product.name != null ) {
					gdgt.product_selector.add_product( product );
				}
			} );
		} );
	}
};

jQuery(function() {
	gdgt.product_selector.post_box = jQuery( "#gdgt-product-selector" );
	if ( gdgt.product_selector.post_box.length === 0 ) {
		// no gdgt product selector module on page. abort.
		return;
	} else {
		gdgt.product_selector.post_box.trigger( "gdgt-product-selector-onload" );
	}

	if ( gdgt.product_selector != null && gdgt.product_selector.readonly !== true ) {
		// turn on event listeners
		gdgt.product_selector.enable();
	}

	// only publishers get the checkbox. a user with edit-only permissions won't see it
	var disable_checkbox = gdgt.product_selector.post_box.find( "#gdgt-products-readonly" );
	if ( disable_checkbox.length > 0 ) {
		disable_checkbox.click( gdgt.product_selector.disable_module_handler );
	}
});
