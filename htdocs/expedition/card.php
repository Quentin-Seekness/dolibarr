<?php
/* Copyright (C) 2003-2008	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2005-2016	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005		Simon TOSSER			<simon@kornog-computing.com>
 * Copyright (C) 2005-2012	Regis Houssin			<regis.houssin@inodbox.com>
 * Copyright (C) 2011-2017	Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2013       Florian Henry		  	<florian.henry@open-concept.pro>
 * Copyright (C) 2013       Marcos García           <marcosgdf@gmail.com>
 * Copyright (C) 2014		Cedric GROSS			<c.gross@kreiz-it.fr>
 * Copyright (C) 2014-2017	Francis Appels			<francis.appels@yahoo.com>
 * Copyright (C) 2015		Claudio Aschieri		<c.aschieri@19.coop>
 * Copyright (C) 2016-2018	Ferran Marcet			<fmarcet@2byte.es>
 * Copyright (C) 2016		Yasser Carreón			<yacasia@gmail.com>
 * Copyright (C) 2018       Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2020       Lenin Rivas         	<lenin@leninrivas.com>
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/expedition/card.php
 *	\ingroup    expedition
 *	\brief      Card of a shipment
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/sendings.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/expedition/modules_expedition.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
if (!empty($conf->product->enabled) || !empty($conf->service->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
}
if (!empty($conf->propal->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
}
if (!empty($conf->productbatch->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/product/class/productbatch.class.php';
}
if (!empty($conf->projet->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
}

// Load translation files required by the page
$langs->loadLangs(array("sendings", "companies", "bills", 'deliveries', 'orders', 'stocks', 'other', 'propal'));

if (!empty($conf->incoterm->enabled)) {
	$langs->load('incoterm');
}
if (!empty($conf->productbatch->enabled)) {
	$langs->load('productbatch');
}

$origin = GETPOST('origin', 'alpha') ?GETPOST('origin', 'alpha') : 'expedition'; // Example: commande, propal
$origin_id = GETPOST('id', 'int') ?GETPOST('id', 'int') : '';
$id = $origin_id;
if (empty($origin_id)) {
	$origin_id  = GETPOST('origin_id', 'int'); // Id of order or propal
}
if (empty($origin_id)) {
	$origin_id  = GETPOST('object_id', 'int'); // Id of order or propal
}
$ref = GETPOST('ref', 'alpha');
$line_id = GETPOST('lineid', 'int') ?GETPOST('lineid', 'int') : '';

$action		= GETPOST('action', 'alpha');
$confirm	= GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');

//PDF
$hidedetails = (GETPOST('hidedetails', 'int') ? GETPOST('hidedetails', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0));
$hidedesc = (GETPOST('hidedesc', 'int') ? GETPOST('hidedesc', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0));
$hideref = (GETPOST('hideref', 'int') ? GETPOST('hideref', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0));

$object = new Expedition($db);
$objectorder = new Commande($db);
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
$extrafields->fetch_name_optionals_label($object->table_element_line);
$extrafields->fetch_name_optionals_label($objectorder->table_element_line);

// Load object. Make an object->fetch
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('expeditioncard', 'globalcard'));

$date_delivery = dol_mktime(GETPOST('date_deliveryhour', 'int'), GETPOST('date_deliverymin', 'int'), 0, GETPOST('date_deliverymonth', 'int'), GETPOST('date_deliveryday', 'int'), GETPOST('date_deliveryyear', 'int'));

if ($id > 0 || !empty($ref)) {
	$object->fetch($id, $ref);
	$object->fetch_thirdparty();
}

// Security check
$socid = '';
if ($user->socid) {
	$socid = $user->socid;
}

$result = restrictedArea($user, 'expedition', $object->id, '');

$permissiondellink = $user->rights->expedition->delivery->creer; // Used by the include of actions_dellink.inc.php
$permissiontoadd = $user->rights->expedition->creer;


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	if ($cancel) {
		if ($origin && $origin_id > 0) {
			if ($origin == 'commande') {
				header("Location: ".DOL_URL_ROOT.'/expedition/shipment.php?id='.((int) $origin_id));
				exit;
			}
		} else {
			$action = '';
			$object->fetch($id); // show shipment also after canceling modification
		}
	}

	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php'; // Must be include, not include_once

	// Actions to build doc
	$upload_dir = $conf->expedition->dir_output.'/sending';
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';

	// Reopen
	if ($action == 'reopen' && $user->rights->expedition->creer) {
		$object->fetch($id);
		$result = $object->reOpen();
	}

	// Set incoterm
	if ($action == 'set_incoterms' && !empty($conf->incoterm->enabled)) {
		$result = $object->setIncoterms(GETPOST('incoterm_id', 'int'), GETPOST('location_incoterms', 'alpha'));
	}

	if ($action == 'setref_customer') {
		$result = $object->fetch($id);
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}

		$result = $object->setValueFrom('ref_customer', GETPOST('ref_customer', 'alpha'), '', null, 'text', '', $user, 'SHIPMENT_MODIFY');
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'editref_customer';
		} else {
			header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
			exit;
		}
	}

	if ($action == 'update_extras') {
		$object->oldcopy = dol_clone($object);

		// Fill array 'array_options' with data from update form
		$ret = $extrafields->setOptionalsFromPost(null, $object, GETPOST('attribute', 'restricthtml'));
		if ($ret < 0) {
			$error++;
		}

		if (!$error) {
			// Actions on extra fields
			$result = $object->insertExtraFields('SHIPMENT_MODIFY');
			if ($result < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$error++;
			}
		}

		if ($error) {
			$action = 'edit_extras';
		}
	}

	// Create shipment
	if ($action == 'add' && $user->rights->expedition->creer) {
		$error = 0;

		$db->begin();

		$object->note = GETPOST('note', 'alpha');
		$object->origin				= $origin;
		$object->origin_id = $origin_id;
		$object->fk_project = GETPOST('projectid', 'int');
		$object->weight				= GETPOST('weight', 'int') == '' ? "NULL" : GETPOST('weight', 'int');
		$object->sizeH				= GETPOST('sizeH', 'int') == '' ? "NULL" : GETPOST('sizeH', 'int');
		$object->sizeW				= GETPOST('sizeW', 'int') == '' ? "NULL" : GETPOST('sizeW', 'int');
		$object->sizeS				= GETPOST('sizeS', 'int') == '' ? "NULL" : GETPOST('sizeS', 'int');
		$object->size_units = GETPOST('size_units', 'int');
		$object->weight_units = GETPOST('weight_units', 'int');

		// We will loop on each line of the original document to complete the shipping object with various info and quantity to deliver
		$classname = ucfirst($object->origin);
		$objectsrc = new $classname($db);
		$objectsrc->fetch($object->origin_id);

		$object->socid = $objectsrc->socid;
		$object->ref_customer = GETPOST('ref_customer', 'alpha');
		$object->model_pdf = GETPOST('model');
		$object->date_delivery = $date_delivery; // Date delivery planed
		$object->fk_delivery_address	= $objectsrc->fk_delivery_address;
		$object->shipping_method_id		= GETPOST('shipping_method_id', 'int');
		$object->tracking_number = GETPOST('tracking_number', 'alpha');
		$object->note_private = GETPOST('note_private', 'restricthtml');
		$object->note_public = GETPOST('note_public', 'restricthtml');
		$object->fk_incoterms = GETPOST('incoterm_id', 'int');
		$object->location_incoterms = GETPOST('location_incoterms', 'alpha');

		$batch_line = array();
		$stockLine = array();
		$array_options = array();

		$num = count($objectsrc->lines);
		$totalqty = 0;

		for ($i = 0; $i < $num; $i++) {
			$idl = "idl".$i;

			$sub_qty = array();
			$subtotalqty = 0;

			$j = 0;
			$batch = "batchl".$i."_0";
			$stockLocation = "ent1".$i."_0";
			$qty = "qtyl".$i;

			if (!empty($conf->productbatch->enabled) && $objectsrc->lines[$i]->product_tobatch) {      // If product need a batch number
				if (GETPOSTISSET($batch)) {
					//shipment line with batch-enable product
					$qty .= '_'.$j;
					while (GETPOSTISSET($batch)) {
						// save line of detail into sub_qty
						$sub_qty[$j]['q'] = GETPOST($qty, 'int'); // the qty we want to move for this stock record
						$sub_qty[$j]['id_batch'] = GETPOST($batch, 'int'); // the id into llx_product_batch of stock record to move
						$subtotalqty += $sub_qty[$j]['q'];

						//var_dump($qty);var_dump($batch);var_dump($sub_qty[$j]['q']);var_dump($sub_qty[$j]['id_batch']);

						$j++;
						$batch = "batchl".$i."_".$j;
						$qty = "qtyl".$i.'_'.$j;
					}

					$batch_line[$i]['detail'] = $sub_qty; // array of details
					$batch_line[$i]['qty'] = $subtotalqty;
					$batch_line[$i]['ix_l'] = GETPOST($idl, 'int');

					$totalqty += $subtotalqty;
				} else {
					// No detail were provided for lots, so if a qty was provided, we can show an error.
					if (GETPOST($qty)) {
						// We try to set an amount
						// Case we dont use the list of available qty for each warehouse/lot
						// GUI does not allow this yet
						setEventMessages($langs->trans("StockIsRequiredToChooseWhichLotToUse"), null, 'errors');
					}
				}
			} elseif (GETPOSTISSET($stockLocation)) {
				//shipment line from multiple stock locations
				$qty .= '_'.$j;
				while (GETPOSTISSET($stockLocation)) {
					// save sub line of warehouse
					$stockLine[$i][$j]['qty'] = price2num(GETPOST($qty, 'alpha'), 'MS');
					$stockLine[$i][$j]['warehouse_id'] = GETPOST($stockLocation, 'int');
					$stockLine[$i][$j]['ix_l'] = GETPOST($idl, 'int');

					$totalqty += price2num(GETPOST($qty, 'alpha'), 'MS');

					$j++;
					$stockLocation = "ent1".$i."_".$j;
					$qty = "qtyl".$i.'_'.$j;
				}
			} else {
				//var_dump(GETPOST($qty,'alpha')); var_dump($_POST); var_dump($batch);exit;
				//shipment line for product with no batch management and no multiple stock location
				if (GETPOST($qty, 'int') > 0) {
					$totalqty += price2num(GETPOST($qty, 'alpha'), 'MS');
				}
			}

			// Extrafields
			$array_options[$i] = $extrafields->getOptionalsFromPost($object->table_element_line, $i);
			// Unset extrafield
			if (is_array($extrafields->attributes[$object->table_element_line]['label'])) {
				// Get extra fields
				foreach ($extrafields->attributes[$object->table_element_line]['label'] as $key => $value) {
					unset($_POST["options_".$key]);
				}
			}
		}

		//var_dump($batch_line[2]);

		if ($totalqty > 0) {		// There is at least one thing to ship
			//var_dump($_POST);exit;
			for ($i = 0; $i < $num; $i++) {
				$qty = "qtyl".$i;
				if (!isset($batch_line[$i])) {
					// not batch mode
					if (isset($stockLine[$i])) {
						//shipment from multiple stock locations
						$nbstockline = count($stockLine[$i]);
						for ($j = 0; $j < $nbstockline; $j++) {
							if ($stockLine[$i][$j]['qty'] > 0) {
								$ret = $object->addline($stockLine[$i][$j]['warehouse_id'], $stockLine[$i][$j]['ix_l'], $stockLine[$i][$j]['qty'], $array_options[$i]);
								if ($ret < 0) {
									setEventMessages($object->error, $object->errors, 'errors');
									$error++;
								}
							}
						}
					} else {
						if (GETPOST($qty, 'int') > 0 || (GETPOST($qty, 'int') == 0 && $conf->global->SHIPMENT_GETS_ALL_ORDER_PRODUCTS)) {
							$ent = "entl".$i;
							$idl = "idl".$i;
							$entrepot_id = is_numeric(GETPOST($ent, 'int')) ?GETPOST($ent, 'int') : GETPOST('entrepot_id', 'int');
							if ($entrepot_id < 0) {
								$entrepot_id = '';
							}
							if (!($objectsrc->lines[$i]->fk_product > 0)) {
								$entrepot_id = 0;
							}

							$ret = $object->addline($entrepot_id, GETPOST($idl, 'int'), GETPOST($qty, 'int'), $array_options[$i]);
							if ($ret < 0) {
								setEventMessages($object->error, $object->errors, 'errors');
								$error++;
							}
						}
					}
				} else {
					// batch mode
					if ($batch_line[$i]['qty'] > 0) {
						$ret = $object->addline_batch($batch_line[$i], $array_options[$i]);
						if ($ret < 0) {
							setEventMessages($object->error, $object->errors, 'errors');
							$error++;
						}
					}
				}
			}
			// Fill array 'array_options' with data from add form
			$ret = $extrafields->setOptionalsFromPost(null, $object);
			if ($ret < 0) {
				$error++;
			}

			if (!$error) {
				$ret = $object->create($user); // This create shipment (like Odoo picking) and lines of shipments. Stock movement will be done when validating shipment.
				if ($ret <= 0) {
					setEventMessages($object->error, $object->errors, 'errors');
					$error++;
				}
			}
		} else {
			$labelfieldmissing = $langs->transnoentitiesnoconv("QtyToShip");
			if (!empty($conf->stock->enabled)) {
				$labelfieldmissing .= '/'.$langs->transnoentitiesnoconv("Warehouse");
			}
			setEventMessages($langs->trans("ErrorFieldRequired", $labelfieldmissing), null, 'errors');
			$error++;
		}

		if (!$error) {
			$db->commit();
			header("Location: card.php?id=".$object->id);
			exit;
		} else {
			$db->rollback();
			$_GET["commande_id"] = GETPOST('commande_id', 'int');
			$action = 'create';
		}
	} elseif ($action == 'create_delivery' && $conf->delivery_note->enabled && $user->rights->expedition->delivery->creer) {
		// Build a receiving receipt
		$result = $object->create_delivery($user);
		if ($result > 0) {
			header("Location: ".DOL_URL_ROOT.'/delivery/card.php?action=create_delivery&id='.$result);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} elseif ($action == 'confirm_valid' && $confirm == 'yes' &&
		((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !empty($user->rights->expedition->creer))
		|| (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !empty($user->rights->expedition->shipping_advance->validate)))
	) {
		$object->fetch_thirdparty();

		$result = $object->valid($user);

		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			// Define output language
			if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
				$outputlangs = $langs;
				$newlang = '';
				if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
					$newlang = GETPOST('lang_id', 'aZ09');
				}
				if ($conf->global->MAIN_MULTILANGS && empty($newlang)) {
					$newlang = $object->thirdparty->default_lang;
				}
				if (!empty($newlang)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($newlang);
				}
				$model = $object->model_pdf;
				$ret = $object->fetch($id); // Reload to get new records

				$result = $object->generateDocument($model, $outputlangs, $hidedetails, $hidedesc, $hideref);
				if ($result < 0) {
					dol_print_error($db, $result);
				}
			}
		}
	} elseif ($action == 'confirm_cancel' && $confirm == 'yes' && $user->rights->expedition->supprimer) {
		$also_update_stock = (GETPOST('alsoUpdateStock', 'alpha') ? 1 : 0);
		$result = $object->cancel(0, $also_update_stock);
		if ($result > 0) {
			$result = $object->setStatut(-1);
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} elseif ($action == 'confirm_delete' && $confirm == 'yes' && $user->rights->expedition->supprimer) {
		$also_update_stock = (GETPOST('alsoUpdateStock', 'alpha') ? 1 : 0);
		$result = $object->delete(0, $also_update_stock);
		if ($result > 0) {
			header("Location: ".DOL_URL_ROOT.'/expedition/index.php');
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		// TODO add alternative status
		//} elseif ($action == 'reopen' && (! empty($user->rights->expedition->creer) || ! empty($user->rights->expedition->shipping_advance->validate)))
		//{
		//	$result = $object->setStatut(0);
		//	if ($result < 0)
		//	{
		//		setEventMessages($object->error, $object->errors, 'errors');
		//	}
		//}
	} elseif ($action == 'setdate_livraison' && $user->rights->expedition->creer) {
		//print "x ".$_POST['liv_month'].", ".$_POST['liv_day'].", ".$_POST['liv_year'];
		$datedelivery = dol_mktime(GETPOST('liv_hour', 'int'), GETPOST('liv_min', 'int'), 0, GETPOST('liv_month', 'int'), GETPOST('liv_day', 'int'), GETPOST('liv_year', 'int'));

		$object->fetch($id);
		$result = $object->setDeliveryDate($user, $datedelivery);
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} elseif (($action == 'settracking_number'
		|| $action == 'settracking_url'
		|| $action == 'settrueWeight'
		|| $action == 'settrueWidth'
		|| $action == 'settrueHeight'
		|| $action == 'settrueDepth'
		|| $action == 'setshipping_method_id')
		&& $user->rights->expedition->creer
		) {
		// Action update
		$error = 0;

		if ($action == 'settracking_number') {
			$object->tracking_number = trim(GETPOST('tracking_number', 'alpha'));
		}
		if ($action == 'settracking_url') {
			$object->tracking_url = trim(GETPOST('tracking_url', 'int'));
		}
		if ($action == 'settrueWeight') {
			$object->trueWeight = trim(GETPOST('trueWeight', 'int'));
			$object->weight_units = GETPOST('weight_units', 'int');
		}
		if ($action == 'settrueWidth') {
			$object->trueWidth = trim(GETPOST('trueWidth', 'int'));
		}
		if ($action == 'settrueHeight') {
						$object->trueHeight = trim(GETPOST('trueHeight', 'int'));
						$object->size_units = GETPOST('size_units', 'int');
		}
		if ($action == 'settrueDepth') {
			$object->trueDepth = trim(GETPOST('trueDepth', 'int'));
		}
		if ($action == 'setshipping_method_id') {
			$object->shipping_method_id = trim(GETPOST('shipping_method_id', 'int'));
		}

		if (!$error) {
			if ($object->update($user) >= 0) {
				header("Location: card.php?id=".$object->id);
				exit;
			}
			setEventMessages($object->error, $object->errors, 'errors');
		}

		$action = "";
	} elseif ($action == 'classifybilled') {
		$object->fetch($id);
		$result = $object->setBilled();
		if ($result >= 0) {
			header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
			exit();
		}
		setEventMessages($object->error, $object->errors, 'errors');
	} elseif ($action == 'classifyclosed') {
		$object->fetch($id);
		$result = $object->setClosed();
		if ($result >= 0) {
			header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
			exit();
		}
		setEventMessages($object->error, $object->errors, 'errors');
	} elseif ($action == 'deleteline' && !empty($line_id)) {
		// delete a line
		$object->fetch($id);
		$lines = $object->lines;
		$line = new ExpeditionLigne($db);

		$num_prod = count($lines);
		for ($i = 0; $i < $num_prod; $i++) {
			if ($lines[$i]->id == $line_id) {
				if (count($lines[$i]->details_entrepot) > 1) {
					// delete multi warehouse lines
					foreach ($lines[$i]->details_entrepot as $details_entrepot) {
						$line->id = $details_entrepot->line_id;
						if (!$error && $line->delete($user) < 0) {
							$error++;
						}
					}
				} else {
					// delete single warehouse line
					$line->id = $line_id;
					if (!$error && $line->delete($user) < 0) {
						$error++;
					}
				}
			}
			unset($_POST["lineid"]);
		}

		if (!$error) {
			header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
			exit();
		} else {
			setEventMessages($line->error, $line->errors, 'errors');
		}
	} elseif ($action == 'updateline' && $user->rights->expedition->creer && GETPOST('save')) {
		// Update a line
		// Clean parameters
		$qty = 0;
		$entrepot_id = 0;
		$batch_id = 0;

		$lines = $object->lines;
		$num_prod = count($lines);
		for ($i = 0; $i < $num_prod; $i++) {
			if ($lines[$i]->id == $line_id) {		// we have found line to update
				$line = new ExpeditionLigne($db);

				// Extrafields Lines
				$line->array_options = $extrafields->getOptionalsFromPost($object->table_element_line);
				// Unset extrafield POST Data
				if (is_array($extrafields->attributes[$object->table_element_line]['label'])) {
					foreach ($extrafields->attributes[$object->table_element_line]['label'] as $key => $value) {
						unset($_POST["options_".$key]);
					}
				}
				$line->fk_product = $lines[$i]->fk_product;
				if (is_array($lines[$i]->detail_batch) && count($lines[$i]->detail_batch) > 0) {
					// line with lot
					foreach ($lines[$i]->detail_batch as $detail_batch) {
						$lotStock = new Productbatch($db);
						$batch = "batchl".$detail_batch->fk_expeditiondet."_".$detail_batch->fk_origin_stock;
						$qty = "qtyl".$detail_batch->fk_expeditiondet.'_'.$detail_batch->id;
						$batch_id = GETPOST($batch, 'int');
						$batch_qty = GETPOST($qty, 'int');
						if (!empty($batch_id) && ($batch_id != $detail_batch->fk_origin_stock || $batch_qty != $detail_batch->qty)) {
							if ($lotStock->fetch($batch_id) > 0 && $line->fetch($detail_batch->fk_expeditiondet) > 0) {	// $line is ExpeditionLine
								if ($lines[$i]->entrepot_id != 0) {
									// allow update line entrepot_id if not multi warehouse shipping
									$line->entrepot_id = $lotStock->warehouseid;
								}

								// detail_batch can be an object with keys, or an array of ExpeditionLineBatch
								if (empty($line->detail_batch)) {
									$line->detail_batch = new stdClass();
								}

								$line->detail_batch->fk_origin_stock = $batch_id;
								$line->detail_batch->batch = $lotStock->batch;
								$line->detail_batch->id = $detail_batch->id;
								$line->detail_batch->entrepot_id = $lotStock->warehouseid;
								$line->detail_batch->qty = $batch_qty;
								if ($line->update($user) < 0) {
									setEventMessages($line->error, $line->errors, 'errors');
									$error++;
								}
							} else {
								setEventMessages($lotStock->error, $lotStock->errors, 'errors');
								$error++;
							}
						}
						unset($_POST[$batch]);
						unset($_POST[$qty]);
					}
					// add new batch
					$lotStock = new Productbatch($db);
					$batch = "batchl".$line_id."_0";
					$qty = "qtyl".$line_id."_0";
					$batch_id = GETPOST($batch, 'int');
					$batch_qty = GETPOST($qty, 'int');
					$lineIdToAddLot = 0;
					if ($batch_qty > 0 && !empty($batch_id)) {
						if ($lotStock->fetch($batch_id) > 0) {
							// check if lotStock warehouse id is same as line warehouse id
							if ($lines[$i]->entrepot_id > 0) {
								// single warehouse shipment line
								if ($lines[$i]->entrepot_id == $lotStock->warehouseid) {
									$lineIdToAddLot = $line_id;
								}
							} elseif (count($lines[$i]->details_entrepot) > 1) {
								// multi warehouse shipment lines
								foreach ($lines[$i]->details_entrepot as $detail_entrepot) {
									if ($detail_entrepot->entrepot_id == $lotStock->warehouseid) {
										$lineIdToAddLot = $detail_entrepot->line_id;
									}
								}
							}
							if ($lineIdToAddLot) {
								// add lot to existing line
								if ($line->fetch($lineIdToAddLot) > 0) {
									$line->detail_batch->fk_origin_stock = $batch_id;
									$line->detail_batch->batch = $lotStock->batch;
									$line->detail_batch->entrepot_id = $lotStock->warehouseid;
									$line->detail_batch->qty = $batch_qty;
									if ($line->update($user) < 0) {
										setEventMessages($line->error, $line->errors, 'errors');
										$error++;
									}
								} else {
									setEventMessages($line->error, $line->errors, 'errors');
									$error++;
								}
							} else {
								// create new line with new lot
								$line->origin_line_id = $lines[$i]->origin_line_id;
								$line->entrepot_id = $lotStock->warehouseid;
								$line->detail_batch[0] = new ExpeditionLineBatch($db);
								$line->detail_batch[0]->fk_origin_stock = $batch_id;
								$line->detail_batch[0]->batch = $lotStock->batch;
								$line->detail_batch[0]->entrepot_id = $lotStock->warehouseid;
								$line->detail_batch[0]->qty = $batch_qty;
								if ($object->create_line_batch($line, $line->array_options) < 0) {
									setEventMessages($object->error, $object->errors, 'errors');
									$error++;
								}
							}
						} else {
							setEventMessages($lotStock->error, $lotStock->errors, 'errors');
							$error++;
						}
					}
				} else {
					if ($lines[$i]->fk_product > 0) {
						// line without lot
						if ($lines[$i]->entrepot_id > 0) {
							// single warehouse shipment line
							$stockLocation = "entl".$line_id;
							$qty = "qtyl".$line_id;
							$line->id = $line_id;
							$line->entrepot_id = GETPOST($stockLocation, 'int');
							$line->qty = GETPOST($qty, 'int');
							if ($line->update($user) < 0) {
								setEventMessages($line->error, $line->errors, 'errors');
								$error++;
							}
							unset($_POST[$stockLocation]);
							unset($_POST[$qty]);
						} elseif (count($lines[$i]->details_entrepot) > 1) {
							// multi warehouse shipment lines
							foreach ($lines[$i]->details_entrepot as $detail_entrepot) {
								if (!$error) {
									$stockLocation = "entl".$detail_entrepot->line_id;
									$qty = "qtyl".$detail_entrepot->line_id;
									$warehouse = GETPOST($stockLocation, 'int');
									if (!empty($warehouse)) {
										$line->id = $detail_entrepot->line_id;
										$line->entrepot_id = $warehouse;
										$line->qty = GETPOST($qty, 'int');
										if ($line->update($user) < 0) {
											setEventMessages($line->error, $line->errors, 'errors');
											$error++;
										}
									}
									unset($_POST[$stockLocation]);
									unset($_POST[$qty]);
								}
							}
						} elseif (empty($conf->stock->enabled) && empty($conf->productbatch->enabled)) { // both product batch and stock are not activated.
							$qty = "qtyl".$line_id;
							$line->id = $line_id;
							$line->qty = GETPOST($qty, 'int');
							$line->entrepot_id = 0;
							if ($line->update($user) < 0) {
								setEventMessages($line->error, $line->errors, 'errors');
								$error++;
							}
							unset($_POST[$qty]);
						}
					} else {
						// Product no predefined
						$qty = "qtyl".$line_id;
						$line->id = $line_id;
						$line->qty = GETPOST($qty, 'int');
						$line->entrepot_id = 0;
						if ($line->update($user) < 0) {
							setEventMessages($line->error, $line->errors, 'errors');
							$error++;
						}
						unset($_POST[$qty]);
					}
				}
			}
		}

		unset($_POST["lineid"]);

		if (!$error) {
			if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
				// Define output language
				$outputlangs = $langs;
				$newlang = '';
				if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
					$newlang = GETPOST('lang_id', 'aZ09');
				}
				if ($conf->global->MAIN_MULTILANGS && empty($newlang)) {
					$newlang = $object->thirdparty->default_lang;
				}
				if (!empty($newlang)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($newlang);
				}

				$ret = $object->fetch($object->id); // Reload to get new records
				$object->generateDocument($object->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
			}
		} else {
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id); // To redisplay the form being edited
			exit();
		}
	} elseif ($action == 'updateline' && $user->rights->expedition->creer && GETPOST('cancel', 'alpha') == $langs->trans("Cancel")) {
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id); // To redisplay the form being edited
		exit();
	}

	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

	// Actions to send emails
	if (empty($id)) {
		$id = $facid;
	}
	$triggersendname = 'SHIPPING_SENTBYMAIL';
	$paramname = 'id';
	$mode = 'emailfromshipment';
	$trackid = 'shi'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';
}


/*
 * View
 */

