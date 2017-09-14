<?php
/**
 * Created by PhpStorm.
 * User: Azathoth
 * Date: 2017-05-25
 * Time: 21:08
 */

require_once 'WordpressService.php';

$time_pre = microtime(true);

$service = new WordpressService();
$service->installWordpress('wordpress', 'root', '', 'localhost', 'my wordpress', 'admin', 'admin', 'admin@admin.cz');

$time_post = microtime(true);

$exec_time = $time_post - $time_pre;
print($exec_time);