<?php

$user_config = array (
    "auto_installer" => false,
    "db" => array(
        //"name" => "wordpress",
        //"user" => "",
        //"pwd" => "",
        //"host" => "localhost",
        //"prefix" => "wp_",
    ),
);

$file = "wp-quick-install.php";
$url = "https://cdn.rawgit.com/Pravdomil/WP-Quick-Install/master/wp-quick-install.php";
if(!file_exists($file)) file_put_contents($file, file_get_contents($url));
include $file;
