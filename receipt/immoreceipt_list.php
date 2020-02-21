<?php
/* Copyright (C) 2007-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2020 Philippe GRAND 	<philippe.grand@atoo-net.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *   	\file       immoreceipt_list.php
 *		\ingroup    ultimateimmo
 *		\brief      List page for immoreceipt
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
// Try main.inc.php using relative path
if (! $res && file_exists("../main.inc.php")) $res=@include "../main.inc.php";
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
dol_include_once('/ultimateimmo/class/immoreceipt.class.php');
dol_include_once('/ultimateimmo/class/immorenter.class.php');
dol_include_once('/ultimateimmo/class/immoowner.class.php');

// Load translation files required by the page
$langs->loadLangs(array("ultimateimmo@ultimateimmo","other"));

$action     = GETPOST('action','aZ09')?GETPOST('action','aZ09'):'view';				// The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction = GETPOST('massaction','alpha');											// The bulk action (combo box choice into lists)
$show_files = GETPOST('show_files','int');												// Show files area generated by bulk actions ?
$confirm    = GETPOST('confirm','alpha');												// Result of a confirmation
$cancel     = GETPOST('cancel', 'alpha');												// We click on a Cancel button
$toselect   = GETPOST('toselect', 'array');												// Array of ids of elements selected into a list
$contextpage= GETPOST('contextpage','aZ')?GETPOST('contextpage','aZ'):'immoreceiptlist';   // To manage different context of search
$backtopage = GETPOST('backtopage','alpha');											// Go back to a dedicated page
$optioncss  = GETPOST('optioncss','aZ');												// Option for the css output (always '' except when 'print')

$id			= GETPOST('id','int');

//$search_fk_soc = GETPOST('search_fk_soc', 'alpha');

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOST('page', 'int');
if (empty($page) || $page == -1 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha') || (empty($toselect) && $massaction === '0')) { $page = 0; }     // If $page is not defined, or '' or -1 or if we click on clear filters or if we select empty mass action
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize technical objects
$object = new ImmoReceipt($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->ultimateimmo->dir_output . '/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('immoreceiptlist'));     // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Default sort order (if not yet defined by previous GETPOST)
if (! $sortfield) $sortfield="t.".key($object->fields);   // Set here default search field. By default 1st field in definition.
if (! $sortorder) $sortorder = "ASC";

// Security check
if (empty($conf->ultimateimmo->enabled)) accessforbidden('Module not enabled');
$socid = 0;
if ($user->societe_id > 0)	// Protection if external user
{
	//$socid = $user->societe_id;
	accessforbidden();
}
//$result = restrictedArea($user, 'ultimateimmo', $id, '');

// Initialize array of search criterias
$search_all = trim(GETPOST("search_all", 'alpha'));
$search = array();
foreach ($object->fields as $key => $val)
{
	if (GETPOST('search_'.$key, 'alpha') !== '') $search[$key] = GETPOST('search_'.$key, 'alpha');
}
	//var_dump($search['t.'.$key]);
	//var_dump($key);

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array();
foreach ($object->fields as $key => $val)
{
	if ($val['searchall']) $fieldstosearchall['t.'.$key] = $val['label'];
}

// Definition of fields for list
$arrayfields = array();
foreach ($object->fields as $key => $val)
{
	// If $val['visible']==0, then we never show the field
	if (!empty($val['visible'])) $arrayfields['t.'.$key] = array('label'=>$val['label'], 'checked'=>(($val['visible'] < 0) ? 0 : 1), 'enabled'=>($val['enabled'] && ($val['visible'] != 3)), 'position'=>$val['position']);
}
// Extra fields
if (is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label']) > 0)
{
	foreach($extrafields->attributes[$object->table_element]['label'] as $key => $val)
	{
		if (!empty($extrafields->attributes[$object->table_element]['list'][$key])) {
			$arrayfields["ef.".$key] = array(
				'label'=>$extrafields->attributes[$object->table_element]['label'][$key],
				'checked'=>(($extrafields->attributes[$object->table_element]['list'][$key]<0)?0:1),
				'position'=>$extrafields->attributes[$object->table_element]['pos'][$key],
				'enabled'=>(abs($extrafields->attributes[$object->table_element]['list'][$key])!=3 && $extrafields->attributes[$object->table_element]['perms'][$key])
			);
		}
	}
}
$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields = dol_sort_array($arrayfields, 'position');

$permissiontoread = $user->rights->ultimateimmo->read;
$permissiontoadd = $user->rights->ultimateimmo->write;
$permissiontodelete = $user->rights->ultimateimmo->delete;

/*
 * Actions
 * 
 */
 
 if ($action == 'validaterent') 
 {	
	$error = 0;
	
	$db->begin();
	
	$sql1 = "UPDATE " . MAIN_DB_PREFIX . "ultimateimmo_immoreceipt as lo ";
	$sql1 .= " SET lo.partial_payment=";
	$sql1 .= "(SELECT SUM(p.amount)";
	$sql1 .= " FROM " . MAIN_DB_PREFIX . "ultimateimmo_immopayment as p";
	$sql1 .= " WHERE lo.rowid = p.fk_receipt";
	$sql1 .= " GROUP BY p.fk_receipt )";
	//var_dump($sql1);exit;exit;
	// dol_syslog ( get_class ( $this ) . ":: loyer.php action=" . $action . " sql1=" . $sql1, LOG_DEBUG );
	$resql1 = $db->query($sql1);
	if (! $resql1) 
	{
		$error ++;
		setEventMessages($db->lasterror(), null, 'errors');
	} 
	else 
	{
		
		$sql1 = "UPDATE " . MAIN_DB_PREFIX . "ultimateimmo_immoreceipt ";
		$sql1 .= " SET paye=1";
		$sql1 .= " WHERE total_amount=partial_payment";
		
		// dol_syslog ( get_class ( $this ) . ":: loyer.php action=" . $action . " sql1=" . $sql1, LOG_DEBUG );
		$resql1 = $db->query($sql1);
		if (! $resql1) 
		{
			$error ++;
			setEventMessages($db->lasterror(), null, 'errors');
		}
		
		if (! $error) 
		{
			$sql1 = "UPDATE " . MAIN_DB_PREFIX . "ultimateimmo_immoreceipt ";
			$sql1 .= " SET balance = total_amount-partial_payment";
			
			// dol_syslog ( get_class ( $this ) . ":: loyer.php action=" . $action . " sql1=" . $sql1, LOG_DEBUG );
			$resql1 = $db->query($sql1);
			if (! $resql1) 
			{
				$error ++;
				setEventMessages($db->lasterror(), null, 'errors');
			}
			
			if (! $error) 
			{
				$sql1 = "UPDATE " . MAIN_DB_PREFIX . "ultimateimmo_immorent as ir";
				$sql1 .= " SET ir.encours=";
				$sql1 .= "(SELECT SUM(il.balance)";
				$sql1 .= " FROM " . MAIN_DB_PREFIX . "ultimateimmo_immoreceipt as il";
				$sql1 .= " WHERE ir.rowid = il.fk_rent";
				$sql1 .= " GROUP BY il.fk_rent )";
				
				$resql1 = $db->query($sql1);
			if (! $resql1) 
			{
				$error ++;
				setEventMessages($db->lasterror(), null, 'errors');
			}
				
				$db->commit();
				
				$mesg=$langs->trans("Loyer mis a jour avec succes");
				setEventMessages($mesg, null, 'mesgs');
			}
		} 
		else 
		{
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
}

if 	($massaction == 'validate') 
{	
	foreach($toselect as $key => $val) 
	{		
		$immoreceipt = new ImmoReceipt($db);	
		$result = $immoreceipt->fetch($val);		
		if ($result >= 0)
		{		
			$resultvalid = $immoreceipt->validate($user);
		
			if ($resultvalid >= 0)
			{
				if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
				{
					// Define output language
					$outputlangs = $langs;
					$newlang = '';
					if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id','aZ09')) $newlang = GETPOST('lang_id','aZ09');
					if ($conf->global->MAIN_MULTILANGS && empty($newlang))	$newlang = $immoreceipt->thirdparty->default_lang;
					if (! empty($newlang)) 
					{
						$outputlangs = new Translate("", $conf);
						$outputlangs->setDefaultLang($newlang);
					}
					$model = $immoreceipt->modelpdf;
					$ret = $immoreceipt->fetch($val); // Reload to get new records
					$immoreceipt->generateDocument($model, $outputlangs, $hidedetails, $hidedesc, $hideref);
					
				}			
			} 
			else 
			{
				$langs->load("errors");
				if (count($immoreceipt->errors) > 0) setEventMessages($immoreceipt->error, $immoreceipt->errors, 'errors');
				else setEventMessages($langs->trans($immoreceipt->error), null, 'errors');
			}
		}
	}
}

if (GETPOST('cancel','alpha')) { $action = 'list'; $massaction = ''; }
if (! GETPOST('confirmmassaction','alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') { $massaction = ''; }

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	// Selection of new fields
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') ||GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
	{
		foreach ($object->fields as $key => $val)
		{
			$search[$key] = '';
		}
		$toselect = '';
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha'))
	{
		//$search_fk_soc = '';
		$massaction = '';     // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'ImmoReceipt';
	$objectlabel = 'ImmoReceipt';
	$uploaddir = $conf->ultimateimmo->dir_output . '/receipt/';
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}



/*
 * View
 * 
 */

$form = new Form($db);

$now = dol_now();

//$help_url="EN:Module_ImmoReceipt|FR:Module_ImmoReceipt_FR|ES:Módulo_ImmoReceipt";
$help_url = '';
$title = $langs->trans('ListOf', $langs->transnoentitiesnoconv("ImmoReceipts"));


// Build and execute select
// --------------------------------------------------------------------
$sql = 'SELECT ';
foreach ($object->fields as $key => $val)
{
	$sql .= 't.'.$key.', ';
}
$sql .= 'lc.lastname as nomlocataire, lc.firstname as prenomlocataire, ll.label as nomlocal, ll.rowid as property_id, soc.rowid as soc_id, soc.nom as owner_name';
// Add fields from extrafields
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) $sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? "ef.".$key.' as options_'.$key.', ' : '');
}
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object);    // Note that $action and $object may have been modified by hook
$sql .= preg_replace('/^,/', '', $hookmanager->resPrint);
$sql = preg_replace('/,\s*$/', '', $sql);
$sql .= " FROM ".MAIN_DB_PREFIX.$object->table_element." as t";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."ultimateimmo_immorenter as lc ON t.fk_renter = lc.rowid";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."ultimateimmo_immoproperty as ll ON t.fk_property = ll.rowid";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe as soc ON soc.rowid = t.fk_owner";
if (is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields as ef on (t.rowid = ef.fk_object)";
if ($object->ismultientitymanaged == 1) $sql.= " WHERE t.entity IN (".getEntity($object->element).")";
else $sql .= " WHERE 1 = 1";
foreach ($search as $key => $val)
{
	$mode_search = (($object->isInt($object->fields[$key]) || $object->isFloat($object->fields[$key])) ? 1 : 0);
	if ($search[$key] != '') $sql .= natural_search('t.'.$key, $search[$key], (($key == 'status') ? 2 : $mode_search));
}
if ($search_all) $sql .= natural_search(array_keys($fieldstosearchall), $search_all);

// Add where from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
// Add where from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object);    // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;

/* If a group by is required
$sql.= " GROUP BY "
foreach($object->fields as $key => $val)
{
	$sql.='t.'.$key.', ';
}
// Add fields from extrafields
if (! empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) $sql.=($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? "ef.".$key.', ' : '');
// Add where from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListGroupBy',$parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;
$sql=preg_replace('/, $/','', $sql);
*/

$sql .= $db->order($sortfield, $sortorder);

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
{
	$resql = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($resql);
	if (($page * $limit) > $nbtotalofrecords)	// if total of record found is smaller than page * limit, goto and load page 0
	{
		$page = 0;
		$offset = 0;
	}
}

// if total of record found is smaller than limit, no need to do paging and to restart another select with limits set.
if (is_numeric($nbtotalofrecords) && ($limit > $nbtotalofrecords || empty($limit)))
{
	$num = $nbtotalofrecords;
}
else
{
	if ($limit) $sql .= $db->plimit($limit + 1, $offset);

	$resql = $db->query($sql);
	if (!$resql)
	{
		dol_print_error($db);
		exit;
	}

	$num = $db->num_rows($resql);
}

// Direct jump if only one record found
if ($num == 1 && !empty($conf->global->MAIN_SEARCH_DIRECT_OPEN_IF_ONLY_ONE) && $search_all && !$page)
{
	$obj = $db->fetch_object($resql);
	$id = $obj->rowid;
	header("Location: ".dol_buildpath('/ultimateimmo/receipt/immoreceipt_card.php', 1).'?id='.$id);
	exit;
}

// Output page
// --------------------------------------------------------------------

llxHeader('', $title, $help_url);

$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.urlencode($contextpage);
if ($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit='.urlencode($limit);
foreach ($search as $key => $val)
{
	if (is_array($search[$key]) && count($search[$key])) foreach ($search[$key] as $skey) $param .= '&search_'.$key.'[]='.urlencode($skey);
    else $param .= '&search_'.$key.'='.urlencode($search[$key]);
}
if ($optioncss != '')     $param.='&optioncss='.urlencode($optioncss);
// Add $param from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';

// List of mass actions available
$arrayofmassactions =  array(
	'validate'=>$langs->trans("Validate"),
	'presend'=>$langs->trans("SendByMail"),
	'builddoc'=>$langs->trans("PDFMerge"),
);
if ($permissiontodelete) $arrayofmassactions['predelete'] = '<span class="fa fa-trash paddingrightonly"></span>'.$langs->trans("Delete");
if (GETPOST('nomassaction', 'int') || in_array($massaction, array('presend', 'predelete'))) $arrayofmassactions = array();
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/ultimateimmo/receipt/immoreceipt_card.php', 1).'?action=create&backtopage='.urlencode($_SERVER['PHP_SELF']), '', $permissiontoadd);

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'title_companies', 0, $newcardbutton, '', $limit);

// Add code for pre mass action (confirmation or email presend form)
$topicmail = "SendImmoReceiptRef";
$modelmail = "immoreceipt";
$objecttmp = new ImmoReceipt($db);
$trackid = 'receipt'.$object->id;
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

if ($search_all)
{
	foreach ($fieldstosearchall as $key => $val) $fieldstosearchall[$key] = $langs->trans($val);
	print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $search_all).join(', ', $fieldstosearchall).'</div>';
}

