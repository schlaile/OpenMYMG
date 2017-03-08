<?php

abstract class OpenMYMG_Download {
	abstract public function get_api_version();

	static public function get_instance($requested_api_version) {
		return OpenMYMG_Download_get_instance($requested_api_version);
	}
	static public function register($class) {
		return OpenMYMG_Download_register($class);	
	}
}

abstract class OpenMYMG_Download_V1 extends OpenMYMG_Download {
	public function get_api_version() { return 1.0; }

	public function begin($division, $subdivision, $changed_since) {
		/* prepare sql query and startup the engines */
	}

	/* Fetch one row from the database (or prepared file) and return.
	   On End Of Data return false */

	abstract public function fetchrow();

	public function cleanup() {
		/* insert cleanup code here (if needed) */
	}
}

abstract class OpenMYMG_Order {
	abstract public function get_api_version();

	static public function get_instance($requested_api_version) {
		return OpenMYMG_Order_get_instance($requested_api_version);
	}
	static public function register($class) {
		return OpenMYMG_Order_register($class);	
	}
}

/**
 * 
 * 
 * @service OrderV1
 */

abstract class OpenMYMG_Order_V1 extends OpenMYMG_Order {
	public function get_api_version() { return 1.0; }

	abstract public function begin();

	abstract public function set_shipping_address();
	abstract public function set_billing_address();

	abstract public function set_amount($orderid, $articleid, $amount);

	abstract public function commit($orderid);
}

abstract class OpenMYMG_Article {
	abstract public function get_api_version();

	static public function get_instance($requested_api_version) {
		return OpenMYMG_Article_get_instance($requested_api_version);
	}
	static public function register($class) {
		return OpenMYMG_Article_register($class);	
	}
}

/**
 * 
 * 
 * @service ArticleV1
 */

abstract class OpenMYMG_Article_V1 extends OpenMYMG_Article {
	public function get_api_version() { return 1.0; }

	/**
         * Article Status
	 *
         * @param  string article-number
         * @return ArticleStatus The object
         */
        abstract public function get_status($articleid);

}


?>