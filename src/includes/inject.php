<?php
/**
 * A bunch of code to inject in every page load
 */

if (substr($_SERVER['SCRIPT_NAME'], -18) == 'configgateways.php') {

    if (isset($_GET['action']) && $_GET['action'] == 'save') {

        if (isset($_POST['field']['acumulusAccount'])) {
            $gateway = mysql_real_escape_string($_POST['module']);
            $acumulusAccount = mysql_real_escape_string($_POST['field']['acumulusAccount']);

            $result = mysql_query('SELECT gateway FROM tblpaymentgateways WHERE gateway = "' . $gateway . '" AND setting = "acumulusAccount" LIMIT 1');

            if (mysql_num_rows($result) == 0) {
                mysql_query('INSERT INTO tblpaymentgateways (gateway, setting, value) VALUES ("' . $gateway . '", "acumulusAccount", "' . $acumulusAccount . '")');
            } else {
                mysql_query('UPDATE tblpaymentgateways SET value = "' . $acumulusAccount . '" WHERE gateway = "' . $gateway . '" AND setting = "acumulusAccount" LIMIT 1');
            }
        }
    }
}

//// SELECT * FROM tblinvoices WHERE datepaid >= '2014-01-01' AND status = 'Paid'
////
//if (isset($_GET['TESTINVOICE'])) {
////    $invoiceQuery = mysql_query('SELECT * FROM tblinvoices WHERE datepaid > "2014-01-01" AND status = "Paid" AND acumulusid IS NULL') or die(mysql_error());
////    $invoiceQuery = mysql_query('SELECT * FROM tblinvoices WHERE datepaid > "2014-01-01" AND status = "Paid" AND taxrate = 0.00') or die(mysql_error());
//    $invoiceQuery = mysql_query('SELECT * FROM tblinvoices WHERE datepaid >= "2014-01-01" AND status = "Paid" AND acumulusid IS NULL') or die(mysql_error());
//
//    while ($invoiceFetch = mysql_fetch_assoc($invoiceQuery)) {
//        echo $invoiceFetch['id'].PHP_EOL;
//        acumulus_add_invoice(array('invoiceid' => $invoiceFetch['id']));
//    }
//}