<?php 
function getClientsDetails($userid = "", $contactid = "")
{
    if( !$userid ) 
    {
        $userid = $_SESSION["uid"];
    }

    $client = new WHMCS\Client($userid);
    $details = $client->getDetails($contactid);
    return $details;
}

function getClientsStats($userid)
{
    global $CONFIG;
    global $currency;
    $currency = getCurrency($userid);
    $stats = array(  );
    $result = full_query("SELECT COUNT(*),SUM(total)-COALESCE(SUM((SELECT SUM(amountin)-SUM(amountout) FROM tblaccounts WHERE tblaccounts.invoiceid=tblinvoices.id)),0),(SELECT SUM(amountin-fees-amountout) FROM tblaccounts WHERE userid=" . (int) $userid . "),(SELECT credit FROM tblclients WHERE id=" . (int) $userid . ") FROM tblinvoices WHERE userid=" . (int) $userid . " AND status='Unpaid' AND (select count(id) from tblinvoiceitems where invoiceid=tblinvoices.id and type='Invoice')<=0");
    $data = mysql_fetch_array($result);
    $stats["numdueinvoices"] = $data[0];
    $stats["dueinvoicesbalance"] = formatCurrency($data[1]);
    $stats["income"] = formatCurrency($data[2]);
    $stats["incredit"] = (0 < $data[3] ? true : false);
    $stats["creditbalance"] = formatCurrency($data[3]);
    $result = full_query("SELECT COUNT(*),SUM(total)-COALESCE(SUM((SELECT SUM(amountin)-SUM(amountout) FROM tblaccounts WHERE tblaccounts.invoiceid=tblinvoices.id)),0) FROM tblinvoices WHERE userid=" . (int) $userid . " AND status='Unpaid' AND duedate<" . date("Ymd") . " AND (select count(id) from tblinvoiceitems where invoiceid=tblinvoices.id and type='Invoice')<=0");
    $data = mysql_fetch_array($result);
    $stats["numoverdueinvoices"] = $data[0];
    $stats["overdueinvoicesbalance"] = formatCurrency($data[1]);
    $draftInvoices = WHMCS\Database\Capsule::table("tblinvoices")->selectRaw("COUNT('id') as invoice_count,\n        SUM(total) - COALESCE(\n            SUM(\n                (\n                    SELECT SUM(amountin)-SUM(amountout) FROM tblaccounts WHERE tblaccounts.invoiceid=tblinvoices.id\n                )\n            ),\n            0\n        ) as balance")->where("userid", "=", $userid)->where("status", "=", "Draft")->first();
    $stats["numDraftInvoices"] = $draftInvoices->invoice_count;
    $stats["draftInvoicesBalance"] = formatCurrency($draftInvoices->balance);
    $invoicestats = array(  );
    $result = select_query("tblinvoices", "status,COUNT(*),SUM(total)", "userid=" . (int) $userid . " GROUP BY status");
    while( $data = mysql_fetch_array($result) ) 
    {
        $invoicestats[$data[0]] = $data;
    }
    $stats["numpaidinvoices"] = (isset($invoicestats["Paid"][1]) ? $invoicestats["Paid"][1] : 0);
    $stats["paidinvoicesamount"] = (isset($invoicestats["Paid"][2]) ? formatCurrency($invoicestats["Paid"][2]) : formatCurrency(0));
    $stats["numunpaidinvoices"] = (isset($invoicestats["Unpaid"][1]) ? $invoicestats["Unpaid"][1] : 0);
    $stats["unpaidinvoicesamount"] = (isset($invoicestats["Unpaid"][2]) ? formatCurrency($invoicestats["Unpaid"][2]) : formatCurrency(0));
    $stats["numcancelledinvoices"] = (isset($invoicestats["Cancelled"][1]) ? $invoicestats["Cancelled"][1] : 0);
    $stats["cancelledinvoicesamount"] = (isset($invoicestats["Cancelled"][2]) ? formatCurrency($invoicestats["Cancelled"][2]) : formatCurrency(0));
    $stats["numrefundedinvoices"] = (isset($invoicestats["Refunded"][1]) ? $invoicestats["Refunded"][1] : 0);
    $stats["refundedinvoicesamount"] = (isset($invoicestats["Refunded"][2]) ? formatCurrency($invoicestats["Refunded"][2]) : formatCurrency(0));
    $stats["numcollectionsinvoices"] = (isset($invoicestats["Collections"][1]) ? $invoicestats["Collections"][1] : 0);
    $stats["collectionsinvoicesamount"] = (isset($invoicestats["Collections"][2]) ? formatCurrency($invoicestats["Collections"][2]) : formatCurrency(0));
    $stats["numpaymentpendinginvoices"] = (isset($invoicestats["Payment Pending"][1]) ? $invoicestats["Payment Pending"][1] : 0);
    $stats["paymentpendinginvoicesamount"] = (isset($invoicestats["Payment Pending"][2]) ? formatCurrency($invoicestats["Payment Pending"][2]) : formatCurrency(0));
    $productstats = array(  );
    $result = full_query("SELECT tblproducts.type,domainstatus,COUNT(*) FROM tblhosting INNER JOIN tblproducts ON tblhosting.packageid=tblproducts.id WHERE tblhosting.userid=" . (int) $userid . " GROUP BY domainstatus,tblproducts.type");
    while( $data = mysql_fetch_array($result) ) 
    {
        $productstats[$data[0]][$data[1]] = $data[2];
    }
    $stats["productsnumactivehosting"] = (isset($productstats["hostingaccount"]["Active"]) ? $productstats["hostingaccount"]["Active"] : 0);
    $stats["productsnumhosting"] = 0;
    if( array_key_exists("hostingaccount", $productstats) && is_array($productstats["hostingaccount"]) ) 
    {
        foreach( $productstats["hostingaccount"] as $status => $count ) 
        {
            $stats["productsnumhosting"] += $count;
        }
    }

    $stats["productsnumactivereseller"] = (isset($productstats["reselleraccount"]["Active"]) ? $productstats["reselleraccount"]["Active"] : 0);
    $stats["productsnumreseller"] = 0;
    if( array_key_exists("reselleraccount", $productstats) && is_array($productstats["reselleraccount"]) ) 
    {
        foreach( $productstats["reselleraccount"] as $status => $count ) 
        {
            $stats["productsnumreseller"] += $count;
        }
    }

    $stats["productsnumactiveservers"] = (isset($productstats["server"]["Active"]) ? $productstats["server"]["Active"] : 0);
    $stats["productsnumservers"] = 0;
    if( array_key_exists("server", $productstats) && is_array($productstats["server"]) ) 
    {
        foreach( $productstats["server"] as $status => $count ) 
        {
            $stats["productsnumservers"] += $count;
        }
    }

    $stats["productsnumactiveother"] = (isset($productstats["other"]["Active"]) ? $productstats["other"]["Active"] : 0);
    $stats["productsnumother"] = 0;
    if( array_key_exists("other", $productstats) && is_array($productstats["other"]) ) 
    {
        foreach( $productstats["other"] as $status => $count ) 
        {
            $stats["productsnumother"] += $count;
        }
    }

    $stats["productsnumactive"] = $stats["productsnumactivehosting"] + $stats["productsnumactivereseller"] + $stats["productsnumactiveservers"] + $stats["productsnumactiveother"];
    $stats["productsnumtotal"] = $stats["productsnumhosting"] + $stats["productsnumreseller"] + $stats["productsnumservers"] + $stats["productsnumother"];
    $domainstats = array(  );
    $result = select_query("tbldomains", "status,COUNT(*)", "userid=" . (int) $userid . " GROUP BY status");
    while( $data = mysql_fetch_array($result) ) 
    {
        $domainstats[$data[0]] = $data[1];
    }
    $stats["numactivedomains"] = (isset($domainstats["Active"]) ? $domainstats["Active"] : 0);
    $stats["numdomains"] = 0;
    foreach( $domainstats as $count ) 
    {
        $stats["numdomains"] += $count;
    }
    $quotestats = array(  );
    $result = select_query("tblquotes", "stage,COUNT(*)", "userid=" . (int) $userid . " GROUP BY stage");
    while( $data = mysql_fetch_array($result) ) 
    {
        $quotestats[$data[0]] = $data[1];
    }
    $stats["numacceptedquotes"] = (isset($quotestats["Accepted"]) ? $quotestats["Accepted"] : 0);
    $stats["numquotes"] = 0;
    foreach( $quotestats as $count ) 
    {
        $stats["numquotes"] += $count;
    }
    $statusfilter = array(  );
    $result = select_query("tblticketstatuses", "title", array( "showactive" => "1" ));
    while( $data = mysql_fetch_array($result) ) 
    {
        $statusfilter[] = $data[0];
    }
    $ticketstats = array(  );
    $result = select_query("tbltickets", "status,COUNT(*)", "userid=" . (int) $userid . " AND merged_ticket_id = 0 GROUP BY status");
    while( $data = mysql_fetch_array($result) ) 
    {
        $ticketstats[$data[0]] = $data[1];
    }
    $stats["numtickets"] = 0;
    $stats["numactivetickets"] = $stats["numtickets"];
    foreach( $ticketstats as $status => $count ) 
    {
        if( in_array($status, $statusfilter) ) 
        {
            $stats["numactivetickets"] += $count;
        }

        $stats["numtickets"] += $count;
    }
    $result = select_query("tblaffiliatesaccounts", "COUNT(*)", array( "clientid" => $userid ), "", "", "", "tblaffiliates ON tblaffiliatesaccounts.affiliateid=tblaffiliates.id");
    $data = mysql_fetch_array($result);
    $stats["numaffiliatesignups"] = $data[0];
    $stats["isAffiliate"] = (get_query_val("tblaffiliates", "id", array( "clientid" => (int) $userid )) ? true : false);
    return $stats;
}

