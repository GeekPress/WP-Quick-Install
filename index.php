<?php
/*
Script Name: WP Quick Install
Author: Jonathan Buttigieg
Contributors: Julio Potier, Pravdomil Toman
Script URI: http://wp-quick-install.com
Licence: GPLv3
*/

/*
Release notes:
for future all the contents of this file should be loaded from WordPress api
And this file replaced with eval( file_get_contents(WP_API) )
*/

ob_start();

// SET YOUR CONFIGURATION HERE ?>

{
	//"auto_installer": true,
	"db": {
		//"name": "wordpress",
		//"user": "admin",
		//"pwd": "",
		//"host": "localhost",
		//"prefix": "wp_",
	},
}

<?php

$user_config = ob_get_clean();

class wp_quick_install {
	
	const CACHE_PATH = "wp_quick_install_cache/";
	
	var $data = array();
	var $error = array();
	
	function __construct($config_json = "{}") { 
        
		// time limit
		@set_time_limit(120);
		
		// error reporting
		$this->error_log();
		
		// user config
		$this->config_json = $config_json;
		
		// sets data
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
			$this->error[] = "You don't have good permissions rights on " . basename($this->data["dir"]);
		}
		else {
			return true;
		}
	}
	
	function no_installed_wp() {
		
		if (file_exists($directory . 'wp-config.php')) {
			$this->error[] = "WordPress seems installed, please clean the folder before continue.";
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
		$url = "http://api.wordpress.org/core/version-check/1.7/?locale=" . $this->data['lang'];
		
		$langs = json_decode(file_get_contents($url), true);
		
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
.spinner { visibility: visible; display: none; background-image: url('data:image/gif;base64,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') !important; }

		</style>
	</head>
	<body class="wp-core-ui">

<h1 id="logo"><a href="https://wordpress.org/" tabindex="-1">WordPress</a></h1>
<div step="first">
	<p>Hi, press the button to install WordPress into
		<b title="<?php echo $this->data["dir"] ?>"><?php echo basename($this->data["dir"]); ?></b>
		directory.
	</p>
	<p><input type="submit" value="Install WordPress" class="button button-large" onclick="return wp_install.submit()"></p>
</div>

<div step="db"><form onsubmit="return wp_install.submit()">
	<p>Below you should enter your database connection details. If you’re not sure about these, contact your host.</p>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="dbname">Database Name</label></th>
			<td><input name="db[name]" id="dbname" type="text" size="25" value="wordpress" required></td>
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
			<td><input name="db[prefix]" id="prefix" type="text" value="<?php echo preg_replace("/[^a-Ž0-9_]/", "_", basename(__DIR__)) ?>_" size="25" required pattern="[a-zA-Z0-9_]+"></td>
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
<div class="spinner"></div>

<script src="//code.jquery.com/jquery-1.8.3.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery-serialize-object/2.0.0/jquery.serialize-object.compiled.js"></script>
<script type="text/javascript">
var wp_install = new function() {
	
	this.data = <?php echo $this->config_json ?>;
	this.data.step = "first";
	
	this.$step = $();
	
	this.submit = function() {
		
		$("#error").stop().fadeOut(0);
		
		var formData = this.$step.find("form").serializeObject();
		$.extend(this.data, formData);
		
		this.$step.find(":input").prop("disabled", true);
		
		$(".spinner").stop().fadeOut(0).fadeIn(3000);
		$.post("", { "data": this.data }).always($.proxy(this.submitted, this));
		
		return false;
	}
	
	this.submitted = function(ajax, status) {
		
		$(".spinner").hide();
		
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
		this.$step.fadeIn(200);
		
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
