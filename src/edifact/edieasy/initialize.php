<?php
// our path begins wherever index.php resides.
if (!defined("_PATH_")) {
  define("_PATH_",dirname(__FILE__)."\\");
}

// retrieve configuration
require_once(_PATH_."class\\EDIFACT.class.php");

// retrieve configuration
require_once(_PATH_."include\\EDIFACT_tools.php");

// start our session
// session_name(_SESSIONNAME_);
// session_start();

?>
