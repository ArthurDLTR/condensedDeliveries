<?php
/* Copyright (C) 2024 LENOBLE Arthur <arthurl52100@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

 /**
 * \file    core/triggers/interface_99_modCondensedDeliveries_CondensedDeliveriesTriggers.class.php
 * \ingroup CondensedDeliveries
 * \brief   Delivery validation trigger.
 *
 * Trigger to class as delivered all the orders linked to an expedition
 *
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class of triggers for CondensedDeliveries
 */
class InterfaceCondensedDeliveriesTriggers extends DolibarrTriggers
{
    /**
     * Constructor
     * 
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        parent::__construct($db);
        $this->family = "Atu home made";
		$this->description = "Trigger to class as delivered all the orders linked to an expedition.";
		// $this->version = self::VERSIONS['dev'];
		$this->picto = 'condenseddeliveries@condenseddeliveries';
    }

    /**
	 * Function called when a Dolibarr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
        $methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		$callback = array($this, $methodName);
		if (is_callable($callback)) {
		// 	dol_syslog(
		// 		"Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id
		// 	);

		 	return call_user_func($callback, $action, $object, $user, $langs, $conf);
		}

        switch ($action) {
            case 'SHIPPING_VALIDATE':
                $object->fetchObjectLinked(null, 'commande', $object->id, 'shipping');
                // var_dump($object->linkedObjects);
                // print 'VAR DUMP pour l\'object seul : <br>';
                // var_dump($object->linkedObjects["commande"]);
                // print 'VAR DUMP pour les ids des objets liés : <br>';
                // var_dump($object->linkedObjectsIds);
                // print "Object lié : ".$object->linkedObjects[0]['element'].' et son id : '.$object->linkedObjects[0]['id'].'<br>';
                foreach ($object->linkedObjects["commande"] as $linkedobj){
                    print "Object lié : ".$linkedobj->id.'<br>';
                    $object->setStatut(Commande::STATUS_CLOSED, $linkedobj->id, 'commande');
                }
                break;
            default:
                // dol_syslog("Trigger '".$this->name."' for action '".$action."' launched by ".__FILE__.". id=".$object->id);
				break;
        }
    }
}