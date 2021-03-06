<?php
/**
 * The original PHP version of this code was written by Geoffrey T. Dairiki
 * <dairiki@dairiki.org>, and is used/adapted with his permission.
 *
 * Copyright 2004 Geoffrey T. Dairiki <dairiki@dairiki.org>
 * Copyright 2004-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://opensource.org/licenses/lgpl-license.php.
 *
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */
class Horde_Text_Diff_Op_Delete extends Horde_Text_Diff_Op_Base
{
    public function __construct($lines)
    {
        $this->orig = $lines;
        $this->final = false;
    }

    public function reverse()
    {
        return new Horde_Text_Diff_Op_Add($this->orig);
    }
}