function getCountriesDropDown($selected = "", $fieldname = "", $tabindex = "", $selectInline = true, $disable = false)
{
    global $CONFIG;
    global $_LANG;
    if( !$selected ) 
    {
        $selected = $CONFIG["DefaultCountry"];
    }

    if( !$fieldname ) 
    {
        $fieldname = "country";
    }

    if( $tabindex ) 
    {
        $tabindex = " tabindex=\"" . $tabindex . "\"";
    }

    if( $disable ) 
    {
        $disable = " disabled";
    }
    else
    {
        $disable = "";
    }

    $countries = new WHMCS\Utility\Country();
    $selectInlineClass = ($selectInline ? " select-inline" : "");
    $dropdowncode = "<select name=\"" . $fieldname . "\" id=\"" . $fieldname . "\" class=\"form-control" . $selectInlineClass . "\"" . $tabindex . $disable . ">";
    foreach( $countries->getCountryNameArray() as $countriesvalue1 => $countriesvalue2 ) 
    {
        $dropdowncode .= "<option value=\"" . $countriesvalue1 . "\"";
        if( $countriesvalue1 == $selected ) 
        {
            $dropdowncode .= " selected=\"selected\"";
        }

        $dropdowncode .= ">" . $countriesvalue2 . "</option>";
    }
    $dropdowncode .= "</select>";
    return $dropdowncode;
}

function checkDetailsareValid($uid = "", $signup = false, $checkemail = true, $captcha = true, $checkcustomfields = true)
{
    global $whmcs;
    $validate = new WHMCS\Validate();
    $validate->setOptionalFields($whmcs->get_config("ClientsProfileOptionalFields"));
    if( !$signup ) 
    {
        $ClientsProfileUneditableFields = $whmcs->get_config("ClientsProfileUneditableFields");
        if( $whmcs->isApiRequest() ) 
        {
            $ClientsProfileUneditableFields = preg_replace("/email,?/i", "", $ClientsProfileUneditableFields);
        }

        $validate->setOptionalFields($ClientsProfileUneditableFields);
    }

    $validate->validate("required", "firstname", "clientareaerrorfirstname");
    $validate->validate("required", "lastname", "clientareaerrorlastname");
    if( ($signup || $checkemail) && $validate->validate("required", "email", "clientareaerroremail") && $validate->validate("email", "email", "clientareaerroremailinvalid") && $validate->validate("banneddomain", "email", "clientareaerrorbannedemail") ) 
    {
        $validate->validate("uniqueemail", "email", "ordererroruserexists", array( $uid, "" ));
    }

    $validate->validate("required", "address1", "clientareaerroraddress1");
    $validate->validate("required", "city", "clientareaerrorcity");
    $validate->validate("required", "state", "clientareaerrorstate");
    $validate->validate("required", "postcode", "clientareaerrorpostcode");
    $validate->validate("postcode", "postcode", "clientareaerrorpostcode2");
    $validate->validate("required", "phonenumber", "clientareaerrorphonenumber");
    $validate->validate("phone", "phonenumber", "clientareaerrorphonenumber2");
    $validate->validate("country", "country", "clientareaerrorcountry");
    if( $signup && $validate->validate("required", "password", "ordererrorpassword") && $validate->validate("pwstrength", "password", "pwstrengthfail") && $validate->validate("required", "password2", "clientareaerrorpasswordconfirm") ) 
    {
        $validate->validate("match_value", "password", "clientareaerrorpasswordnotmatch", "password2");
    }

    if( $checkcustomfields ) 
    {
        $validate->validateCustomFields("client", "", $signup);
    }

    if( $signup ) 
    {
        $securityquestions = getSecurityQuestions();
        if( $securityquestions ) 
        {
            $validate->validate("required", "securityqans", "securityanswerrequired");
        }

        if( $captcha ) 
        {
            $validate->validate("captcha", "code", "captchaverifyincorrect");
        }

        if( $whmcs->get_config("EnableTOSAccept") ) 
        {
            $validate->validate("required", "accepttos", "ordererroraccepttos");
        }

    }

    run_validate_hook($validate, "ClientDetailsValidation", $_POST);
    $errormessage = $validate->getHTMLErrorOutput();
    return $errormessage;
}

function checkContactDetails($cid = "", $reqpw = false, $prefix = "")
{
    global $whmcs;
    $subaccount = $whmcs->get_req_var("subaccount");
    $validate = new WHMCS\Validate();
    $validate->setOptionalFields($whmcs->get_config("ClientsProfileOptionalFields"));
    $validate->validate("required", $prefix . "firstname", "clientareaerrorfirstname");
    $validate->validate("required", $prefix . "lastname", "clientareaerrorlastname");
    if( $validate->validate("required", $prefix . "email", "clientareaerroremail") && $validate->validate("email", $prefix . "email", "clientareaerroremailinvalid") && $validate->validate("banneddomain", $prefix . "email", "clientareaerrorbannedemail") && $subaccount ) 
    {
        $validate->validate("uniqueemail", $prefix . "email", "ordererroruserexists", array( "", $cid ));
    }

    $validate->validate("required", $prefix . "address1", "clientareaerroraddress1");
    $validate->validate("required", $prefix . "city", "clientareaerrorcity");
    $validate->validate("required", $prefix . "state", "clientareaerrorstate");
    $validate->validate("required", $prefix . "postcode", "clientareaerrorpostcode");
    $validate->validate("postcode", $prefix . "postcode", "clientareaerrorpostcode2");
    $validate->validate("required", $prefix . "phonenumber", "clientareaerrorphonenumber");
    $validate->validate("phone", $prefix . "phonenumber", "clientareaerrorphonenumber2");
    $validate->validate("country", $prefix . "country", "clientareaerrorcountry");
    if( $subaccount && $reqpw && $validate->validate("required", "password", "ordererrorpassword") && $validate->validate("pwstrength", "password", "pwstrengthfail") && $validate->validate("required", "password2", "clientareaerrorpasswordconfirm") ) 
    {
        $validate->validate("match_value", "password", "clientareaerrorpasswordnotmatch", "password2");
    }

    run_validate_hook($validate, "ContactDetailsValidation", $_POST);
    $errormessage = $validate->getHTMLErrorOutput();
    return $errormessage;
}

