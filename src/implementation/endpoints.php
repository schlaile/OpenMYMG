<?php

include_once($LIB . "/controller/func.php");
include_once($LIB . "/controller/sql.php");

controller::set_default_max_version(1);

// ----------------------------------------------------------------------

feature::enable("status/article");

sql_controller::create(
	regex("status/article/(.*)"),
	"SELECT ... WHERE artikelnr = :capture ");

sql_controller::create(
	"status/article/index.csv",
	"SELECT ... ");

// ----------------------------------------------------------------------

feature::enable("invoices/index");
feature::enable("invoices/pdf");

sql_controller::create(
	"invoices/index.csv",
	"SELECT ... ");

func_controller::create(
	regex("invoices/(.*)\.pdf"),
	function ($version, $customer, $path, $params) {
		$invoiceno = $params["capture"];

		// retrieve pdf-file

		send_pdf($pdf);
	});

// ----------------------------------------------------------------------

feature::enable("invoices/csv");

// ----------------------------------------------------------------------

feature::enable("new/order/csv");

func_controller::create(
	"new/order",
	function ($version, $customer, $path, $params) {


	});

?>