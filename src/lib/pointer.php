<?php

class pointer {
	var $child;

	function pointer(&$child) {
		$this->child = $child;
	}

	public function __call($name, $arguments) {
		return call_user_func_array(
			array($this->child, $name), $arguments);
	}

}

?>