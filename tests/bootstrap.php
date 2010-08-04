<?php

require_once '../library/SplClassLoader.php';
$respectLoader = new \SplClassLoader();
$respectLoader->setIncludePath('../library/');
$respectLoader->register();