<?php
/* Copyright (C) 2025 Arthur LENOBLE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   condensedorders     Module CondensedOrders
 *  \brief      CondensedOrders module descriptor.
 *
 *  \file       htdocs/condensedorders/core/modules/modCondensedOrders.class.php
 *  \ingroup    condensedorders
 *  \brief      Description and activation file for module CondensedOrders
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

/**
 * CondensedDeliveries class 
 */
class CondensedDeliveries extends CommonObject {
    /**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;
    }

    /**
     * Checking if all the orders have the same thirdparty
     * 
     * @param       array     $ordersArray     array of orders to check
     * 
     * @return      int                        0 if KO, 1 if OK
     */
    public function checkThirdparty($ordersArray){
        $comm = new Commande($this->db);
        $soc = -1;
        foreach ($ordersArray as $key => $id){
            $comm->fetch($id);
            if ($soc == -1){ // If no thirdparty id registered, we get the first one
                $soc = $comm->socid;
            } else { // Checking if thirdparty id is the same as the one registered
                if ($soc != $comm->socid){ // If not we stop and return an error
                    return 0;
                }
            }
        }
        return 1;
    }
}