<?php
/*
Script Name: WP Quick Install
Author: Jonathan Buttigieg
Contributors: Julio Potier, Pravdomil Toman
Script URI: http://wp-quick-install.com
Licence: GPLv3
*/

ob_start();

// PUT YOUR CONFIGURATION HERE ?>

{
	
}

<?php

$config_json = ob_get_clean();

@set_time_limit(120);

class wp_quick_install {
	
	const API_CORE = "http://api.wordpress.org/core/version-check/1.7/?locale=";
	const CACHE_PATH = "wp_quick_install_cache/";
	
	var $data = array();
	var $error = array();
	
	function __construct($initData = array()) { 
        
		// error reporting
		$this->error_log();
		
		// sets data
		$this->data = $initData;
		if($_POST["data"]) $this->data = $_POST["data"];
		
		// no submit by default
		$this->data["auto_submit"] = false;
		
		// install dir
		if(!$this->data["dir"]) $this->data["dir"] = __DIR__;
		
		// decide steps
		if($this->data["step"]) {
			
			$func = "step_" . $this->data["step"];
			
			if(method_exists($this, $func)) call_user_func( array($this, $func) );
		}
		
		// return ajax or page
		$is_ajax = $_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest";
		$is_ajax ? $this->ajax_send() : $this->page();
    }
	
	function ajax_send() {
		header("Content-type: application/json");
		echo json_encode(array("data" => $this->data, "error" => $this->error));
	}
	
	function error_log() {
		error_reporting(E_ALL);
		@ini_set('html_errors', false);
		@ini_set('display_errors', false);
		
		set_error_handler( array($this, "error_handler") );
		register_shutdown_function( array($this, "shutdown_function") );
	}
	
	function shutdown_function() {
		if($e = error_get_last()) {
			$this->error_handler($e["type"], $e["message"], $e["file"], $e["line"], null);
		}
	}
	
	function error_handler($errno, $str, $file, $line, $context) {
		if (!error_reporting()) return true; // silence operator @
		if (!(4983 & $errno)) return true;
    	
		$msg = $str . " line " . $line;
		$msg = trim($msg);
		
		@header("Content-type: application/json");
		echo json_encode(array("error" => array($msg)));
		exit;
		
		return true;
	}
	
	function dir_ok() {
		
		if (!is_writable($this->data["dir"])) {
			$this->error[] = _("You don't have good permissions rights on ") . basename($this->data["dir"]);
		}
		else {
			return true;
		}
	}
	
	function no_installed_wp() {
		
		if (file_exists($directory . 'wp-config.php')) {
			$this->error[] = _("WordPress seems installed, please clean the folder before continue.");
		}
		else {
			return true;
		}
	}
	
	function step_first() {
		if ($this->dir_ok() && $this->no_installed_wp()) {
			$this->data["step"] = "db";
		}
	}
	
	function db_test_ok() {
		
		try {
			$dsn = "mysql:host=" . $this->data["db"]["host"] . ";dbname=" . $this->data["db"]["name"] ;
			new PDO($dsn, $this->data["db"]["user"], $this->data["db"]["pwd"]);
		}
		catch (Exception $e) {
			$this->error[] = "Error establishing database connection.";
			return false;
		}
		return true;
	}
	
	function step_db() {
		if($this->db_test_ok()) {
			$this->data["step"] = "lang";
		}
	}
	
	function step_lang() {
		
		$langs = json_decode(file_get_contents(self::API_CORE . $this->data['lang']), true);
		
		if(!$langs["offers"][0]) {
			
			$this->error[] = "Language is not available";
			return;
		}
		
		$this->data["zip_file_url"] = $langs["offers"][0]["download"];
	
		$this->data["step"] = "download";
		$this->data["auto_submit"] = true;
	}
	
	function step_download() {
		$this->download_wp();
		$this->data["step"] = "unzip";
		$this->data["auto_submit"] = true;
	}
	
	function download_wp() {
		
		@mkdir( self::CACHE_PATH );
		
		$this->data["zip_file"] = self::CACHE_PATH . basename($this->data["zip_file_url"]);
		
		file_put_contents( $this->data["zip_file"], file_get_contents($this->data["zip_file_url"]) );
	}
	
	function step_unzip() {
		
		$this->unzip();
		
		$this->data["step"] = "config";
		$this->data["auto_submit"] = true;
	}
	
