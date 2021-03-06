<?php
/**
 * Helper class to generate the match dictionary for the incoming request.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Horde_Routes
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Horde_Routes
 * @since    1.0.1
 */

/**
 * Generates the match dictionary for the incoming request.
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see
 * http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html.
 *
 * @category Horde
 * @package  Horde_Routes
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Horde_Routes
 * @since    1.0.1
 */
class Horde_Routes_Matcher
{
    /**
     * The routes mapper.
     *
     * @var Horde_Routes_Mapper
     */
    protected $_mapper;

    /**
     * The incoming request.
     *
     * @var Horde_Controller_Request
     */
    protected $_request;

    /**
     * The match dictionary.
     *
     * @var array
     */
    protected $_match_dict;

    /**
     * Constructor
     *
     * @param Horde_Routes_Mapper $mapper        The mapper
     * @param Horde_Controller_Request $request  A request object
     */
    public function __construct(
        Horde_Routes_Mapper $mapper,
        Horde_Controller_Request $request)
    {
        $this->_mapper = $mapper;
        $this->_request = $request;
    }

    /**
     * Return the match dictionary for the incoming request.
     *
     * @return array The match dictionary.
     */
    public function getMatchDict()
    {
        if ($this->_match_dict === null) {
            $path = $this->_request->getPath();
            if (($pos = strpos($path, '?')) !== false) {
                $path = substr($path, 0, $pos);
            }
            if (!$path) {
                $path = '/';
            }
            $this->_match_dict = new Horde_Support_Array($this->_mapper->match($path));
        }
        return $this->_match_dict;
    }

}