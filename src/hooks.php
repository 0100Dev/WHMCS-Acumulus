<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

include_once dirname(__FILE__) . '/includes/general.php';

function acumulus_add_invoice($vars)
{
    $vars = array_merge($vars, getAddonVars());

    $dataQuery = mysql_query('SELECT tblclients.*, tblinvoices.*, tblpaymentgateways.value AS acumulusBank FROM tblinvoices LEFT JOIN tblclients ON tblclients.id = tblinvoices.userid LEFT JOIN tblpaymentgateways ON tblpaymentgateways.gateway = tblinvoices.paymentmethod AND tblpaymentgateways.setting = "acumulusAccount" WHERE tblinvoices.id = ' . $vars['invoiceid']);

    if (mysql_num_rows($dataQuery) != 1) {
        logActivity('Acumulus Add Invoice: received ' . mysql_num_rows($dataQuery) . ' invoices for ID ' . $vars['invoiceid']);
        return;
    }

    $dataFetch = mysql_fetch_assoc($dataQuery);

    if ($dataFetch['total'] == '0.00' || $dataFetch['total'] == 0) { //Invoice fully paid using credit or no items
        return;
    }

    $customerId = updateCustomer($vars, $dataFetch['userid']);

    $api = new api($vars['code'], $vars['username'], $vars['password']);
    $api->setCategory('invoices')->setAction('invoice_add');

    if (isset($vars['debug']) && $vars['debug'] == 'on') {
        $api->enableDebug($vars['debug_email']);
    }

    $taxRate = round($dataFetch['taxrate']);

    if ($dataFetch['country'] == 'NL') { // All Dutch customers needs to get vatType 1 and the defined taxRate
        $vatType = 1;
    } else {
        if (inEurope($dataFetch['country'])) { //If in Europe, then
            if (!empty($dataFetch['companyname'])) { // Check if customer IS a company
                $vatType = 3;
                $taxRate = -1;
            } else {
                $vatType = 6;
            }
        } else { // If not in Europe, then defaults to outside EU
            $vatType = 4;
            $taxRate = -1;
        }
    }

    $api->setParams(array(
        'customer' => array(
            'contactid' => $customerId,
            'countrycode' => $dataFetch['country'],
            'invoice' => array(
                'concept' => 0,
                'number' => ((empty($dataFetch['invoicenum'])) ? $dataFetch['id'] : $dataFetch['invoicenum']),
                'vattype' => $vatType,
                'issuedate' => $dataFetch['date'],
                'paymentstatus' => 2,
                'paymentdate' => @date('Y-m-d', @strtotime($dataFetch['datepaid'])),
                'accountnumber' => $dataFetch['acumulusBank'],
                'costcenter' => $vars['cost_center'],
                'template' => $vars['invoice_template']
            )
        )
    ));

    $btwCheckQuery = mysql_query('SELECT SUM(amount) as amount FROM tblinvoiceitems WHERE invoiceid = ' . $dataFetch['id']);
    $btwCheckFetch = mysql_fetch_assoc($btwCheckQuery);

    $removeVat = false;
    if ($btwCheckFetch['amount'] != $dataFetch['subtotal']) {
        $removeVat = true;
    }

    $invoiceLinesQuery = mysql_query('SELECT * FROM tblinvoiceitems WHERE invoiceid = ' . $dataFetch['id']);

    while ($invoiceLinesFetch = mysql_fetch_assoc($invoiceLinesQuery)) {
        $api->setParams(array(
            'customer' => array(
                'invoice' => array(
                    'line_' . $invoiceLinesFetch['id'] => array(
                        'product' => $invoiceLinesFetch['description'],
                        'unitprice' => (($removeVat) ? $invoiceLinesFetch['amount'] / 1.21 : $invoiceLinesFetch['amount']),
                        'vatrate' => $taxRate,
                        'quantity' => 1
                    )
                )
            )
        ));
    }

    if ($dataFetch['credit'] != '0.00') {
        $vatrate = 0;

        if ($taxRate != -1) {
            $vatrate = $taxRate;
        }

        $price = $dataFetch['credit'] / 1.21;

        $api->setParams(array(
            'customer' => array(
                'invoice' => array(
                    'line_credit' => array(
                        'product' => 'Betaald d.m.v. credit (€ ' . $dataFetch['credit'] . ')',
                        'unitprice' => '-' . $price,
                        'vatrate' => $vatrate,
                        'quantity' => 1
                    )
                )
            )
        ));
    }

    $api->execute();

    $response = $api->getResponse();

    if ($api->hasErrors()) {
        $errors = '';

        foreach ($api->getErrors() as $error) {
            $errors = $error['code'] . ' - ' . $error['message'] . ', ';
        }

        logActivity('Acumulus API error(s): ' . substr($errors, 0, -2));
    } else {
        mysql_query('UPDATE tblinvoices SET acumulusid = ' . $response['invoice']['entryid'] . ' WHERE id = ' . $vars['invoiceid'] . ' LIMIT 1');
    }
}

