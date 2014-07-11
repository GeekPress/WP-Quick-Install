WP Quick Install 1.3.1
================

WP Quick Install is the easiest way to install WordPress.

A lightweight script which automatically downloads and install WordPress, plugins and themes you want.

Simply download the .zip archive et go to *wp-quick-install/index.php*

Changelog
================

1.3.2
-----------

* Add a script header
* Security improvement

1.3.1
-----------

* Fix error for PHP > 5.5: Strict standards: Only variables should be passed by reference in ../wp-quick-install/index.php on line 10

1.3
-----------

* Possiblity to select WordPress language installation
* Permaling management


1.2.8.1
-----------

* You can now declare articles to be generated via data.ini file
* Fix bug on new articles
* You can now select the revision by articles

1.2.8
-----------

* Media management

1.2.7.2
-----------

* Security : Forbiden access to data.ini from the browser

1.2.7.1
-----------

* noindex nofollow tag.

1.2.7
-----------

* Premium extension by adding archives in plugins folder
* You can enable extension after installation
* Auto supression of Hello Dolly extension
* You can add a theme and enable it
* PYou can delete Twenty Elever and Twenty Ten

1.2.6
-----------

* Fix a JS bug with data.ini

1.2.5
-----------

* You can delete the default content added by WordPress
* You can add new pages with data.ini
* Data.ini update

1.2.4
-----------

* Two new debug options : *Display errors* (WP_DEBUG_DISPLAY) and *Write errors in a log file* (WP_DEBUG_LOG)

1.2.3
-----------

* SEO Fix bug
* Automatic deletion of licence.txt and readme.html

1.2.2
-----------

* Deletion of all exec() fucntions
* Unzip WordPress and plugins with ZipArchive class
* Using scandir() and rename() to move WordPress files

1.2.1
-----------

* Checking chmod on parent folder
* Adding a link to website and admin if success

1.2
-----------

* You can now pre-configure the form with data.ini


1.1
-----------

* Code Optimisation


1.0
-----------

* Initial Commit
