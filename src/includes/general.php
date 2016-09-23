<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once dirname(__FILE__) . '/api.php';
require_once dirname(__FILE__) . '/inject.php';

/**
 * Updates a customer at Acumulus
 * @param $vars : $vars from the hook for API credentials
 * @param $clientid : clientid to update
 * @param null $customerid : customerid at Acumulus to update
 * @throws Exception
 * @return $customerid : new/updated customerid at Acumulus
 */
function updateCustomer($vars, $clientid, $customerid = null)
{
    global $whmcs;

    $whmcs->load_function('invoice');

    $clientQuery = mysql_query('SELECT tblclients.*, tblcustomfieldsvalues.value AS vatnumber FROM tblclients LEFT JOIN tblcustomfieldsvalues ON tblclients.id = tblcustomfieldsvalues.relid AND tblcustomfieldsvalues.fieldid = (SELECT id FROM tblcustomfields WHERE type = "client" AND fieldname = "' . $vars['vat_field'] . '" LIMIT 1) WHERE tblclients.id = ' . $clientid . ' LIMIT 1');

    if (mysql_num_rows($clientQuery) != 1) {
        throw new Exception('Failed to receive client ' . $clientid);
    }

    $clientFetch = mysql_fetch_assoc($clientQuery);

    $api = new api($vars['code'], $vars['username'], $vars['password']);
    $api->setCategory('contacts')->setAction('contact_manage');

    if (isset($vars['debug']) && $vars['debug'] == 'on') {
        $api->enableDebug($vars['debug_email']);
    }

    if ($clientFetch['acumulusid'] != null) {
        $api->setParam('contact/contactid', $clientFetch['acumulusid']);
    }

    if ($customerid != null) {
        $api->setParam('contact/contactid', $customerid);
    }

    if ($clientFetch['country'] == 'NL') {
        $api->setParam('contact/contactlocationcode', 1);
    } elseif (inEurope($clientFetch['country'])) {
        $api->setParam('contact/contactlocationcode', 2);
    } else {
        $api->setParam('contact/contactlocationcode', 3);
    }

    $taxData = getTaxRate(1, $clientQuery['state'], $clientQuery['country']);

    $api->setParams(array(
        'contact' => array(
            'contactemail' => $clientFetch['email'],
            'contacttype' => 1,
            'overwriteifexists' => 1,
            'contactname1' => ucfirst($clientFetch['firstname']) . ' ' . $clientFetch['lastname'],
            'contactname2' => '',
            'contactperson' => '',
            'contactsalutation' => '',
            'contactaddress1' => $clientFetch['address1'],
            'contactaddress2' => $clientFetch['address2'],
            'contactpostalcode' => $clientFetch['postcode'],
            'contactcity' => $clientFetch['city'],
            'contactcountrycode' => ((inEurope($clientFetch['country'])) ? $clientFetch['country'] : ''),
            'contactvatnumber' => $clientFetch['vatnumber'],
            'contactvatratebase' => (($clientFetch['taxexempt'] == 'on') ? -1 : round($taxData['rate'])),
            'contacttelephone' => $clientFetch['phonenumber'],
            'contactfax' => '',
            'contactsepaincassostatus' => 'FRST',
            'contactinvoicetemplateid' => '',
            'contactstatus' => 1
        )
    ));

    if (!empty($clientFetch['companyname'])) {
        $api->setParams(array(
            'contact' => array(
                'contactname1' => $clientFetch['companyname'],
                'contactperson' => ucfirst($clientFetch['firstname']) . ' ' . $clientFetch['lastname']
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

        return false;
    } else {
        mysql_query('UPDATE tblclients SET acumulusid = ' . $response['contact']['contactid'] . ' WHERE id = ' . $clientid . ' LIMIT 1');

        return $response['contact']['contactid'];
    }
}

/**
 * Returns the option vars of Acumulus for in our Hooks.
 * @return array $vars : Acumulus option vars
 */
function getAddonVars()
{
    $vars = array();

    $addonQuery = mysql_query('SELECT setting, value FROM tbladdonmodules WHERE module = "acumulus"');

    if (mysql_num_rows($addonQuery) <= 1) {
        return $vars;
    }

    while ($addonFetch = mysql_fetch_assoc($addonQuery)) {
        $vars[$addonFetch['setting']] = $addonFetch['value'];
    }

    return $vars;
}

/**
 * Tests if a country is in Europe.
 * @param $country : Country to test
 * @return boolean $result : Result of the test
 */
function inEurope($country)
{
    require $_SERVER['DOCUMENT_ROOT'] . '/includes/countries.php';

    return isset($countries[strtoupper($country)]);
}
