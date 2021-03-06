<?php
/**
 * Test the MissingValue exception.
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
require_once dirname(__FILE__) . '/../../Autoload.php';

/**
 * Test the MissingValue exception.
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
class Horde_Kolab_Format_Unit_Exception_MissingValueTest
extends PHPUnit_Framework_TestCase
{
    public function testMissingValueTest()
    {
        $exception = new Horde_Kolab_Format_Exception_MissingValue('value');
        $this->assertEquals(
            'Data value for "value" is empty in the Kolab XML object!',
            $exception->getMessage()
        );
    }

    public function testParseErrorValue()
    {
        $exception = new Horde_Kolab_Format_Exception_MissingValue('value');
        $this->assertEquals(
            'value',
            $exception->getValue()
        );
    }
}
