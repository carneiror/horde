<?php
/**
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/gpl.html GPL
 * @package  Kronolith
 */

require_once dirname(__FILE__) . '/../lib/Mnemo.php';

/**
 * Add hierarchcal related columns to the legacy sql share driver
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/gpl.html GPL
 * @package  Kronolith
 */
class MnemoUpgradeSqlnghierarchical extends Horde_Db_Migration_Base
{
    /**
     * Upgrade.
     */
    public function up()
    {
        $this->addColumn('mnemo_sharesng', 'share_parents','text');
    }

    /**
     * Downgrade
     */
    public function down()
    {
        $this->removeColumn('mnemo_sharesng', 'share_parents');
    }

}
