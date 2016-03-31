<?php
/*

For auto installer set:
skip_welcome, submit_db, submit_config = true

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
