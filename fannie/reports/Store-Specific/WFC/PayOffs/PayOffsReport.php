<?php
include('../../../../config.php');
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../../../../classlib2.0/FannieAPI.php');
}

class PayOffsReport extends FannieReportPage
{
    protected $report_headers = array('Date', '# of Owners', '$ Total');
    protected $required_fields = array('date1', 'date2');
    protected $title = 'Pay Off Report';
    protected $header = 'Pay Off Report';

    public function preprocess()
    {
        $this->addScript($this->config->get('URL') . 'src/javascript/Chart.min.js');
        $this->addScript($this->config->get('URL') . 'src/javascript/CoreChart.js');

        return parent::preprocess();
    }

    public function report_content() 
    {
        $default = parent::report_content();

        if ($this->report_format == 'html') {
            $default .= '<div id="chartDiv"><canvas id="chartCanvas"></canvas></div>';
            $this->addOnloadCommand('showGraph()');
        }

        return $default;
    }

    public function javascriptContent()
    {
        if ($this->report_format != 'html') {
            return '';
        }

        return <<<JAVASCRIPT
function showGraph() {
    var xData = $('td.reportColumn0').toArray().map(x => x.innerHTML.trim());
    var yData = $('td.reportColumn1').toArray().map(x => Number(x.innerHTML.trim()));
    CoreChart.lineChart('chartCanvas', xData, [yData], ['Paid Off Equity']);
}
JAVASCRIPT;
    }

    public function fetch_report_data()
    {
        try {
            $date1 = $this->form->date1;
            $date2 = $this->form->date2;
        } catch (Exception $ex) {
            return array();
        }
        
        $dlog = DTransactionsModel::selectDlog($date1, $date2);

        $equityP = $this->connection->prepare("
            SELECT SUM(stockPurchase) AS ttl,
                MAX(tdate) AS tdate
            FROM " . $this->config->get('TRANS_DB') . $this->connection->sep() . "stockpurchases
            WHERE card_no=?");
        $classA = $this->connection->prepare("
            SELECT SUM(total) AS ttl
            FROM {$dlog}
            WHERE tdate BETWEEN ? AND ?
                AND department=992
                AND trans_num=?");

        $query = "
            SELECT YEAR(tdate),
                MONTH(tdate),
                DAY(tdate),
                trans_num,
                card_no,
                total
            FROM {$dlog}
            WHERE tdate BETWEEN ? AND ?
                AND department=991
                AND register_no <> 30";
        $prep = $this->connection->prepare($query);
        $args = array($date1 . ' 00:00:00', $date2 . ' 23:59:59');
        $data = array();
        $res = $this->connection->execute($prep, $args);
        while ($row = $this->connection->fetchRow($res)) {
            $ts = mktime(0,0,0,$row[1],$row[2],$row[0]);
            $date = date('Y-m-d', $ts);
            if (!isset($data[$date])) {
                $data[$date] = array($date, 0, 0);
            }
            $equity = $this->connection->getRow($equityP, array($row['card_no']));
            if (!$equity || $equity['ttl'] < 100) {
                continue;
            }
            if ($date != date('Y-m-d', strtotime($equity['tdate']))) {
                continue;
            }
            $aArgs = $args;
            $aArgs[] = $row['trans_num'];
            if ($this->connection->getValue($classA, $aArgs)) {
                continue;
            }
            $data[$date][1]++;
            $data[$date][2] += $row['total'];
        }

        return $this->dekey_array($data);
    }

    function calculate_footers($data)
    {
        $sum = array_reduce($data, function($c, $i) { return $c + $i[1]; }, 0);
        $sum2 = array_reduce($data, function($c, $i) { return $c + $i[2]; }, 0);
        return array('Total', $sum, $sum2);
    }

    public function form_content()
    {
        $dates = FormLib::standardDateFields();
        return <<<HTML
<form method="get">
    {$dates}
    <div class="form-group">
        <button type="submit" class="btn btn-default btn-core">Get Report</button>
    </div>
</form>
HTML;
    }
}

FannieDispatch::conditionalExec();

