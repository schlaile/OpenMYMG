<?php

include_once("config.php");
include_once($LIB . "/controller.php");
include_once($LIB . "/feature.php");
include_once($IMPL . "/auth.php");
include_once($IMPL . "/endpoints.php");

if (!controller::run(
	    isset($_SERVER["PATH_INFO"]) ? $_SERVER["PATH_INFO"] : "", 
            $_REQUEST)) {
	header("HTTP/1.1 404 Not found");
	print "No such endpoint!";
}

?>