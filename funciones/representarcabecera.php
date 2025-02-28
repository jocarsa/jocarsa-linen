<?php

	function renderHeader($pageTitle) {
    echo "<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <title>" . htmlspecialchars($pageTitle) . " - jocarsa | linen</title>
  <link rel='stylesheet' href='estilo/style.css'>
  <link rel='icon' type='image/svg+xml' href='linen.png' />
</head>
<body>
<header><img src='linen.png'>jocarsa | linen</header>
<div class='container'>
";
}

?>
