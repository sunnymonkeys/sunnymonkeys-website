<?php
session_start();
session_destroy();
header('Location: https://sunnymonkeys.com/portal/login.php');
exit;
