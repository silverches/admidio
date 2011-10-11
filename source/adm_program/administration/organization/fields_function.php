<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Profilfelder
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usf_id: ID des Feldes
 * mode:   1 - Feld anlegen oder updaten
 *         2 - Feld loeschen
 *         4 - Reihenfolge fuer die uebergebene usf_id anpassen
 * sequence: neue Reihenfolge fuer die uebergebene usf_id
 *
 *****************************************************************************/
 
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_user_field.php');
require_once('../../libs/htmlawed/htmlawed.php');

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isWebmaster())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getUsfId    = admFuncVariableIsValid($_GET, 'usf_id', 'numeric', 0);
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'numeric', null, true);
$getSequence = admFuncVariableIsValid($_GET, 'sequence', 'string', '', false, array('UP', 'DOWN'));

// UserField-objekt anlegen
$user_field = new TableUserField($gDb);

if($getUsfId > 0)
{
    $user_field->readData($getUsfId);
    
    // Pruefung, ob das Feld zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
    if($user_field->getValue('cat_org_id') >  0
    && $user_field->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

if($getMode == 1)
{
   // Feld anlegen oder updaten

    $_SESSION['fields_request'] = $_REQUEST;
    
    // pruefen, ob Pflichtfelder gefuellt sind
    // (bei Systemfeldern duerfen diese Felder nicht veraendert werden)
    if($user_field->getValue('usf_system') == 0 && strlen($_POST['usf_name']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
    }    

    if($user_field->getValue('usf_system') == 0 && strlen($_POST['usf_type']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_DATATYPE')));
    }    

    if($user_field->getValue('usf_system') == 0 && $_POST['usf_cat_id'] == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_CATEGORY')));
    }

    if(($_POST['usf_type'] == 'DROPDOWN' || $_POST['usf_type'] == 'RADIO_BUTTON')
	&& strlen($_POST['usf_value_list']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_VALUE_LIST')));
    }
    
    // Nachname und Vorname sollen immer Pflichtfeld bleiben
    if($user_field->getValue('usf_name_intern') == 'LAST_NAME'
    || $user_field->getValue('usf_name_intern') == 'FIRST_NAME')
    {
        $_POST['usf_mandatory'] = 1;
    }
    
    if(isset($_POST['usf_name']) && $user_field->getValue('usf_name') != $_POST['usf_name'])
    {
        // Schauen, ob das Feld bereits existiert
        $sql    = 'SELECT COUNT(*) as count 
                     FROM '. TBL_USER_FIELDS. '
                    WHERE usf_name LIKE \''.$_POST['usf_name'].'\'
                      AND usf_cat_id  = '.$_POST['usf_cat_id'].'
                      AND usf_id     <> '.$getUsfId;
        $result = $gDb->query($sql);
        $row    = $gDb->fetch_array($result);

        if($row['count'] > 0)
        {
            $gMessage->show($gL10n->get('ORG_FIELD_EXIST'));
        }      
    }

    // Eingabe verdrehen, da der Feldname anders als im Dialog ist
    if(isset($_POST['usf_hidden']))
    {
        $_POST['usf_hidden'] = 0;
    }
    else
    {
        $_POST['usf_hidden'] = 1;
    }
    if(isset($_POST['usf_disabled']) == false)
    {
        $_POST['usf_disabled'] = 0;
    }
    if(isset($_POST['usf_mandatory']) == false)
    {
        $_POST['usf_mandatory'] = 0;
    }
	
    // make html in description secure
    $_POST['usf_description'] = htmLawed(stripslashes($_POST['usf_description']));

    // POST Variablen in das UserField-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'usf_') === 0)
        {
            if($user_field->setValue($key, $value) == false)
			{
				// Daten wurden nicht uebernommen, Hinweis ausgeben
				if($key == 'usf_url')
				{
					$gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', $gL10n->get('ORG_URL')));
				}
			}
        }
    }
    
    // Daten in Datenbank schreiben
    $return_code = $user_field->save();

    if($return_code < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }    

    $_SESSION['navigation']->deleteLastUrl();
    unset($_SESSION['fields_request']);

	// zu den Organisationseinstellungen zurueck
	$gMessage->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
	$gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}
elseif($getMode == 2)
{
    if($user_field->getValue('usf_system') == 1)
    {
        // Systemfelder duerfen nicht geloescht werden
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    // Feld loeschen
    if($user_field->delete())
    {
        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
    exit();
}
elseif($getMode == 4)
{
    // Feldreihenfolge aktualisieren
    $user_field->moveSequence($getSequence);
    exit();
}
         
?>