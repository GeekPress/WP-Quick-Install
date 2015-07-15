<?php
/*
Script Name: WP Quick Install
Author: Jonathan Buttigieg, Pravdomil Toman
Contributors: Julio Potier
Script URI: http://wp-quick-install.com
Licence: GPLv3
*/

class wp_quick_install {
	
	const CACHE_PATH = "wp_quick_install_cache/";
	
	var $data = array();
	var $error = array();
	var $return = null;
	
	var $user_config = null;
	
	function __construct($user_config) { 
        
		// time limit
		@set_time_limit(120);
		
		// setup error reporting
		$this->error_report();
		
		// user config
		if(is_array($user_config)) $this->user_config = $user_config;
		
		// setup data array
		if($_POST["data"]) $this->data = json_decode($_POST["data"], true);
		
		// install dir
		$this->data["dir"] = __DIR__; // hardcored for now
		
		// decide function, only functions starting with underscore can be called
		if(($f = "_" . $_POST["func"]) && method_exists($this, $f))
		{
			$this->return = call_user_func( array($this, $f), $_POST["func_arg1"]);
		}
		
		// return ajax or page
		$is_ajax = $_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest";
		$is_ajax ? $this->ajax_send() : $this->page();
    }
	
	function ajax_send() {
		header("Content-type: application/json");
		$response = array("data" => $this->data, "error" => $this->error, "return" => $this->return);
		echo json_encode($response);
	}
	
	function error_report() {
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
		if (!(5111 & $errno)) return true; // http://www.bx.com.au/tools/ultimate-php-error-reporting-wizard
    	
		$msg = $str . " line " . $line;
		$msg = trim($msg);
		
		@header("Content-type: application/json");
		echo json_encode( array("error" => array($msg)) );
		exit;
		
		return true;
	}
	
	
	
	// AJAX FUNCTIONS
	
	function _dir_ok() {
		
		if (!is_writable($this->data["dir"])) {
			$this->error[] = "You don't have good permissions rights on " . basename($this->data["dir"]);
		}
	}
	
	function _no_installed_wp() {
		$config = file_exists($this->data["dir"] . '/wp-config.php');
		$admin = file_exists($this->data["dir"] . '/wp-admin');
		$content = file_exists($this->data["dir"] . '/wp-content');
		$includes = file_exists($this->data["dir"] . '/wp-includes');
		
		if ($config || $admin || $content || $includes) {
			$this->error[] = "WordPress seems installed, please clean the folder before continue.";
		}
	}
	
	function _db_test_ok() {
		
		try {
			$dsn = "mysql:host=" . $this->data["db"]["host"] . ";dbname=" . $this->data["db"]["name"] ;
			$pdo = new PDO($dsn, $this->data["db"]["user"], $this->data["db"]["pwd"]);
			
			$result = $pdo->query("SELECT 1 FROM " . $this->data["db"]["prefix"] . "users LIMIT 1");
			
			if($result) $this->error[] = "Table prefix already in use. Choose a different or clean up.";
		    
		}
		catch (Exception $e) {
			$this->error[] = "Error establishing database connection.";
		}
	}
	
	function _wp_zip_url() {
		$url = "http://api.wordpress.org/core/version-check/1.7/?locale=" . $this->data["config"]['lang'];
		
		$langs = json_decode(file_get_contents($url), true);
		
		if(!$langs["offers"][0]) {
			
			$this->error[] = "Language is not available.";
			return;
		}
		
		return $langs["offers"][0]["download"];
	}

	function _download_wp($url) {
		
		@mkdir( self::CACHE_PATH );
		
		$zip_path = self::CACHE_PATH . basename($url);
		
		file_put_contents($zip_path, file_get_contents($url));
		
		return $zip_path;
	}
	
