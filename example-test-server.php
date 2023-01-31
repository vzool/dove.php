<?php

define('DOVE', 1);
require_once 'dove.php';

Dove::Serve(true, 1, Dove::INTEGRITY_ALL);
?>