function addClientFromModel(WHMCS\User\Client $client)
{
    return addClient($client->firstName, $client->lastName, $client->companyName, $client->email, $client->address1, $client->address2, $client->city, $client->state, $client->postcode, $client->country, $client->phoneNumber, $client->passwordHash, $client->securityQuestionId, $client->securityQuestionAnswer, false, array(  ), $client->uuid);
}

function addClient($firstname, $lastname, $companyname, $email, $address1, $address2, $city, $state, $postcode, $country, $phonenumber, $password, $securityqid = "", $securityqans = "", $sendemail = "on", array $additionalData = array(  ), $uuid = "", $isAdmin = false)
{
    global $whmcs;
    global $remote_ip;
    $verifyEmailAddress = WHMCS\Config\Setting::getValue("EnableEmailVerification");
    if( !$country ) 
    {
        $country = $whmcs->get_config("DefaultCountry");
    }

    if( !$uuid ) 
    {
        $uuid = Ramsey\Uuid\Uuid::uuid4();
        $uuid = $uuid->toString();
    }

    $fullhost = gethostbyaddr($remote_ip);
    $currency = (is_array($_SESSION["currency"]) ? $_SESSION["currency"] : getCurrency("", $_SESSION["currency"]));
    $hasher = new WHMCS\Security\Hash\Password();
    $password_hash = $hasher->hash(WHMCS\Input\Sanitize::decode($password));
    $table = "tblclients";
    $array = array( "uuid" => $uuid, "firstname" => $firstname, "lastname" => $lastname, "companyname" => $companyname, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phonenumber, "password" => $password_hash, "lastlogin" => "now()", "securityqid" => $securityqid, "securityqans" => encrypt($securityqans), "ip" => $remote_ip, "host" => $fullhost, "status" => "Active", "datecreated" => "now()", "language" => $_SESSION["Language"], "currency" => $currency["id"], "email_verified" => 0 );
    $uid = insert_query($table, $array);
    logActivity("Created Client " . $firstname . " " . $lastname . " - User ID: " . $uid);
    if( !empty($additionalData) ) 
    {
        $legacyBooleanColumns = array( "taxexempt", "latefeeoveride", "overideduenotices", "separateinvoices", "disableautocc", "emailoptout", "overrideautoclose" );
        foreach( $legacyBooleanColumns as $column ) 
        {
            if( isset($additionalData[$column]) ) 
            {
                $additionalData[$column] = (bool) $additionalData[$column];
            }

        }
        if( !empty($additionalData["credit"]) && $additionalData["credit"] <= 0 ) 
        {
            unset($additionalData["credit"]);
        }

        $tableData = $additionalData;
        if( isset($tableData["customfields"]) ) 
        {
            unset($tableData["customfields"]);
        }

        update_query("tblclients", $tableData, array( "id" => $uid ));
        if( !empty($tableData["credit"]) ) 
        {
            WHMCS\Database\Capsule::table("tblcredit")->insert(array( "clientid" => $uid, "date" => Carbon\Carbon::now()->format("Y-m-d"), "description" => "Opening Credit Balance", "amount" => $tableData["credit"] ));
        }

    }

    if( !function_exists("saveCustomFields") ) 
    {
        require(ROOTDIR . "/includes/customfieldfunctions.php");
    }

    if( defined("ADMINAREA") ) 
    {
        $isAdmin = true;
    }

    $customFields = $whmcs->get_req_var("customfield");
    if( empty($customFields) && !empty($additionalData["customfields"]) ) 
    {
        $customFields = $additionalData["customfields"];
    }

    saveCustomFields($uid, $customFields, "client", $isAdmin);
    if( $verifyEmailAddress ) 
    {
        $client = WHMCS\User\Client::find($uid);
        if( !is_null($client) ) 
        {
            $client->sendEmailAddressVerification();
        }

    }
    else
    {
        if( $sendemail ) 
        {
            sendMessage("Client Signup Email", $uid, array( "client_password" => $password ));
        }

    }

    if( defined("CLIENTAREA") ) 
    {
        $_SESSION["uid"] = $uid;
        $_SESSION["upw"] = WHMCS\Authentication\Client::generateClientLoginHash($uid, NULL, $password_hash);
        $_SESSION["tkval"] = genRandomVal();
        run_hook("ClientLogin", array( "userid" => $uid, "contactid" => 0 ));
    }

    if( !defined("APICALL") ) 
    {
        run_hook("ClientAdd", array_merge(array( "userid" => $uid, "firstname" => $firstname, "lastname" => $lastname, "companyname" => $companyname, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phonenumber, "password" => $password ), $additionalData, array( "customfields" => $customFields )));
    }

    return $uid;
}

function addContact($userid, $firstname, $lastname, $companyname, $email, $address1, $address2, $city, $state, $postcode, $country, $phonenumber, $password = "", $permissions = array(  ), $generalemails = "", $productemails = "", $domainemails = "", $invoiceemails = "", $supportemails = "", $affiliateemails = "")
{
    global $CONFIG;
    if( !$country ) 
    {
        $country = $CONFIG["DefaultCountry"];
    }

    $subaccount = ($password ? "1" : "0");
    if( $permissions ) 
    {
        $permissions = implode(",", $permissions);
    }

    $table = "tblcontacts";
    $hasher = new WHMCS\Security\Hash\Password();
    $password = WHMCS\Input\Sanitize::decode($password);
    $array = array( "userid" => $userid, "firstname" => $firstname, "lastname" => $lastname, "companyname" => $companyname, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phonenumber, "subaccount" => $subaccount, "password" => $hasher->hash($password), "permissions" => $permissions, "generalemails" => $generalemails, "productemails" => $productemails, "domainemails" => $domainemails, "invoiceemails" => $invoiceemails, "supportemails" => $supportemails, "affiliateemails" => $affiliateemails );
    $contactid = insert_query($table, $array);
    run_hook("ContactAdd", array_merge($array, array( "contactid" => $contactid, "password" => $password )));
    logActivity("Added Contact - User ID: " . $userid . " - Contact ID: " . $contactid, $userid);
    return $contactid;
}

function deleteClient($userid)
{
    $userid = (int) get_query_val("tblclients", "id", array( "id" => (int) $userid ));
    if( !$userid ) 
    {
        return false;
    }

    run_hook("PreDeleteClient", array( "userid" => $userid ));
    delete_query("tblclients", array( "id" => $userid ));
    delete_query("tblcontacts", array( "userid" => $userid ));
    delete_query("tblhostingconfigoptions", "relid IN (SELECT id FROM tblhosting WHERE userid=" . $userid . ")");
    $result = select_query("tblhosting", "id", array( "userid" => $userid ));
    while( $data = mysql_fetch_array($result) ) 
    {
        $domainlistid = $data["id"];
        delete_query("tblhostingaddons", array( "hostingid" => $domainlistid ));
    }
    $result = select_query("tblcustomfields", "id", array( "type" => "client" ));
    while( $data = mysql_fetch_array($result) ) 
    {
        $customfieldid = $data["id"];
        delete_query("tblcustomfieldsvalues", array( "fieldid" => $customfieldid, "relid" => $userid ));
    }
    $result = select_query("tblcustomfields", "id,relid", array( "type" => "product" ));
    while( $data = mysql_fetch_array($result) ) 
    {
        $customfieldid = $data["id"];
        $customfieldpid = $data["relid"];
        $result2 = select_query("tblhosting", "id", array( "userid" => $userid, "packageid" => $customfieldpid ));
        while( $data = mysql_fetch_array($result2) ) 
        {
            $hostingid = $data["id"];
            delete_query("tblcustomfieldsvalues", array( "fieldid" => $customfieldid, "relid" => $hostingid ));
        }
    }
    delete_query("tblorders", array( "userid" => $userid ));
    delete_query("tblhosting", array( "userid" => $userid ));
    delete_query("tbldomains", array( "userid" => $userid ));
    delete_query("tblemails", array( "userid" => $userid ));
    delete_query("tblinvoices", array( "userid" => $userid ));
    delete_query("tblinvoiceitems", array( "userid" => $userid ));
    delete_query("tbltickets", array( "userid" => $userid ));
    delete_query("tblaffiliates", array( "clientid" => $userid ));
    delete_query("tblnotes", array( "userid" => $userid ));
    delete_query("tblcredit", array( "clientid" => $userid ));
    delete_query("tblactivitylog", array( "userid" => $userid ));
    delete_query("tblsslorders", array( "userid" => $userid ));
    delete_query("tblauthn_account_links", array( "client_id" => $userid ));
    logActivity("Client Deleted - ID: " . $userid);
    return true;
}