$moreforfilter = '';
/*$moreforfilter.='<div class="divsearchfield">';
$moreforfilter.= $langs->trans('MyFilter') . ': <input type="text" name="search_myfield" value="'.dol_escape_htmltag($search_myfield).'">';
$moreforfilter.= '</div>';*/

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object);    // Note that $action and $object may have been modified by hook
if (empty($reshook)) $moreforfilter .= $hookmanager->resPrint;
else $moreforfilter = $hookmanager->resPrint;

if (! empty($moreforfilter))
{
	print '<div class="liste_titre liste_titre_bydiv centpercent">';
	print $moreforfilter;
	print '</div>';
}

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print '<div class="div-table-responsive">';		// You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">'."\n";


// Fields title search
// --------------------------------------------------------------------
print '<tr class="liste_titre">';
foreach ($object->fields as $key => $val)
{
	$align = '';
	if (in_array($val['type'], array('date','datetime','timestamp'))) $align .= ($align?' ':'').'center';
	if (in_array($val['type'], array('timestamp'))) $align .= ($align?' ':'').'nowrap';
	if ($key == 'status') $align .= ($align?' ':'').'center';
	if ($key == 'civility_id') $align .= ($align?' ':'').'center';
	if (! empty($arrayfields['t.'.$key]['checked'])) print '<td class="liste_titre'.($align?' '.$align:'').'"><input type="text" class="flat maxwidth75" name="search_'.$key.'" value="'.dol_escape_htmltag($search[$key]).'"></td>';
}
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_input.tpl.php';

