<?php
require_once __DIR__ . '/lib/core.php';
portal_session();
portal_session_destroy();
portal_redirect('login.php');
