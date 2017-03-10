<?php

class feature {
	static function enable($feature) {
		feature::$features[$feature] = "1";
	}
	static function disable($feature) {
		feature::$features[$feature] = "0";
	}

	static function set($key, $val) {
		feature::$features[$key] = $val;
	}

	static function get_features() {
		return feature::$features;
	}

	private static $features = array();
}

func_controller::create(
	"info.csv",
	function ($version, $customer, $path, $params) {
		send_begin(array("Key", "Value"));

		$f = feature::get_features();

		foreach ($f as $k => $v) {
			send_row(array($k, $v));
		}

		send_end();

	})->set_public(true);

?>