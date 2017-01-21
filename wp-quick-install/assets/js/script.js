$(document).ready(function() {

	// Debug mode
	var $debug		   = $('#debug'),
		$debug_options = $('#debug_options'),
		$debug_display = $debug_options.find('#debug_display');
		$debug_log 	   = $debug_options.find('#debug_log');

	$debug.change(function() {
		if ( $debug.is(':checked') ) {
			$debug.parent().hide().siblings('p').hide();
			$debug_options.slideDown();
			$debug_display.attr('checked', true);
			$debug_log.attr('checked', true);
		}
	});

	$('#debug_display, #debug_log').change(function(){
		if ( ! $debug_display.is(':checked') && ! $debug_log.is(':checked') ) {
			$debug_options.slideUp().siblings().slideDown();
			$debug.removeAttr('checked');
		}
	});

	/*--------------------------*/
	/*	Install folder
	/*--------------------------*/

	if ( typeof data.directory !='undefined' ) {
		$('#directory').val(data.directory);
	}

	/*--------------------------*/
	/*	Blog Title
	/*--------------------------*/

	if ( typeof data.title !='undefined' ) {
		$('#weblog_title').val(data.title);
	}

	/*--------------------------*/
	/*	Language
	/*--------------------------*/

	if ( typeof data.language !='undefined' ) {
		$('#language').val(data.language);
	}

	/*--------------------------*/
	/*	Database
	/*--------------------------*/

	if ( typeof data.db !='undefined' ) {

		if ( typeof data.db.dbname !='undefined' ) {
		$('#dbname').val(data.db.dbname);
		}

		if ( typeof data.db.dbhost !='undefined' ) {
			$('#dbhost').val(data.db.dbhost);
		}

		if ( typeof data.db.prefix !='undefined' ) {
			$('#prefix').val(data.db.prefix);
		}

		if ( typeof data.db.uname !='undefined' ) {
			$('#uname').val(data.db.uname);
		}

		if ( typeof data.db.pwd !='undefined' ) {
			$('#pwd').val(data.db.pwd);
		}

		if ( typeof data.db.default_content !='undefined' ) {
			( parseInt(data.db.default_content) == 1 ) ? $('#default_content').attr('checked', 'checked') : $('#default_content').removeAttr('checked');
		}
	}

	/*--------------------------*/
	/*	Admin user
	/*--------------------------*/

	if ( typeof data.admin !='undefined' ) {

		if ( typeof data.admin.user_login !='undefined' ) {
			$('#user_login').val(data.admin.user_login);
		}

		if ( typeof data.admin.password !='undefined' ) {
			$('#admin_password').val(data.admin.password);
		}

		if ( typeof data.admin.email !='undefined' ) {
			$('#admin_email').val(data.admin.email);
		}

	}

	/*--------------------------*/
	/*	Enable SEO
	/*--------------------------*/

	if ( typeof data.seo !='undefined' ) {
		( parseInt(data.seo) == 1 ) ? $('#blog_public').attr('checked', 'checked') : $('#blog_public').removeAttr('checked');
	}

	/*--------------------------*/
	/*	Themes
	/*--------------------------*/

	if ( typeof data.activate_theme !='undefined' ) {
		( parseInt(data.activate_theme) == 1 ) ? $('#activate_theme').attr('checked', 'checked') : $('#activate_theme').removeAttr('checked');
	}

	if ( typeof data.delete_default_themes !='undefined' ) {
		( parseInt(data.delete_default_themes) == 1 ) ? $('#delete_default_themes').attr('checked', 'checked') : $('#delete_default_themes').removeAttr('checked');
	}

	/*--------------------------*/
	/*	Plugins
	/*--------------------------*/

	if ( typeof data.plugins !='undefined' ) {
		$('#plugins').val( data.plugins.join(';') );
	}

	if ( typeof data.plugins_premium !='undefined' ) {
		( parseInt(data.plugins_premium) == 1 ) ? $('#plugins_premium').attr('checked', 'checked') : $('#plugins_premium').removeAttr('checked');
	}

	if ( typeof data.activate_plugins !='undefined' ) {
		( parseInt(data.activate_plugins) == 1 ) ? $('#activate_plugins').attr('checked', 'checked') : $('#activate_plugins').removeAttr('checked');
	}

	/*--------------------------*/
	/*	Permalinks
	/*--------------------------*/

	if ( typeof data.permalink_structure !='undefined' ) {
		$('#permalink_structure').val(data.permalink_structure);
	}

	/*--------------------------*/
	/*	Medias
	/*--------------------------*/

	if ( typeof data.uploads !='undefined' ) {

		if ( typeof data.uploads.thumbnail_size_w !='undefined' ) {
			$('#thumbnail_size_w').val(parseInt(data.uploads.thumbnail_size_w));
		}

		if ( typeof data.uploads.thumbnail_size_h !='undefined' ) {
			$('#thumbnail_size_h').val(parseInt(data.uploads.thumbnail_size_h));
		}

		if ( typeof data.uploads.thumbnail_crop !='undefined' ) {
			( parseInt(data.uploads.thumbnail_crop) == 1 ) ? $('#thumbnail_crop').attr('checked', 'checked') : $('#thumbnail_crop').removeAttr('checked');
		}

		if ( typeof data.uploads.medium_size_w !='undefined' ) {
			$('#medium_size_w').val(parseInt(data.uploads.medium_size_w));
		}

		if ( typeof data.uploads.medium_size_h !='undefined' ) {
			$('#medium_size_h').val(parseInt(data.uploads.medium_size_h));
		}

		if ( typeof data.uploads.large_size_w !='undefined' ) {
			$('#large_size_w').val(parseInt(data.uploads.large_size_w));
		}

		if ( typeof data.uploads.large_size_h !='undefined' ) {
			$('#large_size_h').val(parseInt(data.uploads.large_size_h));
		}

		if ( typeof data.uploads.upload_dir !='undefined' ) {
			$('#upload_dir').val(data.uploads.upload_dir);
		}

		if ( typeof data.uploads.uploads_use_yearmonth_folders !='undefined' ) {
			( parseInt(data.uploads.uploads_use_yearmonth_folders) == 1 ) ? $('#uploads_use_yearmonth_folders').attr('checked', 'checked') : $('#uploads_use_yearmonth_folders').removeAttr('checked');
		}

	}

	/*--------------------------*/
	/*	wp-config.php constants
	/*--------------------------*/

	if ( typeof data.wp_config !='undefined' ) {

		if ( typeof data.wp_config.autosave_interval !='undefined' ) {
			$('#autosave_interval').val(data.wp_config.autosave_interval);
		}

		if ( typeof data.wp_config.post_revisions !='undefined' ) {
			$('#post_revisions').val(data.wp_config.post_revisions);
		}

		if ( typeof data.wp_config.disallow_file_edit !='undefined' ) {
			( parseInt(data.wp_config.disallow_file_edit) == 1 ) ? $('#disallow_file_edit').attr('checked', 'checked') : $('#disallow_file_edit').removeAttr('checked');
		}

		if ( typeof data.wp_config.debug !='undefined' ) {
			if ( parseInt(data.wp_config.debug) == 1 ) {
				$debug.attr('checked', 'checked');
				$debug.parent().hide().siblings('p').hide();
				$debug_options.slideDown();
				$debug_display.attr('checked', true);
				$debug_log.attr('checked', true);
			} else {
				$('#debug').removeAttr('checked');
			}
		}
		
		if ( typeof data.wp_config.wpcom_api_key !='undefined' ) {
			$('#wpcom_api_key').val(data.wp_config.wpcom_api_key);
		}

	}

	var $response  = $('#response');

	$('#submit').click( function() {

		errors = false;

		// We hide errors div
		$('#errors').hide().html('<strong>Warning !</strong>');

		$('input.required').each(function(){
			if ( $.trim($(this).val()) == '' ) {
				errors = true;
				$(this).addClass('error');
				$(this).css("border", "1px solid #FF0000");
			} else {
				$(this).removeClass('error');
				$(this).css("border", "1px solid #DFDFDF");
			}
		});

		if ( ! errors ) {

			/*--------------------------*/
			/*	We verify the database connexion and if WP already exists
			/*  If there is no errors we install
			/*--------------------------*/

			$.post(window.location.href + '?action=check_before_upload', $('form').serialize(), function(data) {

				errors = false;
				data = $.parseJSON(data);

				if ( data.db == "error etablishing connection" ) {
					errors = true;
					$('#errors').show().append('<p style="margin-bottom:0px;">&bull; Error Establishing a Database Connection.</p>');
				}

				if ( data.wp == "error directory" ) {
					errors = true;
					$('#errors').show().append('<p style="margin-bottom:0px;">&bull; WordPress seems to be Already Installed.</p>');
				}

				if ( ! errors ) {
					$('form').fadeOut( 'fast', function() {

						$('.progress').show();

						// Fire Step
						// We dowload WordPress
						$response.html("<p>WordPress Download in Progress ...</p>");

						$.post(window.location.href + '?action=download_wp', $('form').serialize(), function() {
							unzip_wp();
						});
					});
				} else {
					// If there is an error
					$('html,body').animate( { scrollTop: $( 'html,body' ).offset().top } , 'slow' );
				}
			});

		} else {
			// If there is an error
			$('html,body').animate( { scrollTop: $( 'input.error:first' ).offset().top-20 } , 'slow' );
		}
		return false;
	});

	// Let's unzip WordPress
	function unzip_wp() {
		$response.html("<p>Decompressing Files...</p>" );
		$('.progress-bar').animate({width: "16.5%"});
		$.post(window.location.href + '?action=unzip_wp', $('form').serialize(), function(data) {
			wp_config();
		});
	}

	// Let's create the wp-config.php file
	function wp_config() {
		$response.html("<p>File Creation for wp-config...</p>");
		$('.progress-bar').animate({width: "33%"});
		$.post(window.location.href + '?action=wp_config', $('form').serialize(), function(data) {
			install_wp();
		});
	}

	// CDatabase
	function install_wp() {
		$response.html("<p>Database Installation in Progress...</p>");
		$('.progress-bar').animate({width: "49.5%"});
		$.post(window.location.href + '?action=install_wp', $('form').serialize(), function(data) {
			install_theme();
		});
	}

	// Theme
	function install_theme() {
		$response.html("<p>Theme Installation in Progress...</p>");
		$('.progress-bar').animate({width: "66%"});
		$.post(window.location.href + '?action=install_theme', $('form').serialize(), function(data) {
			install_plugins();
		});
	}

	// Plugin
	function install_plugins() {
		$response.html("<p>Plugins Installation in Progress...</p>");
		$('.progress-bar').animate({width: "82.5%"});
		$.post(window.location.href + '?action=install_plugins', $('form').serialize(), function(data) {
			$response.html(data);
			success();
		});
	}

	// Remove the archive
	function success() {
		$response.html("<p>Successful installation completed</p>");
		$('.progress-bar').animate({width: "100%"});
		$response.hide();
		$('.progress').delay(500).hide();
		$.post(window.location.href + '?action=success',$('form').serialize(), function(data) {
			$('#success').show().append(data);
		});
		$.get( 'http://wp-quick-install.com/inc/incr-counter.php' );
	}

});
