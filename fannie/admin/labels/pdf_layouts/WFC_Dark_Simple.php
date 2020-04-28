<?php
use COREPOS\Fannie\API\FanniePlugin;
if (!class_exists('FpdfWithBarcode')) {
    include(dirname(__FILE__) . '/../FpdfWithBarcode.php');
}
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../../classlib2.0/FannieAPI.php');
}

class WFC_Dark_Simple extends FpdfWithBarcode
{
    private $tagdate;
    public function setTagDate($str){
        $this->tagdate = $str;
    }

    public function barcodeText($x, $y, $h, $barcode, $len)
    {
        $this->SetFont('Arial','',8);
        $this->Text($x,$y-$h+(17/$this->k),substr($barcode,-$len).' '.$this->tagdate);
    }
}

function WFC_Dark_Simple ($data,$offset=0)
{
    $dbc = FannieDB::get(FannieConfig::config('OP_DB'));
    $pdf = new WFC_Dark_Simple('L','mm','Letter');
    $pdf->AddPage();
    $pdf->SetFillColor(0, 0, 0);
    $pdf->SetTextColor(255, 255, 255);

    define('FPDF_FONTPATH', __DIR__ . '/../../../modules/plugins2.0/CoopDealsSigns/noauto/fonts/');
    $pdf->AddFont('Gill', '', 'GillSansMTPro-Medium.php');
    $pdf->AddFont('Gill', 'B', 'GillSansMTPro-Heavy.php');
    $pdf->SetFont('Gill', 'B', 16); 

    $width = 68;
    $height = 34;
    $left = 3;  
    $top = 3;
    $guide = 0.3;

    $x = $left+$guide; $y = $top+$guide;

    $pdf->SetTopMargin($top); 
    $pdf->SetLeftMargin($left);
    $pdf->SetRightMargin($left);
    $pdf->SetAutoPageBreak(False);

    $i = 0;
    foreach($data as $k => $row){
        if ($i % 24 == 0 && $i != 0) {
            $pdf->AddPage('L');
            $x = $left;
            $y = $top;
            $i = 0;
        }
        if ($i == 0) {
            $pdf = generateSimpleTag($x, $y, $guide, $width, $height, $pdf, $row, $dbc);
        } else if ($i % 4 == 0 && $i != 0) {
            $x = $left+$guide;
            $y += $height+$guide;
        } else {
            $x += $width+$guide;
        }
        $pdf = generateSimpleTag($x, $y, $guide, $width, $height, $pdf, $row, $dbc);
        $i++;
    }

    /*
        Print additional mirror images for back side of tags
    */
    $i = 0;
    $x = $left+$guide; $y = $top+$guide;
    if (count($data) % 4 != 0) {
        for ($j=count($data) % 4; $j<4; $j++) {
            $data[] = '';
        }
    }
    $data = arrayMirrorRowsSimple($data, 4);
    $pdf->AddPage('L');
    foreach($data as $k => $row){
        if ($i % 24 == 0 && $i != 0) {
            $pdf->AddPage('L');
            $x = $left;
            $y = $top;
            $i = 0;
        }
        if ($i == 0) {
            $pdf = generateMirrorTagSimple($x, $y, $guide, $width, $height, $pdf, $row, $dbc);
        } else if ($i % 4 == 0 && $i != 0) {
            $x = $left+$guide;
            $y += $height+$guide;
        } else {
            $x += $width+$guide;
        }
        $pdf = generateMirrorTagSimple($x, $y, $guide, $width, $height, $pdf, $row, $dbc);
        $i++;
    }

    $pdf = $pdf->Output();
}

