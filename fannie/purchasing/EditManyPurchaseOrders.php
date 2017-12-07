<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

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

include(dirname(__FILE__) . '/../config.php');
if (!class_exists('FannieAPI')) {
    include_once(__DIR__ . '/../classlib2.0/FannieAPI.php');
}

class EditManyPurchaseOrders extends FannieRESTfulPage 
{
    protected $header = 'Purchase Orders';
    protected $title = 'Purchase Orders';

    public $description = '[Multi-Vendor Purchase Order] creates and edits multiple purchase orders
    as items from different vendors are scanned.';

    protected $must_authenticate = true;

    function preprocess()
    {
        $this->__routes[] = 'get<search>';
        $this->__routes[] = 'get<id><sku><qty>';
        return parent::preprocess();
    }

    protected function get_search_handler()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $ret = array(); 

        // search by vendor SKU
        $skuQ = 'SELECT brand, description, size, units, cost, sku,
            i.vendorID, vendorName
            FROM vendorItems AS i LEFT JOIN vendors AS v ON
            i.vendorID=v.vendorID WHERE sku LIKE ?';
        $skuP = $dbc->prepare($skuQ);
        $skuR = $dbc->execute($skuP, array('%'.$this->search.'%'));
        while($w = $dbc->fetch_row($skuR)){
            $result = array(
            'sku' => $w['sku'],
            'title' => '['.$w['vendorName'].'] '.$w['brand'].' - '.$w['description'],
            'unitSize' => $w['size'],   
            'caseSize' => $w['units'],
            'unitCost' => sprintf('%.2f',$w['cost']),
            'caseCost' => sprintf('%.2f',$w['cost']*$w['units']),
            'vendorID' => $w['vendorID']
            );
            $ret[] = $result;
        }
        if (count($ret) > 0){
            echo json_encode($this->addCurrentQty($dbc, $ret));
            return False;
        }

        // search by UPC
        $upcQ = 'SELECT brand, description, size, units, cost, sku,
            i.vendorID, vendorName
            FROM vendorItems AS i LEFT JOIN vendors AS v ON
            i.vendorID = v.vendorID WHERE upc=?';
        $upcP = $dbc->prepare($upcQ);
        $upcR = $dbc->execute($upcP, array(BarcodeLib::padUPC($this->search)));
        while($w = $dbc->fetch_row($upcR)){
            $result = array(
            'sku' => $w['sku'],
            'title' => '['.$w['vendorName'].'] '.$w['brand'].' - '.$w['description'],
            'unitSize' => $w['size'],   
            'caseSize' => $w['units'],
            'unitCost' => sprintf('%.2f',$w['cost']),
            'caseCost' => sprintf('%.2f',$w['cost']*$w['units']),
            'vendorID' => $w['vendorID']
            );
            $ret[] = $result;
        }
        if (count($ret) > 0){
            echo json_encode($this->addCurrentQty($dbc, $ret));
            return False;
        }

