<?php

class feature {
	static function enable($feature) {
		feature::$features[$feature] = true;
	}
	static function disable($feature) {
		feature::$features[$feature] = false;
	}

	static function get_features() {
		return feature::$features;
	}

	private static $features = array();
}

func_controller::create(
	"info.csv",
	function ($version, $customer, $path, $params) {
		send_row(array("key", "value"));

		$f = feature::get_features();

		foreach ($f as $k => $v) {
			send_row(array($k, $v ? "1" : "0"));
		}
	})->set_public(true);

?>