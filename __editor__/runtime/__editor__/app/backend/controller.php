<?php

// 

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (version_compare(phpversion(), '5.4.0', '<')) {
    die('[PHP_NOT_ERROR]{"message": "PHP 5.4.0 or greater only"}[PHP_NOT_ERROR]');
}

include 'action.php';

//