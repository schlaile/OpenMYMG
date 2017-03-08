<?php

controller::set_default_max_version(1);

// ----------------------------------------------------------------------

feature::enable("article/status");

sql_controller::register(
	"status/article/(.*)",
	"SELECT ... WHERE artikelnr = :capture ");

sql_controller::register(
	"status/article/index.csv",
	"SELECT ... ");

// ----------------------------------------------------------------------

feature::enable("invoices/pdf");

sql_controller::register(
	"invoices/index.csv",
	
	);

func_controller::register(
	"invoices/(.*).pdf",
	function ($version, $customer, $path, $params) {
		$invoiceno = $params["capture"];

		// retrieve pdf-file

		send_pdf($pdf);
	});

// ----------------------------------------------------------------------

feature::enable("invoices/csv");

// ----------------------------------------------------------------------

feature::enable("new/order/csv");

func_controller::register(
	"new/order",
	function ($version, $customer, $path, $params) {


	});

?>