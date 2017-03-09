<?php

class func_controller extends controller {
	function func_controller($path, $func) {
		$this->func = $func;
		$this->set_path($path);
	}
	static function create($path, $func) {
		return controller::register(
			new func_controller($path, $func));
	}

	function exec($version, $customer, $path, $params) {
		$this->func($version, $customer, $path, $params);
	}

	private var $func;
}

?>