function acumulus_inject_gateway($vars)
{
    $vars = array_merge($vars, getAddonVars());

    if ($vars['filename'] != 'configgateways') {
        return;
    }

    $api = new api($vars['code'], $vars['username'], $vars['password']);
    $api->setCategory('picklists')->setAction('picklist_accounts');
    $api->execute();
    $response = $api->getResponse();

    if ($api->hasErrors()) {
        $response = array();
        $response['accounts'] = array();
        $response['accounts']['account'] = array();
        $response['accounts']['account'][] = array(
            'accountid' => '',
            'accountnumber' => 'API errors',
            'accountdescription' => null
        );

        foreach ($api->getErrors() as $error) {
            $response['accounts']['account'][] = array(
                'accountid' => '',
                'accountnumber' => $error['code'],
                'accountdescription' => $error['message']
            );
        }
    }

    $inject = '<script>';

    $gatewayQuery = mysql_query('SELECT name.gateway, acumulus_account.value AS acumulus_account FROM tblpaymentgateways AS name LEFT JOIN tblpaymentgateways AS acumulus_account ON acumulus_account.setting = "acumulusAccount" AND acumulus_account.gateway = name.gateway WHERE name.setting = "name"');

    while ($gatewayFetch = mysql_fetch_assoc($gatewayQuery)) {
        $inject .= '$(\'#Payment-Gateway-Config-' . $gatewayFetch['gateway'] . '\').find(\'tr:last\').before(\'<tr><td class="fieldlabel">Acumulus rekening</td><td class="fieldarea"><select name="field[acumulusAccount]">';

        foreach ($response['accounts']['account'] as $action) {
            $inject .= '<option value="' . $action['accountid'] . '" ' . (($gatewayFetch['acumulus_account'] == $action['accountid']) ? 'selected="selected"' : '') . '>' . $action['accountnumber'] . '' . ((is_string($action['accountdescription'])) ? ' - ' . $action['accountdescription'] : '') . '</option>';
        }

        $inject .= '</select>Voeg alle facturen met deze betaalmethode toe aan een bepaalde rekening, <a href="https://wiki.acumulus.nl/index.php?page=payment-service-provider" target="_blank">zie hier waarom</a>.</td></tr>\');';
    }

    $inject .= '</script>';

    return $inject;
}

function acumulus_update_client($vars)
{
    $vars = array_merge($vars, getAddonVars());

    updateCustomer($vars, $vars['userid']);
}

function acumulus_invoice_controls($vars)
{
    if (isset($_GET['acumulus_sync'])) {

        acumulus_add_invoice(array('invoiceid' => $vars['invoiceid']));

        header('location: invoices.php?action=edit&id=' . $vars['invoiceid']);
        exit();
    }

    $output = '';
    $output .= '<br/><img src="images/spacer.gif" width="1" height="5"><br/>';
    $output .= '<input type="button" value="Sync with Acumulus" onclick="window.location = \'invoices.php?action=edit&id=' . $vars['invoiceid'] . '&acumulus_sync\';" class="btn-success" />';
    return $output;
}

add_hook('InvoicePaid', 99, 'acumulus_add_invoice');
add_hook('AdminAreaPage', 99, 'acumulus_save_gateway');
add_hook('AdminAreaFooterOutput', 99, 'acumulus_inject_gateway');
add_hook('ClientAdd', 99, 'acumulus_update_client');
add_hook('ClientEdit', 99, 'acumulus_update_client');
add_hook('AdminInvoicesControlsOutput', 99, 'acumulus_invoice_controls');