<?php
/* Copyright (C) 2001-2002	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2006-2017	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2009-2012	Regis Houssin			<regis.houssin@inodbox.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *     	\file       htdocs/public/onlinesign/newonlinesign.php
 *		\ingroup    core
 *		\brief      File to offer a way to make an online signature for a particular Dolibarr entity
 */

if (!defined('NOLOGIN')) {
	define("NOLOGIN", 1); // This means this output page does not require to be logged.
}
if (!defined('NOCSRFCHECK')) {
	define("NOCSRFCHECK", 1); // We accept to go on this page from external web site.
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1'); // Do not check IP defined into conf $dolibarr_main_restrict_ip
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}

// For MultiCompany module.
// Do not use GETPOST here, function is not defined and define must be done before including main.inc.php
// TODO This should be useless. Because entity must be retrieve from object ref and not from url.
$entity = (!empty($_GET['entity']) ? (int) $_GET['entity'] : (!empty($_POST['entity']) ? (int) $_POST['entity'] : 1));
if (is_numeric($entity)) {
	define("DOLENTITY", $entity);
}

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/payments.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';

// Load translation files
$langs->loadLangs(array("main", "other", "dict", "bills", "companies", "errors", "paybox","propal"));

// Security check
// No check on module enabled. Done later according to $validpaymentmethod

// Get parameters
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$refusepropal = GETPOST('refusepropal', 'alpha');

// Input are:
// type ('invoice','order','contractline'),
// id (object id),
// amount (required if id is empty),
// tag (a free text, required if type is empty)
// currency (iso code)

$suffix = GETPOST("suffix", 'aZ09');
$source = GETPOST("source", 'alpha');
$ref = $REF = GETPOST("ref", 'alpha');

if (empty($source)) {
	$source = 'proposal';
}

if (!$action) {
	if ($source && !$ref) {
		print $langs->trans('ErrorBadParameters')." - ref missing";
		exit;
	}
}
if (!empty($refusepropal)) {
	$action = "refusepropal";
}

// Define $urlwithroot
//$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT,'/').'$/i','',trim($dolibarr_main_url_root));
//$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
$urlwithroot = DOL_MAIN_URL_ROOT; // This is to use same domain name than current. For Paypal payment, we can use internal URL like localhost.


// Complete urls for post treatment
$SECUREKEY = GETPOST("securekey"); // Secure key

if (!empty($source)) {
	$urlok .= 'source='.urlencode($source).'&';
	$urlko .= 'source='.urlencode($source).'&';
}
if (!empty($REF)) {
	$urlok .= 'ref='.urlencode($REF).'&';
	$urlko .= 'ref='.urlencode($REF).'&';
}
if (!empty($SECUREKEY)) {
	$urlok .= 'securekey='.urlencode($SECUREKEY).'&';
	$urlko .= 'securekey='.urlencode($SECUREKEY).'&';
}
if (!empty($entity)) {
	$urlok .= 'entity='.urlencode($entity).'&';
	$urlko .= 'entity='.urlencode($entity).'&';
}
$urlok = preg_replace('/&$/', '', $urlok); // Remove last &
$urlko = preg_replace('/&$/', '', $urlko); // Remove last &

$creditor = $mysoc->name;

$object = new Propal($db);
$object->fetch(0, $ref);

/*
 * Actions
 */

if ($action == 'confirm_refusepropal') {
	$sql  = "UPDATE ".MAIN_DB_PREFIX."propal";
	$sql .= " SET fk_statut = ".((int) $object::STATUS_NOTSIGNED).", note_private = '".$object->note_private."', date_signature='".$db->idate(dol_now())."'";
	$sql .= " WHERE rowid = ".((int) $object->id);
	dol_syslog(__METHOD__, LOG_DEBUG);
	$resql = $db->query($sql);
	if (!$resql) {
		$error++;
	}
	if (!$error) {
		$db->commit();
		setEventMessage("PropalRefused");
	} else {
		$db->rollback();
	}
	$object->fetch(0, $ref);
}


/*
 * View
 */

