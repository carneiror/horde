<?php
/**
 * This class implements an IMP system flag with matching on IMAP flags.
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/gpl.html GPL
 * @package  IMP
 */
abstract class IMP_Flag_System_Match_Flag extends IMP_Flag_Base
{
    /**
     * @param array $data  List of IMAP flags.
     */
    public function match(array $data)
    {
        return false;
    }

}
