jQuery( document ).ready( function( $ ) {
// @TODO add comments to explain these calls
	$ ('#purge_varnish_post_id') .on('click',function(){
		var purge_varnish_post_id_input = $ ('#purge_varnish_post_id_input') .val();
		var data = {
			'action': 'purge_varnish_post_id',
			'your_post_id': purge_varnish_post_id_input
		};

		$.post(ajaxurl, data, function(response) {
			//Spit out the output of the php function in the page so we can display the results
			$ ('#purge_results_text').text(response);
		});
	});

	$ ('#reset_global_last_modified') .on('click',function(){
		var data = {
			'action': 'reset_global_last_modified'
		};
		//this can be kind of heavy, so willy nilly clicking it isn't the best, get a confirmation before running.
		if (confirm("Resetting the Global Last-Modified will lead to more users and bots hitting the site. Are you sure you'd like to proceed?") === true) {
			$.post(ajaxurl, data, function(response) {
				//convert the date in line
				var d = new Date(response * 1000);
				$ ('#wpe_ac_global_last_modified_text').text('Global Last-Modified Header updated to ' + d.toUTCString());
				$ ('#wpe_ac_global_last_modified').val(response);
			});
		}
	});

});
