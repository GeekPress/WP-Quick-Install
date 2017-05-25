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

	public function installWordpress($dbName, $dbUserName, $dbPassword, $dbHost)
	{
		$data = parse_ini_file('data.ini');
		$data['dbname'] = $dbName;
		$data['uname'] = $dbUserName;
		$data['pwd'] = $dbPassword;
		$data['dbhost'] = $dbHost;
		$data['admin_password'] = $this->randomString(10);
		$data['language'] = 'en_US';

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
		$response = $client->post($installAddress . '/wp-quick-install/index.php?action=check_before_upload', $postData);
		$response = $client->post($installAddress . '/wp-quick-install/index.php?action=download_wp', $postData);
		$response = $client->post($installAddress . '/wp-quick-install/index.php?action=unzip_wp', $postData);
		$response = $client->post($installAddress . '/wp-quick-install/index.php?action=wp_config', $postData);
		$response = $client->post($installAddress . '/wp-admin/install.php?action=install_wp', $postData);
		$response = $client->post($installAddress . '/wp-admin/install.php?action=install_theme', $postData);
		$response = $client->post($installAddress . '/wp-quick-install/index.php?action=install_plugins', $postData);
		$response = $client->post($installAddress . '/wp-quick-install/index.php?action=success', $postData);
	}
}
