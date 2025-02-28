<?php
	$action = (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) ? 'panel' : 'login';
?>
