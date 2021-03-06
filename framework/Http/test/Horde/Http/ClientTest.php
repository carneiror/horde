<?php
/**
 * @category   Horde
 * @package    Http
 * @subpackage UnitTests
 * @copyright  2007-2011 The Horde Project (http://www.horde.org/)
 * @license    http://opensource.org/licenses/bsd-license.php
 */

/**
 * Prepare the test setup.
 */
require_once dirname(__FILE__) . '/Autoload.php';

/**
 * @group      support
 * @category   Horde
 * @package    Http
 * @subpackage UnitTests
 * @copyright  2007-2011 The Horde Project (http://www.horde.org/)
 * @license    http://opensource.org/licenses/bsd-license.php
 */
class Horde_Http_ClientTest extends Horde_Test_Case
{
    public function testGetTimeout()
    {
        $request = new Horde_Http_Request_Mock();
        $this->assertEquals(5, $request->timeout);
    }

    public function testSetTimeout()
    {
        $request = new Horde_Http_Request_Mock();
        $client = new Horde_Http_Client(
            array('request' => $request)
        );
        $client->{'request.timeout'} = 10;
        $this->assertEquals(10, $request->timeout);
    }
}
