<?php

require_once "handler.php";
$request = $_SERVER['REQUEST_URI'];
$msg = new Handler();
switch ($request) {
    case '/webinar-spe-bot/index.php/cek' :   
        $msg->getUpdates();
        break;
}