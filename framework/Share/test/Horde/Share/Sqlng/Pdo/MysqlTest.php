<?php
/**
 * Prepare the test setup.
 */
require_once dirname(__FILE__) . '/../Base.php';

/**
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @package    Share
 * @subpackage UnitTests
 * @copyright  2010 The Horde Project (http://www.horde.org/)
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 */
class Horde_Share_Sqlng_Pdo_MysqlTest extends Horde_Share_Test_Sqlng_Base
{
    public static function setUpBeforeClass()
    {
        if (!extension_loaded('pdo') ||
            !in_array('mysql', PDO::getAvailableDrivers())) {
            return;
        }
        $config = self::getConfig('SHARE_SQL_PDO_MYSQL_TEST_CONFIG',
                                  dirname(__FILE__) . '/../..');
        if ($config && !empty($config['share']['sql']['pdo_mysql'])) {
            self::$db = new Horde_Db_Adapter_Pdo_Mysql($config['share']['sql']['pdo_mysql']);
            parent::setUpBeforeClass();
        }
    }
}
