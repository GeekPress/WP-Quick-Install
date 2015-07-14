<?php

$user_config = array(
	'auto_installer' => false,
	'db' => array(
		//'name' => 'wordpress',
		//'user' => '',
		//'pwd' => '',
		//'host' => 'localhost',
		//'prefix' => 'wp_',
	),
	//'site_title' => 'My new site',
	'user' => array(
		//'name' => '',
		//'pwd' => '',
		//'email' => '',
	),
	//'blog_public' => '1',
	//'page_on_front' => '1',
	//'permalink_structure' => '/%postname%/',
	//'set_avatar' => '1',
	//'del_default' => '1',
	//'install_theme' => '',
	//'install_plugin' => '',
);

$file = "wp-quick-install.php";
$url = "https://cdn.rawgit.com/Pravdomil/WP-Quick-Install/master/wp-quick-install.php";
if(!file_exists($file)) file_put_contents($file, file_get_contents($url));
include $file;
