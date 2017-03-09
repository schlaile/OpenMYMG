<?php

include_once("io.php");
include_once("matcher.php");
include_once("pointer.php");

abstract class controller {
	static function run($path, $params = array()) {
		$path = explode("/", ltrim($path, "/"));

		if (sizeof($path) < 2) {
			return false;
		}

		$version = $path[0];
		$customer = $path[1];

		array_splice($path, 0, 2);

		if (strlen($version) < 2 || substr($version, 0, 1) != "v") {
			return false;
		}
		$version = (int) substr($version, 1);

		$c = false;

		foreach (controller::$all_controllers as $c) {
			if ($c->matches($customer, $path) &&
			    $c->matches_version($version)) {
				break;
			}
		}

		if ($c == false) {
			return false;
		}

		if (!$c->check_permission($customer, $path)) {
			header("HTTP/1.1 403 Permission denied");
			print "User and/or password incorrect!";
			return true;
		}
		
		$c->capture_params_from_path($path, $params);

		$c->exec($version, $customer, $path, $params);

		return true;
	}

	static function register($c) {
		$p = new pointer($c);
		controller::$all_controllers[] = $p;
		return $p;
	}

	static function root() {
		global $CONTROLLER;

		return $CONTROLLER;
	}

	function check_permission($customer, $path) {
		if (!isset($_SERVER['PHP_AUTH_USER'])) {
			header('WWW-Authenticate: Basic realm="OpenMYMG"');
			header('HTTP/1.0 401 Unauthorized');

			print 'Authentication failed';
			exit;
		}

		return check_auth($customer, $path,
				  $_SERVER['PHP_AUTH_USER'],
				  $_SERVER['PHP_AUTH_PW']);
	}

	function matches($customer, $path) {
		if (($customer == "public") xor $this->is_public()) {
			return false;
		}

		$m = $this->get_path();

		if (is_object($m)) {
			return $m->match($path);
		} else {
			return ($m == $path);
		}
	}

	function capture_params_from_path($path, &$params) {
		$m = $this->get_path();

		if (is_object($m)) {
			$capture = $m->extract($path);

			foreach ($capture as $k => $v) {
				if ($k == 0) $k = "";
				$params["capture" . $k] = $v;
			}
		}
	}

	abstract function exec($version, $customer, $path, $params);

	static function set_default_max_version($version) {
		controller::$default_max_version = $version;
	}

	function set_version($min_version, $max_version) {
		$this->min_version = $min_version;
		$this->max_version = $max_version;
	}

	function matches_version($version) {
		return ($this->get_min_version() <= $version) &&
			($this->get_max_version() >= $version);
	}

	function get_min_version() {
		return (isset($this->min_version) ? $this->min_version : 1);
	}
	function get_max_version() {
		return (isset($this->max_version) ? 
			$this->max_version : controller::$default_max_version);
	}

	function set_public($bool) {
		$this->public_area = $bool;
	}

	function is_public() {
		return (isset($this->public_area) ? $this->public_area : false);
	}

	function set_path($path) {
		$this->path = $path;
	}

	function get_path() {
		return $this->path;
	}

	private static var $default_max_version = 1;
	private var $min_version;
	private var $max_version;
	private var $public_area;
	private var $path;
	private static var $all_controllers = array();
}

?>