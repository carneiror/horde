<?php
/**
 * Copyright 1999-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Anil Madhavapeddy <anil@recoil.org>
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package Chora
 */

require_once dirname(__FILE__) . '/lib/Application.php';
Horde_Registry::appInit('chora');

if (!$atdir) {
    require CHORA_BASE . '/browsefile.php';
    exit;
}

$onb = $VC->hasFeature('snapshots')
    ? Horde_Util::getFormData('onb')
    : null;
$branchArgs = $onb ? array('onb' => $onb) : array();

try {
    $atticFlags = (bool)$acts['sa'];
    $dir = $VC->getDirObject($where, array('quicklog' => true, 'rev' => $onb, 'showattic' => $atticFlags));
    $dir->applySort($acts['sbt'], $acts['ord']);
    $dirList = $dir->queryDirList();
    $fileList = $dir->queryFileList($atticFlags);
} catch (Horde_Vcs_Exception $e) {
    Chora::fatal($e);
}

/* Decide what title to display. */
$title = ($where == '')
    ? $chora_conf['introTitle']
    : "/$where";

$extraLink = $VC->hasFeature('deleted')
    ? Horde::widget(Chora::url('browsedir', $where . '/', $branchArgs + array('sa' => ($acts['sa'] ? 0 : 1))), $acts['sa'] ? _("Hide Deleted Files") : _("Show Deleted Files"), 'widget', '', '', $acts['sa'] ? _("Hide _Deleted Files") : _("Show _Deleted Files"))
    : '';

$umap = array(
    'age' => Horde_Vcs::SORT_AGE,
    'rev' => Horde_Vcs::SORT_REV,
    'name' => Horde_Vcs::SORT_NAME,
    'author' => Horde_Vcs::SORT_AUTHOR
);

foreach ($umap as $key => $val) {
    $args = $branchArgs + array('sbt' => $val);
    if ($acts['sbt'] == $val) {
        $args['ord'] = !$acts['ord'];
    }
    $url[$key] = Chora::url('browsedir', $where . '/', $args);
}

$branches = array();
if ($VC->hasFeature('branches')) {
    $branches = $dir->getBranches();
}

/* Print out the directory header. */
$printAllCols = count($fileList);
$sortdirclass = $acts['sbt'] ? 'sortdown' : 'sortup';

Horde::addScriptFile('tables.js', 'horde');
require $registry->get('templates', 'horde') . '/common-header.inc';
require CHORA_TEMPLATES . '/menu.inc';
require CHORA_TEMPLATES . '/headerbar.inc';
require CHORA_TEMPLATES . '/directory/header.inc';

/* Unless we're at the top, display the 'back' bar. */
if ($where != '') {
    $url = Chora::url('browsedir', preg_replace('|[^/]+$|', '', $where), $branchArgs);
    require CHORA_TEMPLATES . '/directory/back.inc';
}

/* Display all the directories first. */
if ($dirList) {
    echo '<tbody>';
    foreach ($dirList as $currentDir) {
        if ($conf['hide_restricted'] && Chora::isRestricted($currentDir)) {
            continue;
        }
        $url = Chora::url('browsedir', $where . '/' . $currentDir . '/', $branchArgs);
        $currDir = $injector->getInstance('Horde_Core_Factory_TextFilter')->filter($currentDir, 'space2html', array('encode' => true, 'encode_all' => true));
        require CHORA_TEMPLATES . '/directory/dir.inc';
    }
    echo '</tbody>';
}

/* Display all of the files in this directory */
$readmes = array();
if ($fileList) {
    echo '<tbody>';
    foreach ($fileList as $currFile) {
        if ($conf['hide_restricted'] &&
            Chora::isRestricted($currFile->queryName())) {
            continue;
        }

        $lg = $currFile->queryLastLog();
        $realname = $currFile->queryName();
        $mimeType = Horde_Mime_Magic::filenameToMIME($realname);
        $currFile->mimeType = $mimeType;

        if (Horde_String::lower(Horde_String::substr($realname, 0, 6)) == 'readme') {
            $readmes[] = $currFile;
        }

        $icon = $injector->getInstance('Horde_Core_Factory_MimeViewer')->getIcon($mimeType);
        $author = Chora::showAuthorName($lg->queryAuthor());
        $filerev = $lg->queryRevision();
        $date = $lg->queryDate();
        $log = $lg->queryLog();
        $attic = $currFile->isDeleted();
        $fileName = $where . ($attic ? '/' . 'Attic' : '') . '/' . $realname;
        $name = $injector->getInstance('Horde_Core_Factory_TextFilter')->filter($realname, 'space2html', array('encode' => true, 'encode_all' => true));
        $url = Chora::url('browsefile', $fileName, $branchArgs);
        $readableDate = Chora::readableTime($date);
        if ($log) {
            $shortLog = Horde_String::truncate(str_replace("\n", ' ', trim($log)), $conf['options']['shortLogLength']);
        }
        require CHORA_TEMPLATES . '/directory/file.inc';
    }
    echo '</tbody>';
}

echo '</table>';
if ($readmes) {
    $readmeCollection = new Chora_Readme_Collection($readmes);
    $readmeFile = $readmeCollection->chooseReadme();
    $readmeRenderer = new Chora_Renderer_File_Html($injector->createInstance('Horde_View'), $readmeFile, $readmeFile->queryRevision());
    echo $readmeRenderer->render();
}
require $registry->get('templates', 'horde') . '/common-footer.inc';
