<?php

$stdout = fopen('php://stdout', 'w');

function send_row($row)
{
	global $stdout;

	fputcsv($stdout, $row);
}

function send_pdf($pdf)
{
	header("Content-Type: application/pdf");

	print $pdf;
}

?>