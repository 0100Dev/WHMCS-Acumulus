<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once dirname(__FILE__) . '/includes/general.php';

function acumulus_config()
{
    $vars = getAddonVars();

    $invoiceTemplateFields = '';
    $costCenterFields = '';

    if (!empty($vars)) {
        $api = new api($vars['code'], $vars['username'], $vars['password']);
        $api->setCategory('picklists')->setAction('picklist_invoicetemplates');
        $api->execute();

        if ($api->hasErrors()) {
            foreach ($api->getErrors() as $error) {
                $invoiceTemplateFields .= $error['code'] . ' - ' . $error['message'] . ',';
            }
        } else {
            $response = $api->getResponse();

            foreach ($response['invoicetemplates'] as $invoiceTemplate) {
                $invoiceTemplateFields .= $invoiceTemplate['invoicetemplatename'] . ',';
            }
        }

        $invoiceTemplateFields = substr($invoiceTemplateFields, 0, -1);

        $api = new api($vars['code'], $vars['username'], $vars['password']);
        $api->setCategory('picklists')->setAction('picklist_costcenters');
        $api->execute();

        if ($api->hasErrors()) {
            foreach ($api->getErrors() as $error) {
                $costCenterFields .= $error['code'] . ' - ' . $error['message'] . ',';
            }
        } else {
            $response = $api->getResponse();

            foreach ($response['costcenters'] as $invoiceTemplate) {
                $costCenterFields .= $invoiceTemplate['costcentername'] . ',';
            }
        }

        $costCenterFields = substr($costCenterFields, 0, -1);

    } else {
        $invoiceTemplateFields .= 'Vul eerst uw gegevens in en sla deze op.';
        $costCenterFields .= 'Vul eerst uw gegevens in en sla deze op.';
    }

    $vatFields = 'Geen';

    $fieldQuery = mysql_query('SELECT * FROM tblcustomfields WHERE type = "client"');

    while ($fieldFetch = mysql_fetch_assoc($fieldQuery)) {
        $vatFields .= ',' . $fieldFetch['fieldname'];
    }

    $configarray = array(
        'name' => 'Acumulus',
        'description' => 'A module which connects WHMCS with Acumulus.',
        'version' => '1.0',
        'author' => '<a href="http://devapp.nl/">Dev App</a>',
        'language' => 'dutch',
        'fields' => array(
            'code' => array(
                'FriendlyName' => 'Contractcode',
                'Type' => 'text',
                'Description' => 'Uw contractcode van Acumulus.'
            ),
            'username' => array(
                'FriendlyName' => 'Gebruikersnaam',
                'Type' => 'text',
                'Description' => 'Uw gebruikersnaam van Acumulus (TIP: maak een aparte gebruiker aan).'
            ),
            'password' => array(
                'FriendlyName' => 'Wachwoord',
                'Type' => 'password',
                'Description' => 'Uw wachtwoord van Acumulus (TIP: maak een aparte gebruiker aan).'
            ),
            'add_paid' => array(
                'FriendlyName' => 'Synchroniseer betaalde factuur',
                'Type' => 'yesno',
                'Description' => 'Voeg een factuur toe wanneer hij betaald is via WHMCS (TIP: gebruik de <a href="http://www.whmcs.com/members/communityaddons.php?action=viewmod&id=219" target="_blank">EU VAT Addon</a></a> voor Proforma Invoicing).'
            ),
            'invoice_template' => array(
                'FriendlyName' => 'Factuur template',
                'Type' => 'dropdown',
                'Options' => $invoiceTemplateFields,
                'Description' => 'Selecteer hier een factuur template die voor alle facturen gebruikt wordt.'
            ),
            'cost_center' => array(
                'FriendlyName' => 'Kostenplaats',
                'Type' => 'dropdown',
                'Options' => $costCenterFields,
                'Description' => 'Selecteer hier een kostenplaats die voor alle facturen gebruikt wordt.'
            ),
            'vat_field' => array(
                'FriendlyName' => 'VAT-nummer veld',
                'Type' => 'dropdown',
                'Options' => $vatFields,
                'Description' => 'Selecteer hier het VAT-nummer veld van de <a href="configcustomfields.php">Custom Client Fields</a> pagina  (TIP: gebruik de <a href="http://www.whmcs.com/members/communityaddons.php?action=viewmod&id=219" target="_blank">EU VAT Addon</a></a> voor een automatisch VAT-nummer check)'
            ),
            'hide_sync' => array(
                'FriendlyName' => 'Verberg synchonisatie',
                'Type' => 'yesno',
                'Description' => 'Verberg hiermee de synchonisatie optie van bestaande facturen, zodat het niet onverwachts nog is gebeurd.'
            ),
            'debug' => array(
                'FriendlyName' => 'API Debug',
                'Type' => 'yesno',
                'Description' => 'Activeer de API debug mode.'
            ),
            'debug_email' => array(
                'FriendlyName' => 'Debug E-mail',
                'Type' => 'text',
                'Description' => 'Uw e-mailadres voor de API-error en warning e-mails.'
            )
        )
    );

    return $configarray;
}