$form = new Form($db);
$head = '';
if (!empty($conf->global->MAIN_SIGN_CSS_URL)) {
	$head = '<link rel="stylesheet" type="text/css" href="'.$conf->global->MAIN_SIGN_CSS_URL.'?lang='.$langs->defaultlang.'">'."\n";
}

$conf->dol_hide_topmenu = 1;
$conf->dol_hide_leftmenu = 1;

$replacemainarea = (empty($conf->dol_hide_leftmenu) ? '<div>' : '').'<div>';
llxHeader($head, $langs->trans("OnlineSignature"), '', '', 0, 0, '', '', '', 'onlinepaymentbody', $replacemainarea, 1);

if ($action == 'refusepropal') {
	print $form->formconfirm($_SERVER["PHP_SELF"].'?ref='.$ref, $langs->trans('RefusePropal'), $langs->trans('ConfirmRefusePropal', $object->ref), 'confirm_refusepropal', '', '', 1);
}

// Check link validity for param 'source'
if (!empty($source) && in_array($ref, array('member_ref', 'contractline_ref', 'invoice_ref', 'order_ref', ''))) {
	$langs->load("errors");
	dol_print_error_email('BADREFINONLINESIGNFORM', $langs->trans("ErrorBadLinkSourceSetButBadValueForRef", $source, $ref));
	// End of page
	llxFooter();
	$db->close();
	exit;
}

print '<span id="dolpaymentspan"></span>'."\n";
print '<div class="center">'."\n";
print '<form id="dolpaymentform" class="center" name="paymentform" action="'.$_SERVER["PHP_SELF"].'" method="POST">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">'."\n";
print '<input type="hidden" name="action" value="dosign">'."\n";
print '<input type="hidden" name="tag" value="'.GETPOST("tag", 'alpha').'">'."\n";
print '<input type="hidden" name="suffix" value="'.GETPOST("suffix", 'alpha').'">'."\n";
print '<input type="hidden" name="securekey" value="'.$SECUREKEY.'">'."\n";
print '<input type="hidden" name="entity" value="'.$entity.'" />';
print "\n";
print '<!-- Form to sign -->'."\n";

print '<table id="dolpaymenttable" summary="Payment form" class="center">'."\n";

// Show logo (search order: logo defined by ONLINE_SIGN_LOGO_suffix, then ONLINE_SIGN_LOGO_, then small company logo, large company logo, theme logo, common logo)
// Define logo and logosmall
$logosmall = $mysoc->logo_small;
$logo = $mysoc->logo;
$paramlogo = 'ONLINE_SIGN_LOGO_'.$suffix;
if (!empty($conf->global->$paramlogo)) {
	$logosmall = $conf->global->$paramlogo;
} elseif (!empty($conf->global->ONLINE_SIGN_LOGO)) {
	$logosmall = $conf->global->ONLINE_SIGN_LOGO;
}
//print '<!-- Show logo (logosmall='.$logosmall.' logo='.$logo.') -->'."\n";
// Define urllogo
$urllogo = '';
$urllogofull = '';
if (!empty($logosmall) && is_readable($conf->mycompany->dir_output.'/logos/thumbs/'.$logosmall)) {
	$urllogo = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&amp;entity='.$conf->entity.'&amp;file='.urlencode('logos/thumbs/'.$logosmall);
	$urllogofull = $dolibarr_main_url_root.'/viewimage.php?modulepart=mycompany&entity='.$conf->entity.'&file='.urlencode('logos/thumbs/'.$logosmall);
} elseif (!empty($logo) && is_readable($conf->mycompany->dir_output.'/logos/'.$logo)) {
	$urllogo = DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&amp;entity='.$conf->entity.'&amp;file='.urlencode('logos/'.$logo);
	$urllogofull = $dolibarr_main_url_root.'/viewimage.php?modulepart=mycompany&entity='.$conf->entity.'&file='.urlencode('logos/'.$logo);
}
// Output html code for logo
if ($urllogo) {
	print '<div class="backgreypublicpayment">';
	print '<div class="logopublicpayment">';
	print '<img id="dolpaymentlogo" src="'.$urllogo.'"';
	print '>';
	print '</div>';
	if (empty($conf->global->MAIN_HIDE_POWERED_BY)) {
		print '<div class="poweredbypublicpayment opacitymedium right"><a class="poweredbyhref" href="https://www.dolibarr.org?utm_medium=website&utm_source=poweredby" target="dolibarr" rel="noopener">'.$langs->trans("PoweredBy").'<br><img class="poweredbyimg" src="'.DOL_URL_ROOT.'/theme/dolibarr_logo.svg" width="80px"></a></div>';
	}
	print '</div>';
}
if (!empty($conf->global->PROPOSAL_IMAGE_PUBLIC_SIGN)) {
	print '<div class="backimagepublicproposalsign">';
	print '<img id="idEVENTORGANIZATION_IMAGE_PUBLIC_INTERFACE" src="'.$conf->global->PROPOSAL_IMAGE_PUBLIC_SIGN.'">';
	print '</div>';
}

