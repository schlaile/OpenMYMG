<?php

include_once($LIB . "/controller/func.php");
include_once($LIB . "/controller/sql.php");

controller::set_default_max_version(1);
sql_controller::connect("mysql:host=localhost;dbname=test", "dbuser", "dbpass");

feature::enable("version/1");
feature::set("company", "Demo Company, 12345 Mars Avenue, Mars City");
feature::set("contact", "openmymg@demo-company.com");

// ----------------------------------------------------------------------

feature::enable("contact/index");

sql_controller::create(
	"contact/index.csv",
	"SELECT id, name, role, phone, email ".
	"FROM contacts, customer " .
	"WHERE contacts.area = customer.area ".
	"  AND customer.id   = :customer" );

sql_controller::create(
	"contact/index.csv",
	"SELECT id, name, role, phone, email ".
	"FROM contacts ".
	"WHERE public = 'Y'")->set_public(true);


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