	function _unzip($zipFile) {
		
		$zip = new ZipArchive;

		if ( $zip->open($zipFile) === false ) {
			
			$this->error[] = "Cannot unzip? " . basename($zipFile);
			return;
		}
		else
		{
			$this->removeDir($this->data["dir"] . "/wp-admin");
			$this->removeDir($this->data["dir"] . "/wp-content");
			$this->removeDir($this->data["dir"] . "/wp-includes");
			
			$zip->extractTo(".");
			$zip->close();
			
			$files = scandir("wordpress");
			$files = array_diff($files, array( ".", ".." ));

			foreach( $files as $file )
			{
				$from = "wordpress/" . $file;
				$to = $this->data["dir"] . "/" . $file;
				
				if($file == "index.php") $to .= ".orig";
				
				rename($from, $to);
			}

			$this->removeDir(self::CACHE_PATH);
			$this->removeDir("wordpress");
			
			unlink($this->data["dir"] . "/license.txt");
			unlink($this->data["dir"] . "/readme.html");
		}
	}
	
	function _wp_config() {
		
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
	}
	
	function _install() {
		
		define( 'WP_INSTALLING', true );
		
		$this->load_wp_core();
		
		$result = wp_install(
			$this->data["config"]["site_title"],
			$this->data["config"]["username"],
			$this->data["config"]["email"],
			(int) $this->data["config"]["blog_public"],
			"",
			$this->data["config"]["password"]
		);
		
		if(!$result['password']) {
			$this->error[] = $result['password_message'];
			return;
		}
	}
	
	function _more() {
		
		$this->load_wp_core();
		
		if($this->data["more"]["page_on_front"]) {
			update_option("show_on_front", "page");
			update_option("page_on_front", 2);
		}
		
		if($this->data["more"]["permalink_str"]) {
			$this->set_permalink_structure($this->data["more"]["permalink_str"]);
		}
		
		if($this->data["more"]["avatar"]) {
			update_option("avatar_default", $this->data["more"]["avatar"]);
		}
		
		if($this->data["more"]["no_default_content"]) $this->delete_default_content();
	}
	
	function _install_themes() {
		
		$this->load_wp_core();
		
		if($this->data["more"]["themes"]) {
			$this->wp_install_themes(explode("\n", $this->data["more"]["themes"]));
		}
	}
	
	function _install_plugins() {
		
		$this->load_wp_core();
		
		if($this->data["more"]["plugins"]) {
			$this->wp_install_plugins(explode("\n", $this->data["more"]["plugins"]));
		}
	}
	
	function _finish() {
		
		$this->load_wp_core();
		
		unlink($this->data["dir"] . "/index.php");
		unlink($this->data["dir"] . "/wp-quick-install.php");
		rename($this->data["dir"] . "/index.php.orig", $this->data["dir"] . "/index.php");
		
		return get_admin_url();
	}
	
	
	
	// SUPPORT
	
	function get_secret_keys() {
		
		$secret_keys = file_get_contents('https://api.wordpress.org/secret-key/1.1/salt/');
		
		if ( !$secret_keys ) {
			trigger_error("Cannot retrieve secret keys.", E_USER_ERROR);
			return;
		}
		
		$secret_keys = explode("\n", $secret_keys);
		foreach ( $secret_keys as $k => $v ) {
			$secret_keys[$k] = substr($v, 28, 64);
		}
		
		return $secret_keys;
	}
	
	function replace_const(&$config, $const, $value) {
		
		$const = addcslashes($const, '\'');
		$value = addcslashes($value, '\'');
		
		$uniqid = " " . uniqid() . " ";
		$config = preg_replace("/(define\('" . $const . "',\s*').+('\))/", '$1' . $uniqid . '$2', $config);
		$config = str_replace($uniqid, $value, $config);
	}
	
	function replace_var(&$config, $var, $value) {
		
		$var = addcslashes($var, '\'');
		$value = addcslashes($value, '\'');
		
		$uniqid = " " . uniqid() . " ";
		$config = preg_replace("/(\\\$" . $var . "\s*=\s*').+(')/", '$1' . $uniqid . '$2', $config);
		$config = str_replace($uniqid, $value, $config);
	}
	