function getSecurityQuestions($questionid = "")
{
    if( $questionid ) 
    {
        $query = select_query("tbladminsecurityquestions", "", array( "question" => $questionid ));
    }
    else
    {
        $query = select_query("tbladminsecurityquestions", "", "");
    }

    $results = array(  );
    while( $data = mysql_fetch_assoc($query) ) 
    {
        $results[] = array( "id" => $data["id"], "question" => decrypt($data["question"]) );
    }
    return $results;
}

function generateClientPW($plain, $salt = "")
{
    if( !$salt ) 
    {
        $seeds = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ#!%()#!%()#!%()";
        $seeds_count = strlen($seeds) - 1;
        for( $i = 0; $i < 5; $i++ ) 
        {
            $salt .= $seeds[rand(0, $seeds_count)];
        }
    }

    return md5($salt . WHMCS\Input\Sanitize::decode($plain)) . ":" . $salt;
}

function checkContactPermission($requiredPermission, $noRedirect = false)
{
    if( WHMCS\Session::get("cid") ) 
    {
        $contact = WHMCS\User\Client\Contact::find(WHMCS\Session::get("cid"));
        $permissions = $contact->permissions;
        if( !in_array($requiredPermission, $permissions) ) 
        {
            global $ca;
            global $_LANG;
            global $smartyvalues;
            if( $noRedirect ) 
            {
                return false;
            }

            foreach( $permissions as $key => $permission ) 
            {
                $permissions[$key] = Lang::trans("subaccountperms" . $permission);
            }
            Menu::primarySidebar("clientView");
            Menu::secondarySidebar("support");
            if( is_object($ca) ) 
            {
                $ca->setDisplayTitle(Lang::trans("accessdenied"));
                $ca->assign("allowedpermissions", $permissions);
                $ca->assign("requiredpermission", $reqperm);
                $ca->setTemplate("contactaccessdenied");
                $ca->output();
                exit();
            }

            $smartyvalues["allowedpermissions"] = $permissions;
            $smartyvalues["requiredpermission"] = $reqperm;
            $templatefile = "contactaccessdenied";
            outputClientArea($templatefile);
            exit();
        }

    }

    return true;
}

function validateClientLogin($username, $password)
{
    $authentication = new WHMCS\Authentication\Client($username, $password);
    if( $authentication::isInSecondFactorRequestState() ) 
    {
        if( !$authentication->verifySecondFactor() ) 
        {
            return false;
        }

        $authentication->finalizeLogin();
        return true;
    }

    if( $authentication->verifyFirstFactor() ) 
    {
        if( !$authentication->needsSecondFactorToFinalize() ) 
        {
            $authentication->finalizeLogin();
            return true;
        }

        $authentication->prepareSecondFactor();
    }

    return false;
}

function createCancellationRequest($userid, $serviceid, $reason, $type)
{
    global $CONFIG;
    global $currency;
    $existing = get_query_val("tblcancelrequests", "COUNT(id)", array( "relid" => $serviceid ));
    if( $existing == 0 ) 
    {
        if( !in_array($type, array( "Immediate", "End of Billing Period" )) ) 
        {
            $type = "End of Billing Period";
        }

        insert_query("tblcancelrequests", array( "date" => "now()", "relid" => $serviceid, "reason" => $reason, "type" => $type ));
        if( $type == "End of Billing Period" ) 
        {
            logActivity("Automatic Cancellation Requested for End of Current Cycle - Service ID: " . $serviceid, $userid);
        }
        else
        {
            logActivity("Automatic Cancellation Requested Immediately - Service ID: " . $serviceid, $userid);
        }

        $data = get_query_vals("tblhosting", "domain,freedomain", array( "tblhosting.id" => $serviceid ), "", "", "", "tblproducts ON tblproducts.id=tblhosting.packageid");
        list($domain, $freedomain) = $data;
        if( $freedomain && $domain ) 
        {
            $data = get_query_vals("tbldomains", "id,recurringamount,registrationperiod,dnsmanagement,emailforwarding,idprotection", array( "userid" => $userid, "domain" => $domain ), "status", "ASC");
            $domainid = $data["id"];
            $recurringamount = $data["recurringamount"];
            $regperiod = $data["registrationperiod"];
            $dnsmanagement = $data["dnsmanagement"];
            $emailforwarding = $data["emailforwarding"];
            $idprotection = $data["idprotection"];
            if( $recurringamount <= 0 ) 
            {
                $currency = getCurrency($userid);
                $result = select_query("tblpricing", "msetupfee,qsetupfee,ssetupfee", array( "type" => "domainaddons", "currency" => $currency["id"], "relid" => 0 ));
                $data = mysql_fetch_array($result);
                $domaindnsmanagementprice = $data["msetupfee"] * $regperiod;
                $domainemailforwardingprice = $data["qsetupfee"] * $regperiod;
                $domainidprotectionprice = $data["ssetupfee"] * $regperiod;
                $domainparts = explode(".", $domain, 2);
                if( !function_exists("getTLDPriceList") ) 
                {
                    require(ROOTDIR . "/includes/domainfunctions.php");
                }

                $temppricelist = getTLDPriceList("." . $domainparts[1], "", true, $userid);
                $recurringamount = $temppricelist[$regperiod]["renew"];
                if( $dnsmanagement ) 
                {
                    $recurringamount += $domaindnsmanagementprice;
                }

                if( $emailforwarding ) 
                {
                    $recurringamount += $domainemailforwardingprice;
                }

                if( $idprotection ) 
                {
                    $recurringamount += $domainidprotectionprice;
                }

                update_query("tbldomains", array( "recurringamount" => $recurringamount ), array( "id" => $domainid ));
            }

        }

        run_hook("CancellationRequest", array( "userid" => $userid, "relid" => $serviceid, "reason" => $reason, "type" => $type ));
        if( $CONFIG["CancelInvoiceOnCancellation"] ) 
        {
            cancelUnpaidInvoicebyProductID($serviceid, $userid);
        }

        if( WHMCS\Config\Setting::getValue("AutoCancelSubscriptions") ) 
        {
            if( !function_exists("cancelSubscriptionForService") ) 
            {
                require(ROOTDIR . "/includes/gatewayfunctions.php");
            }

            try
            {
                cancelSubscriptionForService($serviceid, $userid);
            }
            catch( Exception $e ) 
            {
                return $e->getMessage();
            }
        }

        return "success";
    }

    return "Existing Cancellation Request Exists";
}

