<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Statistics extends \App\Controllers\BaseController{

    use ResponseTrait;
    private $reports_path='../writable/reports';
    public function sellParametersGet(){
        $store_id=  (int) $this->request->getPost('store_id');
        $point_span=(int) $this->request->getPost('point_span');
        $point_num= (int) $this->request->getPost('point_num');

        $StoreModel=model('StoreModel');
        if( !$StoreModel->permit($store_id,'w') ){
            return $this->failForbidden('forbidden');
        }
        $tmp_drop_sql="DROP TEMPORARY TABLE IF EXISTS tmp_sell_parameters";
        $tmp_create_sql="
            CREATE TEMPORARY TABLE tmp_sell_parameters AS (
                SELECT
                MAX(created_at) point_finish,
                FLOOR(DATEDIFF(NOW(),created_at)/(:point_span:+0.001)) point_index,
                COUNT(order_id) order_count,
                ROUND(SUM(order_sum_product)) order_sum,
                ROUND(SUM(order_sum_product)/COUNT(order_id)) order_sum_avg,
                ROUND(SUM(entry_quantity_total)) product_quantity
            FROM
                (SELECT
                    order_id,
                    order_list.created_at,
                    order_sum_product,
                    SUM(entry_quantity) entry_quantity_total
                FROM
                    order_list
                        JOIN
                    order_entry_list USING(order_id)
                WHERE
                    order_list.created_at>DATE_SUB(NOW(), INTERVAL :overall_span: DAY)
                    AND order_status='finished'
                GROUP BY order_id) inner_t
            GROUP BY point_index
            )";
        $avg_sql="SELECT 
                    AVG(order_count) order_count,
                    AVG(order_sum) order_sum,
                    AVG(order_sum_avg) order_sum_avg,
                    AVG(product_quantity) product_quantity
                FROM
                    tmp_sell_parameters";
        $points_sql="SELECT * FROM tmp_sell_parameters";
        
        $db = db_connect();
        $db->query($tmp_drop_sql);
        $db->query($tmp_create_sql, ['point_span'=> $point_span,'overall_span' => $point_span*$point_num]);

        $response=[
            'head'=>[
                'avg'=>$db->query($avg_sql)->getRow()
            ],
            'body'=>$db->query($points_sql)->getResult()
        ];
        return $this->respond($response);
    }

    public function sellProductFunnelGet(){
        $store_id=  (int) $this->request->getPost('store_id');
        $point_span=(int) $this->request->getPost('point_span');
        $point_num= (int) $this->request->getPost('point_num');
        $StoreModel=model('StoreModel');
        if( !$StoreModel->permit($store_id,'w') ){
            return $this->failForbidden('forbidden');
        }
        $ProductModel=model('ProductModel');
        $store_product_count=$ProductModel->where('store_id',$store_id)->where('is_disabled',0)->select('COUNT(*) c')->get()->getRow('c');

        $tmp_drop_sql="DROP TEMPORARY TABLE IF EXISTS tmp_sell_parameters";
        $tmp_create_sql="
            CREATE TEMPORARY TABLE tmp_sell_parameters AS (
            SELECT
                MAX(created_at) point_finish,
                FLOOR(DATEDIFF(NOW(),created_at)/(:point_span:+0.001)) point_index,
                SUM(IF(act_type='get',:store_product_count:,0)) product_viewed,
                SUM(IF(act_type='create',entry_count,0)) product_added,
                SUM(IF(act_type='finish',entry_count,0)) product_purchased
            FROM
                (SELECT
                    created_at,
                    act_type,
                    IFNULL(act_data->>'$.entry_count',0) entry_count
                FROM
                    metric_act_list
                WHERE
                    created_at>DATE_SUB(NOW(), INTERVAL :overall_span: DAY)
                    AND (
                        act_group='store' AND act_type='get' AND act_target_id=:store_id:
                        OR act_group='order' AND act_type='create' AND act_data->>'$.store_id'=:store_id:
                        OR act_group='order' AND act_type='finish' AND act_data->>'$.store_id'=:store_id:
                    )
                ) inner_t
            GROUP BY point_index
            )";
        $avg_sql="SELECT 
                    AVG(product_viewed) product_viewed,
                    AVG(product_added) product_added,
                    AVG(product_purchased) product_purchased
                FROM
                    tmp_sell_parameters";
        $points_sql="SELECT * FROM tmp_sell_parameters";
        
        $db = db_connect();
        $db->query($tmp_drop_sql);
        $db->query($tmp_create_sql, ['point_span'=> $point_span,'overall_span' => $point_span*$point_num,'store_id'=>$store_id,'store_product_count'=>$store_product_count]);

        $response=[
            'head'=>[
                'avg'=>$db->query($avg_sql)->getRow()
            ],
            'body'=>$db->query($points_sql)->getResult()
        ];
        return $this->respond($response);
    }

    


    /**
     * Generates sell report 
     * @todo implement offset limit
     */
    public function sellReportProductGet(){
        $store_id=$this->request->getPost('store_id');
        $start_at=$this->request->getPost('start_at');
        $finish_at=$this->request->getPost('finish_at');
        $search_query=$this->request->getPost('searchQuery');
        $output=$this->request->getPost('output');

        $StoreModel=model('StoreModel');
        if( !$store_id ){
            $StoreModel->permitWhere('w');
            $store_id=$StoreModel->get(1)->getRow('store_id');
        }
        if( !$StoreModel->permit($store_id,'w') ){
            return $this->failForbidden('forbidden');
        }
        $OrderModel=model('OrderModel');
        $OrderModel->join('order_entry_list','order_id');
        $OrderModel->join('order_group_list','group_id=order_group_id');
        $OrderModel->join('store_list','store_id=order_store_id');

        $app_title=getenv('app.title');
        $card_title="карта";
        $cash_title="наличные";
        $store_cash_title="нал. магазин";
        $OrderModel->select("
            order_list.created_at,
            order_id,
            entry_text,
            entry_price,
            entry_quantity,
            COALESCE(entry_discount,0) entry_discount,
            ROUND(entry_quantity *entry_price - COALESCE(entry_discount, 0),2) AS `entry_sum`,
            COALESCE(order_data->>'$.order_fee',order_data->>'$.payment_fee',0) fee,
            IF(order_data->>'$.delivery_by_courier','$app_title',
            IF(order_data->>'$.delivery_by_store',store_name,
            '-')) delivery,

            IF(order_data->>'$.payment_by_card','$card_title',
            IF(order_data->>'$.payment_by_cash','$cash_title',
            IF(order_data->>'$.payment_by_cash_store','$store_cash_title',
            '-'))) payment_type,
            order_data->>'$.invoice_link' invoice_link
        ");
        if( $store_id ){
            $OrderModel->where('store_id',$store_id);
        }
        $OrderModel->orderBy('order_id','DESC');
        $OrderModel->where('order_list.order_status','finished');
        $OrderModel->where('order_list.created_at>',$start_at);
        $OrderModel->where('order_list.created_at<',$finish_at);
        if( $search_query ){
            $OrderModel->like('entry_text',$search_query);
        }
        $body=$OrderModel->get()->getResult();
        
        $total_quantity=0;
        $total_sum=0;
        $total_discount=0;
        $total_commission=0;
        foreach($body as $row){
            $total_quantity+=$row->entry_quantity;
            $total_sum+=$row->entry_sum;
            $total_discount+=$row->entry_discount;
            $total_commission+=$row->fee*$row->entry_sum/100;
        }
        $head=[
            'start_at'=>$start_at,
            'finish_at'=>$finish_at,
            'search_query'=>$search_query,
            'total_quantity'=>number_format($total_quantity,2,'.',''),
            'total_sum'=>number_format($total_sum,2,'.',''),
            'total_discount'=>number_format($total_discount,2,'.',''),
            'total_commission'=>number_format($total_commission,2,'.',''),
            'total_topay'=>number_format($total_sum-$total_commission,2,'.',''),
        ];

        $report=['head'=>$head,'body'=>$body];
        if( $output=='xlsx' ){
            return $this->statSellReportExport( $report );
        }
        return $this->respond($report);
    }

    public function statSellReportExport( $report ){
        function style_total($text,$is_num=false){
            if( $is_num ){
                $text=style_num($text);
            }
            return '<style bgcolor="#ddeeff"><b>'.$text.'</b></style>';
        }
        function style_num($text){
            return '<right>'.number_format($text,2,'.','').'</right>';
        }
        $app_title=getenv('app.title');
        $report['body'][]=[
            style_total(''),
            style_total(''),
            style_total('Итого'),
            style_total(''),
            style_total($report['head']['total_quantity'],true),
            style_total($report['head']['total_discount'],true),
            style_total($report['head']['total_sum'],true),
            style_total($report['head']['total_commission'],true),
            style_total(''),
            style_total(''),
            style_total(''),
        ];
        $header=[
            ['<center><style font-size="24" height="50">Отчет по заказам</style></center>',null,null,null,null],
            ['Начальная дата',$report['head']['start_at']],
            ['Конечная дата',$report['head']['finish_at']],
            [],
            ['Фильтр',$report['head']['search_query']],
            ['<b>Итог</b>'],
            ['Количество',style_num($report['head']['total_quantity'])],
            ['Скидка',style_num($report['head']['total_discount'])],
            ['Сумма',style_num($report['head']['total_sum'])],
            ['Вознаграждение',style_num($report['head']['total_commission'])],
            ['К выплате',style_num($report['head']['total_topay'])],
            [],
            [
                style_total('Время'),
                style_total('Заказ #'),
                style_total('Товар'),
                style_total('Цена'),
                style_total('Количество'),
                style_total('Скидка'),
                style_total('Сумма'),
                style_total('Вознаграждение %'),
                style_total('Доставка'),
                style_total('Оплата'),
                style_total('Чек'),
            ],
        ];

        $body=$report['body'];
        $footer=[
            [],
            [],
            [null,null,'<style border="none none medium#000000 none">'.$app_title.'</style>']
        ];
        $data=array_merge($header,$body,$footer);
        $tmp_file_name=md5(microtime().rand(0,1000));
        \App\Libraries\xlsx\SimpleXLSXGen::fromArray($data)
            ->setDefaultFontSize(12)
            ->mergeCells('A1:K1')
            ->saveAs($this->reports_path.'/'.$tmp_file_name);
        return $this->respondCreated($tmp_file_name);
    }

    private function folderClean($folderName){
        $timeout=2*24*60*60;//2 days
        if (file_exists($folderName)) {
            foreach (new \DirectoryIterator($folderName) as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }
                if ($fileInfo->isFile() && time() - $fileInfo->getCTime() >= $timeout) {
                    unlink($fileInfo->getRealPath());
                }
            }
        }
    }
    public function download($hash,$filename){
        $this->folderClean($this->reports_path);
        $filepath=$this->reports_path.'/'.$hash;
        if(!file_exists($filepath)){
            http_response_code(404);
            die('notfound');
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Transfer-Encoding: Binary"); 
        header("Content-disposition: attachment; filename=\"" . basename($filename) . "\""); 
        readfile($filepath); 
    }
 
}