	function unzip() {
		
		$zip = new ZipArchive;

		if ( $zip->open($this->data["zip_file"]) === false ) {
			
			$this->error[] = "Cannot unzip?";
			return;
		}
		else
		{
			$zip->extractTo(".");
			$zip->close();
			
			$files = scandir("wordpress");
			$files = array_diff( $files, array( ".", ".." ) );

			foreach( $files as $file )
			{
				$from = "wordpress/" . $file;
				$to = $this->data["dir"] . "/" . $file;
				
				if($file == "index.php") $to .= ".orig";
				
				rename($from, $to);
			}

			unlink($this->data["zip_file"]);
			rmdir("wordpress");
			rmdir(self::CACHE_PATH);
			unlink($this->data["dir"] . "/license.txt");
			unlink($this->data["dir"] . "/readme.html");
		}
	}
	
	function step_config() {
		
		$this->config();
		
		$this->data["step"] = "setup";
	}
	
	function get_secret_keys() {
		
		$secret_keys = file_get_contents('https://api.wordpress.org/secret-key/1.1/salt/');
		
		if ( !$secret_keys ) {
			$this->error[] = "Cannot retrieve secret keys.";
			return;
		}
		
		$secret_keys = explode("\n", $secret_keys);
		foreach ( $secret_keys as $k => $v ) {
			$secret_keys[$k] = substr($v, 28, 64);
		}
		
		return $secret_keys;
	}
	
	function replace_const(&$config, $const, $value) {
		
		$const = addcslashes(preg_quote($const), '\'');
		$value = addcslashes(preg_quote($value), '\'');
		
		$config = preg_replace("/(define\('" . $const . "',\s*').+('\))/", '$1' . $value . '$2', $config);
	}
	
	function replace_var(&$config, $var, $value) {
		
		$var = addcslashes(preg_quote($var), '\'');
		$value = addcslashes(preg_quote($value), '\'');
		
		$config = preg_replace("/(\\\$" . $var . "\s*=\s*').+(')/", '$1' . $value . '$2', $config);
	}
	
	function config() {
		
		$config = file_get_contents( $this->data["dir"] . '/wp-config-sample.php');
		$secret_keys = $this->get_secret_keys();
		
		$this->replace_const($config, "DB_NAME", $this->data["db"]["name"]);
		$this->replace_const($config, "DB_USER", $this->data["db"]["user"]);
		$this->replace_const($config, "DB_PASSWORD", $this->data["db"]["pwd"]);
		$this->replace_const($config, "DB_HOST", $this->data["db"]["host"]);
		
		$this->replace_const($config, "AUTH_KEY", $secret_keys[0]);
		$this->replace_const($config, "SECURE_AUTH_KEY", $secret_keys[1]);
		$this->replace_const($config, "LOGGED_IN_KEY", $secret_keys[2]);
		$this->replace_const($config, "NONCE_KEY", $secret_keys[3]);
		
		$this->replace_const($config, "AUTH_SALT", $secret_keys[4]);
		$this->replace_const($config, "SECURE_AUTH_SALT", $secret_keys[5]);
		$this->replace_const($config, "LOGGED_IN_SALT", $secret_keys[6]);
		$this->replace_const($config, "NONCE_SALT", $secret_keys[7]);
		
		$this->replace_var($config, "table_prefix", $this->data["db"]["prefix"]);

		$config_path = $this->data["dir"] . '/wp-config.php';
		file_put_contents($config_path, $config);
		@chmod($config_path, 0666);
		
		// for syntax check
		include($config_path);
	}
	
	function load_wp_core() {
		require_once($this->data["dir"] . '/wp-load.php');
		require_once($this->data["dir"] . '/wp-admin/includes/upgrade.php');
	}
	
	function step_setup() {
		
		define( 'WP_INSTALLING', true );
		
		$this->load_wp_core();
		
		$result = wp_install(
			$this->data["site_title"],
			$this->data["user"]["name"],
			$this->data["user"]["email"],
			(int) $this->data["blog_public"],
			"",
			$this->data["user"]["pwd"]
		);
		
		if(!$result['password']) {
			$this->error[] = $result['password_message'];
			return;
		}
		
		$this->data["url"] = $result['url'];
		$this->data["step"] = "more";
		
	}
	
	function delete_default_content() {
		
		delete_theme('twentysixteen');
		delete_theme('twentyfifteen');
		delete_theme('twentyfourteen');
		delete_theme('twentythirteen');
		delete_theme('twentytwelve');
		delete_theme('twentyeleven');
		delete_theme('twentyten');
		
		delete_plugins(array('hello.php', 'akismet/akismet.php'));
	}
	
	function set_permalink_struct($str) {
		global $wp_rewrite;
		
		$wp_rewrite->set_permalink_structure($str);
		save_mod_rewrite_rules();
	}
	