// Fields from hook
$parameters = array('arrayfields'=>$arrayfields);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
// Action column
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>'."\n";


// Fields title label
// --------------------------------------------------------------------
print '<tr class="liste_titre">';
foreach ($object->fields as $key => $val)
{
	$align = '';
	if (in_array($val['type'], array('date','datetime','timestamp'))) $align.=($align?' ':'').'center';
	if (in_array($val['type'], array('timestamp'))) $align .= ($align?' ':'').'nowrap';
	if ($key == 'status') $align .= ($align?' ':'').'center';
	if (! empty($arrayfields['t.'.$key]['checked'])) print getTitleFieldOfList($arrayfields['t.'.$key]['label'], 0, $_SERVER['PHP_SELF'], 't.'.$key, '', $param, ($align?'class="'.$align.'"':''), $sortfield, $sortorder, $align.' ')."\n";
}

// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
// Hook fields
$parameters = array('arrayfields'=>$arrayfields, 'param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
// Action column
print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
print '</tr>'."\n";

// Detect if we need a fetch on each output line
$needToFetchEachLine = 0;
if (is_array($extrafields->attributes[$object->table_element]['computed']) && count($extrafields->attributes[$object->table_element]['computed']) > 0)
{
	foreach ($extrafields->attributes[$object->table_element]['computed'] as $key => $val)
	{
		if (preg_match('/\$object/', $val)) $needToFetchEachLine++; // There is at least one compute field that use $object
	}
}

// Loop on record
// --------------------------------------------------------------------
$i = 0;
$totalarray = array();
while ($i < ($limit ? min($num, $limit) : $num))
{
	$obj = $db->fetch_object($resql);
	if (empty($obj)) break;		// Should not happen

	// Store properties in $object
	$object->setVarsFromFetchObj($obj);

	// Show here line of result
	print '<tr class="oddeven">';
	foreach($object->fields as $key => $val)
	{
		$cssforfield = (empty($val['css']) ? '' : $val['css']);
	    if (in_array($val['type'], array('date', 'datetime', 'timestamp'))) $cssforfield .= ($cssforfield ? ' ' : '').'center';
	    elseif ($key == 'status') $cssforfield .= ($cssforfield ? ' ' : '').'center';

	    if (in_array($val['type'], array('timestamp'))) $cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
	    elseif ($key == 'ref') $cssforfield .= ($cssforfield ? ' ' : '').'nowrap';

	    if (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && $key != 'status') $cssforfield .= ($cssforfield ? ' ' : '').'right';

		if (! empty($arrayfields['t.'.$key]['checked']))
		{
			print '<td'.($cssforfield ? ' class="'.$cssforfield.'"' : '').'>';
			if ($key == 'status') print $object->getLibStatut(5);
			
			elseif ($val['label'] == 'Owner') 
			{
				$staticowner = new ImmoOwner($db);
				$staticowner->fetch($object->fk_owner);			
				if ($staticowner->ref)
				{
					$staticowner->ref = $staticowner->getFullName($langs);
				}
				print $staticowner->ref;
			}
			elseif ($val['label'] == 'Renter') 
			{
				$staticrenter = new ImmoRenter($db);
				$staticrenter->fetch($object->fk_renter);			
				if ($staticrenter->ref)
				{
					$staticrenter->ref = $staticrenter->getFullName($langs);
				}
				print $staticrenter->ref;
			}
			elseif ($val['label'] == 'PartialPayment') 
			{
				if ($object->getSommePaiement())
				{
					$totalpaye = price($object->getSommePaiement(), 0, $outputlangs, 1, -1, -1, $conf->currency);
					print $totalpaye;
				}		
			}
			elseif ($val['label'] == 'Balance') 
			{
				$balance = $object->total_amount - $object->getSommePaiement();
				if ($balance>=0)
				{
					print price($balance, 0, $outputlangs, 1, -1, -1, $conf->currency);
				}			
			}
			elseif ($val['label'] == 'Paye') 
			{
				if ($totalpaye==0)
				{
					print $object->paye = $langs->trans('UnPaidReceipt');
				}
				elseif ($balance==0)
				{
					print $object->paye = $langs->trans('PaidReceipt');
				}
				else
				{
					print $object->paye = $langs->trans('PartiallyPaidReceipt');
				}
			}
			else
			{
				print $object->showOutputField($val, $key, $obj->$key, '');
			}
			print '</td>';
			if (! $i) $totalarray['nbfield']++;
			if (! empty($val['isameasure']))
			{
				if (! $i) $totalarray['pos'][$totalarray['nbfield']] = 't.'.$key;
				$totalarray['val']['t.'.$key] += $obj->$key;
			}
		}
	}
	// Extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';
	// Fields from hook
	$parameters=array('arrayfields'=>$arrayfields, 'obj'=>$obj);
	$reshook=$hookmanager->executeHooks('printFieldListValue', $parameters, $object);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	// Action column
	print '<td class="nowrap center">';
	if ($massactionbutton || $massaction)   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
	{
		$selected = 0;
		if (in_array($object->id, $arrayofselected)) $selected = 1;
		print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
	}
	print '</td>';
	if (! $i) $totalarray['nbfield']++;

	print '</tr>'."\n";

	$i++;
}

// Show total line
include DOL_DOCUMENT_ROOT.'/core/tpl/list_print_total.tpl.php';

// If no record found
if ($num == 0)
{
	$colspan = 1;
	foreach ($arrayfields as $key => $val) { if (!empty($val['checked'])) $colspan++; }
	print '<tr><td colspan="'.$colspan.'" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
}

$db->free($resql);

$parameters = array('arrayfields'=>$arrayfields, 'sql'=>$sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object);    // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print '</table>'."\n";
print '</div>'."\n";

print '</form>'."\n";

if (in_array('builddoc', $arrayofmassactions) && ($nbtotalofrecords === '' || $nbtotalofrecords))
{
	$hidegeneratedfilelistifempty = 1;
	if ($massaction == 'builddoc' || $action == 'remove_file' || $show_files) $hidegeneratedfilelistifempty = 0;

	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
	$formfile = new FormFile($db);

	// Show list of available documents
	$urlsource = $_SERVER['PHP_SELF'].'?sortfield='.$sortfield.'&sortorder='.$sortorder;
	$urlsource .= str_replace('&amp;', '&', $param);

	$filedir = $diroutputmassaction;
	$genallowed = $permissiontoread;
	$delallowed = $permissiontoadd;

	print $formfile->showdocuments('massfilesarea_ultimateimmo', '', $filedir, $urlsource, 0, $delallowed, '', 1, 1, 0, 48, 1, $param, $title, '', '', '', null, $hidegeneratedfilelistifempty);
}

// End of page
llxFooter();
$db->close();
