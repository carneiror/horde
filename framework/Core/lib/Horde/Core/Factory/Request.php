<?php
/**
 * @category Horde
 * @package  Core
 */
class Horde_Core_Factory_Request extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector $injector)
    {
        $request = new Horde_Controller_Request_Http();
        $request->setPath(isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : $_SERVER['REQUEST_URI']);
        return $request;
    }
}
