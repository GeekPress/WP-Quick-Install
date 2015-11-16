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


$user_config = <<<EOD
{
    //"skip_welcome": false,
	
    "db":{  
        //"name": "wordpress",
        //"user": "",
        //"pwd": "",
        //"host": "localhost",
        //"prefix": "wp_"
    },
    //"submit_db": false,
	
    "config":{  
        //"lang": "",
        //"site_title": "",
        //"username": "",
        //"password": "",
        //"email": "",
        //"blog_public": true
    },
    "more":{  
        //"page_on_front": false,
        //"permalink_str": "",
        //"avatar": "",
        //"no_default_content": false,
        //"themes": "",
        //"plugins": ""
    }
    //,"submit_config": false
}
EOD;


$user_config = json_decode(preg_replace("/\s\/\/.*/", "", $user_config), true);

$file = "wp-quick-install.php";
$url = "https://cdn.rawgit.com/Pravdomil/WP-Quick-Install/master/wp-quick-install.php";

if(!file_exists($file))
{
	if(!is_writable(__DIR__)) die("Folder does not have sufficient write permissions.");
	file_put_contents($file, file_get_contents($url));
}
include $file;