function recalcRecurringProductPrice($serviceid, $userid = "", $pid = "", $billingcycle = "", $configoptionsrecurring = "empty", $promoid = 0, $includesetup = false)
{
    if( !$userid || !$pid || !$billingcycle ) 
    {
        $result = select_query("tblhosting", "userid,packageid,billingcycle", array( "id" => $serviceid ));
        $data = mysql_fetch_array($result);
        if( !$userid ) 
        {
            $userid = $data["userid"];
        }

        if( !$pid ) 
        {
            $pid = $data["packageid"];
        }

        if( !$billingcycle ) 
        {
            $billingcycle = $data["billingcycle"];
        }

    }

    global $currency;
    $currency = getCurrency($userid);
    $result = select_query("tblpricing", "", array( "type" => "product", "currency" => $currency["id"], "relid" => $pid ));
    $data = mysql_fetch_array($result);
    if( $billingcycle == "Monthly" ) 
    {
        $amount = $data["monthly"];
    }
    else
    {
        if( $billingcycle == "Quarterly" ) 
        {
            $amount = $data["quarterly"];
        }
        else
        {
            if( $billingcycle == "Semi-Annually" ) 
            {
                $amount = $data["semiannually"];
            }
            else
            {
                if( $billingcycle == "Annually" ) 
                {
                    $amount = $data["annually"];
                }
                else
                {
                    if( $billingcycle == "Biennially" ) 
                    {
                        $amount = $data["biennially"];
                    }
                    else
                    {
                        if( $billingcycle == "Triennially" ) 
                        {
                            $amount = $data["triennially"];
                        }
                        else
                        {
                            $amount = 0;
                        }

                    }

                }

            }

        }

    }

    if( $amount <= 0 ) 
    {
        $amount = 0;
    }

    if( $includesetup === true ) 
    {
        $setupvar = substr(strtolower($billingcycle), 0, 1);
        if( 0 < $data[$setupvar . "setupfee"] ) 
        {
            $amount += $data[$setupvar . "setupfee"];
        }

    }

    if( $configoptionsrecurring == "empty" ) 
    {
        if( !function_exists("getCartConfigOptions") ) 
        {
            require(ROOTDIR . "/includes/configoptionsfunctions.php");
        }

        $configoptions = getCartConfigOptions($pid, "", $billingcycle, $serviceid);
        foreach( $configoptions as $configoption ) 
        {
            $amount += $configoption["selectedrecurring"];
            if( $includesetup === true ) 
            {
                $amount += $configoption["selectedsetup"];
            }

        }
    }
    else
    {
        $amount += $configoptionsrecurring;
    }

    if( $promoid ) 
    {
        $amount -= recalcPromoAmount($pid, $userid, $serviceid, $billingcycle, $amount, $promoid);
    }

    return $amount;
}

function closeClient($userid)
{
    update_query("tblclients", array( "status" => "Closed" ), array( "id" => $userid ));
    update_query("tblhosting", array( "domainstatus" => "Cancelled", "termination_date" => date("Y-m-d") ), array( "userid" => $userid, "domainstatus" => "Pending" ));
    update_query("tblhosting", array( "domainstatus" => "Cancelled", "termination_date" => date("Y-m-d") ), array( "userid" => $userid, "domainstatus" => "Active" ));
    update_query("tblhosting", array( "domainstatus" => "Terminated", "termination_date" => date("Y-m-d") ), array( "userid" => $userid, "domainstatus" => "Suspended" ));
    $result = select_query("tblhosting", "id", array( "userid" => $userid ));
    while( $data = mysql_fetch_array($result) ) 
    {
        $domainlistid = $data["id"];
        update_query("tblhostingaddons", array( "status" => "Cancelled", "termination_date" => date("Y-m-d") ), array( "hostingid" => $domainlistid, "status" => "Pending" ));
        update_query("tblhostingaddons", array( "status" => "Cancelled", "termination_date" => date("Y-m-d") ), array( "hostingid" => $domainlistid, "status" => "Active" ));
        update_query("tblhostingaddons", array( "status" => "Terminated", "termination_date" => date("Y-m-d") ), array( "hostingid" => $domainlistid, "status" => "Suspended" ));
    }
    update_query("tbldomains", array( "status" => "Cancelled" ), array( "userid" => $userid, "status" => "Pending" ));
    update_query("tbldomains", array( "status" => "Cancelled" ), array( "userid" => $userid, "status" => "Active" ));
    update_query("tbldomains", array( "status" => "Cancelled" ), array( "userid" => $userid, "status" => "Pending-Transfer" ));
    update_query("tblinvoices", array( "status" => "Cancelled" ), array( "userid" => $userid, "status" => "Unpaid" ));
    update_query("tblbillableitems", array( "invoiceaction" => "0" ), array( "userid" => $userid ));
    logActivity("Client Status changed to Closed - User ID: " . $userid, $userid);
    run_hook("ClientClose", array( "userid" => $userid ));
}

