<?php
/**
 * The IMP_Sentmail_Null:: class is a null logging implementation.
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
class IMP_Sentmail_Null extends IMP_Sentmail_Base
{
    /**
     */
    protected function _log($action, $message_id, $recipient, $success)
    {
    }

    /**
     */
    public function favouriteRecipients($limit, $filter = null)
    {
        return array();
    }

    /**
     */
    public function numberOfRecipients($hours, $user = false)
    {
        return 0;
    }

    /**
     */
    protected function _deleteOldEntries($before)
    {
    }

}
