<?php
/**
 * A Horde_Injector:: based Horde_Tree:: factory.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Core
 */

/**
 * A Horde_Injector:: based Horde_Tree:: factory.
 *
 * Copyright 2010-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Core
 */
class Horde_Core_Factory_Tree extends Horde_Core_Factory_Base
{
    /**
     * Singleton instances.
     *
     * @var array
     */
    private $_instances = array();

    /**
     * Return the Horde_Tree:: instance.
     *
     * @param string $name     The name of this tree instance.
     * @param mixed $renderer  The type of tree renderer.
     * @param array $params    Any additional parameters the constructor
     *                         needs. Defined by this class:
     * <pre>
     * 'nosession' - (boolean) Don't store tree state in the session.
     *               DEFAULT: false
     * </pre>
     *
     * @return Horde_Tree_Base  The singleton instance.
     * @throws Horde_Tree_Exception
     */
    public function create($name, $renderer, array $params = array())
    {
        $lc_renderer = Horde_String::lower($renderer);
        $id = $name . '|' . $lc_renderer;

        if (!isset($this->_instances[$id])) {
            switch ($lc_renderer) {
            case 'html':
                $renderer = 'Horde_Core_Tree_Html';
                break;

            case 'javascript':
                $renderer = 'Horde_Core_Tree_Javascript';
                break;

            case 'simplehtml':
                $renderer = 'Horde_Core_Tree_Simplehtml';
                break;
            }

            if (empty($params['nosession'])) {
                $params['session'] = array(
                    'get' => array(__CLASS__, 'getSession'),
                    'set' => array(__CLASS__, 'setSession')
                );
            }

            $this->_instances[$id] = Horde_Tree::factory($name, $renderer, $params);
        }

        return $this->_instances[$id];
    }

    /**
     */
    static public function getSession($instance, $id, $mask = 0)
    {
        return $GLOBALS['session']->get('horde', 'tree-' . $instance . '/' . $id, $mask);
    }

    /**
     */
    static public function setSession($instance, $id, $val)
    {
        if ($val) {
            $GLOBALS['session']->set('horde', 'tree-' . $instance . '/' . $id, $val);
        } else {
            $GLOBALS['session']->remove('horde', 'tree-' . $instance . '/' . $id);
        }
    }

}
