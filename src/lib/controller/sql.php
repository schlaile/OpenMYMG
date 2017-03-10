<?php

class sql_controller extends controller {
	function sql_controller($path, $query) {
		$this->query = $query;
		$this->set_path($path);
		
	}
	static function create($path, $query) {
		return controller::register(
			new sql_controller($path, $query));
	}

	function exec($version, $customer, $path, $params) {

		$sth = sql_controller::$dbh->prepare($this->query);
		
		$params["customer"] = $customer;

		foreach ($params as $k => $v) {
			$sth->bindParam(":" . $k, $v);
		}

		try {
			$sth->execute();
		} catch (PDOException $e) {
			header("HTTP/1.1 500 Internal server error");
			print "Query failed!";
			return true;
		}
		
		$first = true;

		while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
			if ($first) {
				send_begin(array_keys($row));
				$first = false;
			}

			send_row(array_values($row));
		}
		if (!$first) {
			send_end();
		}
	}

	static function connect(
		$dsn, $username, $password, 
		$options = array(PDO::ATTR_PERSISTENT => true)) {
		sql_controller::$dbh = new PDO(
			$dsn, $username, $password, $options);
	}

	private var $query;
	private static var $dbh;
}

?>