	function load_wp_core() {
		require_once($this->data["dir"] . '/wp-load.php');
		require_once($this->data["dir"] . '/wp-admin/includes/upgrade.php');
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
	
	function set_permalink_structure($str) {
		global $wp_rewrite;
		
		$wp_rewrite->set_permalink_structure($str);
		save_mod_rewrite_rules();
	}
	
	function wp_install_plugins($array) {
		
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
	
	function wp_install_themes($array) {
		
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
	
	function removeDir($dir) {
		
		if(!is_dir($dir)) return;
		
		$files = array_diff(scandir($dir), array('.','..'));
		
		foreach ($files as $file) { 
			(is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file"); 
		}
		
		return rmdir($dir); 
	} 
	
	
	
	// PAGE
	
	function page() {
		
		?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8" />
		<title>WP Quick Install</title>
		<meta name="robots" content="noindex, nofollow">
		<link rel='stylesheet' href='//fonts.googleapis.com/css?family=Open+Sans%3A300italic%2C400italic%2C600italic%2C300%2C400%2C600&amp;subset=latin%2Clatin-ext'>
		<link rel='stylesheet' href='https://cdn.rawgit.com/Pravdomil/WP-Quick-Install/master/style.css'>
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
			<td><input name="db[user]" id="uname" type="text" size="25" required></td>
			<td>Your MySQL username</td>
		</tr>
		<tr>
			<th scope="row"><label for="pwd">Password</label></th>
			<td><input name="db[pwd]" id="pwd" type="text" size="25" autocomplete="off" required></td>
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
		<tr>
			<th scope="row"></th>
			<td><input type="submit" value="Submit" class="button button-large"></td>
		</tr>
	</table>
</form></div>

<div step="config"><form onsubmit="return wp_install.submit()">
	
	<table class="form-table">
		<tr>
			<th scope="row"><label for="lang">Select language</label></th>
			<td>
				<select name="config[lang]" id="lang">
					<option value="en_US">English (United States)</option>
<?php
$langs = json_decode(file_get_contents('http://api.wordpress.org/translations/core/1.0/'))->translations;

foreach ( $langs as $l ) {
	echo '<option value="' . $l->language . '">' . $l->native_name . '</option>';
}
?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="site_title">Site Title</label></th>
			<td><input name="config[site_title]" type="text" id="site_title" size="25" required></td>
		</tr>
		<tr>
			<th scope="row">
				<label for="username">Username</label>
				<p>For admin account. It can be also email adress.</p>
			</th>
			<td><input name="config[username]" type="text" id="username" size="25" required></td>
		</tr>
		<tr>
			<th scope="row">
				<label for="password">Password</label>
				<p>A password will be automatically generated for you if you leave this blank.</p>
			</th>
			<td>
				<input name="config[password]" type="password" id="password" size="25">
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="email">E-mail</label></th>
			<td><input name="config[email]" type="email" id="email" size="25" required/>
			<p>Double-check your email address before continuing.</p></td>
		</tr>
		<tr>
			<th scope="row">Privacy</th>
			<td colspan="2"><label>
				<input type="checkbox" name="config[blog_public]" value="1" checked>
				Allow search engines to index this site.
			</label></td>
		</tr>
		<tr>
			<th scope="row"></th>
			<td colspan="2"><input type="submit" value="Launch the site" class="button-primary button-large"></td>
		</tr>
	</table>
	
	
	<h2>More setup</h2>
	<table class="form-table">
		<tr>
			<th scope="row">Front page</th>
			<td><label>
				<input type="checkbox" name="more[page_on_front]" value="1">
				Static page
			</label></td>
		</tr>
		<tr>
			<th scope="row">
				<label for="permalink_str">Permalink structure</label>
				<p>For example /%postname%/</p>
			</th>
			<td>
				<input name="more[permalink_str]" type="text" id="permalink_str" size="25">
			</td>
		</tr>
		<tr>
			<th scope="row">Default avatar</th>
			<td><label>
				<input type="checkbox" name="more[avatar]" value="identicon">
				Identicon
			</label></td>
		</tr>
		<tr>
			<th scope="row">Default content</th>
			<td><label>
				<input type="checkbox" name="more[no_default_content]" value="1">
				Delete default themes and plugins
			</label></td>
		</tr>
		<tr>
			<th scope="row">
				<label for="themes">Themes</label>
				<p>Line separated, name or url, first will be activated.</p>
			</th>
			<td><textarea name="more[themes]" type="text" id="themes" size="25"></textarea></td>
		</tr>
		<tr>
			<th scope="row">
				<label for="plugins">Plugins</label>
				<p>Line separated, name or url, all will be activated.</p>
			</th>
			<td><textarea name="more[plugins]" type="text" id="plugins" size="25"></textarea></td>
		</tr>
	</table>
</div>

<div id="info"></div>

<div id="error">
	<p><span id="error_msg"></span> <small><a href="javascript:wp_install.submit()">try again</a></small></p>
</div>

<div style="height: 20px"><div class="spinner"></div></div>

	</body>
</html>

<script src="//code.jquery.com/jquery-1.8.3.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery-serialize-object/2.0.0/jquery.serialize-object.compiled.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/async/1.3.0/async.js"></script>

<script type="text/javascript">

$.cookie.json = true;

var wp_install = new function() {
	
	this.data = <?php echo json_encode($this->user_config) ?>;
	
	this.dataCookie = function(val) {
		
		// do not store dynamic variables in cookie
		if(typeof val === "object") {
			
			var clone = $.extend(true, {}, val);
			
			if(clone.hasOwnProperty("db")) delete clone.db.prefix;
			
			if(clone.hasOwnProperty("config"))
			{
				delete clone.config.site_title;
				delete clone.config.username;
				delete clone.config.password;
			}
			
			val = clone;
		}
		
		return $.cookie("wp_quick_install_data", val);
	}
	
	if(!this.data) {
		this.dataFromCookie = true;
		this.data = this.dataCookie();
		if(typeof this.data !== "object") this.data = {};
	}
	
	this.$info = $("#info");
	
	this.info = function(msg) {
		this.$info.text(msg);
		console.log(msg);
	}
	
	this.step_first = function() {
		if(this.data.skip_welcome) this.submit();
	}
	
	this.step_first_submit = function() {

		async.waterfall(
			[
				function(callback) {

					this.info("Checking for right permissions");
					this.phpFunc("dir_ok", callback);
				
				}.bind(this),
				function(arg1, callback) {
				
					this.info("Checking for installed wp");
					this.phpFunc("no_installed_wp", callback);
					
				}.bind(this),
			],
			function (err, result) {
				if(!err) this.next_step("db");
			}.bind(this)
		);
	}
	
	this.step_db = function() {
		if(this.data.submit_db) this.submit();
	}
	
	this.step_db_submit = function() {
		
		this.extendDataFromInputs();
		if(this.dataFromCookie) this.dataCookie(this.data);
		
		async.waterfall(
			[
				function(callback) {

					this.info("Checking db connection");
					this.phpFunc("db_test_ok", callback);
				
				}.bind(this),
			],
			function (err, result) {
				if(!err) this.next_step("config");
			}.bind(this)
		);
	}
	
	this.step_config = function() {
		if(this.data.submit_config) this.submit();
	}
	
	this.step_config_submit = function() {
		
		this.extendDataFromInputs();
		
		if(this.dataFromCookie) this.dataCookie(this.data);
		
		this.$step.hide();
		this.$info.show();
		
		async.waterfall([
			function(callback) {

				this.info("Getting zip url...");
				this.phpFunc("wp_zip_url", callback);
				
			}.bind(this),
			function(arg1, callback) {
				
				this.info("Downloading from " + arg1.return);
				this.phpFunc("download_wp", callback, arg1.return);
				
			}.bind(this),
			function(arg1, callback) {
				
				this.info("Unzipping...");
				this.phpFunc("unzip", callback, arg1.return);
				
			}.bind(this),
			function(arg1, callback) {
				
				this.info("Configuring...");
				this.phpFunc("wp_config", callback);
				
			}.bind(this),
			function(arg1, callback) {
				
				this.info("Installing...");
				this.phpFunc("install", callback);
				
			}.bind(this),
			function(arg1, callback) {
				
				this.info("Doing more config...");
				this.phpFunc("more", callback);
				
			}.bind(this),
			function(arg1, callback) {
				
				this.info("Installing plugins...");
				this.phpFunc("install_plugins", callback);
				
			}.bind(this),
			function(arg1, callback) {
				
				this.info("Installing themes...");
				this.phpFunc("install_themes", callback);
				
			}.bind(this),
			function(arg1, callback) {
				
				this.info("Finishing...");
				this.phpFunc("finish", callback);
				
			}.bind(this),
			function(arg1, callback) {
				
				this.info("Redirecting...");
				location.href = arg1.return;
				
			}.bind(this),
		]);
	}
	
	this.phpFunc = function(func, callback, arg) {
		
		var data = { "data": JSON.stringify(this.data), func: func, func_arg1: arg };
		
		var complete = function(ajax) {
			
			this.ajaxComplete();
			
			var json = $.parseJSON(ajax.responseText);
			
			if(typeof json !== "object")
			{
				this.ajaxError([ajax.responseText]);
			}
			else if(json.error && json.error.length)
			{
				this.ajaxError(json.error);
				callback.apply(this, [json.error, json]);
			}
			else
			{
				callback.apply(this, [null, json]);
			}
		};
		
		this.ajaxBegin();
		this.ajax(data).complete($.proxy(complete, this));	
	}
	
	this.ajaxBegin = function() {
		$("#error").stop().fadeOut(100);
		this.$step.find(":input").prop("disabled", true);
		$(".spinner").stop().fadeIn(3000);
	}
	
	this.ajaxComplete = function() {
		$(".spinner").stop().fadeOut(100);
		this.$step.find(":input").prop("disabled", false);
	}
	
	this.ajaxError = function(array) {
		$("#error_msg").html(array.join("<br>"));
		$("#error").stop().fadeTo(100, 1);
	}
	
	this.ajax = function(data) {
		return $.ajax({ type: "POST", data: data });
	}
	
	this.next_step = function(name) {
		if(this.$step) {
			this.$step.hide();
		}
		
		this.data.step = name;
		
		this.$step = $("*[step=" + this.data.step + "]");
		
		populateForm(this.$step, this.data);
		
		this.$step.fadeIn(200);
		
		this["step_" + this.data.step]();
	}
	
	this.submit = function() {
		
		this["step_" + this.data.step + "_submit"]();
		
		return false;
	}
	
	this.extendDataFromInputs = function() {
		var formData = this.$step.find("form").serializeObject();
		$.extend(this.data, formData);
	}
	
	this.next_step("first");
	
}

function populateForm(frm, data) {
	
	data = flat_obj(data);
	  
	$.each(data, function(key, value) {
		
		var $ctrl = $('[name="'+key+'"]', frm); 
		
		if($ctrl.is('select')) {
			$("option", $ctrl).each(function() {
				this.selected = (this.value == value);
			});
		}
		else if ($ctrl.is('textarea')) {
            $ctrl.val(value);
        } 
		else switch($ctrl.attr("type"))  
		{  
			case "radio":
			case "checkbox":   
				$ctrl.each(function() {
					$(this).attr("checked", $(this).attr('value') == value);
				});
				break;
			default:
				$ctrl.val(value);
		} 
	});
}

function flat_obj(obj, ret, parent){

	if(!ret) ret = {};
	if(!parent) parent = '';

	if(typeof obj == 'object') for(var key in obj)
	{
		var child = !parent ? key : parent + '[' + key + ']';
		ret = arguments.callee(obj[key], ret, child);
	}
	else
	{
		ret[parent] = obj;
	}
	
	return ret;
}

</script>
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
new wp_quick_install($user_config);
