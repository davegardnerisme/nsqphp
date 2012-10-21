<?php

// bootstrap autoloader
if (!class_exists('\SplClassLoader')) {
    include __DIR__ . '/dependencies/spl-class-loader/SplClassLoader.php';
}
$classLoader = new \SplClassLoader('nsqphp', __DIR__ . '/src/');
$classLoader->register();