function acumulus_output($vars)
{
    if (isset($_GET['sync'])) {
        if (acumulus_sync($vars)) {
            return;
        }
    }

    ?>
    <h2>Openstaande facturen (van creditors)</h2>
    <table id="sortabletbl2" class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
        <tr>
            <th width="20">
                <input type="checkbox" onclick="$('#sortabletbl2 .checkall').attr('checked',this.checked);">
            </th>
            <th width="150">Datum</th>
            <th>Contact</th>
            <th width="200">Rekening</th>
            <th width="200">Bedrag</th>
            <th width="150">&nbsp;</th>
        </tr>
        <tr>
            <?php
            function invoice($entry) {
            ?>
        <tr>
            <td><input type="checkbox" name="selectedRequests[]" value="<?php echo $entry['entryid']; ?>"
                       class="checkall"></td>
            <td><?php echo $entry['issuedate']; ?></td>
            <td><?php echo $entry['contactid']; ?> - <?php echo $entry['contactname']; ?></td>
            <td><?php echo $entry['accountnumber']; ?></td>
            <td><?php echo $entry['amount']; ?></td>
            <td>
                <a href="https://www.sielsystems.nl/acumulus/editboeking.php?boeking_ID=<?php echo $entry['entryid']; ?>"
                   onclick="javascript:void window.open('https://www.sielsystems.nl/acumulus/editboeking.php?boeking_ID=<?php echo $entry['entryid']; ?>','boeking wijzigen','width=820,height=700,toolbar=0,menubar=0,location=0,status=1,scrollbars=1,resizable=1,left=0,top=0');return false;">Bekijk</a>
            </td>
        </tr>
        <?php
        }

        $api = new api($vars['code'], $vars['username'], $vars['password']);
        $api->setCategory('reports')->setAction('report_unpaid_creditors');
        $api->execute();
        $response = $api->getResponse();

        if ($api->hasErrors()) {
            ?>
            <td colspan="6" align="middle">
                <strong>Error(s) on API</strong><br/>
                <?php
                foreach ($api->getErrors() as $error) {
                    echo $error['code'] . ' | ' . $error['message'] . '<br/>';
                }
                ?>
            </td>
            <?php
        } else {
            if (isset($response['unpaidcreditorinvoices']['entry'][0])) {
                foreach ($response['unpaidcreditorinvoices']['entry'] as $entry) {
                    invoice($entry);
                }
            } else {
                if (isset($response['unpaidcreditorinvoices']['entry'])) {
                    invoice($response['unpaidcreditorinvoices']['entry']);
                } else {
                    ?>
                    <td colspan="6" align="middle">
                        Geen openstaande facturen
                    </td>
                    <?php
                }
            }
        }
        ?>
        </tr>
    </table><br/><br/>

    <h1>Acumulus - Tools</h1>

    <?php
    if (isset($_POST['sync_vat'])) {
        acumulus_sync_vat($vars);
    }
    ?>

    <form action="" method="POST">
        <input type="submit" name="sync_vat" value="Synchroniseer VAT"/>
    </form>

    <?php
    if (!isset($vars['hide_sync']) || empty($vars['hide_sync'])) {
        ?>
        <h2>Synchroniseer reeds betaalde facturen</h2>
        <form action="" method="POST">
            Van <input type="text" name="sync_start" size="15" value="" class="datepick"> &nbsp;&nbsp; tot <input
                type="text" name="sync_end" size="15" value="" class="datepick">.<br/>
            <input type="submit" name="start_sync" value="Synchroniseer"/> (Er zal een popup open, sluit deze niet!)
        </form>
        <?php
    }
}

function acumulus_sync_vat($vars)
{
    $taxQuery = select_query('tbltax');

    while ($taxFetch = mysql_fetch_assoc($taxQuery)) {
        $api = new api($vars['code'], $vars['username'], $vars['password']);
        $api->setCategory('lookups')->setAction('lookup_vatinfo');
        $api->setParam('vatcountry', $taxFetch['country']);
        $api->execute();

        $response = $api->getResponse();

        foreach ($response['vatinfo']['vat'] as $vat) {
            if ($vat['vattype'] == 'normal') {
                update_query('tbltax', array('taxrate' => $vat['vatrate']), array('id' => $taxFetch['id']));
            }
        }
    }
}

function acumulus_sync($vars)
{
    if (isset($vars['hide_sync']) && $vars['hide_sync'] == 'on') {
        return false;
    }
    ?>
    <table id="sortabletbl2" class="invoices datatable" border="0" cellspacing="1" cellpadding="3">
        <tr>
            <th width="150">Status</th>
            <th width="200">Factuur</th>
            <th width="250">Klant</th>
        </tr>
        <?php
        $invoiceQuery = mysql_query('SELECT * FROM tblinvoices WHERE status = "Paid"');

        while ($invoiceFetch = mysql_fetch_assoc($invoiceQuery)) {
            ?>
            <tr data-id="<?php echo $invoiceFetch['id']; ?>">
                <td>Wachtend</td>
                <td><?php echo $invoiceFetch['id']; ?></td>
                <td><?php echo $invoiceFetch['userid']; ?></td>
            </tr>
            <?php
        }
        ?>
    </table>

    <script>
        $(function () {
            $('.invoices  tr[data-id]').each(function () {
                console.log($(this).data('id'));
            });
        });
    </script>
    <?php

    return true;
}