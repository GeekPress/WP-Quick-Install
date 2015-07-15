<?php

// config can be stored in $user_config or in browser cookie
// uncomment $user_config for use as predefined installer

/*
$user_config = array(
	
	//"skip_welcome" => true,
	
	"db" => array(
		"name" => "wordpress",
		"user" => "",
		"pwd" => "",
		"host" => "localhost",
		//"prefix" => "wp_",
	),
	//"submit_db" => true,
	
	"config" => array(
		"lang" => "",
		"site_title" => "",
		"username" => "",
		"password" => "",
		"email" => "",
		"blog_public" => true,
	),
	"more" => array(
		"page_on_front" => true,
		"permalink_str" => "/%postname%/",
		"avatar" => "identicon",
		"no_default_content" => true,
		"themes" => "",
		"plugins" => "",
	),
	//"submit_config" => true,
);
*/

$file = "wp-quick-install.php";
$url = "https://cdn.rawgit.com/Pravdomil/WP-Quick-Install/master/wp-quick-install.php";

if(!file_exists($file))
{
	if(!is_writable(__DIR__)) die("Folder does not have sufficient write permissions.");
	file_put_contents($file, file_get_contents($url));
}
include $file;
