<?php
/**
 * Test the decorator for memory measurements.
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
 * Test the decorator for memory measurements.
 *
 * Copyright 2010-2011 The Horde Project (http://www.horde.org/)
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
class Horde_Kolab_Format_Unit_Decorator_MemoryTest
extends Horde_Kolab_Format_TestCase
{
    public function testConstructor()
    {
        $this->getFactory()->create(
            'XML', 'contact', array('memlog' => true)
        );
    }

    public function testLogLoad()
    {
        $timed = $this->_getMemoryMock();
        $a = '';
        $timed->load($a);
        $this->assertContains(
            'Kolab Format data parsing complete. Memory usage:',
            array_pop($this->logger->log)
        );
    }

    public function testLogSave()
    {
        $timed = $this->_getMemoryMock();
        $a = array();
        $timed->save($a);
        $this->assertContains(
            'Kolab Format data generation complete. Memory usage:',
            array_pop($this->logger->log)
        );
    }

    private function _getMemoryMock()
    {
        $this->logger = new Stub_Log();
        return new Horde_Kolab_Format_Decorator_Memory(
            $this->getMock('Horde_Kolab_Format'),
            new Horde_Support_Memory(),
            $this->logger
        );
    }
}