$help_url = 'EN:Module_Shipments|FR:Module_Expéditions|ES:M&oacute;dulo_Expediciones|DE:Modul_Lieferungen';

llxHeader('', $langs->trans('Shipment'), 'Expedition', $help_url);

if (empty($action)) {
	$action = 'view';
}

$form = new Form($db);
$formfile = new FormFile($db);
$formproduct = new FormProduct($db);
if (!empty($conf->projet->enabled)) {
	$formproject = new FormProjets($db);
}

$product_static = new Product($db);
$shipment_static = new Expedition($db);
$warehousestatic = new Entrepot($db);

if ($action == 'create2') {
	print load_fiche_titre($langs->trans("CreateShipment"), '', 'dolly');

	print '<br>'.$langs->trans("ShipmentCreationIsDoneFromOrder");
	$action = ''; $id = ''; $ref = '';
}

// Mode creation.
if ($action == 'create') {
	$expe = new Expedition($db);

	print load_fiche_titre($langs->trans("CreateShipment"), '', 'dolly');

	if (!$origin) {
		setEventMessages($langs->trans("ErrorBadParameters"), null, 'errors');
	}

	if ($origin) {
		$classname = ucfirst($origin);

		$object = new $classname($db);
		if ($object->fetch($origin_id)) {	// This include the fetch_lines
			$soc = new Societe($db);
			$soc->fetch($object->socid);

			$author = new User($db);
			$author->fetch($object->user_author_id);

			if (!empty($conf->stock->enabled)) {
				$entrepot = new Entrepot($db);
			}

			print '<form action="'.$_SERVER["PHP_SELF"].'" method="post">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="add">';
			print '<input type="hidden" name="origin" value="'.$origin.'">';
			print '<input type="hidden" name="origin_id" value="'.$object->id.'">';
			print '<input type="hidden" name="ref_int" value="'.$object->ref_int.'">';
			if (GETPOST('entrepot_id', 'int')) {
				print '<input type="hidden" name="entrepot_id" value="'.GETPOST('entrepot_id', 'int').'">';
			}

			print dol_get_fiche_head('');

			print '<table class="border centpercent">';

			// Ref
			print '<tr><td class="titlefieldcreate fieldrequired">';
			if ($origin == 'commande' && !empty($conf->commande->enabled)) {
				print $langs->trans("RefOrder");
			}
			if ($origin == 'propal' && !empty($conf->propal->enabled)) {
				print $langs->trans("RefProposal");
			}
			print '</td><td colspan="3">';
			print $object->getNomUrl(1);
			print '</td>';
			print "</tr>\n";

			// Ref client
			print '<tr><td>';
			if ($origin == 'commande') {
				print $langs->trans('RefCustomerOrder');
			} elseif ($origin == 'propal') {
				print $langs->trans('RefCustomerOrder');
			} else {
				print $langs->trans('RefCustomer');
			}
			print '</td><td colspan="3">';
			print '<input type="text" name="ref_customer" value="'.$object->ref_client.'" />';
			print '</td>';
			print '</tr>';

			// Tiers
			print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Company').'</td>';
			print '<td colspan="3">'.$soc->getNomUrl(1).'</td>';
			print '</tr>';

			// Project
			if (!empty($conf->projet->enabled)) {
				$projectid = GETPOST('projectid', 'int') ?GETPOST('projectid', 'int') : 0;
				if (empty($projectid) && !empty($object->fk_project)) {
					$projectid = $object->fk_project;
				}
				if ($origin == 'project') {
					$projectid = ($originid ? $originid : 0);
				}

				$langs->load("projects");
				print '<tr>';
				print '<td>'.$langs->trans("Project").'</td><td colspan="2">';
				print img_picto('', 'project');
				$numprojet = $formproject->select_projects($soc->id, $projectid, 'projectid', 0);
				print ' <a class="paddingleft" href="'.DOL_URL_ROOT.'/projet/card.php?socid='.$soc->id.'&action=create&status=1&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=create&socid='.$soc->id).'"><span class="fa fa-plus-circle valignmiddle"></span></a>';
				print '</td>';
				print '</tr>';
			}

			// Date delivery planned
			print '<tr><td>'.$langs->trans("DateDeliveryPlanned").'</td>';
			print '<td colspan="3">';
			$date_delivery = ($date_delivery ? $date_delivery : $object->delivery_date); // $date_delivery comes from GETPOST
			print $form->selectDate($date_delivery ? $date_delivery : -1, 'date_delivery', 1, 1, 1);
			print "</td>\n";
			print '</tr>';

			// Note Public
			print '<tr><td>'.$langs->trans("NotePublic").'</td>';
			print '<td colspan="3">';
			$doleditor = new DolEditor('note_public', $object->note_public, '', 60, 'dolibarr_notes', 'In', 0, false, empty($conf->global->FCKEDITOR_ENABLE_NOTE_PUBLIC) ? 0 : 1, ROWS_3, '90%');
			print $doleditor->Create(1);
			print "</td></tr>";

			// Note Private
			if ($object->note_private && !$user->socid) {
				print '<tr><td>'.$langs->trans("NotePrivate").'</td>';
				print '<td colspan="3">';
				$doleditor = new DolEditor('note_private', $object->note_private, '', 60, 'dolibarr_notes', 'In', 0, false, empty($conf->global->FCKEDITOR_ENABLE_NOTE_PRIVATE) ? 0 : 1, ROWS_3, '90%');
				print $doleditor->Create(1);
				print "</td></tr>";
			}

			// Weight
			print '<tr><td>';
			print $langs->trans("Weight");
			print '</td><td colspan="3"><input name="weight" size="4" value="'.GETPOST('weight', 'int').'"> ';
			$text = $formproduct->selectMeasuringUnits("weight_units", "weight", GETPOST('weight_units', 'int'), 0, 2);
			$htmltext = $langs->trans("KeepEmptyForAutoCalculation");
			print $form->textwithpicto($text, $htmltext);
			print '</td></tr>';
			// Dim
			print '<tr><td>';
			print $langs->trans("Width").' x '.$langs->trans("Height").' x '.$langs->trans("Depth");
			print ' </td><td colspan="3"><input name="sizeW" size="4" value="'.GETPOST('sizeW', 'int').'">';
			print ' x <input name="sizeH" size="4" value="'.GETPOST('sizeH', 'int').'">';
			print ' x <input name="sizeS" size="4" value="'.GETPOST('sizeS', 'int').'">';
			print ' ';
			$text = $formproduct->selectMeasuringUnits("size_units", "size", GETPOST('size_units', 'int'), 0, 2);
			$htmltext = $langs->trans("KeepEmptyForAutoCalculation");
			print $form->textwithpicto($text, $htmltext);
			print '</td></tr>';

			// Delivery method
			print "<tr><td>".$langs->trans("DeliveryMethod")."</td>";
			print '<td colspan="3">';
			$expe->fetch_delivery_methods();
			print $form->selectarray("shipping_method_id", $expe->meths, GETPOST('shipping_method_id', 'int'), 1, 0, 0, "", 1);
			if ($user->admin) {
				print info_admin($langs->trans("YouCanChangeValuesForThisListFromDictionarySetup"), 1);
			}
			print "</td></tr>\n";

			// Tracking number
			print "<tr><td>".$langs->trans("TrackingNumber")."</td>";
			print '<td colspan="3">';
			print '<input name="tracking_number" size="20" value="'.GETPOST('tracking_number', 'alpha').'">';
			print "</td></tr>\n";

			// Other attributes
			$parameters = array('objectsrc' => $objectsrc, 'colspan' => ' colspan="3"', 'cols' => '3', 'socid' => $socid);
			$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $expe, $action); // Note that $action and $object may have been modified by hook
			print $hookmanager->resPrint;

			if (empty($reshook)) {
				// copy from order
				if ($object->fetch_optionals() > 0) {
					$expe->array_options = array_merge($expe->array_options, $object->array_options);
				}
				print $expe->showOptionals($extrafields, 'edit', $parameters);
			}


			// Incoterms
			if (!empty($conf->incoterm->enabled)) {
				print '<tr>';
				print '<td><label for="incoterm_id">'.$form->textwithpicto($langs->trans("IncotermLabel"), $object->label_incoterms, 1).'</label></td>';
				print '<td colspan="3" class="maxwidthonsmartphone">';
				print $form->select_incoterms((!empty($object->fk_incoterms) ? $object->fk_incoterms : ''), (!empty($object->location_incoterms) ? $object->location_incoterms : ''));
				print '</td></tr>';
			}

			// Document model
			include_once DOL_DOCUMENT_ROOT.'/core/modules/expedition/modules_expedition.php';
			$list = ModelePdfExpedition::liste_modeles($db);
			if (count($list) > 1) {
				print "<tr><td>".$langs->trans("DefaultModel")."</td>";
				print '<td colspan="3">';
				print $form->selectarray('model', $list, $conf->global->EXPEDITION_ADDON_PDF);
				print "</td></tr>\n";
			}

			print "</table>";

			print dol_get_fiche_end();


			// Shipment lines

			$numAsked = count($object->lines);

			print '<script type="text/javascript" language="javascript">'."\n";
			print 'jQuery(document).ready(function() {'."\n";
			print 'jQuery("#autofill").click(function() {';
			$i = 0;
			while ($i < $numAsked) {
				print 'jQuery("#qtyl'.$i.'").val(jQuery("#qtyasked'.$i.'").val() - jQuery("#qtydelivered'.$i.'").val());'."\n";
				if (!empty($conf->productbatch->enabled)) {
					print 'jQuery("#qtyl'.$i.'_'.$i.'").val(jQuery("#qtyasked'.$i.'").val() - jQuery("#qtydelivered'.$i.'").val());'."\n";
				}
				$i++;
			}
			print 'return false; });'."\n";
			print 'jQuery("#autoreset").click(function() { console.log("Reset values to 0"); jQuery(".qtyl").val(0);'."\n";
			print 'return false; });'."\n";
			print '});'."\n";
			print '</script>'."\n";

			print '<br>';

			print '<table class="noborder centpercent">';

			// Load shipments already done for same order
			$object->loadExpeditions();


			$alreadyQtyBatchSetted = $alreadyQtySetted = array();

			if ($numAsked) {
				print '<tr class="liste_titre">';
				print '<td>'.$langs->trans("Description").'</td>';
				print '<td class="center">'.$langs->trans("QtyOrdered").'</td>';
				print '<td class="center">'.$langs->trans("QtyShipped").'</td>';
				print '<td class="center">'.$langs->trans("QtyToShip");
				if (empty($conf->productbatch->enabled)) {
					print '<br><a href="#" id="autofill" class="opacitymedium link cursor cursorpointer">'.img_picto($langs->trans("Autofill"), 'autofill', 'class="paddingrightonly"').'</a>';
					print ' / ';
				} else {
					print '<br>';
				}
				print '<span id="autoreset" class="opacitymedium link cursor cursorpointer">'.img_picto($langs->trans("Reset"), 'eraser').'</span>';
				print '</td>';
				if (!empty($conf->stock->enabled)) {
					if (empty($conf->productbatch->enabled)) {
						print '<td class="left">'.$langs->trans("Warehouse").' ('.$langs->trans("Stock").')</td>';
					} else {
						print '<td class="left">'.$langs->trans("Warehouse").' / '.$langs->trans("Batch").' ('.$langs->trans("Stock").')</td>';
					}
				}
				print "</tr>\n";
			}

			$warehouse_id = GETPOST('entrepot_id', 'int');
			$warehousePicking = array();
			// get all warehouse children for picking
			if ($warehouse_id > 0) {
				$warehousePicking[] = $warehouse_id;
				$warehouseObj = new Entrepot($db);
				$warehouseObj->get_children_warehouses($warehouse_id, $warehousePicking);
			}

			$indiceAsked = 0;
			while ($indiceAsked < $numAsked) {
				$product = new Product($db);

				$line = $object->lines[$indiceAsked];

				$parameters = array('i' => $indiceAsked, 'line' => $line, 'num' => $numAsked);
				$reshook = $hookmanager->executeHooks('printObjectLine', $parameters, $object, $action);
				if ($reshook < 0) {
					setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
				}

				if (empty($reshook)) {
					// Show product and description
					$type = $line->product_type ? $line->product_type : $line->fk_product_type;
					// Try to enhance type detection using date_start and date_end for free lines where type
					// was not saved.
					if (!empty($line->date_start)) {
						$type = 1;
					}
					if (!empty($line->date_end)) {
						$type = 1;
					}

					print '<!-- line '.$line->id.' for product -->'."\n";
					print '<tr class="oddeven">'."\n";

					// Product label
					if ($line->fk_product > 0) {  // If predefined product
						$product->fetch($line->fk_product);
						$product->load_stock('warehouseopen'); // Load all $product->stock_warehouse[idwarehouse]->detail_batch
						//var_dump($product->stock_warehouse[1]);

						print '<td>';
						print '<a name="'.$line->id.'"></a>'; // ancre pour retourner sur la ligne

						// Show product and description
						$product_static->type = $line->fk_product_type;
						$product_static->id = $line->fk_product;
						$product_static->ref = $line->ref;
						$product_static->status = $line->product_tosell;
						$product_static->status_buy = $line->product_tobuy;
						$product_static->status_batch = $line->product_tobatch;

						$text = $product_static->getNomUrl(1);
						$text .= ' - '.(!empty($line->label) ? $line->label : $line->product_label);
						$description = ($conf->global->PRODUIT_DESC_IN_FORM ? '' : dol_htmlentitiesbr($line->desc));
						print $form->textwithtooltip($text, $description, 3, '', '', $i);

						// Show range
						print_date_range($db->jdate($line->date_start), $db->jdate($line->date_end));

						// Add description in form
						if (!empty($conf->global->PRODUIT_DESC_IN_FORM)) {
							print ($line->desc && $line->desc != $line->product_label) ? '<br>'.dol_htmlentitiesbr($line->desc) : '';
						}

						print '</td>';
					} else {
						print "<td>";
						if ($type == 1) {
							$text = img_object($langs->trans('Service'), 'service');
						} else {
							$text = img_object($langs->trans('Product'), 'product');
						}

						if (!empty($line->label)) {
							$text .= ' <strong>'.$line->label.'</strong>';
							print $form->textwithtooltip($text, $line->desc, 3, '', '', $i);
						} else {
							print $text.' '.nl2br($line->desc);
						}

						// Show range
						print_date_range($db->jdate($line->date_start), $db->jdate($line->date_end));
						print "</td>\n";
					}

					// unit of order
					$unit_order = '';
					if (!empty($conf->global->PRODUCT_USE_UNITS)) {
						$unit_order = measuringUnitString($line->fk_unit);
					}

					// Qty
					print '<td class="center">'.$line->qty;
					print '<input name="qtyasked'.$indiceAsked.'" id="qtyasked'.$indiceAsked.'" type="hidden" value="'.$line->qty.'">';
					print ''.$unit_order.'</td>';
					$qtyProdCom = $line->qty;

					// Qty already shipped
					print '<td class="center">';
					$quantityDelivered = $object->expeditions[$line->id];
					print $quantityDelivered;
					print '<input name="qtydelivered'.$indiceAsked.'" id="qtydelivered'.$indiceAsked.'" type="hidden" value="'.$quantityDelivered.'">';
					print ''.$unit_order.'</td>';

					// Qty to ship
					$quantityAsked = $line->qty;
					if ($line->product_type == 1 && empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
						$quantityToBeDelivered = 0;
					} else {
						$quantityToBeDelivered = $quantityAsked - $quantityDelivered;
					}

					$warehouseObject = null;
					if (count($warehousePicking) == 1 || !($line->fk_product > 0) || empty($conf->stock->enabled)) {     // If warehouse was already selected or if product is not a predefined, we go into this part with no multiwarehouse selection
						print '<!-- Case warehouse already known or product not a predefined product -->';
						//ship from preselected location
						$stock = + $product->stock_warehouse[$warehouse_id]->real; // Convert to number
						$deliverableQty = min($quantityToBeDelivered, $stock);
						if ($deliverableQty < 0) {
							$deliverableQty = 0;
						}
						if (empty($conf->productbatch->enabled) || !$product->hasbatch()) {
							// Quantity to send
							print '<td class="center">';
							if ($line->product_type == Product::TYPE_PRODUCT || !empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
								if (GETPOST('qtyl'.$indiceAsked, 'int')) {
									$deliverableQty = GETPOST('qtyl'.$indiceAsked, 'int');
								}
								print '<input name="idl'.$indiceAsked.'" type="hidden" value="'.$line->id.'">';
								print '<input name="qtyl'.$indiceAsked.'" id="qtyl'.$indiceAsked.'" class="qtyl center" type="text" size="4" value="'.$deliverableQty.'">';
							} else {
								print $langs->trans("NA");
							}
							print '</td>';

							// Stock
							if (!empty($conf->stock->enabled)) {
								print '<td class="left">';
								if ($line->product_type == Product::TYPE_PRODUCT || !empty($conf->global->STOCK_SUPPORTS_SERVICES)) {   // Type of product need stock change ?
									// Show warehouse combo list
									$ent = "entl".$indiceAsked;
									$idl = "idl".$indiceAsked;
									$tmpentrepot_id = is_numeric(GETPOST($ent, 'int')) ?GETPOST($ent, 'int') : $warehouse_id;
									if ($line->fk_product > 0) {
										print '<!-- Show warehouse selection -->';

										$stockMin = false;
										if (empty($conf->global->STOCK_ALLOW_NEGATIVE_TRANSFER)) {
											$stockMin = 0;
										}
										print $formproduct->selectWarehouses($tmpentrepot_id, 'entl'.$indiceAsked, '', 1, 0, $line->fk_product, '', 1, 0, array(), 'minwidth200', '', 1, $stockMin, 'stock DESC, e.ref');

										if ($tmpentrepot_id > 0 && $tmpentrepot_id == $warehouse_id) {
											//print $stock.' '.$quantityToBeDelivered;
											if ($stock < $quantityToBeDelivered) {
												print ' '.img_warning($langs->trans("StockTooLow")); // Stock too low for this $warehouse_id but you can change warehouse
											}
										}
									}
								} else {
									print $langs->trans("Service");
								}
								print '</td>';
							}

							print "</tr>\n";

							// Show subproducts of product
							if (!empty($conf->global->PRODUIT_SOUSPRODUITS) && $line->fk_product > 0) {
								$product->get_sousproduits_arbo();
								$prods_arbo = $product->get_arbo_each_prod($qtyProdCom);
								if (count($prods_arbo) > 0) {
									foreach ($prods_arbo as $key => $value) {
										//print $value[0];
										$img = '';
										if ($value['stock'] < $value['stock_alert']) {
											$img = img_warning($langs->trans("StockTooLow"));
										}
										print "<tr class=\"oddeven\"><td>&nbsp; &nbsp; &nbsp; ->
											<a href=\"".DOL_URL_ROOT."/product/card.php?id=".$value['id']."\">".$value['fullpath']."
											</a> (".$value['nb'].")</td><td class=\"center\"> ".$value['nb_total']."</td><td>&nbsp;</td><td>&nbsp;</td>
											<td class=\"center\">".$value['stock']." ".$img."</td></tr>";
									}
								}
							}
						} else {
							// Product need lot
							print '<td></td><td></td></tr>'; // end line and start a new one for lot/serial
							print '<!-- Case product need lot -->';

							$staticwarehouse = new Entrepot($db);
							if ($warehouse_id > 0) {
								$staticwarehouse->fetch($warehouse_id);
							}

							$subj = 0;
							// Define nb of lines suggested for this order line
							$nbofsuggested = 0;
							if (is_object($product->stock_warehouse[$warehouse_id]) && count($product->stock_warehouse[$warehouse_id]->detail_batch)) {
								foreach ($product->stock_warehouse[$warehouse_id]->detail_batch as $dbatch) {
									$nbofsuggested++;
								}
							}
							print '<input name="idl'.$indiceAsked.'" type="hidden" value="'.$line->id.'">';
							if (is_object($product->stock_warehouse[$warehouse_id]) && count($product->stock_warehouse[$warehouse_id]->detail_batch)) {
								foreach ($product->stock_warehouse[$warehouse_id]->detail_batch as $dbatch) {	// $dbatch is instance of Productbatch
									//var_dump($dbatch);
									$batchStock = + $dbatch->qty; // To get a numeric
									$deliverableQty = min($quantityToBeDelivered, $batchStock);
									print '<!-- subj='.$subj.'/'.$nbofsuggested.' --><tr '.((($subj + 1) == $nbofsuggested) ? $bc[$var] : '').'>';
									print '<td colspan="3" ></td><td class="center">';
									print '<input class="qtyl" name="qtyl'.$indiceAsked.'_'.$subj.'" id="qtyl'.$indiceAsked.'_'.$subj.'" type="text" size="4" value="'.$deliverableQty.'">';
									print '</td>';

									print '<!-- Show details of lot -->';
									print '<td class="left">';

									print $staticwarehouse->getNomUrl(0).' / ';

									print '<input name="batchl'.$indiceAsked.'_'.$subj.'" type="hidden" value="'.$dbatch->id.'">';

									$detail = '';
									$detail .= $langs->trans("Batch").': '.$dbatch->batch;
									if (empty($conf->global->PRODUCT_DISABLE_SELLBY)) {
										$detail .= ' - '.$langs->trans("SellByDate").': '.dol_print_date($dbatch->sellby, "day");
									}
									if (empty($conf->global->PRODUCT_DISABLE_EATBY)) {
										$detail .= ' - '.$langs->trans("EatByDate").': '.dol_print_date($dbatch->eatby, "day");
									}
									$detail .= ' - '.$langs->trans("Qty").': '.$dbatch->qty;
									$detail .= '<br>';
									print $detail;

									$quantityToBeDelivered -= $deliverableQty;
									if ($quantityToBeDelivered < 0) {
										$quantityToBeDelivered = 0;
									}
									$subj++;
									print '</td></tr>';
								}
							} else {
								print '<!-- Case there is no details of lot at all -->';
								print '<tr class="oddeven"><td colspan="3"></td><td class="center">';
								print '<input class="qtyl" name="qtyl'.$indiceAsked.'_'.$subj.'" id="qtyl'.$indiceAsked.'_'.$subj.'" type="text" size="4" value="0" disabled="disabled"> ';
								print '</td>';

								print '<td class="left">';
								print img_warning().' '.$langs->trans("NoProductToShipFoundIntoStock", $staticwarehouse->label);
								print '</td></tr>';
							}
						}
					} else {
						// ship from multiple locations
						if (empty($conf->productbatch->enabled) || !$product->hasbatch()) {
							print '<!-- Case warehouse not already known and product does not need lot -->';
							print '<td></td><td></td></tr>'."\n"; // end line and start a new one for each warehouse

							print '<input name="idl'.$indiceAsked.'" type="hidden" value="'.$line->id.'">';
							$subj = 0;
							// Define nb of lines suggested for this order line
							$nbofsuggested = 0;

							foreach ($product->stock_warehouse as $warehouse_id => $stock_warehouse) {
								if ($stock_warehouse->real > 0) {
									$nbofsuggested++;
								}
							}
							$tmpwarehouseObject = new Entrepot($db);
							foreach ($product->stock_warehouse as $warehouse_id => $stock_warehouse) {    // $stock_warehouse is product_stock
								if (!empty($warehousePicking) && !in_array($warehouse_id, $warehousePicking)) {
									// if a warehouse was selected by user, picking is limited to this warehouse and his children
									continue;
								}

								$tmpwarehouseObject->fetch($warehouse_id);
								if ($stock_warehouse->real > 0) {
									$stock = + $stock_warehouse->real; // Convert it to number
									$deliverableQty = min($quantityToBeDelivered, $stock);
									$deliverableQty = max(0, $deliverableQty);
									// Quantity to send
									print '<!-- subj='.$subj.'/'.$nbofsuggested.' --><tr '.((($subj + 1) == $nbofsuggested) ? $bc[$var] : '').'>';
									print '<td colspan="3" ></td><td class="center"><!-- qty to ship (no lot management for product line indiceAsked='.$indiceAsked.') -->';
									if ($line->product_type == Product::TYPE_PRODUCT || !empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
										if (isset($alreadyQtySetted[$line->fk_product][intval($warehouse_id)])) {
											$deliverableQty = min($quantityToBeDelivered, $stock - $alreadyQtySetted[$line->fk_product][intval($warehouse_id)]);
										} else {
											if (!isset($alreadyQtySetted[$line->fk_product])) {
												$alreadyQtySetted[$line->fk_product] = array();
											}

											$deliverableQty = min($quantityToBeDelivered, $stock);
										}

										if ($deliverableQty < 0) $deliverableQty = 0;

										$tooltip = '';
										if (!empty($alreadyQtySetted[$line->fk_product][intval($warehouse_id)])) {
											$tooltip = ' class="classfortooltip" title="'.$langs->trans('StockQuantitiesAlreadyAllocatedOnPreviousLines').' : '.$alreadyQtySetted[$line->fk_product][intval($warehouse_id)].'" ';
										}

										$alreadyQtySetted[$line->fk_product][intval($warehouse_id)] = $deliverableQty + $alreadyQtySetted[$line->fk_product][intval($warehouse_id)];

										$inputName = 'qtyl'.$indiceAsked.'_'.$subj;
										if (GETPOSTISSET($inputName)) {
											$deliverableQty = GETPOST($inputName, 'int');
										}

										print '<input '.$tooltip.' name="qtyl'.$indiceAsked.'_'.$subj.'" id="qtyl'.$indiceAsked.'" type="text" size="4" value="'.$deliverableQty.'">';
										print '<input name="ent1'.$indiceAsked.'_'.$subj.'" type="hidden" value="'.$warehouse_id.'">';
									} else {
										print $langs->trans("NA");
									}
									print '</td>';

									// Stock
									if (!empty($conf->stock->enabled)) {
										print '<td class="left">';
										if ($line->product_type == Product::TYPE_PRODUCT || !empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
											print $tmpwarehouseObject->getNomUrl(0).' ';

											print '<!-- Show details of stock -->';
											print '('.$stock.')';
										} else {
											print $langs->trans("Service");
										}
										print '</td>';
									}
									$quantityToBeDelivered -= $deliverableQty;
									if ($quantityToBeDelivered < 0) {
										$quantityToBeDelivered = 0;
									}
									$subj++;
									print "</tr>\n";
								}
							}
							// Show subproducts of product (not recommanded)
							if (!empty($conf->global->PRODUIT_SOUSPRODUITS) && $line->fk_product > 0) {
								$product->get_sousproduits_arbo();
								$prods_arbo = $product->get_arbo_each_prod($qtyProdCom);
								if (count($prods_arbo) > 0) {
									foreach ($prods_arbo as $key => $value) {
										//print $value[0];
										$img = '';
										if ($value['stock'] < $value['stock_alert']) {
											$img = img_warning($langs->trans("StockTooLow"));
										}
										print '<tr class"oddeven"><td>';
										print "&nbsp; &nbsp; &nbsp; ->
										<a href=\"".DOL_URL_ROOT."/product/card.php?id=".$value['id']."\">".$value['fullpath']."
										</a> (".$value['nb'].")</td><td class=\"center\"> ".$value['nb_total']."</td><td>&nbsp;</td><td>&nbsp;</td>
										<td class=\"center\">".$value['stock']." ".$img."</td>";
										print "</tr>";
									}
								}
							}
						} else {
							print '<!-- Case warehouse not already known and product need lot -->';
							print '<td></td><td></td></tr>'; // end line and start a new one for lot/serial

							$subj = 0;
							print '<input name="idl'.$indiceAsked.'" type="hidden" value="'.$line->id.'">';

							$tmpwarehouseObject = new Entrepot($db);
							$productlotObject = new Productlot($db);

							// Define nb of lines suggested for this order line
							$nbofsuggested = 0;
							foreach ($product->stock_warehouse as $warehouse_id => $stock_warehouse) {
								if (($stock_warehouse->real > 0) && (count($stock_warehouse->detail_batch))) {
									$nbofsuggested+=count($stock_warehouse->detail_batch);
								}
							}

							foreach ($product->stock_warehouse as $warehouse_id => $stock_warehouse) {
								$tmpwarehouseObject->fetch($warehouse_id);
								if (($stock_warehouse->real > 0) && (count($stock_warehouse->detail_batch))) {
									foreach ($stock_warehouse->detail_batch as $dbatch) {
										$batchStock = + $dbatch->qty; // To get a numeric
										if (isset($alreadyQtyBatchSetted[$line->fk_product][$dbatch->batch][intval($warehouse_id)])) {
											$deliverableQty = min($quantityToBeDelivered, $batchStock - $alreadyQtyBatchSetted[$line->fk_product][$dbatch->batch][intval($warehouse_id)]);
										} else {
											if (!isset($alreadyQtyBatchSetted[$line->fk_product])) {
												$alreadyQtyBatchSetted[$line->fk_product] = array();
											}

											if (!isset($alreadyQtyBatchSetted[$line->fk_product][$dbatch->batch])) {
												$alreadyQtyBatchSetted[$line->fk_product][$dbatch->batch] = array();
											}

											$deliverableQty = min($quantityToBeDelivered, $batchStock);
										}

										if ($deliverableQty < 0) $deliverableQty = 0;

										$inputName = 'qtyl'.$indiceAsked.'_'.$subj;
										if (GETPOSTISSET($inputName)) {
											$deliverableQty = GETPOST($inputName, 'int');
										}

										$tooltipClass = $tooltipTitle = '';
										if (!empty($alreadyQtyBatchSetted[$line->fk_product][$dbatch->batch][intval($warehouse_id)])) {
											$tooltipClass = ' classfortooltip';
											$tooltipTitle = $langs->trans('StockQuantitiesAlreadyAllocatedOnPreviousLines').' : '.$alreadyQtyBatchSetted[$line->fk_product][$dbatch->batch][intval($warehouse_id)];
										}
										$alreadyQtyBatchSetted[$line->fk_product][$dbatch->batch][intval($warehouse_id)] = $deliverableQty + $alreadyQtyBatchSetted[$line->fk_product][$dbatch->batch][intval($warehouse_id)];

										print '<!-- subj='.$subj.'/'.$nbofsuggested.' --><tr '.((($subj + 1) == $nbofsuggested) ? $bc[$var] : '').'><td colspan="3"></td><td class="center">';
										print '<input class="qtyl '.$tooltipClass.'" title="'.$tooltipTitle.'" name="'.$inputName.'" id="'.$inputName.'" type="text" size="4" value="'.$deliverableQty.'">';
										print '</td>';

										print '<td class="left">';

										print $tmpwarehouseObject->getNomUrl(0).' / ';

										print '<!-- Show details of lot -->';
										print '<input name="batchl'.$indiceAsked.'_'.$subj.'" type="hidden" value="'.$dbatch->id.'">';

										//print '|'.$line->fk_product.'|'.$dbatch->batch.'|<br>';
										print $langs->trans("Batch").': ';
										$result = $productlotObject->fetch(0, $line->fk_product, $dbatch->batch);
										if ($result > 0) {
											print $productlotObject->getNomUrl(1);
										} else {
											print 'TableLotIncompleteRunRepairWithParamStandardEqualConfirmed';
										}
										print ' ('.$dbatch->qty.')';
										$quantityToBeDelivered -= $deliverableQty;
										if ($quantityToBeDelivered < 0) {
											$quantityToBeDelivered = 0;
										}
										//dol_syslog('deliverableQty = '.$deliverableQty.' batchStock = '.$batchStock);
										$subj++;
										print '</td></tr>';
									}
								}
							}
						}
						if ($subj == 0) { // Line not shown yet, we show it
							$warehouse_selected_id = GETPOST('entrepot_id', 'int');

							print '<!-- line not shown yet, we show it -->';
							print '<tr class="oddeven"><td colspan="3"></td><td class="center">';

							if ($line->product_type == Product::TYPE_PRODUCT || !empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
								$disabled = '';
								if (!empty($conf->productbatch->enabled) && $product->hasbatch()) {
									$disabled = 'disabled="disabled"';
								}
								if ($warehouse_selected_id <= 0) {		// We did not force a given warehouse, so we won't have no warehouse to change qty.
									$disabled = 'disabled="disabled"';
								}
								print '<input class="qtyl" name="qtyl'.$indiceAsked.'_'.$subj.'" id="qtyl'.$indiceAsked.'_'.$subj.'" type="text" size="4" value="0"'.($disabled ? ' '.$disabled : '').'> ';
							} else {
								print $langs->trans("NA");
							}
							print '</td>';

							print '<td class="left">';
							if ($line->product_type == Product::TYPE_PRODUCT || !empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
								if ($warehouse_selected_id > 0) {
									$warehouseObject = new Entrepot($db);
									$warehouseObject->fetch($warehouse_selected_id);
									print img_warning().' '.$langs->trans("NoProductToShipFoundIntoStock", $warehouseObject->label);
								} else {
									if ($line->fk_product) {
										print img_warning().' '.$langs->trans("StockTooLow");
									} else {
										print '';
									}
								}
							} else {
								print $langs->trans("Service");
							}
							print '</td>';
							print '</tr>';
						}
					}

					// Line extrafield
					if (!empty($extrafields)) {
						//var_dump($line);
						$colspan = 5;
						$expLine = new ExpeditionLigne($db);

						$srcLine = new OrderLine($db);
						$srcLine->id = $line->id;
						$srcLine->fetch_optionals(); // fetch extrafields also available in orderline
						$expLine->array_options = array_merge($expLine->array_options, $srcLine->array_options);

						print $expLine->showOptionals($extrafields, 'edit', array('style'=>'class="drag drop oddeven"', 'colspan'=>$colspan), $indiceAsked, '', 1);
					}
				}

				$indiceAsked++;
			}

			print "</table>";

			print '<br>';

			print $form->buttonsSaveCancel("Create");

			print '</form>';

			print '<br>';
		} else {
			dol_print_error($db);
		}
	}
} elseif ($id || $ref) {
	/* *************************************************************************** */
	/*                                                                             */
	/* Edit and view mode                                                          */
	/*                                                                             */
	/* *************************************************************************** */
	$lines = $object->lines;

	$num_prod = count($lines);

	if ($object->id > 0) {
		if (!empty($object->origin) && $object->origin_id > 0) {
			$typeobject = $object->origin;
			$origin = $object->origin;
			$origin_id = $object->origin_id;
			$object->fetch_origin(); // Load property $object->commande, $object->propal, ...
		}

		$soc = new Societe($db);
		$soc->fetch($object->socid);

		$res = $object->fetch_optionals();

		$head = shipping_prepare_head($object);
		print dol_get_fiche_head($head, 'shipping', $langs->trans("Shipment"), -1, $object->picto);

		$formconfirm = '';

		// Confirm deleteion
		if ($action == 'delete') {
			$formquestion = array();
			if ($object->statut == Expedition::STATUS_CLOSED && !empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT_CLOSE)) {
				$formquestion = array(
						array(
							'label' => $langs->trans('ShipmentIncrementStockOnDelete'),
							'name' => 'alsoUpdateStock',
							'type' => 'checkbox',
							'value' => 0
						),
					);
			}
			$formconfirm = $form->formconfirm(
				$_SERVER['PHP_SELF'].'?id='.$object->id,
				$langs->trans('DeleteSending'),
				$langs->trans("ConfirmDeleteSending", $object->ref),
				'confirm_delete',
				$formquestion,
				0,
				1
			);
		}

		// Confirmation validation
		if ($action == 'valid') {
			$objectref = substr($object->ref, 1, 4);
			if ($objectref == 'PROV') {
				$numref = $object->getNextNumRef($soc);
			} else {
				$numref = $object->ref;
			}

			$text = $langs->trans("ConfirmValidateSending", $numref);

			if (!empty($conf->notification->enabled)) {
				require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';
				$notify = new Notify($db);
				$text .= '<br>';
				$text .= $notify->confirmMessage('SHIPPING_VALIDATE', $object->socid, $object);
			}

			$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('ValidateSending'), $text, 'confirm_valid', '', 0, 1);
		}
		// Confirm cancelation
		if ($action == 'cancel') {
			$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('CancelSending'), $langs->trans("ConfirmCancelSending", $object->ref), 'confirm_cancel', '', 0, 1);
		}

		// Call Hook formConfirm
		$parameters = array('formConfirm' => $formconfirm);
		$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if (empty($reshook)) {
			$formconfirm .= $hookmanager->resPrint;
		} elseif ($reshook > 0) {
			$formconfirm = $hookmanager->resPrint;
		}

		// Print form confirm
		print $formconfirm;

		// Calculate totalWeight and totalVolume for all products
		// by adding weight and volume of each product line.
		$tmparray = $object->getTotalWeightVolume();
		$totalWeight = $tmparray['weight'];
		$totalVolume = $tmparray['volume'];


		if ($typeobject == 'commande' && $object->$typeobject->id && !empty($conf->commande->enabled)) {
			$objectsrc = new Commande($db);
			$objectsrc->fetch($object->$typeobject->id);
		}
		if ($typeobject == 'propal' && $object->$typeobject->id && !empty($conf->propal->enabled)) {
			$objectsrc = new Propal($db);
			$objectsrc->fetch($object->$typeobject->id);
		}

		// Shipment card
		$linkback = '<a href="'.DOL_URL_ROOT.'/expedition/list.php?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';
		$morehtmlref = '<div class="refidno">';
		// Ref customer shipment
		$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_customer', $object->ref_customer, $object, $user->rights->expedition->creer, 'string', '', 0, 1);
		$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_customer', $object->ref_customer, $object, $user->rights->expedition->creer, 'string', '', null, null, '', 1);
		// Thirdparty
		$morehtmlref .= '<br>'.$langs->trans('ThirdParty').' : '.$object->thirdparty->getNomUrl(1);
		// Project
		if (!empty($conf->projet->enabled)) {
			$langs->load("projects");
			$morehtmlref .= '<br>'.$langs->trans('Project').' ';
			if (0) {    // Do not change on shipment
				if ($action != 'classify') {
					$morehtmlref .= '<a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?action=classify&token='.newToken().'&id='.$object->id.'">'.img_edit($langs->transnoentitiesnoconv('SetProject')).'</a> : ';
				}
				if ($action == 'classify') {
					// $morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'projectid', 0, 0, 1, 1);
					$morehtmlref .= '<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
					$morehtmlref .= '<input type="hidden" name="action" value="classin">';
					$morehtmlref .= '<input type="hidden" name="token" value="'.newToken().'">';
					$morehtmlref .= $formproject->select_projects($object->socid, $object->fk_project, 'projectid', $maxlength, 0, 1, 0, 1, 0, 0, '', 1);
					$morehtmlref .= '<input type="submit" class="button button-edit" value="'.$langs->trans("Modify").'">';
					$morehtmlref .= '</form>';
				} else {
					$morehtmlref .= $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id, $object->socid, $object->fk_project, 'none', 0, 0, 0, 1);
				}
			} else {
				// We don't have project on shipment, so we will use the project or source object instead
				// TODO Add project on shipment
				$morehtmlref .= ' : ';
				if (!empty($objectsrc->fk_project)) {
					$proj = new Project($db);
					$proj->fetch($objectsrc->fk_project);
					$morehtmlref .= '<a href="'.DOL_URL_ROOT.'/projet/card.php?id='.$objectsrc->fk_project.'" title="'.$langs->trans('ShowProject').'">';
					$morehtmlref .= $proj->ref;
					$morehtmlref .= '</a>';
				} else {
					$morehtmlref .= '';
				}
			}
		}
		$morehtmlref .= '</div>';


		dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);


		print '<div class="fichecenter">';
		print '<div class="fichehalfleft">';
		print '<div class="underbanner clearboth"></div>';

		print '<table class="border tableforfield" width="100%">';

		// Linked documents
		if ($typeobject == 'commande' && $object->$typeobject->id && !empty($conf->commande->enabled)) {
			print '<tr><td>';
			print $langs->trans("RefOrder").'</td>';
			print '<td colspan="3">';
			print $objectsrc->getNomUrl(1, 'commande');
			print "</td>\n";
			print '</tr>';
		}
		if ($typeobject == 'propal' && $object->$typeobject->id && !empty($conf->propal->enabled)) {
			print '<tr><td>';
			print $langs->trans("RefProposal").'</td>';
			print '<td colspan="3">';
			print $objectsrc->getNomUrl(1, 'expedition');
			print "</td>\n";
			print '</tr>';
		}

		// Date creation
		print '<tr><td class="titlefield">'.$langs->trans("DateCreation").'</td>';
		print '<td colspan="3">'.dol_print_date($object->date_creation, "dayhour")."</td>\n";
		print '</tr>';

		// Delivery date planned
		print '<tr><td height="10">';
		print '<table class="nobordernopadding" width="100%"><tr><td>';
		print $langs->trans('DateDeliveryPlanned');
		print '</td>';

		if ($action != 'editdate_livraison') {
			print '<td class="right"><a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?action=editdate_livraison&token='.newToken().'&id='.$object->id.'">'.img_edit($langs->trans('SetDeliveryDate'), 1).'</a></td>';
		}
		print '</tr></table>';
		print '</td><td colspan="2">';
		if ($action == 'editdate_livraison') {
			print '<form name="setdate_livraison" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="setdate_livraison">';
			print $form->selectDate($object->date_delivery ? $object->date_delivery : -1, 'liv_', 1, 1, '', "setdate_livraison", 1, 0);
			print '<input type="submit" class="button button-edit" value="'.$langs->trans('Modify').'">';
			print '</form>';
		} else {
			print $object->date_delivery ? dol_print_date($object->date_delivery, 'dayhour') : '&nbsp;';
		}
		print '</td>';
		print '</tr>';

		// Weight
		print '<tr><td>';
		print $form->editfieldkey("Weight", 'trueWeight', $object->trueWeight, $object, $user->rights->expedition->creer);
		print '</td><td colspan="3">';

		if ($action == 'edittrueWeight') {
			print '<form name="settrueweight" action="'.$_SERVER["PHP_SELF"].'" method="post">';
			print '<input name="action" value="settrueWeight" type="hidden">';
			print '<input name="id" value="'.$object->id.'" type="hidden">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input id="trueWeight" name="trueWeight" value="'.$object->trueWeight.'" type="text" class="width50">';
			print $formproduct->selectMeasuringUnits("weight_units", "weight", $object->weight_units, 0, 2);
			print ' <input class="button" name="modify" value="'.$langs->trans("Modify").'" type="submit">';
			print ' <input class="button button-cancel" name="cancel" value="'.$langs->trans("Cancel").'" type="submit">';
			print '</form>';
		} else {
			print $object->trueWeight;
			print ($object->trueWeight && $object->weight_units != '') ? ' '.measuringUnitString(0, "weight", $object->weight_units) : '';
		}

		// Calculated
		if ($totalWeight > 0) {
			if (!empty($object->trueWeight)) {
				print ' ('.$langs->trans("SumOfProductWeights").': ';
			}
			print showDimensionInBestUnit($totalWeight, 0, "weight", $langs, isset($conf->global->MAIN_WEIGHT_DEFAULT_ROUND) ? $conf->global->MAIN_WEIGHT_DEFAULT_ROUND : -1, isset($conf->global->MAIN_WEIGHT_DEFAULT_UNIT) ? $conf->global->MAIN_WEIGHT_DEFAULT_UNIT : 'no');
			if (!empty($object->trueWeight)) {
				print ')';
			}
		}
		print '</td></tr>';

		// Width
		print '<tr><td>'.$form->editfieldkey("Width", 'trueWidth', $object->trueWidth, $object, $user->rights->expedition->creer).'</td><td colspan="3">';
		print $form->editfieldval("Width", 'trueWidth', $object->trueWidth, $object, $user->rights->expedition->creer);
		print ($object->trueWidth && $object->width_units != '') ? ' '.measuringUnitString(0, "size", $object->width_units) : '';
		print '</td></tr>';

		// Height
		print '<tr><td>'.$form->editfieldkey("Height", 'trueHeight', $object->trueHeight, $object, $user->rights->expedition->creer).'</td><td colspan="3">';
		if ($action == 'edittrueHeight') {
			print '<form name="settrueHeight" action="'.$_SERVER["PHP_SELF"].'" method="post">';
			print '<input name="action" value="settrueHeight" type="hidden">';
			print '<input name="id" value="'.$object->id.'" type="hidden">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input id="trueHeight" name="trueHeight" value="'.$object->trueHeight.'" type="text" class="width50">';
			print $formproduct->selectMeasuringUnits("size_units", "size", $object->size_units, 0, 2);
			print ' <input class="button" name="modify" value="'.$langs->trans("Modify").'" type="submit">';
			print ' <input class="button button-cancel" name="cancel" value="'.$langs->trans("Cancel").'" type="submit">';
			print '</form>';
		} else {
			print $object->trueHeight;
			print ($object->trueHeight && $object->height_units != '') ? ' '.measuringUnitString(0, "size", $object->height_units) : '';
		}

		print '</td></tr>';

		// Depth
		print '<tr><td>'.$form->editfieldkey("Depth", 'trueDepth', $object->trueDepth, $object, $user->rights->expedition->creer).'</td><td colspan="3">';
		print $form->editfieldval("Depth", 'trueDepth', $object->trueDepth, $object, $user->rights->expedition->creer);
		print ($object->trueDepth && $object->depth_units != '') ? ' '.measuringUnitString(0, "size", $object->depth_units) : '';
		print '</td></tr>';

		// Volume
		print '<tr><td>';
		print $langs->trans("Volume");
		print '</td>';
		print '<td colspan="3">';
		$calculatedVolume = 0;
		$volumeUnit = 0;
		if ($object->trueWidth && $object->trueHeight && $object->trueDepth) {
			$calculatedVolume = ($object->trueWidth * $object->trueHeight * $object->trueDepth);
			$volumeUnit = $object->size_units * 3;
		}
		// If sending volume not defined we use sum of products
		if ($calculatedVolume > 0) {
			if ($volumeUnit < 50) {
				print showDimensionInBestUnit($calculatedVolume, $volumeUnit, "volume", $langs, isset($conf->global->MAIN_VOLUME_DEFAULT_ROUND) ? $conf->global->MAIN_VOLUME_DEFAULT_ROUND : -1, isset($conf->global->MAIN_VOLUME_DEFAULT_UNIT) ? $conf->global->MAIN_VOLUME_DEFAULT_UNIT : 'no');
			} else {
				print $calculatedVolume.' '.measuringUnitString(0, "volume", $volumeUnit);
			}
		}
		if ($totalVolume > 0) {
			if ($calculatedVolume) {
				print ' ('.$langs->trans("SumOfProductVolumes").': ';
			}
			print showDimensionInBestUnit($totalVolume, 0, "volume", $langs, isset($conf->global->MAIN_VOLUME_DEFAULT_ROUND) ? $conf->global->MAIN_VOLUME_DEFAULT_ROUND : -1, isset($conf->global->MAIN_VOLUME_DEFAULT_UNIT) ? $conf->global->MAIN_VOLUME_DEFAULT_UNIT : 'no');
			//if (empty($calculatedVolume)) print ' ('.$langs->trans("Calculated").')';
			if ($calculatedVolume) {
				print ')';
			}
		}
		print "</td>\n";
		print '</tr>';

		// Other attributes
		$cols = 2;
		include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

		print '</table>';

		print '</div>';
		print '<div class="fichehalfright">';
		print '<div class="underbanner clearboth"></div>';

		print '<table class="border centpercent tableforfield">';

		// Sending method
		print '<tr><td height="10">';
		print '<table class="nobordernopadding" width="100%"><tr><td>';
		print $langs->trans('SendingMethod');
		print '</td>';

		if ($action != 'editshipping_method_id') {
			print '<td class="right"><a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?action=editshipping_method_id&token='.newToken().'&id='.$object->id.'">'.img_edit($langs->trans('SetSendingMethod'), 1).'</a></td>';
		}
		print '</tr></table>';
		print '</td><td colspan="2">';
		if ($action == 'editshipping_method_id') {
			print '<form name="setshipping_method_id" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="setshipping_method_id">';
			$object->fetch_delivery_methods();
			print $form->selectarray("shipping_method_id", $object->meths, $object->shipping_method_id, 1, 0, 0, "", 1);
			if ($user->admin) {
				print info_admin($langs->trans("YouCanChangeValuesForThisListFromDictionarySetup"), 1);
			}
			print '<input type="submit" class="button button-edit" value="'.$langs->trans('Modify').'">';
			print '</form>';
		} else {
			if ($object->shipping_method_id > 0) {
				// Get code using getLabelFromKey
				$code = $langs->getLabelFromKey($db, $object->shipping_method_id, 'c_shipment_mode', 'rowid', 'code');
				print $langs->trans("SendingMethod".strtoupper($code));
			}
		}
		print '</td>';
		print '</tr>';

		// Tracking Number
		print '<tr><td class="titlefield">'.$form->editfieldkey("TrackingNumber", 'tracking_number', $object->tracking_number, $object, $user->rights->expedition->creer).'</td><td colspan="3">';
		print $form->editfieldval("TrackingNumber", 'tracking_number', $object->tracking_url, $object, $user->rights->expedition->creer, 'safehtmlstring', $object->tracking_number);
		print '</td></tr>';

		// Incoterms
		if (!empty($conf->incoterm->enabled)) {
			print '<tr><td>';
			print '<table width="100%" class="nobordernopadding"><tr><td>';
			print $langs->trans('IncotermLabel');
			print '<td><td class="right">';
			if ($user->rights->expedition->creer) {
				print '<a class="editfielda" href="'.DOL_URL_ROOT.'/expedition/card.php?id='.$object->id.'&action=editincoterm&token='.newToken().'">'.img_edit().'</a>';
			} else {
				print '&nbsp;';
			}
			print '</td></tr></table>';
			print '</td>';
			print '<td colspan="3">';
			if ($action != 'editincoterm') {
				print $form->textwithpicto($object->display_incoterms(), $object->label_incoterms, 1);
			} else {
				print $form->select_incoterms((!empty($object->fk_incoterms) ? $object->fk_incoterms : ''), (!empty($object->location_incoterms) ? $object->location_incoterms : ''), $_SERVER['PHP_SELF'].'?id='.$object->id);
			}
			print '</td></tr>';
		}

		// Other attributes
		$parameters = array('colspan' => ' colspan="3"', 'cols' => '3');
		$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		print $hookmanager->resPrint;

		print "</table>";

		print '</div>';
		print '</div>';

		print '<div class="clearboth"></div>';


		// Lines of products

		if ($action == 'editline') {
			print '	<form name="updateline" id="updateline" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;lineid='.$line_id.'" method="POST">
			<input type="hidden" name="token" value="' . newToken().'">
			<input type="hidden" name="action" value="updateline">
			<input type="hidden" name="mode" value="">
			<input type="hidden" name="id" value="' . $object->id.'">
			';
		}
		print '<br>';

		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder" width="100%" id="tablelines" >';
		print '<thead>';
		print '<tr class="liste_titre">';
		// Adds a line numbering column
		if (!empty($conf->global->MAIN_VIEW_LINE_NUMBER)) {
			print '<td width="5" class="center linecolnum">&nbsp;</td>';
		}
		// Product/Service
		print '<td  class="linecoldescription" >'.$langs->trans("Products").'</td>';
		// Qty
		print '<td class="center linecolqty">'.$langs->trans("QtyOrdered").'</td>';
		if ($origin && $origin_id > 0) {
			print '<td class="center linecolqtyinothershipments">'.$langs->trans("QtyInOtherShipments").'</td>';
		}
		if ($action == 'editline') {
			$editColspan = 3;
			if (empty($conf->stock->enabled)) {
				$editColspan--;
			}
			if (empty($conf->productbatch->enabled)) {
				$editColspan--;
			}
			print '<td class="center linecoleditlineotherinfo" colspan="'.$editColspan.'">';
			if ($object->statut <= 1) {
				print $langs->trans("QtyToShip").' - ';
			} else {
				print $langs->trans("QtyShipped").' - ';
			}
			if (!empty($conf->stock->enabled)) {
				print $langs->trans("WarehouseSource").' - ';
			}
			if (!empty($conf->productbatch->enabled)) {
				print $langs->trans("Batch");
			}
			print '</td>';
		} else {
			if ($object->statut <= 1) {
				print '<td class="center linecolqtytoship">'.$langs->trans("QtyToShip").'</td>';
			} else {
				print '<td class="center linecolqtyshipped">'.$langs->trans("QtyShipped").'</td>';
			}
			if (!empty($conf->stock->enabled)) {
				print '<td class="left linecolwarehousesource">'.$langs->trans("WarehouseSource").'</td>';
			}

			if (!empty($conf->productbatch->enabled)) {
				print '<td class="left linecolbatch">'.$langs->trans("Batch").'</td>';
			}
		}
		print '<td class="center linecolweight">'.$langs->trans("CalculatedWeight").'</td>';
		print '<td class="center linecolvolume">'.$langs->trans("CalculatedVolume").'</td>';
		//print '<td class="center">'.$langs->trans("Size").'</td>';
		if ($object->statut == 0) {
			print '<td class="linecoledit"></td>';
			print '<td class="linecoldelete" width="10"></td>';
		}
		print "</tr>\n";
		print '</thead>';

		if (!empty($conf->global->MAIN_MULTILANGS) && !empty($conf->global->PRODUIT_TEXTS_IN_THIRDPARTY_LANGUAGE)) {
			$object->fetch_thirdparty();
			$outputlangs = $langs;
			$newlang = '';
			if (empty($newlang) && GETPOST('lang_id', 'aZ09')) {
				$newlang = GETPOST('lang_id', 'aZ09');
			}
			if (empty($newlang)) {
				$newlang = $object->thirdparty->default_lang;
			}
			if (!empty($newlang)) {
				$outputlangs = new Translate("", $conf);
				$outputlangs->setDefaultLang($newlang);
			}
		}

		// Get list of products already sent for same source object into $alreadysent
		$alreadysent = array();
		if ($origin && $origin_id > 0) {
			$sql = "SELECT obj.rowid, obj.fk_product, obj.label, obj.description, obj.product_type as fk_product_type, obj.qty as qty_asked, obj.fk_unit, obj.date_start, obj.date_end";
			$sql .= ", ed.rowid as shipmentline_id, ed.qty as qty_shipped, ed.fk_expedition as expedition_id, ed.fk_origin_line, ed.fk_entrepot";
			$sql .= ", e.rowid as shipment_id, e.ref as shipment_ref, e.date_creation, e.date_valid, e.date_delivery, e.date_expedition";
			//if ($conf->delivery_note->enabled) $sql .= ", l.rowid as livraison_id, l.ref as livraison_ref, l.date_delivery, ld.qty as qty_received";
			$sql .= ', p.label as product_label, p.ref, p.fk_product_type, p.rowid as prodid, p.tosell as product_tosell, p.tobuy as product_tobuy, p.tobatch as product_tobatch';
			$sql .= ', p.description as product_desc';
			$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet as ed";
			$sql .= ", ".MAIN_DB_PREFIX."expedition as e";
			$sql .= ", ".MAIN_DB_PREFIX.$origin."det as obj";
			//if ($conf->delivery_note->enabled) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."delivery as l ON l.fk_expedition = e.rowid LEFT JOIN ".MAIN_DB_PREFIX."deliverydet as ld ON ld.fk_delivery = l.rowid  AND obj.rowid = ld.fk_origin_line";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON obj.fk_product = p.rowid";
			$sql .= " WHERE e.entity IN (".getEntity('expedition').")";
			$sql .= " AND obj.fk_".$origin." = ".((int) $origin_id);
			$sql .= " AND obj.rowid = ed.fk_origin_line";
			$sql .= " AND ed.fk_expedition = e.rowid";
			//if ($filter) $sql.= $filter;
			$sql .= " ORDER BY obj.fk_product";

			dol_syslog("get list of shipment lines", LOG_DEBUG);
			$resql = $db->query($sql);
			if ($resql) {
				$num = $db->num_rows($resql);
				$i = 0;

				while ($i < $num) {
					$obj = $db->fetch_object($resql);
					if ($obj) {
						// $obj->rowid is rowid in $origin."det" table
						$alreadysent[$obj->rowid][$obj->shipmentline_id] = array(
							'shipment_ref'=>$obj->shipment_ref, 'shipment_id'=>$obj->shipment_id, 'warehouse'=>$obj->fk_entrepot, 'qty_shipped'=>$obj->qty_shipped,
							'product_tosell'=>$obj->product_tosell, 'product_tobuy'=>$obj->product_tobuy, 'product_tobatch'=>$obj->product_tobatch,
							'date_valid'=>$db->jdate($obj->date_valid), 'date_delivery'=>$db->jdate($obj->date_delivery));
					}
					$i++;
				}
			}
			//var_dump($alreadysent);
		}

		print '<tbody>';

		// Loop on each product to send/sent
		for ($i = 0; $i < $num_prod; $i++) {
			$parameters = array('i' => $i, 'line' => $lines[$i], 'line_id' => $line_id, 'num' => $num_prod, 'alreadysent' => $alreadysent, 'editColspan' => $editColspan, 'outputlangs' => $outputlangs);
			$reshook = $hookmanager->executeHooks('printObjectLine', $parameters, $object, $action);
			if ($reshook < 0) {
				setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
			}

			if (empty($reshook)) {
				print '<!-- origin line id = '.$lines[$i]->origin_line_id.' -->'; // id of order line
				print '<tr class="oddeven" id="row-'.$lines[$i]->id.'" data-id="'.$lines[$i]->id.'" data-element="'.$lines[$i]->element.'" >';

				// #
				if (!empty($conf->global->MAIN_VIEW_LINE_NUMBER)) {
					print '<td class="center linecolnum">'.($i + 1).'</td>';
				}

				// Predefined product or service
				if ($lines[$i]->fk_product > 0) {
					// Define output language
					if (!empty($conf->global->MAIN_MULTILANGS) && !empty($conf->global->PRODUIT_TEXTS_IN_THIRDPARTY_LANGUAGE)) {
						$prod = new Product($db);
						$prod->fetch($lines[$i]->fk_product);
						$label = (!empty($prod->multilangs[$outputlangs->defaultlang]["label"])) ? $prod->multilangs[$outputlangs->defaultlang]["label"] : $lines[$i]->product_label;
					} else {
						$label = (!empty($lines[$i]->label) ? $lines[$i]->label : $lines[$i]->product_label);
					}

					print '<td class="linecoldescription">';

					// Show product and description
					$product_static->type = $lines[$i]->fk_product_type;
					$product_static->id = $lines[$i]->fk_product;
					$product_static->ref = $lines[$i]->ref;
					$product_static->status = $lines[$i]->product_tosell;
					$product_static->status_buy = $lines[$i]->product_tobuy;
					$product_static->status_batch = $lines[$i]->product_tobatch;

					$product_static->weight = $lines[$i]->weight;
					$product_static->weight_units = $lines[$i]->weight_units;
					$product_static->length = $lines[$i]->length;
					$product_static->length_units = $lines[$i]->length_units;
					$product_static->width = $lines[$i]->width;
					$product_static->width_units = $lines[$i]->width_units;
					$product_static->height = $lines[$i]->height;
					$product_static->height_units = $lines[$i]->height_units;
					$product_static->surface = $lines[$i]->surface;
					$product_static->surface_units = $lines[$i]->surface_units;
					$product_static->volume = $lines[$i]->volume;
					$product_static->volume_units = $lines[$i]->volume_units;

					$text = $product_static->getNomUrl(1);
					$text .= ' - '.$label;
					$description = (!empty($conf->global->PRODUIT_DESC_IN_FORM) ? '' : dol_htmlentitiesbr($lines[$i]->description));
					print $form->textwithtooltip($text, $description, 3, '', '', $i);
					print_date_range($lines[$i]->date_start, $lines[$i]->date_end);
					if (!empty($conf->global->PRODUIT_DESC_IN_FORM)) {
						print (!empty($lines[$i]->description) && $lines[$i]->description != $lines[$i]->product) ? '<br>'.dol_htmlentitiesbr($lines[$i]->description) : '';
					}
					print "</td>\n";
				} else {
					print '<td class="linecoldescription" >';
					if ($lines[$i]->product_type == Product::TYPE_SERVICE) {
						$text = img_object($langs->trans('Service'), 'service');
					} else {
						$text = img_object($langs->trans('Product'), 'product');
					}

					if (!empty($lines[$i]->label)) {
						$text .= ' <strong>'.$lines[$i]->label.'</strong>';
						print $form->textwithtooltip($text, $lines[$i]->description, 3, '', '', $i);
					} else {
						print $text.' '.nl2br($lines[$i]->description);
					}

					print_date_range($lines[$i]->date_start, $lines[$i]->date_end);
					print "</td>\n";
				}

				$unit_order = '';
				if ($conf->global->PRODUCT_USE_UNITS) {
					$unit_order = measuringUnitString($lines[$i]->fk_unit);
				}

				// Qty ordered
				print '<td class="center linecolqty">'.$lines[$i]->qty_asked.' '.$unit_order.'</td>';

				// Qty in other shipments (with shipment and warehouse used)
				if ($origin && $origin_id > 0) {
					print '<td class="linecolqtyinothershipments center nowrap">';
					foreach ($alreadysent as $key => $val) {
						if ($lines[$i]->fk_origin_line == $key) {
							$j = 0;
							foreach ($val as $shipmentline_id => $shipmentline_var) {
								if ($shipmentline_var['shipment_id'] == $lines[$i]->fk_expedition) {
									continue; // We want to show only "other shipments"
								}

								$j++;
								if ($j > 1) {
									print '<br>';
								}
								$shipment_static->fetch($shipmentline_var['shipment_id']);
								print $shipment_static->getNomUrl(1);
								print ' - '.$shipmentline_var['qty_shipped'];
								$htmltext = $langs->trans("DateValidation").' : '.(empty($shipmentline_var['date_valid']) ? $langs->trans("Draft") : dol_print_date($shipmentline_var['date_valid'], 'dayhour'));
								if (!empty($conf->stock->enabled) && $shipmentline_var['warehouse'] > 0) {
									$warehousestatic->fetch($shipmentline_var['warehouse']);
									$htmltext .= '<br>'.$langs->trans("FromLocation").' : '.$warehousestatic->getNomUrl(1, '', 0, 1);
								}
								print ' '.$form->textwithpicto('', $htmltext, 1);
							}
						}
					}
					print '</td>';
				}

				if ($action == 'editline' && $lines[$i]->id == $line_id) {
					// edit mode
					print '<td colspan="'.$editColspan.'" class="center"><table class="nobordernopadding centpercent">';
					if (is_array($lines[$i]->detail_batch) && count($lines[$i]->detail_batch) > 0) {
						print '<!-- case edit 1 -->';
						$line = new ExpeditionLigne($db);
						foreach ($lines[$i]->detail_batch as $detail_batch) {
							print '<tr>';
							// Qty to ship or shipped
							print '<td><input class="qtyl" name="qtyl'.$detail_batch->fk_expeditiondet.'_'.$detail_batch->id.'" id="qtyl'.$line_id.'_'.$detail_batch->id.'" type="text" size="4" value="'.$detail_batch->qty.'"></td>';
							// Batch number managment
							if ($lines[$i]->entrepot_id == 0) {
								// only show lot numbers from src warehouse when shipping from multiple warehouses
								$line->fetch($detail_batch->fk_expeditiondet);
							}
							$entrepot_id = !empty($detail_batch->entrepot_id)?$detail_batch->entrepot_id:$lines[$i]->entrepot_id;
							print '<td>'.$formproduct->selectLotStock($detail_batch->fk_origin_stock, 'batchl'.$detail_batch->fk_expeditiondet.'_'.$detail_batch->fk_origin_stock, '', 1, 0, $lines[$i]->fk_product, $entrepot_id).'</td>';
							print '</tr>';
						}
						// add a 0 qty lot row to be able to add a lot
						print '<tr>';
						// Qty to ship or shipped
						print '<td><input class="qtyl" name="qtyl'.$line_id.'_0" id="qtyl'.$line_id.'_0" type="text" size="4" value="0"></td>';
						// Batch number managment
						print '<td>'.$formproduct->selectLotStock('', 'batchl'.$line_id.'_0', '', 1, 0, $lines[$i]->fk_product).'</td>';
						print '</tr>';
					} elseif (!empty($conf->stock->enabled)) {
						if ($lines[$i]->fk_product > 0) {
							if ($lines[$i]->entrepot_id > 0) {
								print '<!-- case edit 2 -->';
								print '<tr>';
								// Qty to ship or shipped
								print '<td><input class="qtyl" name="qtyl'.$line_id.'" id="qtyl'.$line_id.'" type="text" size="4" value="'.$lines[$i]->qty_shipped.'">'.$unit_order.'</td>';
								// Warehouse source
								print '<td>'.$formproduct->selectWarehouses($lines[$i]->entrepot_id, 'entl'.$line_id, '', 1, 0, $lines[$i]->fk_product, '', 1).'</td>';
								// Batch number managment
								print '<td> - '.$langs->trans("NA").'</td>';
								print '</tr>';
							} elseif (count($lines[$i]->details_entrepot) > 1) {
								print '<!-- case edit 3 -->';
								foreach ($lines[$i]->details_entrepot as $detail_entrepot) {
									print '<tr>';
									// Qty to ship or shipped
									print '<td><input class="qtyl" name="qtyl'.$detail_entrepot->line_id.'" id="qtyl'.$detail_entrepot->line_id.'" type="text" size="4" value="'.$detail_entrepot->qty_shipped.'">'.$unit_order.'</td>';
									// Warehouse source
									print '<td>'.$formproduct->selectWarehouses($detail_entrepot->entrepot_id, 'entl'.$detail_entrepot->line_id, '', 1, 0, $lines[$i]->fk_product, '', 1).'</td>';
									// Batch number managment
									print '<td> - '.$langs->trans("NA").'</td>';
									print '</tr>';
								}
							} else {
								print '<!-- case edit 4 -->';
								print '<tr><td colspan="3">'.$langs->trans("NotEnoughStock").'</td></tr>';
							}
						} else {
							print '<!-- case edit 5 -->';
							print '<tr>';
							// Qty to ship or shipped
							print '<td><input class="qtyl" name="qtyl'.$line_id.'" id="qtyl'.$line_id.'" type="text" size="4" value="'.$lines[$i]->qty_shipped.'">'.$unit_order.'</td>';
							// Warehouse source
							print '<td></td>';
							// Batch number managment
							print '<td></td>';
							print '</tr>';
						}
					} elseif (empty($conf->stock->enabled) && empty($conf->productbatch->enabled)) { // both product batch and stock are not activated.
						print '<!-- case edit 6 -->';
						print '<tr>';
						// Qty to ship or shipped
						print '<td><input class="qtyl" name="qtyl'.$line_id.'" id="qtyl'.$line_id.'" type="text" size="4" value="'.$lines[$i]->qty_shipped.'"></td>';
						// Warehouse source
						print '<td></td>';
						// Batch number managment
						print '<td></td>';
						print '</tr>';
					}

					print '</table></td>';
				} else {
					// Qty to ship or shipped
					print '<td class="linecolqtytoship center">'.$lines[$i]->qty_shipped.' '.$unit_order.'</td>';

					// Warehouse source
					if (!empty($conf->stock->enabled)) {
						print '<td class="linecolwarehousesource left">';
						if ($lines[$i]->entrepot_id > 0) {
							$entrepot = new Entrepot($db);
							$entrepot->fetch($lines[$i]->entrepot_id);
							print $entrepot->getNomUrl(1);
						} elseif (count($lines[$i]->details_entrepot) > 1) {
							$detail = '';
							foreach ($lines[$i]->details_entrepot as $detail_entrepot) {
								if ($detail_entrepot->entrepot_id > 0) {
									$entrepot = new Entrepot($db);
									$entrepot->fetch($detail_entrepot->entrepot_id);
									$detail .= $langs->trans("DetailWarehouseFormat", $entrepot->libelle, $detail_entrepot->qty_shipped).'<br>';
								}
							}
							print $form->textwithtooltip(img_picto('', 'object_stock').' '.$langs->trans("DetailWarehouseNumber"), $detail);
						}
						print '</td>';
					}

					// Batch number managment
					if (!empty($conf->productbatch->enabled)) {
						if (isset($lines[$i]->detail_batch)) {
							print '<!-- Detail of lot -->';
							print '<td class="linecolbatch">';
							if ($lines[$i]->product_tobatch) {
								$detail = '';
								foreach ($lines[$i]->detail_batch as $dbatch) {	// $dbatch is instance of ExpeditionLineBatch
									$detail .= $langs->trans("Batch").': '.$dbatch->batch;
									if (empty($conf->global->PRODUCT_DISABLE_SELLBY)) {
										$detail .= ' - '.$langs->trans("SellByDate").': '.dol_print_date($dbatch->sellby, "day");
									}
									if (empty($conf->global->PRODUCT_DISABLE_EATBY)) {
										$detail .= ' - '.$langs->trans("EatByDate").': '.dol_print_date($dbatch->eatby, "day");
									}
									$detail .= ' - '.$langs->trans("Qty").': '.$dbatch->qty;
									$detail .= '<br>';
								}
								print $form->textwithtooltip(img_picto('', 'object_barcode').' '.$langs->trans("DetailBatchNumber"), $detail);
							} else {
								print $langs->trans("NA");
							}
							print '</td>';
						} else {
							print '<td class="linecolbatch" ></td>';
						}
					}
				}

				// Weight
				print '<td class="center linecolweight">';
				if ($lines[$i]->fk_product_type == Product::TYPE_PRODUCT) {
					print $lines[$i]->weight * $lines[$i]->qty_shipped.' '.measuringUnitString(0, "weight", $lines[$i]->weight_units);
				} else {
					print '&nbsp;';
				}
				print '</td>';

				// Volume
				print '<td class="center linecolvolume">';
				if ($lines[$i]->fk_product_type == Product::TYPE_PRODUCT) {
					print $lines[$i]->volume * $lines[$i]->qty_shipped.' '.measuringUnitString(0, "volume", $lines[$i]->volume_units);
				} else {
					print '&nbsp;';
				}
				print '</td>';

				// Size
				//print '<td class="center">'.$lines[$i]->volume*$lines[$i]->qty_shipped.' '.measuringUnitString(0, "volume", $lines[$i]->volume_units).'</td>';

				if ($action == 'editline' && $lines[$i]->id == $line_id) {
					print '<td class="center" colspan="2" valign="middle">';
					print '<input type="submit" class="button button-save" id="savelinebutton marginbottomonly" name="save" value="'.$langs->trans("Save").'"><br>';
					print '<input type="submit" class="button button-cancel" id="cancellinebutton" name="cancel" value="'.$langs->trans("Cancel").'"><br>';
					print '</td>';
				} elseif ($object->statut == Expedition::STATUS_DRAFT) {
					// edit-delete buttons
					print '<td class="linecoledit center">';
					print '<a class="editfielda reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=editline&token='.newToken().'&lineid='.$lines[$i]->id.'">'.img_edit().'</a>';
					print '</td>';
					print '<td class="linecoldelete" width="10">';
					print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deleteline&token='.newToken().'&lineid='.$lines[$i]->id.'">'.img_delete().'</a>';
					print '</td>';

					// Display lines extrafields
					if (!empty($rowExtrafieldsStart)) {
						print $rowExtrafieldsStart;
						print $rowExtrafieldsView;
						print $rowEnd;
					}
				}
				print "</tr>";

				// Display lines extrafields
				if (!empty($extrafields)) {
					$colspan = 6;
					if ($origin && $origin_id > 0) {
						$colspan++;
					}
					if (!empty($conf->productbatch->enabled)) {
						$colspan++;
					}
					if (!empty($conf->stock->enabled)) {
						$colspan++;
					}

					$line = $lines[$i];
					$line->fetch_optionals();

					// TODO Show all in same line by setting $display_type = 'line'
					if ($action == 'editline' && $line->id == $line_id) {
						print $lines[$i]->showOptionals($extrafields, 'edit', array('colspan'=>$colspan), $indiceAsked, '', 0, 'card');
					} else {
						print $lines[$i]->showOptionals($extrafields, 'view', array('colspan'=>$colspan), $indiceAsked, '', 0, 'card');
					}
				}
			}
		}

		// TODO Show also lines ordered but not delivered

		print "</table>\n";
		print '</tbody>';
		print '</div>';
	}


	print dol_get_fiche_end();


	$object->fetchObjectLinked($object->id, $object->element);


	/*
	 *    Boutons actions
	 */

	if (($user->socid == 0) && ($action != 'presend')) {
		print '<div class="tabsAction">';

		$parameters = array();
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been
																									   // modified by hook
		if (empty($reshook)) {
			if ($object->statut == Expedition::STATUS_DRAFT && $num_prod > 0) {
				if ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !empty($user->rights->expedition->creer))
				 || (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !empty($user->rights->expedition->shipping_advance->validate))) {
					print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=valid">'.$langs->trans("Validate").'</a>';
				} else {
					print '<a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans("NotAllowed").'">'.$langs->trans("Validate").'</a>';
				}
			}

			// TODO add alternative status
			// 0=draft, 1=validated, 2=billed, we miss a status "delivered" (only available on order)
			if ($object->statut == Expedition::STATUS_CLOSED && $user->rights->expedition->creer) {
				if (!empty($conf->facture->enabled) && !empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)) {  // Quand l'option est on, il faut avoir le bouton en plus et non en remplacement du Close ?
					print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=reopen&token='.newToken().'">'.$langs->trans("ClassifyUnbilled").'</a>';
				} else {
					print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=reopen&token='.newToken().'">'.$langs->trans("ReOpen").'</a>';
				}
			}

			// Send
			if (empty($user->socid)) {
				if ($object->statut > 0) {
					if (empty($conf->global->MAIN_USE_ADVANCED_PERMS) || $user->rights->expedition->shipping_advance->send) {
						print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=presend&mode=init#formmailbeforetitle">'.$langs->trans('SendMail').'</a>';
					} else {
						print '<a class="butActionRefused classfortooltip" href="#">'.$langs->trans('SendMail').'</a>';
					}
				}
			}

			// Create bill
			if (!empty($conf->facture->enabled) && ($object->statut == Expedition::STATUS_VALIDATED || $object->statut == Expedition::STATUS_CLOSED)) {
				if ($user->rights->facture->creer) {
					// TODO show button only   if (! empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT))
					// If we do that, we must also make this option official.
					print '<a class="butAction" href="'.DOL_URL_ROOT.'/compta/facture/card.php?action=create&amp;origin='.$object->element.'&amp;originid='.$object->id.'&amp;socid='.$object->socid.'">'.$langs->trans("CreateBill").'</a>';
				}
			}

			// This is just to generate a delivery receipt
			//var_dump($object->linkedObjectsIds['delivery']);
			if ($conf->delivery_note->enabled && ($object->statut == Expedition::STATUS_VALIDATED || $object->statut == Expedition::STATUS_CLOSED) && $user->rights->expedition->delivery->creer && empty($object->linkedObjectsIds['delivery'])) {
				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=create_delivery">'.$langs->trans("CreateDeliveryOrder").'</a>';
			}
			// Close
			if ($object->statut == Expedition::STATUS_VALIDATED) {
				if ($user->rights->expedition->creer && $object->statut > 0 && !$object->billed) {
					$label = "Close"; $paramaction = 'classifyclosed'; // = Transferred/Received
					// Label here should be "Close" or "ClassifyBilled" if we decided to make bill on shipments instead of orders
					if (!empty($conf->facture->enabled) && !empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)) {  // Quand l'option est on, il faut avoir le bouton en plus et non en remplacement du Close ?
						$label = "ClassifyBilled";
						$paramaction = 'classifybilled';
					}
					print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action='.$paramaction.'&token='.newToken().'">'.$langs->trans($label).'</a>';
				}
			}

			// Cancel
			if ($object->statut == Expedition::STATUS_VALIDATED) {
				if ($user->rights->expedition->supprimer) {
					print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=cancel&token='.newToken().'">'.$langs->trans("Cancel").'</a>';
				}
			}

			// Delete
			if ($user->rights->expedition->supprimer) {
				print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken().'">'.$langs->trans("Delete").'</a>';
			}
		}

		print '</div>';
	}


	/*
	 * Documents generated
	 */

	if ($action != 'presend' && $action != 'editline') {
		print '<div class="fichecenter"><div class="fichehalfleft">';

		$objectref = dol_sanitizeFileName($object->ref);
		$filedir = $conf->expedition->dir_output."/sending/".$objectref;

		$urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;

		$genallowed = $user->rights->expedition->lire;
		$delallowed = $user->rights->expedition->creer;

		print $formfile->showdocuments('expedition', $objectref, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', '', '', $soc->default_lang);


		// Show links to link elements
		//$linktoelem = $form->showLinkToObjectBlock($object, null, array('order'));
		$somethingshown = $form->showLinkedObjectBlock($object, '');


		print '</div><div class="fichehalfright">';

		// List of actions on element
		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
		$formactions = new FormActions($db);
		$somethingshown = $formactions->showactions($object, 'shipping', $socid, 1);

		print '</div></div>';
	}


	/*
	 * Action presend
	 */

	//Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	// Presend form
	$modelmail = 'shipping_send';
	$defaulttopic = $langs->trans('SendShippingRef');
	$diroutput = $conf->expedition->dir_output.'/sending';
	$trackid = 'shi'.$object->id;

	include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
}

// End of page
llxFooter();
$db->close();
