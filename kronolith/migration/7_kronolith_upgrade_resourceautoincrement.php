<?php
/**
 * Adds autoincrement flags
 *
 * Copyright 2010-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/gpl.html GPL
 * @package  Kronolith
 */
class KronolithUpgradeResourceAutoIncrement extends Horde_Db_Migration_Base
{
    /**
     * Upgrade.
     */
    public function up()
    {
        $this->changeColumn('kronolith_resources', 'resource_id', 'autoincrementKey');
        try {
            $this->dropTable('kronolith_resources_seq');
        } catch (Horde_Db_Exception $e) {
        }
    }

    /**
     * Downgrade
     */
    public function down()
    {
        $this->changeColumn('kronolith_resources', 'resource_id', 'integer', array('null' => false, 'autoincrement' => false));
    }

}