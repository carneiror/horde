<?php
/**
 * Turba addressbooks - create.
 *
 * Copyright 2001-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

require_once dirname(__FILE__) . '/../lib/Application.php';
Horde_Registry::appInit('turba');

// Exit if this isn't an authenticated user, or if there's no source
// configured for shares.
if (!$GLOBALS['registry']->getAuth() || !$session->get('turba', 'has_share')) {
    require TURBA_BASE . '/'
        . ($browse_source_count ? basename($prefs->getValue('initial_page')) : 'search.php');
    exit;
}

$vars = Horde_Variables::getDefaultVariables();
$form = new Turba_Form_CreateAddressBook($vars);

// Execute if the form is valid.
if ($form->validate($vars)) {
    try {
        $result = $form->execute();
        $notification->push(sprintf(_("The address book \"%s\" has been created."), $vars->get('name')), 'horde.success');
    } catch (Turba_Exception $e) {
        $notification->push($e, 'horde.error');
    }

    Horde::url('addressbooks/', true)->redirect();
}

$title = $form->getTitle();
require $registry->get('templates', 'horde') . '/common-header.inc';
require TURBA_TEMPLATES . '/menu.inc';
echo $form->renderActive($form->getRenderer(), $vars, Horde::url('addressbooks/create.php'), 'post');
require $registry->get('templates', 'horde') . '/common-footer.inc';
