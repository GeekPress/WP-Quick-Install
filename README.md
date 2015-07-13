WP Quick Install
================

The easiest way to install WordPress.

A lightweight script which automatically downloads and install WordPress, plugins and themes.


Instructions
================

1) Place index.php file in directory you want have WordPress installed (eg. /wp)

2) Visit the folder from browser (eg. yoursite.com/wp)


Auto installer
================

Open and edit index.php to create automatic installer. Follow instructions in file.

Config is in PHP format, eg:
```
$user_config = array(
    "auto_installer" => false,
    "db" => array(
        "name" => "wordpress",
        "user" => "",
        "pwd" => "",
        "host" => "localhost",
        "prefix" => "wp_",
    ),
);
```

Features
================

+ Setup database
+ Select language
+ Create users
+ Set basic site settings
+ Delete default plugins and templates
+ Download, install and activate plugins and themes
+ Set permalinks
+ Set static front page
+ Set default avatar
+ Create predefined user installer

Know issues
================

+ Installer does not work properly, more coding needed


Future
================

+ Merge config and more config form
