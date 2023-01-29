<?php

define('DOVE', 1);
require_once 'vendor/vzool/dove.php/dove.php';

$dove = new Dove('abdelaziz');
$time = $dove->Push('Salam, World!');
$message = $dove->Read($time); # `$time` act like id

$times = $dove->Pull($time); # all times of messages after `$time`
$times = $dove->Pull(); # all times of messages

$dove->Delete($time); # delete one message
$dove->Delete(); # delete all messages
?>