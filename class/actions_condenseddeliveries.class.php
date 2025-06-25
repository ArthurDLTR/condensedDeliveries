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
        $label = '<img width="15px" height="15px" src="'.DOL_URL_ROOT.'/custom/condenseddeliveries/img/object_createdelivery.png"><span> '.$langs->trans("CreateCondensedDeliveries").'</span>';
        
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
        global $db, $conf, $langs, $user, $my_soc;

        $langs->load("condenseddeliveries@condenseddeliveries");

        if(GETPOST('massaction') == 'CREATE_CONDENSED_DELIVERIES'){

            $arrayOrders = GETPOST('toselect', 'array');
            
            $db->begin();
    
            $comm = new Commande($db);
            $expe = new Expedition($db);
    
            $condDel = new CondensedDeliveries($db);
            $res = $condDel->checkThirdparty($arrayOrders);
            // var_dump($arrayOrders);
            if ($res){
                $comm->fetch($arrayOrders[0]);
                // print "Tiers de la commande : ".$comm->socid;
                $expe->socid = $comm->socid;
                $expe->thirdparty = $comm->thirdparty;
                $expe->date = dol_now();
                $expe->array_options = $comm->array_options;
                $arrayIds = array();
                
                foreach ($arrayOrders as $id){
                    // $expe->add_object_linked('order', $id);
                    $arrayIds[] = $id;
                    $res = $comm->fetch($id);
                    // dol_syslog("Id de la commande : ".$id." Trouvé ? ".$res."<br>");
    
                    if ($res > 0){
                        $comm->loadExpeditions();
                        // var_dump($comm->expeditions);
                        $lines = $comm->lines;
                        if (empty($lines) && method_exists($comm, 'fetch_lines')) {
                            $comm->fetch_lines();
                            $lines = $comm->lines;
                        }
                        
                        $fk_parent_line = 0;
                        $i = 0;
                        // Looping on each line of the order and add each one on the delivery
                        foreach ($lines as $line){
                            $desc = ($line->desc ? $line->desc : '');
                            // We have multiples orders to create only one delivery, we must put the ref of order on the invoice line
                            $desc = dol_concatdesc($desc, $langs->trans("Order").' '.$comm->ref.' - '.dol_print_date($comm->date, 'day'));
                            
                            $product_type = ($line->product_type ? $line->product_type : 0);
                            
                            // Reset fk_parent_line for no child products and special product
                            if (($line->product_type != 9 && empty($line->fk_parent_line)) || $lines[$i]->product_type == 9) {
                                $fk_parent_line = 0;
                            }
                            
                            $i++;
                            
                            // Extrafields
                            if (method_exists($line, 'fetch_optionals')) {
                                $line->fetch_optionals();
                                $array_options = $line->array_options;
                            }
                            
                            $expe->context['createfromclone'] = 'createfromclone';
                            
                            $warehouse = getDolGlobalInt('MAIN_DEFAULT_WAREHOUSE');
                            
                            if (empty($warehouse)){
                                $waresql = "SELECT e.rowid as id FROM ".MAIN_DB_PREFIX."entrepot as e WHERE e.statut = 1";
                                $wareres = $db->query($waresql);
                                if($wareres){
                                    $wareobj = $db->fetch_object($wareres);
                                    $warehouse = $wareobj->id;
                                }
                            }
                            // print 'Id entrepot : '.$warehouse." qty : ".$comm->expeditions[$line->id]."<br>";

                            if (empty($comm->expeditions[$line->id])){
                                $lineqty = $line->qty;
                            } else if ($line->qty > $comm->expeditions[$line->id]){
                                $lineqty = $line->qty - $comm->expeditions[$line->id];
                            }
                            if(!empty($lineqty)){
                                // print 'Quantité restante à livrer : '.$lineqty.'<br>';
                                $result = $expe->addline(
                                    $warehouse,
                                    $line->id,
                                    $lineqty,
                                    $array_options
                                );
                                // print "ligne ajoutée produit ".$line->id. ' avec '.$line->qty.' en qty, est-ce ajoutée ? '.$result.'<br>';
                                
                                if ($result >= 0){
                                    $lineid = $result;
                                } else {
                                    print $expe->error;
                                    print $expe->errorhidden;
                                    setEventMessages('Erreur lors de l\'ajout du produit '.$line->id, null, 'errors');
                                }
                                // Define new fk_parent_line
                                if ($result > 0 && $line->product_type == 9){
                                    $fk_parent_line = $result;
                                }
                            }
                            $lineqty = null;
                        }
                    }
                }
    
                $res = $expe->create($user);
                
                // Add all the linked orders to the expedition
                foreach ($arrayIds as $id){
                    $expe->add_object_linked('order', $id);
                }
                
                if ($res > 0) {
                    $db->commit();
                    
                    // Make a redirect to avoid to create expedition twice if we make a refresh or back
                    // $param = '';
                    // if (!empty($mode)) {
                    //     $param .= '&mode='.urlencode($mode);
                    // }
                    // if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
                    //     $param .= '&contextpage='.urlencode($contextpage);
                    // }
                    // if ($limit > 0 && $limit != $conf->liste_limit) {
                    //     $param .= '&limit='.((int) $limit);
                    // }
                    // if ($optioncss != '') {
                    //     $param .= '&optioncss='.urlencode($optioncss);
                    // }
                    // if ($search_all) {
                    //     $param .= '&search_all='.urlencode($search_all);
                    // }
                    // if ($show_files) {
                    //     $param .= '&show_files='.urlencode($show_files);
                    // }
                    // if ($socid > 0) {
                    //     $param .= '&socid='.urlencode($socid);
                    // }
                    // if ($search_status != '') {
                    //     $param .= '&search_status='.urlencode($search_status);
                    // }
                    // if ($search_orderday) {
                    //     $param .= '&search_orderday='.urlencode($search_orderday);
                    // }
                    // if ($search_ordermonth) {
                    //     $param .= '&search_ordermonth='.urlencode($search_ordermonth);
                    // }
                    // if ($search_orderyear) {
                    //     $param .= '&search_orderyear='.urlencode($search_orderyear);
                    // }
                    // if ($search_deliveryday) {
                    //     $param .= '&search_deliveryday='.urlencode($search_deliveryday);
                    // }
                    // if ($search_deliverymonth) {
                    //     $param .= '&search_deliverymonth='.urlencode($search_deliverymonth);
                    // }
                    // if ($search_deliveryyear) {
                    //     $param .= '&search_deliveryyear='.urlencode($search_deliveryyear);
                    // }
                    // if ($search_ref) {
                    //     $param .= '&search_ref='.urlencode($search_ref);
                    // }
                    // if ($search_company) {
                    //     $param .= '&search_company='.urlencode($search_company);
                    // }
                    // if ($search_ref_customer) {
                    //     $param .= '&search_ref_customer='.urlencode($search_ref_customer);
                    // }
                    // if ($search_user > 0) {
                    //     $param .= '&search_user='.urlencode($search_user);
                    // }
                    // if ($search_sale > 0) {
                    //     $param .= '&search_sale='.urlencode($search_sale);
                    // }
                    // if ($search_total_ht != '') {
                    //     $param .= '&search_total_ht='.urlencode($search_total_ht);
                    // }
                    // if ($search_total_vat != '') {
                    //     $param .= '&search_total_vat='.urlencode($search_total_vat);
                    // }
                    // if ($search_total_ttc != '') {
                    //     $param .= '&search_total_ttc='.urlencode($search_total_ttc);
                    // }
                    // if ($search_project_ref >= 0) {
                    //     $param .= "&search_project_ref=".urlencode($search_project_ref);
                    // }
                    // if ($search_billed != '') {
                    //     $param .= '&search_billed='.urlencode($search_billed);
                    // }
                    
                    // header("Location: ".$_SERVER['PHP_SELF'].'?'.$param);
                    
                    if (getDolGlobalInt('CD_OPEN_DIRECTLY_DELIVERY')){
                        header("Location: ".DOL_URL_ROOT.'/expedition/card.php?id='.$expe->id);
                    } else {
                        $texttoshow = $langs->trans('CD_CREATED_EXPE').' (<a href="'.DOL_URL_ROOT.'/expedition/card.php?id='.$expe->id.'">PROV'.$expe->id.'</a>)';
                        setEventMessages($texttoshow, null, 'mesgs');
                    }
                }
            } else {
                // $comm->error = 'All the orders should have the same thirdparty';
                setEventMessages($langs->trans("CD_NOT_SAME_THIRDPARTY"), null, 'errors');
            }
        }
    }
}