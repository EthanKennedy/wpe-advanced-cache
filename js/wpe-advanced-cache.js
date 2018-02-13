jQuery( document ).ready( function( $ ) {
// @TODO add comments to explain these calls
	$('#purge_varnish_post_id').on('click',function(){
		var purge_varnish_post_id_input = $('#purge_varnish_post_id_input').val();
		var data = {
			'action': 'purge_varnish_post_id',
			'your_post_id': purge_varnish_post_id_input
		};

		$.post(ajaxurl, data, function(response) {
			//Spit out the output of the php function in the page so we can display the results
			$('#purge_results_text').text(response);
		});
	});

	$('#reset_global_last_modified').on('click',function(){
		var data = {
			'action': 'reset_global_last_modified'
		};
		//this can be kind of heavy, so willy nilly clicking it isn't the best, get a confirmation before running.
		if (confirm("Resetting the Global Last-Modified will lead to more users and bots hitting the site. Are you sure you'd like to proceed?") === true) {
			$.post(ajaxurl, data, function(response) {
				//convert the date in line
				var d = new Date(response * 1000);
				$('#wpe_ac_global_last_modified_text').text('Global Last-Modified Header updated to ' + d.toUTCString());
				$('#wpe_ac_global_last_modified').val(response);
			});
		}
	});
	$('#purge_varnish_url_verify').on('click',function(){
		var url = $('#purge_varnish_path_input').val();
		$.ajax({
			url: url,
			statusCode: {
				404: function() {
					$('#purge_varnish_url_description').text('URL Invalid');
					$('#purge_varnish_path').attr('disabled','disabled');
				},
				403: function() {
					$('#purge_varnish_url_description').text('URL Returned Forbidden');
					$('#purge_varnish_path').attr('disabled','disabled');
				},
				500: function() {
					$('#purge_varnish_url_description').text('URL Returned Error Response');
					$('#purge_varnish_path').attr('disabled','disabled');
				},
				301: function() {
					$('#purge_varnish_url_description').text('URL Redirects, Please Use Live URL');
					$('#purge_varnish_path').attr('disabled','disabled');
				},
				301: function() {
					$('#purge_varnish_url_description').text('URL Redirects, Please Use Live URL');
					$('#purge_varnish_path').attr('disabled','disabled');
				},
				200: function() {
					$('#purge_varnish_url_description').text('URL Valid');
					$('#purge_varnish_path').removeAttr('disabled');
				}
			}
		});
});
	$('#purge_varnish_path').on('click',function(){
		var purge_varnish_path_input = $('#purge_varnish_path_input').val();
		var data = {
			'action': 'purge_varnish_path',
			'your_path': purge_varnish_path_input
		};

		$.post(ajaxurl, data, function(response) {
			//Spit out the output of the php function in the page so we can display the results
			$('#purge_path_results_text').text(response);
		});
	});

});
