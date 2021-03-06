<?php
/**
 * Test the DOM based XML handler.
 *
 * PHP version 5
 *
 * @category   Kolab
 * @package    Kolab_Format
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link       http://pear.horde.org/index.php?package=Kolab_Format
 */

/**
 * Prepare the test setup.
 */
require_once dirname(__FILE__) . '/../Autoload.php';

/**
 * Test the DOM based XML handler.
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category   Kolab
 * @package    Kolab_Format
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link       http://pear.horde.org/index.php?package=Kolab_Format
 */
class Horde_Kolab_Format_Unit_XmlTest
extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Horde_Kolab_Format_Exception_MissingValue
     */
    public function testMissingUid()
    {
        $factory = new Horde_Kolab_Format_Factory();
        $note = $factory->create('Xml', 'Note');
        $note->save(array());
    }

    public function testSave()
    {
        $factory = new Horde_Kolab_Format_Factory();
        $note = $factory->create('Xml', 'Note');
        $note->save(array('uid' => 'test'));
    }
}
