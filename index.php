<?php
/*

Config property will be taken from:
1. $user_config variable
	if specified
2. then from browser cookie
	which is saved after each succesfull form submit
	admin username, admin password, db prefix and site_title is not saved into cookie
	cookie is set for all domain
3. otherwise from installer defaults
	specified in html

For auto installer: set skip_welcome, submit_db, submit_config to true

*/

$user_config = array(
	
	"skip_welcome" => false,
	
	"db" => array(
		//"name" => "wordpress",
		//"user" => "",
		//"pwd" => "",
		//"host" => "localhost",
		//"prefix" => "wp_",
	),
	"submit_db" => false,
	
	"config" => array(
		//"lang" => "",
		//"site_title" => "",
		//"username" => "",
		//"password" => "",
		//"email" => "",
		//"blog_public" => true,
	),
	"more" => array(
		"page_on_front" => true,
		"permalink_str" => "/%postname%/",
		"avatar" => "identicon",
		"no_default_content" => true,
		"themes" => "",
		"plugins" => "",
	),
	"submit_config" => false,
);


$file = "wp-quick-install.php";
$url = "https://cdn.rawgit.com/Pravdomil/WP-Quick-Install/master/wp-quick-install.php";

if(!file_exists($file))
{
	if(!is_writable(__DIR__)) die("Folder does not have sufficient write permissions.");
	file_put_contents($file, file_get_contents($url));
}
include $file;
