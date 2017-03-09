<?php

abstract class matcher {
	abstract function match($path);
	abstract function extract($path);
}

class regex_matcher extends matcher {
	function regex_matcher($pattern) {
		$this->pattern = $pattern;
	}
	function match($path) {
		return preg_match("|" . $this->pattern . "|", $path);
	}
	function extract($path) {
		$matches = array();
		preg_match("|" . $this->pattern . "|", $path, $matches);
		return $matches;
	}
	private var $pattern;
}

function regex($pattern)
{
	return new regex_matcher($pattern);
}


?>