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

    /**
     * Checking if an order has been completely delivered or its shipment is in progress
     * 
     * @param       int     $orderId    id of the order
     * 
     * @return      int                 1 if all the products are delivered, 0 if partially delivered, -1 if KO
     */
    public function checkOrderStatus($orderId){
        $comm = new Commande($this->db);
        $res = $comm->fetch($orderId);
        if ($res > 0){
            $comm->loadExpeditions();
            $lines = $comm->lines;
            if (empty($lines) && method_exists($comm, 'fetch_lines')) {
                $comm->fetch_lines();
                $lines = $comm->lines;
            }
            foreach ($lines as $line){
                if(empty($comm->expeditions[$line->id])){ // The product has a quantity of 0 delivered, the shipment for this order is in progress
                    return 0;
                } else if ($line->qty > $comm->expeditions[$line->id]){ // The product was partially delivered, the shipment for this order is in progress
                    return 0;
                } else if ($line->qty <= $comm->expeditions[$line->id]){ // The quantity delivered is the same or superior than the quantity in the order, the shipment is delivered
                    continue;
                }
            }
            // All the products are completely delivered
            return 1;
        }
        return -1;
    }
}