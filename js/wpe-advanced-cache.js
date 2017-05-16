jQuery( document ).ready( function( $ ) {
// @TODO add comments to explain these calls
	$ ('#purge_varnish_post_id') .on('click',function(){
		var purge_varnish_post_id_input = $ ('#purge_varnish_post_id_input') .val();
		var data = {
			'action': 'purge_varnish_post_id',
			'your_post_id': purge_varnish_post_id_input
		};

		$.post(ajaxurl, data, function(response) {
			$ ('#results').text(response);
		});
	});

	$ ('#reset_global_last_modified') .on('click',function(){
		var data = {
			'action': 'reset_global_last_modified'
		};
		if (confirm("Resetting the Global Last-Modified will lead to more users and bots hitting the site. Are you sure you'd like to proceed?") === true) {
			$.post(ajaxurl, data, function(response) {
				$ ('#results2').text(response);
			});
		}
	});

});