        echo '[]';
        return False;
    }

    private function addCurrentQty($dbc, $results)
    {
        $idCache = array();
        $uid = FannieAuth::getUID($this->current_user);
        $lookupP = $dbc->prepare('SELECT quantity FROM PurchaseOrderItems WHERE orderID=? AND sku=?');
        for ($i=0; $i<count($results); $i++) {
            $vendorID = $results[$i]['vendorID'];
            $sku = $results[$i]['sku'];
            if (isset($idCache[$vendorID])) {
                $orderID = $idCache[$vendorID];
            } else {
                $orderID = $this->getOrderID($vendorID, $uid);
                $idCache[$vendorID] = $orderID;
            }
            $qty = $dbc->getValue($lookupP, array($orderID, $sku));
            $results[$i]['currentQty'] = $qty === false ? 0 : $qty;
        }

        return $results;
    }

    /**
      AJAX call: ?id=<vendor ID>&sku=<vendor SKU>&qty=<# of cases>
      Add the given SKU & qty to the order
    */
    protected function get_id_sku_qty_handler()
    {
        global $FANNIE_OP_DB;

        $dbc = FannieDB::get($FANNIE_OP_DB);
        $orderID = $this->getOrderID($this->id, FannieAuth::getUID($this->current_user));

        $vitem = new VendorItemsModel($dbc);
        $vitem->vendorID($this->id);
        $vitem->sku($this->sku);
        $vitem->load();

        $pitem = new PurchaseOrderItemsModel($dbc);
        $pitem->orderID($orderID);
        $pitem->sku($this->sku);
        $pitem->quantity($this->qty);
        $pitem->unitCost($vitem->cost());
        $pitem->caseSize($vitem->units());
        $pitem->unitSize($vitem->size());
        $pitem->brand($vitem->brand());
        $pitem->description($vitem->description());
        $pitem->internalUPC($vitem->upc());
    
        $pitem->save();

        $ret = array();
        $pitem->reset();
        $pitem->orderID($orderID);
        $pitem->sku($this->sku);
        if (count($pitem->find()) == 0){
            $ret['error'] = 'Error saving entry';
        } else {
            $ret['sidebar'] = $this->calculate_sidebar();
        }
        echo json_encode($ret);
        return false;
    }

    protected function calculate_sidebar()
    {
        global $FANNIE_OP_DB;
        $userID = FannieAuth::getUID($this->current_user);

        $dbc = FannieDB::get($FANNIE_OP_DB);
        $q = 'SELECT p.orderID, vendorName, 
            sum(case when i.orderID is null then 0 else 1 END) as rows, 
            MAX(creationDate) as date,
            sum(unitCost*caseSize*quantity) as estimatedCost
            FROM PurchaseOrder as p 
            INNER JOIN vendors as v ON p.vendorID=v.vendorID
            LEFT JOIN PurchaseOrderItems as i
            ON p.orderID=i.orderID
            WHERE p.userID=?
            GROUP BY p.orderID, vendorName
            ORDER BY vendorName';
        $p = $dbc->prepare($q);
        $r = $dbc->execute($p, array($userID));  

        $ret = '<ul id="vendorList">';
        while($w = $dbc->fetch_row($r)){
            $ret .= '<li><span id="orderInfoVendor">'.$w['vendorName'].'</span>';
            $ret .= '<ul class="vendorSubList"><li>'.$w['date'];
            $ret .= '<li># of Items: <span class="orderInfoCount">'.$w['rows'].'</span>';
            $ret .= '<li>Est. cost: $<span class="orderInfoCost">'.sprintf('%.2f',$w['estimatedCost']).'</span>';
            $ret .= '</ul></li>';
        }
        $ret .= '</ul>';

        return $ret;
    }

    protected function get_view()
    {
        $ret = '<div class="col-sm-6">';
        $ret .= '<div id="ItemSearch">';
        $ret .= '<form class="form" action="" onsubmit="itemSearch();return false;">';
        $ret .= '<label>UPC/SKU</label><input class="form-control" type="text" id="searchField" />';
        $ret .= '<button type="submit" class="btn btn-default">Search</button>';
        $ret .= '</form>';
        $ret .= '</div>';
        $ret .= '<p><div id="SearchResults"></div></p>';
        $ret .= '</div>';

        $ret .= '<div class="col-sm-6" id="orderInfo">';
        $ret .= $this->calculate_sidebar();
        $ret .= '</div>';

        $this->add_onload_command("\$('#searchField').focus();\n");
        $this->add_script('js/editmany.js');
    
        return $ret;
    }

    private function getOrderID($vendorID, $userID)
    {
        $dbc = FannieDB::get($this->config->get('OP_DB'));
        $store = COREPOS\Fannie\API\lib\Store::getIdByIp();
        $cutoff = date('Y-m-d', strtotime('30 days ago'));
        $orderQ = 'SELECT orderID FROM PurchaseOrder WHERE
            vendorID=? AND userID=? AND storeID=? AND creationDate > ? and placed=0
            ORDER BY creationDate DESC';
        $orderP = $dbc->prepare($orderQ);
        $orderID = $dbc->getValue($orderP, array($vendorID, $userID, $store, $cutoff));
        if (!$orderID) {
            $insQ = 'INSERT INTO PurchaseOrder (vendorID, creationDate,
                placed, userID, storeID) VALUES (?, '.$dbc->now().', 0, ?, ?)';
            $insP = $dbc->prepare($insQ);
            $insR = $dbc->execute($insP, array($vendorID, $userID, $store));
            $orderID = $dbc->insertID();
        }

        return $orderID;
    }

    public function helpContent()
    {
        return '
            <p>Enter UPCs or SKUs. If there are multiple matching items,
            use the dropdown to specify which. Then enter the number
            of cases to order.</p>
            <p>Each time you select an item from a different vendor,
            a pending order is automatically created for that vendor
            if one does not already exist.</p>';
    }

    public function unitTest($phpunit)
    {
        $phpunit->assertNotEquals(0, strlen($this->get_view()));
        $this->search = '4011';
        ob_start();
        $this->get_search_handler();
        $phpunit->assertInternalType('array', json_decode(ob_get_clean(), true));
        $this->id = 1;
        $this->sku = '4011';
        $this->qty = 1;
        ob_start();
        $this->get_id_sku_qty_handler();
        $phpunit->assertInternalType('array', json_decode(ob_get_clean(), true));
    }
}

FannieDispatch::conditionalExec();

