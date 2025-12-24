<?php
ob_start();
include 'admin/dashboard_export.php';
file_put_contents('dashboard_test.xlsx', ob_get_clean());
?>
