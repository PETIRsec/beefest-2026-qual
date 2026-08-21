<?php
header("HTTP/1.1 200 OK");
header("Content-Security-Policy: default-src 'self' 'unsafe-eval'; style-src 'unsafe-inline'");
header("Content-Type: ".$_SERVER['HTTP_ACCEPT']);
if(isset($_POST['path'])){
    @file_get_contents($_POST['path']);
    die();
}
else if (isset($_GET['message'])){
    die($_GET['message']);
}