function convertStateToCode($ostate, $country)
{
    $sc = "";
    $state = strtolower($ostate);
    $country = strtoupper($country);
    if( $country == "US" ) 
    {
        if( $state == "alabama" ) 
        {
            $sc = "AL";
        }
        else
        {
            if( $state == "alaska" ) 
            {
                $sc = "AK";
            }
            else
            {
                if( $state == "arizona" ) 
                {
                    $sc = "AZ";
                }
                else
                {
                    if( $state == "arkansas" ) 
                    {
                        $sc = "AR";
                    }
                    else
                    {
                        if( $state == "california" ) 
                        {
                            $sc = "CA";
                        }
                        else
                        {
                            if( $state == "colorado" ) 
                            {
                                $sc = "CO";
                            }
                            else
                            {
                                if( $state == "connecticut" ) 
                                {
                                    $sc = "CT";
                                }
                                else
                                {
                                    if( $state == "delaware" ) 
                                    {
                                        $sc = "DE";
                                    }
                                    else
                                    {
                                        if( $state == "florida" ) 
                                        {
                                            $sc = "FL";
                                        }
                                        else
                                        {
                                            if( $state == "georgia" ) 
                                            {
                                                $sc = "GA";
                                            }
                                            else
                                            {
                                                if( $state == "hawaii" ) 
                                                {
                                                    $sc = "HI";
                                                }
                                                else
                                                {
                                                    if( $state == "idaho" ) 
                                                    {
                                                        $sc = "ID";
                                                    }
                                                    else
                                                    {
                                                        if( $state == "illinois" ) 
                                                        {
                                                            $sc = "IL";
                                                        }
                                                        else
                                                        {
                                                            if( $state == "indiana" ) 
                                                            {
                                                                $sc = "IN";
                                                            }
                                                            else
                                                            {
                                                                if( $state == "iowa" ) 
                                                                {
                                                                    $sc = "IA";
                                                                }
                                                                else
                                                                {
                                                                    if( $state == "kansas" ) 
                                                                    {
                                                                        $sc = "KS";
                                                                    }
                                                                    else
                                                                    {
                                                                        if( $state == "kentucky" ) 
                                                                        {
                                                                            $sc = "KY";
                                                                        }
                                                                        else
                                                                        {
                                                                            if( $state == "louisiana" ) 
                                                                            {
                                                                                $sc = "LA";
                                                                            }
                                                                            else
                                                                            {
                                                                                if( $state == "maine" ) 
                                                                                {
                                                                                    $sc = "ME";
                                                                                }
                                                                                else
                                                                                {
                                                                                    if( $state == "maryland" ) 
                                                                                    {
                                                                                        $sc = "MD";
                                                                                    }
                                                                                    else
                                                                                    {
                                                                                        if( $state == "massachusetts" ) 
                                                                                        {
                                                                                            $sc = "MA";
                                                                                        }
                                                                                        else
                                                                                        {
                                                                                            if( $state == "michigan" ) 
                                                                                            {
                                                                                                $sc = "MI";
                                                                                            }
                                                                                            else
                                                                                            {
                                                                                                if( $state == "minnesota" ) 
                                                                                                {
                                                                                                    $sc = "MN";
                                                                                                }
                                                                                                else
                                                                                                {
                                                                                                    if( $state == "mississippi" ) 
                                                                                                    {
                                                                                                        $sc = "MS";
                                                                                                    }
                                                                                                    else
                                                                                                    {
                                                                                                        if( $state == "missouri" ) 
                                                                                                        {
                                                                                                            $sc = "MO";
                                                                                                        }
                                                                                                        else
                                                                                                        {
                                                                                                            if( $state == "montana" ) 
                                                                                                            {
                                                                                                                $sc = "MT";
                                                                                                            }
                                                                                                            else
                                                                                                            {
                                                                                                                if( $state == "nebraska" ) 
                                                                                                                {
                                                                                                                    $sc = "NE";
                                                                                                                }
                                                                                                                else
                                                                                                                {
                                                                                                                    if( $state == "nevada" ) 
                                                                                                                    {
                                                                                                                        $sc = "NV";
                                                                                                                    }
                                                                                                                    else
                                                                                                                    {
                                                                                                                        if( $state == "new hampshire" ) 
                                                                                                                        {
                                                                                                                            $sc = "NH";
                                                                                                                        }
                                                                                                                        else
                                                                                                                        {
                                                                                                                            if( $state == "new jersey" ) 
                                                                                                                            {
                                                                                                                                $sc = "NJ";
                                                                                                                            }
                                                                                                                            else
                                                                                                                            {
                                                                                                                                if( $state == "new mexico" ) 
                                                                                                                                {
                                                                                                                                    $sc = "NM";
                                                                                                                                }
                                                                                                                                else
                                                                                                                                {
                                                                                                                                    if( $state == "new york" ) 
                                                                                                                                    {
                                                                                                                                        $sc = "NY";
                                                                                                                                    }
                                                                                                                                    else
                                                                                                                                    {
                                                                                                                                        if( $state == "north carolina" ) 
                                                                                                                                        {
                                                                                                                                            $sc = "NC";
                                                                                                                                        }
                                                                                                                                        else
                                                                                                                                        {
                                                                                                                                            if( $state == "north dakota" ) 
                                                                                                                                            {
                                                                                                                                                $sc = "ND";
                                                                                                                                            }
                                                                                                                                            else
                                                                                                                                            {
                                                                                                                                                if( $state == "ohio" ) 
                                                                                                                                                {
                                                                                                                                                    $sc = "OH";
                                                                                                                                                }
                                                                                                                                                else
                                                                                                                                                {
                                                                                                                                                    if( $state == "oklahoma" ) 
                                                                                                                                                    {
                                                                                                                                                        $sc = "OK";
                                                                                                                                                    }
                                                                                                                                                    else
                                                                                                                                                    {
                                                                                                                                                        if( $state == "oregon" ) 
                                                                                                                                                        {
                                                                                                                                                            $sc = "OR";
                                                                                                                                                        }
                                                                                                                                                        else
                                                                                                                                                        {
                                                                                                                                                            if( $state == "pennsylvania" ) 
                                                                                                                                                            {
                                                                                                                                                                $sc = "PA";
                                                                                                                                                            }
                                                                                                                                                            else
                                                                                                                                                            {
                                                                                                                                                                if( $state == "rhode island" ) 
                                                                                                                                                                {
                                                                                                                                                                    $sc = "RI";
                                                                                                                                                                }
                                                                                                                                                                else
                                                                                                                                                                {
                                                                                                                                                                    if( $state == "south carolina" ) 
                                                                                                                                                                    {
                                                                                                                                                                        $sc = "SC";
                                                                                                                                                                    }
                                                                                                                                                                    else
                                                                                                                                                                    {
                                                                                                                                                                        if( $state == "south dakota" ) 
                                                                                                                                                                        {
                                                                                                                                                                            $sc = "SD";
                                                                                                                                                                        }
                                                                                                                                                                        else
                                                                                                                                                                        {
                                                                                                                                                                            if( $state == "tennessee" ) 
                                                                                                                                                                            {
                                                                                                                                                                                $sc = "TN";
                                                                                                                                                                            }
                                                                                                                                                                            else
                                                                                                                                                                            {
                                                                                                                                                                                if( $state == "texas" ) 
                                                                                                                                                                                {
                                                                                                                                                                                    $sc = "TX";
                                                                                                                                                                                }
                                                                                                                                                                                else
                                                                                                                                                                                {
                                                                                                                                                                                    if( $state == "utah" ) 
                                                                                                                                                                                    {
                                                                                                                                                                                        $sc = "UT";
                                                                                                                                                                                    }
                                                                                                                                                                                    else
                                                                                                                                                                                    {
                                                                                                                                                                                        if( $state == "vermont" ) 
                                                                                                                                                                                        {
                                                                                                                                                                                            $sc = "VT";
                                                                                                                                                                                        }
                                                                                                                                                                                        else
                                                                                                                                                                                        {
                                                                                                                                                                                            if( $state == "virginia" ) 
                                                                                                                                                                                            {
                                                                                                                                                                                                $sc = "VA";
                                                                                                                                                                                            }
                                                                                                                                                                                            else
                                                                                                                                                                                            {
                                                                                                                                                                                                if( $state == "washington" ) 
                                                                                                                                                                                                {
                                                                                                                                                                                                    $sc = "WA";
                                                                                                                                                                                                }
                                                                                                                                                                                                else
                                                                                                                                                                                                {
                                                                                                                                                                                                    if( $state == "west virginia" ) 
                                                                                                                                                                                                    {
                                                                                                                                                                                                        $sc = "WV";
                                                                                                                                                                                                    }
                                                                                                                                                                                                    else
                                                                                                                                                                                                    {
                                                                                                                                                                                                        if( $state == "wisconsin" ) 
                                                                                                                                                                                                        {
                                                                                                                                                                                                            $sc = "WI";
                                                                                                                                                                                                        }
                                                                                                                                                                                                        else
                                                                                                                                                                                                        {
                                                                                                                                                                                                            if( $state == "wyoming" ) 
                                                                                                                                                                                                            {
                                                                                                                                                                                                                $sc = "WY";
                                                                                                                                                                                                            }

                                                                                                                                                                                                        }

                                                                                                                                                                                                    }

                                                                                                                                                                                                }

                                                                                                                                                                                            }

                                                                                                                                                                                        }

                                                                                                                                                                                    }

                                                                                                                                                                                }

                                                                                                                                                                            }

                                                                                                                                                                        }

                                                                                                                                                                    }

                                                                                                                                                                }

                                                                                                                                                            }

                                                                                                                                                        }

                                                                                                                                                    }

                                                                                                                                                }

                                                                                                                                            }

                                                                                                                                        }

                                                                                                                                    }

                                                                                                                                }

                                                                                                                            }

                                                                                                                        }

                                                                                                                    }

                                                                                                                }

                                                                                                            }

                                                                                                        }

                                                                                                    }

                                                                                                }

                                                                                            }

                                                                                        }

                                                                                    }

                                                                                }

                                                                            }

                                                                        }

                                                                    }

                                                                }

                                                            }

                                                        }

                                                    }

                                                }

                                            }

                                        }

                                    }

                                }

                            }

                        }

                    }

                }

            }

        }

    }
    else
    {
        if( $country == "CA" ) 
        {
            if( $state == "alberta" ) 
            {
                $sc = "AB";
            }
            else
            {
                if( $state == "british columbia" ) 
                {
                    $sc = "BC";
                }
                else
                {
                    if( $state == "manitoba" ) 
                    {
                        $sc = "MB";
                    }
                    else
                    {
                        if( $state == "new brunswick" ) 
                        {
                            $sc = "NB";
                        }
                        else
                        {
                            if( $state == "newfoundland" ) 
                            {
                                $sc = "NL";
                            }
                            else
                            {
                                if( $state == "northwest territories" ) 
                                {
                                    $sc = "NT";
                                }
                                else
                                {
                                    if( $state == "nova scotia" ) 
                                    {
                                        $sc = "NS";
                                    }
                                    else
                                    {
                                        if( $state == "nunavut" ) 
                                        {
                                            $sc = "NU";
                                        }
                                        else
                                        {
                                            if( $state == "ontario" ) 
                                            {
                                                $sc = "ON";
                                            }
                                            else
                                            {
                                                if( $state == "prince edward island" ) 
                                                {
                                                    $sc = "PE";
                                                }
                                                else
                                                {
                                                    if( $state == "quebec" ) 
                                                    {
                                                        $sc = "QC";
                                                    }
                                                    else
                                                    {
                                                        if( $state == "saskatchewan" ) 
                                                        {
                                                            $sc = "SK";
                                                        }
                                                        else
                                                        {
                                                            if( $state == "yukon" ) 
                                                            {
                                                                $sc = "YK";
                                                            }

                                                        }

                                                    }

                                                }

                                            }

                                        }

                                    }

                                }

                            }

                        }

                    }

                }

            }

        }

    }

    if( !$sc ) 
    {
        $sc = $ostate;
    }

    return $sc;
}

