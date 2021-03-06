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
class Horde_Http_CurlTest extends Horde_Test_Case
{
    private $_server;

    public function setUp()
    {
        if (!function_exists('curl_exec')) {
            $this->markTestSkipped('Missing PHP extension "curl"!');
        }

        $config = self::getConfig('HTTP_TEST_CONFIG');
        if ($config && !empty($config['http']['server'])) {
            $this->_server = $config['http']['server'];
        }
    }

    /**
     * @expectedException Horde_Http_Exception
     */
    public function testThrowsOnBadUri()
    {
        $client = new Horde_Http_Client(array('request' => new Horde_Http_Request_Curl()));
        $client->get('http://doesntexist/');
    }

    public function testReturnsResponseInsteadOfExceptionOn404()
    {
        $this->_skipMissingConfig();
        $client = new Horde_Http_Client(array('request' => new Horde_Http_Request_Curl()));
        $response = $client->get('http://' . $this->_server . '/doesntexist');
        $this->assertEquals(404, $response->code);
    }

    public function testGetBodyAfter404()
    {
        $this->_skipMissingConfig();
        $client = new Horde_Http_Client(array('request' => new Horde_Http_Request_Curl()));
        $response = $client->get('http://' . $this->_server . '/doesntexist');
        $content = $response->getBody();
        $this->assertTrue(!empty($content));
    }

    private function _skipMissingConfig()
    {
        if (empty($this->_server)) {
            $this->markTestSkipped('Missing configuration!');
        }
    }
}
