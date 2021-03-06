<?php
/**
 * Implementation for tasks in the Kolab XML format.
 *
 * PHP version 5
 *
 * @category Kolab
 * @package  Kolab_Format
 * @author   Thomas Jarosch <thomas.jarosch@intra2net.com>
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Kolab_Server
 */

/**
 * Kolab XML handler for task groupware objects.
 *
 * Copyright 2007-2009 Klarälvdalens Datakonsult AB
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see
 * http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html.
 *
 * @category Kolab
 * @package  Kolab_Format
 * @author   Thomas Jarosch <thomas.jarosch@intra2net.com>
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Kolab_Server
 */
class Horde_Kolab_Format_Xml_Task extends Horde_Kolab_Format_Xml
{
    /**
     * Specific data fields for the note object
     *
     * @var array
     */
    protected $_fields_specific;

    /**
     * Constructor
     */
    public function __construct($parser, $params = array())
    {
        $this->_root_name = 'task';

        /** Specific task fields, in kolab format specification order
         */
        $this->_fields_specific = array(
            'summary' => array(
                'type'    => self::TYPE_STRING,
                'value'   => self::VALUE_DEFAULT,
                'default' => '',
            ),
            'location' => array(
                'type'    => self::TYPE_STRING,
                'value'   => self::VALUE_DEFAULT,
                'default' => '',
            ),
            'organizer' => $this->_fields_simple_person,
            'start-date' => array(
                'type'    => self::TYPE_DATE_OR_DATETIME,
                'value'   => self::VALUE_MAYBE_MISSING,
            ),
            'alarm' => array(
                'type'    => self::TYPE_INTEGER,
                'value'   => self::VALUE_MAYBE_MISSING,
            ),
            'recurrence' => array(
                'type'    => self::TYPE_COMPOSITE,
                'value'   => self::VALUE_CALCULATED,
                'load'    => 'Recurrence',
                'save'    => 'Recurrence',
            ),
            'attendee' => $this->_fields_attendee,
            'priority' => array(
                'type'    => self::TYPE_INTEGER,
                'value'   => self::VALUE_DEFAULT,
                'default' => 3,
            ),
            'completed' => array(
                'type'    => self::TYPE_INTEGER,
                'value'   => self::VALUE_DEFAULT,
                'default' => 0,
            ),
            'status' => array(
                'type'    => self::TYPE_STRING,
                'value'   => self::VALUE_DEFAULT,
                'default' => 'not-started',
            ),
            'due-date' => array(
                'type'    => self::TYPE_DATE_OR_DATETIME,
                'value'   => self::VALUE_MAYBE_MISSING,
            ),
            'parent' => array(
                'type'    => self::TYPE_STRING,
                'value'   => self::VALUE_MAYBE_MISSING,
            ),
            // These are not part of the Kolab specification but it is
            // ok if the client supports additional entries
            'creator'   => $this->_fields_simple_person,
            'percentage' => array(
                'type'    => self::TYPE_INTEGER,
                'value'   => self::VALUE_MAYBE_MISSING,
            ),
            'estimate' => array(
                'type'    => self::TYPE_STRING,
                'value'   => self::VALUE_MAYBE_MISSING,
            ),
            'completed_date' => array(
                'type'    => self::TYPE_DATE_OR_DATETIME,
                'value'   => self::VALUE_MAYBE_MISSING,
            ),
            'horde-alarm-methods' => array(
                'type'    => self::TYPE_STRING,
                'value'   => self::VALUE_MAYBE_MISSING,
            ),
        );

        parent::__construct($parser, $params);
    }
}