// Output introduction text
$text = '';
if (!empty($conf->global->ONLINE_SIGN_NEWFORM_TEXT)) {
	$langs->load("members");
	$reg = array();
	if (preg_match('/^\((.*)\)$/', $conf->global->ONLINE_SIGN_NEWFORM_TEXT, $reg)) {
		$text .= $langs->trans($reg[1])."<br>\n";
	} else {
		$text .= $conf->global->ONLINE_SIGN_NEWFORM_TEXT."<br>\n";
	}
	$text = '<tr><td align="center"><br>'.$text.'<br></td></tr>'."\n";
}
if (empty($text)) {
	$text .= '<tr><td class="textpublicpayment"><br><strong>'.$langs->trans("WelcomeOnOnlineSignaturePage", $mysoc->name).'</strong></td></tr>'."\n";
	$text .= '<tr><td class="textpublicpayment opacitymedium">'.$langs->trans("ThisScreenAllowsYouToSignDocFrom", $creditor).'<br><br></td></tr>'."\n";
}
print $text;

// Output payment summary form
print '<tr><td align="center">';
print '<table with="100%" id="tablepublicpayment">';
print '<tr><td align="left" colspan="2" class="opacitymedium">'.$langs->trans("ThisIsInformationOnDocumentToSign").' :</td></tr>'."\n";

$found = false;
$error = 0;

// Signature on commercial proposal
if ($source == 'proposal') {
	$found = true;
	$langs->load("proposal");

	require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';

	$proposal = new Propal($db);
	$result = $proposal->fetch('', $ref);
	if ($result <= 0) {
		$mesg = $proposal->error;
		$error++;
	} else {
		$result = $proposal->fetch_thirdparty($proposal->socid);
	}

	// Creditor

	print '<tr class="CTableRow2"><td class="CTableRow2">'.$langs->trans("Creditor");
	print '</td><td class="CTableRow2">';
	print img_picto('', 'company', 'class="pictofixedwidth"');
	print '<b>'.$creditor.'</b>';
	print '<input type="hidden" name="creditor" value="'.$creditor.'">';
	print '</td></tr>'."\n";

	// Debitor

	print '<tr class="CTableRow2"><td class="CTableRow2">'.$langs->trans("ThirdParty");
	print '</td><td class="CTableRow2">';
	print img_picto('', 'company', 'class="pictofixedwidth"');
	print '<b>'.$proposal->thirdparty->name.'</b>';
	print '</td></tr>'."\n";

	// Object

	$text = '<b>'.$langs->trans("SignatureProposalRef", $proposal->ref).'</b>';
	print '<tr class="CTableRow2"><td class="CTableRow2 tdtop">'.$langs->trans("Designation");
	print '</td><td class="CTableRow2">'.$text;
	if ($proposal->status == $proposal::STATUS_VALIDATED) {
		$directdownloadlink = $proposal->getLastMainDocLink('proposal');
		if ($directdownloadlink) {
			print '<br><a href="'.$directdownloadlink.'">';
			print img_mime($proposal->last_main_doc, '');
			print $langs->trans("DownloadDocument").'</a>';
		}
	} else {
		/* TODO If proposal signed newer than proposal ref, get link of proposal signed

		*/
	}

	print '<input type="hidden" name="source" value="'.GETPOST("source", 'alpha').'">';
	print '<input type="hidden" name="ref" value="'.$proposal->ref.'">';
	print '</td></tr>'."\n";

	// TODO Add link to download PDF (similar code than for invoice)
}