function getClientsPaymentMethod($userid)
{
    $gatewayclass = new WHMCS\Gateways();
    $paymentmethod = "";
    if( $userid ) 
    {
        $clientPaymentMethod = get_query_val("tblclients", "defaultgateway", array( "id" => $userid ));
        if( $clientPaymentMethod && $gatewayclass->isActiveGateway($clientPaymentMethod) ) 
        {
            $paymentmethod = $clientPaymentMethod;
        }

        if( !$paymentmethod ) 
        {
            $invoicePaymentMethod = get_query_val("tblinvoices", "paymentmethod", array( "userid" => $userid ), "id", "DESC", "0,1");
            if( $invoicePaymentMethod && $gatewayclass->isActiveGateway($invoicePaymentMethod) ) 
            {
                $paymentmethod = $invoicePaymentMethod;
            }

        }

    }

    if( !$paymentmethod ) 
    {
        $paymentmethod = $gatewayclass->getFirstAvailableGateway();
    }

    return $paymentmethod;
}

function clientChangeDefaultGateway($userid, $paymentmethod)
{
    $defaultgateway = get_query_val("tblclients", "defaultgateway", array( "id" => $userid ));
    if( WHMCS\Session::get("adminid") && !$paymentmethod && $defaultgateway ) 
    {
        update_query("tblclients", array( "defaultgateway" => "" ), array( "id" => $userid ));
    }

    if( $paymentmethod && $paymentmethod != $defaultgateway ) 
    {
        if( $paymentmethod == "none" ) 
        {
            update_query("tblclients", array( "defaultgateway" => "" ), array( "id" => $userid ));
        }

        $paymentmethod = get_query_val("tblpaymentgateways", "gateway", array( "gateway" => $paymentmethod ));
        if( !$paymentmethod ) 
        {
            return false;
        }

        update_query("tblclients", array( "defaultgateway" => $paymentmethod ), array( "id" => $userid ));
        update_query("tblhosting", array( "paymentmethod" => $paymentmethod ), array( "userid" => $userid ));
        update_query("tblhostingaddons", array( "paymentmethod" => $paymentmethod ), "hostingid IN (SELECT id FROM tblhosting WHERE userid=" . (int) $userid . ")");
        update_query("tbldomains", array( "paymentmethod" => $paymentmethod ), array( "userid" => $userid ));
        update_query("tblinvoices", array( "paymentmethod" => $paymentmethod ), array( "userid" => $userid, "status" => "Unpaid" ));
    }

}

function recalcPromoAmount($pid, $userid, $serviceid, $billingcycle, $recurringamount, $promoid)
{
    global $currency;
    $currency = getCurrency($userid);
    $recurringdiscount = $used = "";
    $result = select_query("tblpromotions", "", array( "id" => $promoid ));
    $data = mysql_fetch_array($result);
    $id = $data["id"];
    $type = $data["type"];
    $recurring = $data["recurring"];
    $value = $data["value"];
    if( $recurring ) 
    {
        if( $type == "Percentage" ) 
        {
            $recurringdiscount = $recurringamount * $value / 100;
        }
        else
        {
            if( $type == "Fixed Amount" ) 
            {
                if( $currency["id"] != 1 ) 
                {
                    $value = convertCurrency($value, 1, $currency["id"]);
                }

                if( $recurringamount < $value ) 
                {
                    $recurringdiscount = $recurringamount;
                }
                else
                {
                    $recurringdiscount = $value;
                }

            }
            else
            {
                if( $type == "Price Override" ) 
                {
                    if( $currency["id"] != 1 ) 
                    {
                        $value = convertCurrency($value, 1, $currency["id"]);
                    }

                    $recurringdiscount = $recurringamount - $value;
                }

            }

        }

    }

    return $recurringdiscount;
}

function doResetPWEmail($email, $answer = "")
{
    global $CONFIG;
    global $_LANG;
    global $securityquestion;
    if( !$email ) 
    {
        return $_LANG["pwresetemailrequired"];
    }

    $result = select_query("tblclients", "id,password,securityqid,securityqans", array( "email" => $email, "status" => array( "sqltype" => "NEQ", "value" => "Closed" ) ));
    $data = mysql_fetch_array($result);
    $userid = $data["id"];
    $contactid = 0;
    $password = $data["password"];
    $securityqid = $data["securityqid"];
    $securityqans = $data["securityqans"];
    if( !$userid ) 
    {
        $result = select_query("tblcontacts", "tblcontacts.id,tblcontacts.userid,tblcontacts.password", array( "tblcontacts.email" => $email, "tblcontacts.subaccount" => "1", "tblclients.status" => array( "sqltype" => "NEQ", "value" => "Closed" ) ), "", "", "", "tblclients ON tblclients.id=tblcontacts.userid");
        $data = mysql_fetch_array($result);
        $contactid = $data["id"];
        $userid = $data["userid"];
        $password = $data["password"];
    }

    if( !$userid ) 
    {
        return $_LANG["pwresetemailnotfound"];
    }

    if( $securityqid ) 
    {
        $result = select_query("tbladminsecurityquestions", "", array( "id" => $securityqid ));
        $data = mysql_fetch_array($result);
        $securityquestion = decrypt($data["question"]);
        if( !$answer ) 
        {
            return "";
        }

        if( $answer != decrypt($securityqans) ) 
        {
            return $_LANG["pwresetsecurityquestionincorrect"];
        }

    }

    $resetkey = md5($userid . rand(100000, 999999) . $password);
    if( $contactid ) 
    {
        update_query("tblcontacts", array( "pwresetkey" => $resetkey, "pwresetexpiry" => time() + 2 * 60 * 60 ), array( "id" => $contactid ));
    }
    else
    {
        update_query("tblclients", array( "pwresetkey" => $resetkey, "pwresetexpiry" => date("Y-m-d H:i:s", time() + 2 * 60 * 60) ), array( "id" => $userid ));
    }

    $reseturl = App::getSystemUrl() . "pwreset.php?key=" . $resetkey;
    sendMessage("Password Reset Validation", $userid, array( "pw_reset_url" => $reseturl, "contactid" => $contactid ));
    logActivity("Password Reset Requested", $userid);
}

function doResetPWKeyCheck($key)
{
    global $_LANG;
    $result = select_query("tblclients", "id,pwresetexpiry", array( "pwresetkey" => $key ));
    $data = mysql_fetch_array($result);
    $userid = $data["id"];
    $pwresetexpiry = new Carbon\Carbon($data["pwresetexpiry"]);
    if( !$userid ) 
    {
        $result = select_query("tblcontacts", "id,userid,pwresetexpiry", array( "pwresetkey" => $key ));
        $data = mysql_fetch_array($result);
        $userid = $data["userid"];
        $pwresetexpiry = new Carbon\Carbon($data["pwresetexpiry"]);
    }

    if( !$userid ) 
    {
        return $_LANG["pwresetkeyinvalid"];
    }

    $expired = $pwresetexpiry->year != -1 && $pwresetexpiry->isPast();
    if( $expired ) 
    {
        return $_LANG["pwresetkeyexpired"];
    }

}

