#!/usr/bin/env php
<?php

define('DOVE', 1);
require_once 'dove.php';

foreach(array('vzool', 'omar', 'ali') as $client){

    $dove = new Dove($client);

    for($i = 0; $i < 10; $i++) $dove->Push('Salam-' . $i . '!');
}
?>