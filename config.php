<?php
$defaults = require __DIR__ . '/config.example.php';
$localFile = __DIR__ . '/config.local.php';
$local = is_file($localFile) ? require $localFile : array();

return array_merge($defaults, $local);