	function install_plugins($array) {
		
		require_once $this->data['dir'] . '/wp-admin/includes/class-wp-upgrader.php';
		global $WPQI_Installer_Skin;
		$WPQI_Installer_Skin();
		
		foreach ($array as $name) {
			
			if(!$name) continue;
			
			$is_url = preg_match("/^(http|https):\/\//i", $name);
			
			$url = $is_url ? $name : "https://downloads.wordpress.org/plugin/$name.zip";
			
			$upgrader = new Plugin_Upgrader(new WPQI_Installer_Skin());
			$upgrader->install($url);
			activate_plugin($upgrader->plugin_info());
			
		}
		
		wp_clean_plugins_cache();
	}
	
	function install_theme($array) {
		
		require_once $this->data['dir'] . '/wp-admin/includes/class-wp-upgrader.php';
		global $WPQI_Installer_Skin;
		$WPQI_Installer_Skin();
		
		$first = true;
		foreach ($array as $name) {
			
			if(!$name) continue;
			
			$is_url = preg_match("/^(http|https):\/\//i", $name);
			
			$url = $is_url ? $name : "https://downloads.wordpress.org/theme/$name.zip";
			
			$upgrader = new Theme_Upgrader(new WPQI_Installer_Skin());
			$upgrader->install($url);
			
			if($first) {
				switch_theme($name);
				$first = false;
			}
		}
		
		wp_clean_themes_cache();
	}
	
	function step_more() {
		
		$this->load_wp_core();
		
		if ($this->data['del_default']) $this->delete_default_content();
		
		if ($this->data['permalink_structure']) $this->set_permalink_struct($this->data['permalink_structure']);
		
		if($this->data['install_plugin']) {
			$this->install_plugins(explode("\n", $this->data['install_plugin']));
		}
		
		if($this->data['install_theme']) {
			$this->install_theme(explode("\n", $this->data['install_theme']));
		}
		
		if($this->data['page_on_front']) {
			update_option('show_on_front', 'page');
			update_option('page_on_front', 2);
		}
		
		if($this->data['set_avatar']) update_option("avatar_default", "identicon");
		
		//return;
		
		unlink($this->dir . "index.php");
		rename($this->dir . "index.php.orig", $this->dir . "index.php");
		
		$this->data["step"] = get_home_url();
	}
	
