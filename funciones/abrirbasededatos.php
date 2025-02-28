<?php

	function getDB() {
    $db = new PDO('sqlite:../databases/linen.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON;');
    return $db;
}

?>
