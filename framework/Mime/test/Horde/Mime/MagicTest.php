<?php
/**
 * Tests for the Horde_Mime_Magic class.
 *
 * Copyright 2010-2011 The Horde Project (http://www.horde.org/)
 *
 * @author     Michael Slusarz <slusarz@curecanti.org>
 * @category   Horde
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 * @package    Mime
 * @subpackage UnitTests
 */

/**
 * Prepare the test setup.
 */
require_once dirname(__FILE__) . '/Autoload.php';

/**
 * @author     Michael Slusarz <slusarz@curecanti.org>
 * @category   Horde
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 * @package    Mime
 * @subpackage UnitTests
 */
class Horde_Mime_MagicTest extends PHPUnit_Framework_TestCase
{
    public function testBug325()
    {
        if (!extension_loaded('fileinfo')) {
            $this->markTestSkipped('The fileinfo extension is not available.');
        }

        $this->assertEquals(
            'text/plain',
            Horde_Mime_Magic::analyzeFile(dirname(__FILE__) . '/fixtures/flowed_msg.txt')
        );
    }

}
