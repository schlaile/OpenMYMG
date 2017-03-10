<?php

$stdout = fopen('php://stdout', 'w');

function send_begin($columns)
{
	send_row($columns);
}

function send_row($row)
{
	global $stdout;

	fputcsv($stdout, $row);
}

function send_end()
{
	// do nothing
}

function send_pdf($pdf)
{
	header("Content-Type: application/pdf");

	print $pdf;
}

?>