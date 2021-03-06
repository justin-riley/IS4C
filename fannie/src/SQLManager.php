<?php
/*******************************************************************************

    Copyright 2009 Whole Foods Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

/**
 @class SQLManager
 @brief A SQL abstraction layer

 Custom SQL abstraction based on ADOdb.
 Provides some limited functionality for queries
 across two servers that are useful for lane-server
 communication
*/
if (!class_exists('FannieAPI')) {
    include(dirname(__FILE__) . '/../classlib2.0/FannieAPI.php');
}
if (!class_exists('\\COREPOS\\common\\SQLManager', false)) {
    include(dirname(__FILE__) . '/../../common/SQLManager.php');
}

class SQLManager extends \COREPOS\common\SQLManager
{
    /**
      Override to initialize QUERY_LOG
    */
    public function __construct($server,$type,$database,$username,$password='',$persistent=false, $new=false)
    {
        $this->setQueryLog(FannieLogger::factory());

        parent::__construct(
            $server, 
            $type, 
            $database, 
            $username, 
            $password,
            $persistent,
            $new
        );
    }
}

