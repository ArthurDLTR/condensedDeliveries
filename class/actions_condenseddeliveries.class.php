<?php
/* 
 * Copyright (C) 2025 Arthur LENOBLE
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
 * 	\defgroup   condenseddeliveries     Module CondensedDeliveries
 *  \brief      CondensedDeliveries module descriptor.
 *
 *  \file       htdocs/custom/condenseddeliveries/class/modCondensedDeliveries.class.php
 *  \ingroup    condenseddeliveries
 *  \brief      Description and activation file for module CondensedDeliveries
 */

include_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
include_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
include_once DOL_DOCUMENT_ROOT.'/custom/condenseddeliveries/class/CondensedDeliveries.class.php';

class ActionsCondensedDeliveries {
    /** 
     * Overloading the addMoreActions function
     * @param   parameters      meta data of the hook
     * @param   object          the object you want to process
     * @param   action          current action
     * @return  int             -1 to throw an error, 0 if no error
     */
    public function addMoreMassActions($parameters, $object, $action = 'create'){
        global $arrayofaction, $langs;
        // var_dump($langs);
        $langs->loadLangs(array("condenseddeliveries@condenseddeliveries"));
        $label = img_picto('', 'dollyrevert', 'style="color:black"').$langs->trans("CreateCondensedDeliveries");
        
        $this->resprints = '<option value="CREATE_CONDENSED_DELIVERIES" data-html="'. dol_escape_htmltag($label) .'"> '. $label .'</option>';
        return 0;
    }

    /**
     * Overloading the doActions function
     * @param   parameters      meta data of the hook
     * @param   object          the object you want to process
     * @param   action          current action
     * @return  int             -1 to throw an error, 0 if no error
     */
    public function doActions($parameters, $object, $action='create'){
        global $db, $conf, $langs;

        $arrayOrders = GETPOST('toselect', 'array');
        
        $db->begin();

        $comm = new Commande($db);
        $expe = new Expedition($db);

        $condDel = new CondensedDeliveries($db);
        $res = $condDel->checkThirdparty($arrayOrders);
        
        if ($res){
            $comm->fetch($arrayOrders[0]);
            $expe->socid = $comm->socid;
            $expe->thirdparty = $comm->thirdparty;
            $expe->date = dol_now();
            $expe->arrayoptions = $comm->arrayoptions;

            $res = $expe->create($user);

            if($expe->id > 0){
                foreach ($arrayOrders as $id){
                    $res = $expe->add_object_linked('commande', $id);
                    $comm->fetch($id);

                    if ($res > 0){
                        $lines = $comm->lines;
                        if (empty($lines) && method_exists($cmd, 'fetch_lines')) {
                            $cmd->fetch_lines();
                            $lines = $cmd->lines;
                        }

                        $fk_parent_line = 0;

                        // Looping on each line of the order and add each one on the delivery
                        foreach ($lines as $line){
                            $desc = ($line->desc ? $line->desc : '');
                            // We have multiples orders to create only one delivery, we must put the ref of order on the invoice line
                            $desc = dol_concatdesc($desc, $langs->trans("Order").' '.$cmd->ref.' - '.dol_print_date($cmd->date, 'day'));
                            
                            $product_type = ($line->product_type ? $line->product_type : 0);

                            // Reset fk_parent_line for no child products and special product
							if (($lines[$i]->product_type != 9 && empty($lines[$i]->fk_parent_line)) || $lines[$i]->product_type == 9) {
								$fk_parent_line = 0;
							}

                            // Extrafields
							if (method_exists($line, 'fetch_optionals')) {
								$line->fetch_optionals();
								$array_options = $line->array_options;
							}

                            $expe->context['createfromclone'] = 'createfromclone';

                            $result = $expe->addline(
                                getDolGlobalInt('MAIN_DEFAULT_WAREHOUSE'),
                                $line->id,
                                $line->qty,
                                $array_options
                            );
                            // print "ligne ajoutée produit ".$line->id. ' avec '.$line->qty.' en qty, est-ce ajoutée ? '.$result.'<br>';

                            if ($result > 0){
                                print 'ligne ajoutée <br>';
                                $lineid = $result;
                            } else {
                                setEventMessages('Erreur lors de l\'ajout du produit '.$line->id, null, 'error');
                            }
                            // Define new fk_parent_line
                            if ($result > 0 && $line->product_type == 9){
                                $fk_parent_line = $result;
                            }
                        }
                    }
                }
                $db->commit();
                $texttoshow = $langs->trans('CD_CREATED_EXPE').' (<a href="'.DOL_URL_ROOT.'/expedition/card.php?id='.$expe->id.'">PROV_'.$expe->id.'</a>)';
				setEventMessages($texttoshow, null, 'mesgs');
            }

        } else {
            // $comm->error = 'All the orders should have the same thirdparty';
            setEventMessages($langs->trans("CD_NOT_SAME_THIRDPARTY"), null, 'errors');
        }
    }
}