function generateMirrorTagSimple($x, $y, $guide, $width, $height, $pdf, $row, $dbc)
{
    $upc = $row['upc'];
    $desc = $row['description'];
    $brand = $row['brand'];
    $price = $row['normal_price'];
    $vendor = $row['vendor'];
    $size = $row['size'];
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Gill','', 9);

    // prep tag canvas
    $pdf->SetXY($x,$y);
    $pdf->Cell($width, $height, '', 0, 1, 'C', true); 

    /*
        Add UPC Text
    */
    $pdf->SetXY($x,$y+4);
    $pdf->Cell($width, 8, $upc, 0, 1, 'C', true); 

    /*
        Add Brand & Description Text
    */
    $pdf->SetXY($x,$y+12);
    $pdf->Cell($width, 5, $brand, 0, 1, 'C', true); 
    $pdf->SetXY($x,$y+18);
    $pdf->Cell($width, 5, $desc, 0, 1, 'C', true); 

    /*
        Add Vendor Text
    */
    $pdf->SetXY($x,$y+27);
    $pdf->Cell($width, 5, $vendor, 0, 1, 'C', true); 

    /*
        Add Size Text
    */
    $pdf->SetXY($x+40,$y+27);
    $pdf->Cell('25', 8, $size, 0, 1, 'R', true); 


    /*
        Create Guide-Lines
    */ 
    $pdf->SetFillColor(155, 155, 155);
    // vertical 
    $pdf->SetXY($width+$x, $y);
    $pdf->Cell($guide, $height+$guide, '', 0, 1, 'C', true);

    $pdf->SetXY($x-$guide, $y-$guide); 
    $pdf->Cell($guide, $height+$guide, '', 0, 1, 'C', true);

    // horizontal
    $pdf->SetXY($x, $y-$guide); 
    $pdf->Cell($width+$guide, $guide, '', 0, 1, 'C', true);

    $pdf->SetXY($x, $y+$height); 
    $pdf->Cell($width+$guide, $guide, '', 0, 1, 'C', true);

    $pdf->SetFillColor(0, 0, 0);

    return $pdf;

}

function generateSimpleTag($x, $y, $guide, $width, $height, $pdf, $row, $dbc)
{
    $desc = $row['description'];
    $args = array($row['upc']);
    $prep = $dbc->prepare("
        SELECT pu.description, p.scale
        FROM productUser AS pu
            INNER JOIN products AS p ON pu.upc=p.upc
        WHERE pu.upc = ?");
    $res = $dbc->execute($prep, $args);
    $row = $dbc->fetchRow($res);
    $desc = $row['description'];

    // prep tag canvas
    $pdf->SetXY($x,$y);
    $pdf->Cell($width, $height, '', 0, 1, 'C', true); 

    // use line break to split str if exists; else wordwrap if 2 lines req.
    $lines = array();
    if (strstr($desc, "\r\n")) {
        $lines = explode ("\r\n", $desc);
    } elseif (strlen($desc) > 20) {
        $wrp = wordwrap($desc, strlen($desc)/1.5, "*", false);
        $lines = explode('*', $wrp);
    } else {
        $lines[0] = $desc;
    }

    /*
        Add Description Text
    */
    $strlen = (strlen($lines[0]) > strlen($lines[1])) ? strlen($lines[0]) : strlen($lines[1]);
    if ($strlen >= 30) {
        $pdf->SetFont('Gill','B', 9);  //Set the font 
    } elseif ($strlen > 20 && $strlen < 30) {
        $pdf->SetFont('Gill','B', 12);  //Set the font 
    } else {
        $pdf->SetFont('Gill','B', 16);  //Set the font 
    }
    if (count($lines) > 1) {
        $pdf->SetXY($x,$y+12);
        $pdf->Cell($width, 5, $lines[0], 0, 1, 'C', true); 
        $pdf->SetXY($x, $y+19);
        $pdf->Cell($width, 5, $lines[1], 0, 1, 'C', true); 
    } else {
        $pdf->SetXY($x,$y+15);
        $pdf->Cell($width, 5, $lines[0], 0, 1, 'C', true); 
    }

    /*
        Create Guide-Lines
    */ 
    $pdf->SetFillColor(155, 155, 155);
    // vertical 
    $pdf->SetXY($width+$x, $y);
    $pdf->Cell($guide, $height+$guide, '', 0, 1, 'C', true);

    $pdf->SetXY($x-$guide, $y-$guide); 
    $pdf->Cell($guide, $height+$guide, '', 0, 1, 'C', true);

    // horizontal
    $pdf->SetXY($x, $y-$guide); 
    $pdf->Cell($width+$guide, $guide, '', 0, 1, 'C', true);

    $pdf->SetXY($x, $y+$height); 
    $pdf->Cell($width+$guide, $guide, '', 0, 1, 'C', true);

    $pdf->SetFillColor(0, 0, 0);

    return $pdf;
}

function arrayMirrorRowsSimple($array, $cols)
{
    $newArray = array();
    $chunks = array_chunk($array, $cols);
    foreach ($chunks as $chunk) {
        $chunk = array_reverse($chunk);
        foreach ($chunk as $v) {
            $newArray[] = $v;
        }
    }

    return $newArray;
}