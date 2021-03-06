<?php
/**
 * This class provides the base definition for built-in Virtual Folders.
 *
 * Copyright 2010-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/gpl.html GPL
 * @package  IMP
 */
abstract class IMP_Search_Vfolder_Builtin extends IMP_Search_Vfolder
{
    /**
     * Can this query be edited?
     *
     * @var boolean
     */
    protected $_canEdit = false;

    /**
     * List of serialize entries not to save.
     *
     * @var array
     */
    protected $_nosave = array('c', 'i', 'l', 'm');

    /**
     * Constructor.
     *
     * The 'add', 'id', 'label', and 'mboxes' parameters are not honored.
     *
     * @see __construct()
     */
    public function __construct(array $opts = array())
    {
        $this->enabled = empty($opts['disable']);

        $this->_init();
    }

    /**
     * Initialization tasks.
     */
    abstract protected function _init();

    /**
     * Unserialization.
     *
     * @param string $data  Serialized data.
     *
     * @throws Exception
     */
    public function unserialize($data)
    {
        parent::unserialize($data);
        $this->_init();
    }

}
