<?php

require_once '../vendor/autoload.php';

/**
 * Created by PhpStorm.
 * User: Azathoth
 * Date: 2017-05-25
 * Time: 18:58
 */
class WordpressService
{


	private function randomString($length = 10) {
		$characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$string = '';
		$max = strlen($characters) - 1;
		for ($i = 0; $i < $length; $i++) {
			$string .= $characters[mt_rand(0, $max)];
		}
		return $string;
	}

	public function installWordpress($dbName, $dbUserName, $dbPassword, $dbHost, $websiteTitle, $userLogin, $adminPassword, $adminEmail)
	{
		$data = parse_ini_file('data.ini');
		$data['dbname'] = $dbName;
		$data['uname'] = $dbUserName;
		$data['pwd'] = $dbPassword;
		$data['dbhost'] = $dbHost;
		$data['prefix'] = $data['db']['prefix'];
		$data['default_content'] = $data['db']['default_content'];
		$data['language'] = 'en_US';
		$data['directory'] = '';
		$data['admin']['user_login'] = $userLogin;
		$data['user_login'] = $data['admin']['user_login'];
		$data['admin']['password'] = 'demo';
		$data['admin']['email'] = 'demo@example.com';
		$data['weblog_title'] = $websiteTitle;
		$data['admin_password'] = $adminPassword;
		$data['admin_email'] = $adminEmail;
		$data['blog_public'] = 1;
		$data['activate_theme'] = 1;
		$data['plugins'] = 'tiled-gallery-carousel-without-jetpack;wordfence;wp-super-cache;google-calendar-events;easy-facebook-feed';
		$data['activate_plugins'] = 1;
		$data['permalink_structure'] = '/%postname%/';
		$data['thumbnail_size_w'] = 0;
		$data['thumbnail_size_h'] = 0;
		$data['thumbnail_crop'] = 1;
		$data['medium_size_w'] = 0;
		$data['medium_size_h'] = 0;
		$data['large_size_w'] = 0;
		$data['large_size_h'] = 0;
		$data['upload_dir'] = '';
		$data['uploads_use_yearmonth_folders'] = 1;
		$data['post_revisions'] = 0;
		$data['disallow_file_edit'] = 1;
		$data['autosave_interval'] = 7200;
		$data['wpcom_api_key'] = '';

		$installAddress = 'http://localhost/wp-quick-install';

		$client = new \GuzzleHttp\Client([
			// Base URI is used with relative requests
			'base_uri' => $installAddress,
			// You can set any number of default request options.
			'timeout'  => 120.0,
		]);

		$postData = [
			'form_params' => $data
		];
		$response = $client->request('POST', $installAddress . '/wp-quick-install/index.php?action=check_before_upload', $postData);
		if ($response->getStatusCode() >= 300) {
			echo $response->getBody()->getContents();
		}
		$response = $client->request('POST', $installAddress . '/wp-quick-install/index.php?action=download_wp', $postData);
		if ($response->getStatusCode() >= 300) {
			echo $response->getBody()->getContents();
		}
		$response = $client->request('POST',$installAddress . '/wp-quick-install/index.php?action=unzip_wp', $postData);
		if ($response->getStatusCode() >= 300) {
			echo $response->getBody()->getContents();
		}
		$response = $client->request('POST',$installAddress . '/wp-quick-install/index.php?action=wp_config', $postData);
		if ($response->getStatusCode() >= 300) {
			echo $response->getBody()->getContents();
		}
		$response = $client->request('POST',$installAddress . '/wp-admin/install.php?action=install_wp', $postData);
		if ($response->getStatusCode() >= 300) {
			echo $response->getBody()->getContents();
		}
		$response = $client->request('POST',$installAddress . '/wp-admin/install.php?action=install_theme', $postData);
		if ($response->getStatusCode() >= 300) {
			echo $response->getBody()->getContents();
		}
		$response = $client->request('POST',$installAddress . '/wp-quick-install/index.php?action=install_plugins', $postData);
		if ($response->getStatusCode() >= 300) {
			echo $response->getBody()->getContents();
		}
		$response = $client->request('POST',$installAddress . '/wp-quick-install/index.php?action=success', $postData);
		if ($response->getStatusCode() >= 300) {
			echo $response->getBody()->getContents();
		}
	}
}

/*
dbname:wordpress
uname:root
pwd:
dbhost:localhost
prefix:wp_
default_content:1
language:en_US
directory:
weblog_title:my wordpress
user_login:admin
admin_password:admin
admin_email:admin@admin.cz
blog_public:1
activate_theme:1
plugins:tiled-gallery-carousel-without-jetpack;wordfence;wp-super-cache;google-calendar-events;easy-facebook-feed
activate_plugins:1
permalink_structure:/%postname%/
thumbnail_size_w:0
thumbnail_size_h:0
thumbnail_crop:1
medium_size_w:0
medium_size_h:0
large_size_w:0
large_size_h:0
upload_dir:
uploads_use_yearmonth_folders:1
post_revisions:0
disallow_file_edit:1
autosave_interval:7200
wpcom_api_key:
*/