if (!$found && !$mesg) {
	$mesg = $langs->transnoentitiesnoconv("ErrorBadParameters");
}

if ($mesg) {
	print '<tr><td class="center" colspan="2"><br><div class="warning">'.dol_escape_htmltag($mesg).'</div></td></tr>'."\n";
}

print '</table>'."\n";
print "\n";

if ($action != 'dosign') {
	if ($found && !$error) {
		// We are in a management option and no error
	} else {
		dol_print_error_email('ERRORNEWONLINESIGN');
	}
} else {
	// Print
}

print '</td></tr>'."\n";
print '<tr><td class="center">';

if ($action == "dosign" && empty($cancel)) {
	print '<div class="tablepublicpayment">';
	print '<input type="button" class="buttonDelete small" id="clearsignature" value="'.$langs->trans("ClearSignature").'">';
	print '<div id="signature" style="border:solid;"></div>';
	print '</div>';
	print '<input type="button" class="button" id="signpropal" value="'.$langs->trans("Sign").'">';
	print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
	print '<script language="JavaScript" type="text/javascript" src="'.DOL_URL_ROOT.'/includes/jquery/plugins/jSignature/jSignature.js"></script>
	<script type="text/javascript">
	$(document).ready(function() {
	  $("#signature").jSignature({color:"#000",lineWidth:4});

	  $("#signature").on("change",function(){
		$("#clearsignature").css("display","");
		$("#signpropal").attr("disabled",false);
		if(!$._data($("#signpropal")[0], "events")){
			$("#signpropal").on("click",function(){
				var signature = $("#signature").jSignature("getData", "image");
				$.ajax({
					type: "POST",
					url: "'.DOL_URL_ROOT.'/core/ajax/onlineSign.php",
					dataType: "text",
					data: {
						"action" : "importSignature",
						"signaturebase64" : signature,
						"ref" : "'.$REF.'",
						"mode" : "propale",
					},
					success: function(response) {
						if(response == "success"){
							console.log("Success on saving signature");
							window.location.replace("'.$_SERVER["SELF"].'?ref='.$ref.'");
						}else{
							console.error(response);
						}
					},
				});
			});
		}
	  });

	  $("#clearsignature").on("click",function(){
		$("#signature").jSignature("clear");
		$("#signpropal").attr("disabled",true);
		/* $("#clearsignature").css("display","none"); */
	  });

	  /* $("#clearsignature").css("display","none"); */
	  $("#signpropal").attr("disabled",true);
	});
	</script>';
} else {
	if ($object->status == $object::STATUS_SIGNED) {
		print '<br>';
		print '<span class="ok">'.$langs->trans("PropalAlreadySigned").'</span>';
	} elseif ($object->status == $object::STATUS_NOTSIGNED) {
		print '<br>';
		print '<span class="warning">'.$langs->trans("PropalAlreadyRefused").'</span>';
	} else {
		print '<input type="submit" class="butAction small wraponsmartphone marginbottomonly marginleftonly marginrightonly reposition" value="'.$langs->trans("SignPropal").'">';
		print '<input name="refusepropal" type="submit" class="butActionDelete small wraponsmartphone marginbottomonly marginleftonly marginrightonly" value="'.$langs->trans("RefusePropal").'">';
	}
}
print '</td></tr>'."\n";
print '</table>'."\n";
print '</form>'."\n";
print '</div>'."\n";
print '<br>';


htmlPrintOnlinePaymentFooter($mysoc, $langs);

llxFooter('', 'public');

$db->close();
