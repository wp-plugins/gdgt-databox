var gdgt = gdgt || {};
gdgt.settings = {
	// include labels that may be overridden by the page with localized text
	labels: { add:"Add" },

	stop_tags: [],

	// initialize stop tags JavaScript functionality
	stop_tags_init: function() {
		var tags_div = jQuery( "#gdgt_stop_tags_div" );
		if ( tags_div.length === 0 ) {
			return;
		}
		var tags_input = tags_div.find( "#gdgt_stop_tags" );
		var tags = tags_input.val();
		tags_input.removeAttr( "value" );
		tags_input.attr( "id", "gdgt_stop_tags_entry" );
		tags_input.attr( "placeholder", "puppies, kittens" );
		tags_div.find("label").attr( "for", "gdgt_stop_tags_entry" );
		tags_div.append( jQuery( '<input type="hidden" id="gdgt_stop_tags" />' ).attr( "name", tags_input.attr( "name" ) ).val( tags ) );
		tags_input.removeAttr( "name" );
		tags_input.after( jQuery( '<button id="gdgt-stop-tags-button" type="button" />' ).text( gdgt.settings.labels.add ).click( gdgt.settings.add_stop_tag_handler) );
		gdgt.settings.add_stop_tags_from_string( tags );
	},

	// add stop tags from a comma-separated input string
	add_stop_tags_from_string: function( tags ) {
		tags = jQuery.trim( tags );
		if ( tags.length === 0 ) {
			return;
		}
		tags = tags.split(",");
		jQuery.each( tags, function( index, tag ) {
			gdgt.settings.add_stop_tag(tag);
		} );
	},

	// add a stop tag to the tag display and hidden input
	add_stop_tag: function( tag ) {
		tag = jQuery.trim( tag );
		// reject if empty or already known
		if ( tag.length === 0 || jQuery.inArray( tag, gdgt.settings.stop_tags ) !== -1 ) {
			return;
		}
		gdgt.settings.stop_tags.push( tag );
		var tags_div = jQuery( "#gdgt_stop_tags_div" );
		var tags_input = tags_div.find( "#gdgt_stop_tags" );

		var tags_list = tags_div.find( "ul" );
		if ( tags_list.length === 0 ) {
			tags_div.append( jQuery( "<ul />" ) );
			tags_list = tags_div.find( "ul" );
		}
		var li = jQuery( "<li />" );
		li.append( jQuery( '<span><a>X</a></span>' ).click(gdgt.settings.delete_stop_tag_handler) );
		li.append( jQuery( '<span class="gdgt-stop-tag" />' ).text( tag ) );
		tags_list.append( li );

		tags_input.val( gdgt.settings.stop_tags.join( "," ) );
	},
	add_stop_tag_handler: function( e ) {
		var input = jQuery( "#gdgt_stop_tags_entry" );
		gdgt.settings.add_stop_tags_from_string( jQuery.trim( input.val() ) );
		// clear the input
		input.val( "" );
	},
	delete_stop_tag_handler: function( e ) {
		var li = jQuery(this).parent();
		var tag = li.find(".gdgt-stop-tag").text();

		var position = jQuery.inArray( tag, gdgt.settings.stop_tags );
		if ( position !== -1 ) {
			gdgt.settings.stop_tags.splice( position, 1 );
		}

		if ( gdgt.settings.stop_tags.length === 0 ) {
			li.parent().remove();
			jQuery( "#gdgt_stop_tags" ).val( "" );
		} else {
			li.remove();
			jQuery( "#gdgt_stop_tags" ).val( gdgt.settings.stop_tags.join( "," ) );
		}
	}
};

jQuery(function() {
	gdgt.settings.stop_tags_init();
});