function doResetPW($key, $newpw, $confirmpw)
{
    global $_LANG;
    $newpw = WHMCS\Input\Sanitize::decode($newpw);
    $confirmpw = WHMCS\Input\Sanitize::decode($confirmpw);
    if( !$key ) 
    {
        return $_LANG["pwresetemailrequired"];
    }

    $result = select_query("tblclients", "id,email,pwresetexpiry", array( "pwresetkey" => $key ));
    $data = mysql_fetch_array($result);
    $userid = $data["id"];
    $email = $data["email"];
    $pwresetexpiry = new Carbon\Carbon($data["pwresetexpiry"]);
    if( !$userid ) 
    {
        $result = select_query("tblcontacts", "id,email,userid,pwresetexpiry", array( "pwresetkey" => $key ));
        $data = mysql_fetch_array($result);
        $contactid = $data["id"];
        $userid = $data["userid"];
        $pwresetexpiry = new Carbon\Carbon($data["pwresetexpiry"]);
        $email = $data["email"];
    }

    if( !$userid ) 
    {
        return $_LANG["pwresetemailnotfound"];
    }

    $expired = $pwresetexpiry->year != -1 && $pwresetexpiry->isPast();
    if( $expired ) 
    {
        return $_LANG["pwresetkeyexpired"];
    }

    $validate = new WHMCS\Validate();
    if( $validate->validate("required", "newpw", "ordererrorpassword") && $validate->validate("pwstrength", "newpw", "pwstrengthfail") && $validate->validate("required", "confirmpw", "clientareaerrorpasswordconfirm") ) 
    {
        $validate->validate("match_value", "newpw", "clientareaerrorpasswordnotmatch", "confirmpw");
    }

    if( !$validate->hasErrors() ) 
    {
        $hasher = new WHMCS\Security\Hash\Password();
        if( $contactid ) 
        {
            update_query("tblcontacts", array( "password" => $hasher->hash(WHMCS\Input\Sanitize::decode($newpw)), "pwresetkey" => "", "pwresetexpiry" => "" ), array( "id" => $contactid ));
        }
        else
        {
            update_query("tblclients", array( "password" => $hasher->hash(WHMCS\Input\Sanitize::decode($newpw)), "pwresetkey" => "", "pwresetexpiry" => "" ), array( "id" => $userid ));
        }

        run_hook("ClientChangePassword", array( "userid" => $userid, "password" => $newpw ));
        logActivity("Password Reset Completed", $userid);
        sendMessage("Password Reset Confirmation", $userid, array( "contactid" => $contactid ));
        validateclientlogin($email, $newpw);
        redir("success=true", "pwreset.php");
    }

    return $validate->getHTMLErrorOutput();
}

function cancelUnpaidInvoicebyProductID($serviceid, $userid = "")
{
    $userid = (int) $userid;
    $serviceid = (int) $serviceid;
    if( !$userid ) 
    {
        $userid = (int) get_query_val("tblhosting", "userid", array( "id" => $serviceid ));
    }

    $addons = WHMCS\Database\Capsule::table("tblhostingaddons")->where("hostingid", "=", $serviceid)->get(array( "id" ));
    $addonIds = array(  );
    foreach( $addons as $addon ) 
    {
        $addonIds[] = $addon->id;
    }
    $result = select_query("tblinvoiceitems", "tblinvoiceitems.id,tblinvoiceitems.invoiceid", array( "type" => "Hosting", "relid" => $serviceid, "status" => "Unpaid", "tblinvoices.userid" => $userid ), "", "", "", "tblinvoices ON tblinvoices.id=tblinvoiceitems.invoiceid");
    while( $data = mysql_fetch_array($result) ) 
    {
        $itemid = $data["id"];
        $invoiceid = $data["invoiceid"];
        $result2 = select_query("tblinvoiceitems", "COUNT(*)", array( "invoiceid" => $invoiceid ));
        $data = mysql_fetch_array($result2);
        $itemcount = $data[0];
        if( 1 < $itemcount && $itemcount <= 4 ) 
        {
            $itemcount -= get_query_val("tblinvoiceitems", "COUNT(*)", array( "invoiceid" => $invoiceid, "type" => "PromoHosting", "relid" => $serviceid ));
            $itemcount -= get_query_val("tblinvoiceitems", "COUNT(*)", array( "invoiceid" => $invoiceid, "type" => "GroupDiscount" ));
            $itemcount -= get_query_val("tblinvoiceitems", "COUNT(*)", array( "invoiceid" => $invoiceid, "type" => "LateFee" ));
            if( $addonIds ) 
            {
                $itemcount -= WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceid)->where("type", "=", "Addon")->whereIn("relid", $addonIds)->count();
            }

        }

        if( $itemcount == 1 ) 
        {
            update_query("tblinvoices", array( "status" => "Cancelled" ), array( "id" => $invoiceid ));
            logActivity("Cancelled Outstanding Product Renewal Invoice - Invoice ID: " . $invoiceid . " - Service ID: " . $serviceid, $userid);
            run_hook("InvoiceCancelled", array( "invoiceid" => $invoiceid ));
        }
        else
        {
            delete_query("tblinvoiceitems", array( "id" => $itemid ));
            delete_query("tblinvoiceitems", array( "invoiceid" => $invoiceid, "type" => "PromoHosting", "relid" => $serviceid ));
            delete_query("tblinvoiceitems", array( "invoiceid" => $invoiceid, "type" => "GroupDiscount" ));
            if( !function_exists("updateInvoiceTotal") ) 
            {
                require_once(ROOTDIR . "/includes/invoicefunctions.php");
            }

            updateInvoiceTotal($invoiceid);
            logActivity("Removed Outstanding Product Renewal Invoice Line Item - Invoice ID: " . $invoiceid . " - Service ID: " . $serviceid, $userid);
        }

    }
    if( $addonIds ) 
    {
        $invoiceItems = WHMCS\Database\Capsule::table("tblinvoiceitems")->where("type", "=", "Addon")->whereIn("relid", $addonIds)->where("status", "=", "Unpaid")->where("tblinvoices.userid", "=", $userid)->join("tblinvoices", "tblinvoices.id", "=", "tblinvoiceitems.invoiceid")->get(array( "tblinvoiceitems.id", "tblinvoiceitems.relid", "tblinvoiceitems.invoiceid" ));
        foreach( $invoiceItems as $invoiceItem ) 
        {
            $itemCount = WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceItem->invoiceid)->count();
            if( 1 < $itemCount && $itemCount <= 3 ) 
            {
                $itemCount -= WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceItem->invoiceid)->where("type", "=", "GroupDiscount")->count();
                $itemCount -= WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceItem->invoiceid)->where("type", "=", "LateFee")->count();
            }

            if( $itemCount == 1 ) 
            {
                WHMCS\Database\Capsule::table("tblinvoices")->where("id", "=", $invoiceItem->invoiceid)->update(array( "status" => "Cancelled" ));
                logActivity("Cancelled Outstanding Product Addon Invoice - Invoice ID: " . $invoiceItem->invoiceid . " - Service Addon ID: " . $invoiceItem->relid, $userid);
                run_hook("InvoiceCancelled", array( "invoiceid" => $invoiceItem->invoiceid ));
            }
            else
            {
                WHMCS\Database\Capsule::table("tblinvoiceitems")->delete($invoiceItem->id);
                WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceItem->invoiceid)->where("type", "=", "GroupDiscount")->delete();
                if( !function_exists("updateInvoiceTotal") ) 
                {
                    require_once(ROOTDIR . "/includes/invoicefunctions.php");
                }

                updateInvoiceTotal($invoiceItem->invoiceid);
                logActivity("Removed Outstanding Product Renewal Invoice Line Item - Invoice ID: " . $invoiceItem->invoiceid . " - Service ID: " . $invoiceItem->relid, $userid);
            }

        }
    }

    return true;
}


