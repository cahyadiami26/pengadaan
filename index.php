<?php
/**
 * Entry point - langsung ke dashboard (SSO handle login).
 */
require_once __DIR__ . '/includes/bootstrap.php';
header('Location: public/dashboard.php');
exit;
