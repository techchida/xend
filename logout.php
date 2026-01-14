<?php
require_once 'config.php';
require_once 'Auth.php';

$auth = new Auth();
$auth->logout();

header('Location: login.php');
exit;
?>