	function page() {
		
		?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8" />
		<title>WP Quick Install</title>
		<meta name="robots" content="noindex, nofollow">
		
		<link rel='stylesheet' id='open-sans-css'  href='//fonts.googleapis.com/css?family=Open+Sans%3A300italic%2C400italic%2C600italic%2C300%2C400%2C600&amp;subset=latin%2Clatin-ext' type='text/css' media='all' />
		
		<style type="text/css">
		/* wp-admin/css/install.min.css */
a img,abbr{border:0}#logo a,a{text-decoration:none}#logo a,.form-table th p,h1{font-weight:400}html{background:#f1f1f1;margin:0 20px}body{background:#fff;color:#444;font-family:"Open Sans",sans-serif;margin:140px auto 25px;padding:20px 20px 10px;max-width:700px;-webkit-font-smoothing:subpixel-antialiased;-webkit-box-shadow:0 1px 3px rgba(0,0,0,.13);box-shadow:0 1px 3px rgba(0,0,0,.13)}a{color:#0073aa}a:hover{color:#00a0d2}h1{border-bottom:1px solid #dedede;clear:both;color:#666;font-size:24px;margin:30px 0;padding:0 0 7px}h2{font-size:16px}dd,dt,li,p{padding-bottom:2px;font-size:14px;line-height:1.5}.code,code{font-family:Consolas,Monaco,monospace}input,submit,textarea{font-family:"Open Sans",sans-serif}dl,ol,ul{padding:5px 5px 5px 22px}abbr{font-variant:normal}label{cursor:pointer}#logo{margin:6px 0 14px;border-bottom:none;text-align:center}#logo a{background-image:url(../images/w-logo-blue.png?ver=20131202);background-image:none,url(../images/wordpress-logo.svg?ver=20131107);-webkit-background-size:84px;background-size:84px;background-position:center top;background-repeat:no-repeat;color:#999;height:84px;font-size:20px;line-height:1.3em;margin:-130px auto 25px;padding:0;width:84px;text-indent:-9999px;outline:0;overflow:hidden;display:block}.step{margin:20px 0 15px}.step,th{text-align:left;padding:0}.language-chooser.wp-core-ui .step .button.button-large{height:36px;vertical-align:middle;font-size:14px}.form-table td,.form-table th{font-size:14px;vertical-align:top}textarea{border:1px solid #dfdfdf;width:100%;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}.form-table{border-collapse:collapse;margin-top:1em;width:100%}.form-table td{margin-bottom:9px;padding:10px 20px 10px 0;border-bottom:8px solid #fff}.form-table th{text-align:left;padding:16px 20px 10px 0;width:140px}.form-table code{line-height:18px;font-size:14px}.form-table p{margin:4px 0 0;font-size:11px}.form-table input{line-height:20px;font-size:15px;padding:3px 5px;border:1px solid #ddd;-webkit-box-shadow:inset 0 1px 2px rgba(0,0,0,.07);box-shadow:inset 0 1px 2px rgba(0,0,0,.07)}.form-table input[type=email],.form-table input[type=password],.form-table input[type=text],.form-table input[type=url]{width:206px}.form-table.install-success td{vertical-align:middle;padding:16px 20px 10px 0}.form-table.install-success td p{margin:0;font-size:14px}.form-table.install-success td code{margin:0;font-size:18px}#error-page{margin-top:50px}#error-page p{font-size:14px;line-height:18px;margin:25px 0 20px}#error-page code,.code{font-family:Consolas,Monaco,monospace}#pass-strength-result{background-color:#eee;border-color:#ddd!important;border-style:solid;border-width:1px;margin:5px 5px 5px 0;padding:5px;text-align:center;width:200px;display:none}#pass-strength-result.bad{background-color:#ffb78c;border-color:#ff853c!important}#pass-strength-result.good{background-color:#ffec8b;border-color:#fc0!important}#pass-strength-result.short{background-color:#ffa0a0;border-color:#f04040!important}#pass-strength-result.strong{background-color:#c3ff88;border-color:#8dff1c!important}.message{border:1px solid #c00;padding:.5em .7em;margin:5px 0 15px;background-color:#ffebe8}#admin_email,#dbhost,#dbname,#pass1,#pass2,#prefix,#pwd,#uname,#user_login{direction:ltr}.rtl input,.rtl submit,.rtl textarea,body.rtl{font-family:Tahoma,sans-serif}.language-chooser select,:lang(he-il) .rtl input,:lang(he-il) .rtl submit,:lang(he-il) .rtl textarea,:lang(he-il) body.rtl{font-family:Arial,sans-serif}@media only screen and (max-width:799px){body{margin-top:115px}#logo a{margin:-125px auto 30px}}@media screen and (max-width:782px){.form-table{margin-top:0}.form-table td,.form-table th{display:block;width:auto;vertical-align:middle}.form-table th{padding:20px 0 0}.form-table td{padding:5px 0;border:0;margin:0}input,textarea{font-size:16px}.form-table span.description,.form-table td input[type=text],.form-table td input[type=email],.form-table td input[type=url],.form-table td input[type=password],.form-table td select,.form-table td textarea{width:100%;font-size:16px;line-height:1.5;padding:7px 10px;display:block;max-width:none;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}}body.language-chooser{max-width:300px}.language-chooser select{padding:8px;width:100%;display:block;border:1px solid #ddd;background-color:#fff;color:#32373c;font-size:16px;font-weight:400}.language-chooser p{text-align:right}.screen-reader-input,.screen-reader-text{position:absolute;margin:-1px;padding:0;height:1px;width:1px;overflow:hidden;clip:rect(0 0 0 0);border:0}.spinner{background:url(../images/spinner.gif) no-repeat;-webkit-background-size:20px 20px;background-size:20px 20px;visibility:hidden;opacity:.7;filter:alpha(opacity=70);width:20px;height:20px;margin:2px 5px 0}.step .spinner{display:inline-block;margin-top:8px;margin-right:15px;vertical-align:top}@media print,(-webkit-min-device-pixel-ratio:1.25),(min-resolution:120dpi){.spinner{background-image:url(../images/spinner-2x.gif)}}
		</style>
		<style type="text/css">
		/* wp-includes/css/buttons.min.css */
.wp-core-ui .button,.wp-core-ui .button-primary,.wp-core-ui .button-secondary{display:inline-block;text-decoration:none;font-size:13px;line-height:26px;height:28px;margin:0;padding:0 10px 1px;cursor:pointer;border-width:1px;border-style:solid;-webkit-appearance:none;-webkit-border-radius:3px;border-radius:3px;white-space:nowrap;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}.wp-core-ui button::-moz-focus-inner,.wp-core-ui input[type=reset]::-moz-focus-inner,.wp-core-ui input[type=button]::-moz-focus-inner,.wp-core-ui input[type=submit]::-moz-focus-inner{border-width:0;border-style:none;padding:0}.wp-core-ui .button-group.button-large .button,.wp-core-ui .button.button-large{height:30px;line-height:28px;padding:0 12px 2px}.wp-core-ui .button-group.button-small .button,.wp-core-ui .button.button-small{height:24px;line-height:22px;padding:0 8px 1px;font-size:11px}.wp-core-ui .button-group.button-hero .button,.wp-core-ui .button.button-hero{font-size:14px;height:46px;line-height:44px;padding:0 36px}.wp-core-ui .button:active,.wp-core-ui .button:focus{outline:0}.ie8 .wp-core-ui .button-link:focus{outline:#5b9dd9 solid 1px}.wp-core-ui .button.hidden{display:none}.wp-core-ui input[type=reset],.wp-core-ui input[type=reset]:active,.wp-core-ui input[type=reset]:focus,.wp-core-ui input[type=reset]:hover{background:0 0;border:none;-webkit-box-shadow:none;box-shadow:none;padding:0 2px 1px;width:auto}.wp-core-ui .button,.wp-core-ui .button-secondary{color:#555;border-color:#ccc;background:#f7f7f7;-webkit-box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(0,0,0,.08);box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(0,0,0,.08);vertical-align:top}.wp-core-ui p .button{vertical-align:baseline}.wp-core-ui .button-link{border:0;background:0 0;outline:0;cursor:pointer}.wp-core-ui .button-secondary:focus,.wp-core-ui .button-secondary:hover,.wp-core-ui .button.focus,.wp-core-ui .button.hover,.wp-core-ui .button:focus,.wp-core-ui .button:hover{background:#fafafa;border-color:#999;color:#23282d}.wp-core-ui .button-link:focus,.wp-core-ui .button-secondary:focus,.wp-core-ui .button.focus,.wp-core-ui .button:focus{-webkit-box-shadow:0 0 0 1px #5b9dd9,0 0 2px 1px rgba(30,140,190,.8);box-shadow:0 0 0 1px #5b9dd9,0 0 2px 1px rgba(30,140,190,.8)}.wp-core-ui .button-secondary:active,.wp-core-ui .button.active,.wp-core-ui .button.active:hover,.wp-core-ui .button:active{background:#eee;border-color:#999;color:#32373c;-webkit-box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5);box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5)}.wp-core-ui .button.active:focus{-webkit-box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5),0 0 0 1px #5b9dd9,0 0 2px 1px rgba(30,140,190,.8);box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5),0 0 0 1px #5b9dd9,0 0 2px 1px rgba(30,140,190,.8)}.wp-core-ui .button-disabled,.wp-core-ui .button-secondary.disabled,.wp-core-ui .button-secondary:disabled,.wp-core-ui .button-secondary[disabled],.wp-core-ui .button.disabled,.wp-core-ui .button:disabled,.wp-core-ui .button[disabled]{color:#a0a5aa!important;border-color:#ddd!important;background:#f7f7f7!important;-webkit-box-shadow:none!important;box-shadow:none!important;text-shadow:0 1px 0 #fff!important;cursor:default}.wp-core-ui .button-primary{background:#00a0d2;border-color:#0073aa;-webkit-box-shadow:inset 0 1px 0 rgba(120,200,230,.5),0 1px 0 rgba(0,0,0,.15);box-shadow:inset 0 1px 0 rgba(120,200,230,.5),0 1px 0 rgba(0,0,0,.15);color:#fff;text-decoration:none}.wp-core-ui .button-primary.focus,.wp-core-ui .button-primary.hover,.wp-core-ui .button-primary:focus,.wp-core-ui .button-primary:hover{background:#0091cd;border-color:#0073aa;-webkit-box-shadow:inset 0 1px 0 rgba(120,200,230,.6);box-shadow:inset 0 1px 0 rgba(120,200,230,.6);color:#fff}.wp-core-ui .button-primary.focus,.wp-core-ui .button-primary:focus{border-color:#0e3950;-webkit-box-shadow:inset 0 1px 0 rgba(120,200,230,.6),0 0 0 1px #5b9dd9,0 0 2px 1px rgba(30,140,190,.8);box-shadow:inset 0 1px 0 rgba(120,200,230,.6),0 0 0 1px #5b9dd9,0 0 2px 1px rgba(30,140,190,.8)}.wp-core-ui .button-primary.active,.wp-core-ui .button-primary.active:focus,.wp-core-ui .button-primary.active:hover,.wp-core-ui .button-primary:active{background:#0073aa;border-color:#005082;color:rgba(255,255,255,.95);-webkit-box-shadow:inset 0 1px 0 rgba(0,0,0,.1);box-shadow:inset 0 1px 0 rgba(0,0,0,.1);vertical-align:top}.wp-core-ui .button-primary-disabled,.wp-core-ui .button-primary.disabled,.wp-core-ui .button-primary:disabled,.wp-core-ui .button-primary[disabled]{color:#94cde7!important;background:#298cba!important;border-color:#1b607f!important;-webkit-box-shadow:none!important;box-shadow:none!important;text-shadow:0 -1px 0 rgba(0,0,0,.1)!important;cursor:default}.wp-core-ui .button-group{position:relative;display:inline-block;white-space:nowrap;font-size:0;vertical-align:middle}.wp-core-ui .button-group>.button{display:inline-block;-webkit-border-radius:0;border-radius:0;margin-right:-1px;z-index:10}.wp-core-ui .button-group>.button-primary{z-index:100}.wp-core-ui .button-group>.button:hover{z-index:20}.wp-core-ui .button-group>.button:first-child{-webkit-border-radius:3px 0 0 3px;border-radius:3px 0 0 3px}.wp-core-ui .button-group>.button:last-child{-webkit-border-radius:0 3px 3px 0;border-radius:0 3px 3px 0}.wp-core-ui .button-group>.button:focus{position:relative;z-index:1}@media screen and (max-width:782px){.wp-core-ui .button,.wp-core-ui .button.button-large,.wp-core-ui .button.button-small,a.preview,input#publish,input#save-post{padding:6px 14px;line-height:normal;font-size:14px;vertical-align:middle;height:auto;margin-bottom:4px}#media-upload.wp-core-ui .button{padding:0 10px 1px;height:24px;line-height:22px;font-size:13px}.media-frame.mode-grid .bulk-select .button{margin-bottom:0}.wp-core-ui .save-post-status.button{position:relative;margin:0 14px 0 10px}.wp-core-ui.wp-customizer .button{padding:0 10px 1px;font-size:13px;line-height:26px;height:28px;margin:0;vertical-align:inherit}.interim-login .button.button-large{height:30px;line-height:28px;padding:0 12px 2px}}
		</style>
		<style type="text/css">
#logo a {
	background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB2ZXJzaW9uPSIxLjEiIGlkPSJMYXllcl8xIiB4PSIwcHgiIHk9IjBweCIgd2lkdGg9IjY0cHgiIGhlaWdodD0iNjRweCIgdmlld0JveD0iMCAwIDY0IDY0IiBlbmFibGUtYmFja2dyb3VuZD0ibmV3IDAgMCA2NCA2NCIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PHN0eWxlPi5zdHlsZTB7ZmlsbDoJIzAwNzNhYTt9PC9zdHlsZT48Zz48Zz48cGF0aCBkPSJNNC41NDggMzEuOTk5YzAgMTAuOSA2LjMgMjAuMyAxNS41IDI0LjcwNkw2LjkyNSAyMC44MjdDNS40MDIgMjQuMiA0LjUgMjggNC41IDMxLjk5OXogTTUwLjUzMSAzMC42MTRjMC0zLjM5NC0xLjIxOS01Ljc0Mi0yLjI2NC03LjU3Yy0xLjM5MS0yLjI2My0yLjY5NS00LjE3Ny0yLjY5NS02LjQzOWMwLTIuNTIzIDEuOTEyLTQuODcyIDQuNjA5LTQuODcyIGMwLjEyMSAwIDAuMiAwIDAuNCAwLjAyMkM0NS42NTMgNy4zIDM5LjEgNC41IDMyIDQuNTQ4Yy05LjU5MSAwLTE4LjAyNyA0LjkyMS0yMi45MzYgMTIuNCBjMC42NDUgMCAxLjMgMCAxLjggMC4wMzNjMi44NzEgMCA3LjMxNi0wLjM0OSA3LjMxNi0wLjM0OWMxLjQ3OS0wLjA4NiAxLjcgMi4xIDAuMiAyLjMgYzAgMC0xLjQ4NyAwLjE3NC0zLjE0MiAwLjI2MWw5Ljk5NyAyOS43MzVsNi4wMDgtMTguMDE3bC00LjI3Ni0xMS43MThjLTEuNDc5LTAuMDg3LTIuODc5LTAuMjYxLTIuODc5LTAuMjYxIGMtMS40OC0wLjA4Ny0xLjMwNi0yLjM0OSAwLjE3NC0yLjI2MmMwIDAgNC41IDAuMyA3LjIgMC4zNDljMi44NyAwIDcuMzE3LTAuMzQ5IDcuMzE3LTAuMzQ5IGMxLjQ3OS0wLjA4NiAxLjcgMi4xIDAuMiAyLjI2MmMwIDAtMS40ODkgMC4xNzQtMy4xNDIgMC4yNjFsOS45MiAyOS41MDhsMi43MzktOS4xNDggQzQ5LjYyOCAzNS43IDUwLjUgMzMgNTAuNSAzMC42MTR6IE0zMi40ODEgMzQuNGwtOC4yMzcgMjMuOTM0YzIuNDYgMC43IDUuMSAxLjEgNy44IDEuMSBjMy4xOTcgMCA2LjI2Mi0wLjU1MiA5LjExNi0xLjU1NmMtMC4wNzItMC4xMTgtMC4xNDEtMC4yNDMtMC4xOTYtMC4zNzlMMzIuNDgxIDM0LjR6IE01Ni4wODggMTguOCBjMC4xMTkgMC45IDAuMiAxLjggMC4yIDIuODIzYzAgMi43ODUtMC41MjEgNS45MTYtMi4wODggOS44MzJsLTguMzg1IDI0LjI0MmM4LjE2MS00Ljc1OCAxMy42NS0xMy42IDEzLjY1LTIzLjcyOCBDNTkuNDUxIDI3LjIgNTguMiAyMi43IDU2LjEgMTguODN6IE0zMiAwYy0xNy42NDUgMC0zMiAxNC4zNTUtMzIgMzJDMCA0OS42IDE0LjQgNjQgMzIgNjRzMzItMTQuMzU1IDMyLTMyLjAwMSBDNjQgMTQuNCA0OS42IDAgMzIgMHogTTMyIDYyLjUzM2MtMTYuODM1IDAtMzAuNTMzLTEzLjY5OC0zMC41MzMtMzAuNTM0QzEuNDY3IDE1LjIgMTUuMiAxLjUgMzIgMS41IHMzMC41MzQgMTMuNyAzMC41IDMwLjUzMkM2Mi41MzMgNDguOCA0OC44IDYyLjUgMzIgNjIuNTMzeiIgY2xhc3M9InN0eWxlMCIvPjwvZz48L2c+PC9zdmc+");
}
*[step] { display: none; }
#error { color: darkred; display: none; }

		</style>
	</head>
	<body class="wp-core-ui">

<h1 id="logo"><a href="https://wordpress.org/" tabindex="-1">WordPress</a></h1>
<div step="first">
	<p><?php echo _('Hi, press the button to install WordPress into ') . '<b title="' . $this->data["dir"] . '">' . basename($this->data["dir"]) . "</b>" . _(' directory.');?></p>
	<p><input type="submit" value="Install WordPress" class="button button-large" onclick="return wp_install.submit()"></p>
</div>

<div step="db"><form onsubmit="return wp_install.submit()">
	<p>Below you should enter your database connection details. If you’re not sure about these, contact your host.</p>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="dbname">Database Name</label></th>
			<td><input name="db[name]" id="dbname" type="text" size="25" value="woddpress" required></td>
			<td>The name of the database you want to run WP in.</td>
		</tr>
		<tr>
			<th scope="row"><label for="dbuser">User Name</label></th>
			<td><input name="db[user]" id="uname" type="text" size="25" value="" required></td>
			<td>Your MySQL username</td>
		</tr>
		<tr>
			<th scope="row"><label for="pwd">Password</label></th>
			<td><input name="db[pwd]" id="pwd" type="text" size="25" value="" autocomplete="off" required></td>
			<td>…and your MySQL password.</td>
		</tr>
		<tr>
			<th scope="row"><label for="dbhost">Database Host</label></th>
			<td><input name="db[host]" id="dbhost" type="text" size="25" value="localhost" required></td>
			<td>You should be able to get this info from your web host, if <code>localhost</code> does not work.</td>
		</tr>
		<tr>
			<th scope="row"><label for="prefix">Table Prefix</label></th>
			<td><input name="db[prefix]" id="prefix" type="text" value="<?php echo basename(__DIR__) ?>_" size="25" required pattern="[a-zA-Z0-9_]+"></td>
			<td>If you want to run multiple WordPress installations in a single database, change this.</td>
		</tr>
	</table>
	<p><input type="submit" value="Submit" class="button button-large"></p>
</form></div>

<div step="lang">
	<p>
		Select language<br>
		<select name="lang">
			<option value="en_US">English (United States)</option>
			<?php
			$langs = json_decode(file_get_contents('http://api.wordpress.org/translations/core/1.0/'))->translations;

			foreach ( $langs as $l ) {
				echo '<option value="' . $l->language . '">' . $l->native_name . '</option>';
			}
			?>
		</select>
	</p>
	<p><input type="submit" value="Submit" class="button button-large" onclick="return wp_install.submit()"></p>
</div>

<div step="download">
	<p>Downloading...</p>
</div>

<div step="unzip">
	<p>Unpacking...</p>
</div>

<div step="config">
	<p>Configuring...</p>
</div>

<div step="setup"><form onsubmit="return wp_install.submit()">
	<p>Wordpress was installed, please configure it.</p>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="site_title">Site Title</label></th>
			<td><input name="site_title" type="text" id="site_title" size="25" required></td>
		</tr>
		<tr>
			<th scope="row"><label for="user_login">Username</label></th>
			<td><input name="user[name]" type="text" id="user_login" size="25" value="admin" required></td>
		</tr>
		<tr>
			<th scope="row">
				<label for="pass1">Password</label>
				<p>A password will be automatically generated for you if you leave this blank.</p>
			</th>
			<td>
				<input name="user[pwd]" type="password" id="pass1" size="25" value="" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="admin_email">Your E-mail</label></th>
			<td><input name="user[email]" type="email" id="admin_email" size="25" value="" required/>
			<p>Double-check your email address before continuing.</p></td>
		</tr>
		<tr>
			<th scope="row">Privacy?</th>
			<td colspan="2"><label><input type="checkbox" name="blog_public" id="blog_public" value="1" checked> Allow search engines to index this site.</label></td>
		</tr>
	</table>
	<p><input type="submit" value="Submit" class="button button-large"></p>
</form></div>

<div step="more">
	<p>We are ready, you can <input type="submit" value="launch the site" class="button-primary button-large" onclick="return wp_install.submit()"></p>
	
	<h1>You can do setup more</h1>
	<form onsubmit="return wp_install.submit()">
		<table class="form-table">
			<tr>
				<th scope="row">Front page</th>
				<td><label><input type="checkbox" name="page_on_front" id="page_on_front" value="1" checked>Static page</label></td>
			</tr>
			<tr>
				<th scope="row"><label for="permalink_structure">Permalink structure</label></th>
				<td><input name="permalink_structure" type="text" id="permalink_structure" size="25" value="/%postname%/"></td>
			</tr>
			<tr>
				<th scope="row">Default avatar</th>
				<td><label><input type="checkbox" name="set_avatar" id="set_avatar" value="1" checked>Identicon</label></td>
			</tr>
			<tr>
				<th scope="row">Default content</th>
				<td><label><input type="checkbox" name="del_default" id="del_default" value="1" checked>Delete default themes, plugins</label></td>
			</tr>
			<tr>
				<th scope="row"><label for="install_theme">Themes<p>Line separated, name or url, first will be activated.</p></label></th>
				<td><textarea name="install_theme" type="text" id="install_theme" size="25"></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="install_plugin">Plugins<p>Line separated, name or url, all will be activated.</p></label></th>
				<td><textarea name="install_plugin" type="text" id="install_plugin" size="25">
	https://github.com/Pravdomil/wp-github-plugins/archive/master.zip
	https://github.com/Pravdomil/wp-pravdomil/archive/master.zip
				</textarea></td>
			</tr>
		</table>
	<p><input type="submit" value="Submit and launch" class="button button-large"></p>
	</form>
</div>

<div id="error">
	<p><span id="error_msg"></span> <small><a href="javascript:wp_install.submit()">try again</a></small></p>
</div>

<script src="//code.jquery.com/jquery-1.8.3.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery-serialize-object/2.0.0/jquery.serialize-object.compiled.js"></script>
<script type="text/javascript">
var wp_install = new function() {
	
	this.data = {};
	this.data.step = "first";
	
	this.$step = $();
	
	this.submit = function() {
		
		$("#error").fadeOut(0);
		
		var formData = this.$step.find("form").serializeObject();
		$.extend(this.data, formData);
		
		this.$step.find(":input").prop("disabled", true);
		
		$.post("", { "data": this.data }).always($.proxy(this.submitted, this));
		
		return false;
	}
	
	this.submitted = function(ajax, status) {
		
		if(status == "error") ajax = $.parseJSON(ajax.responseText);
		
		this.$step.find(":input").prop("disabled", false);
		
		// error?
		if(ajax && ajax.error.length) {
			
			$("#error_msg").html(ajax.error.join("<br>"));
			$("#error").fadeIn();
			
			return;
		}
		
		// hide current step
		this.$step.hide();
		
		// load new data from server if any
		if(ajax && ajax.data) this.data = ajax.data;
		
		// if step is url then redirect
		var is_url = /^(http|https):\/\//i.test(this.data.step);
		if(is_url) {
			location.href = this.data.step;
			return;
		}
		
		// refresh
		this.refreshProps();
		
		// show current step
		this.$step.fadeIn(.1);
		
		if(this.data["auto_submit"]) this.submit();
		
	}
	
	this.refreshProps = function() {
		this.$step = $("*[step=" + this.data.step + "]");
	}
	
	this.submitted();
	
}
</script>

	</body>
</html>
	<?php
		
	}
}

// for future translations...
if(!function_exists('_')) { function _($s) { echo $s; } }

$WPQI_Installer_Skin = function() {
	if(class_exists("WPQI_Installer_Skin")) return;
	
	class WPQI_Installer_Skin extends WP_Upgrader_Skin {
		public function feedback($string) { return; }
		public function header() { return; }
		public function footer() { return; }
	}
};

// go
new wp_quick_install($config_json);
