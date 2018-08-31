<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/20
 * Time: 上午12:07
 */

namespace app\model;
use plugin\push\printer;

class receipt_model
{
    private $printer;

    public function __construct()
    {
        $this->printer = new printer();
    }

    public function order($data)
    {
        $printer = $this->printer;
        $printer->init_print_content();
        $printer->center($GLOBALS['cfg']['site_name'])->bold()->scale()->append();
        $printer->append($printer->divide_line());
        $printer->append('订单号：' . $data['order']['order_id']);
        $printer->bold('支付方式：' . $data['order']['payment_method_name'])->append();
        $printer->bold('配送方式：' . $data['order']['shipping_method_name'])->append();
        $printer->append('下单时间：' . date("Y-m-d H:i:s", $data['order']['created_date']));
        (trim($data['order']['memos'])) && ($printer->bold()->scale()->append("备注：{$data['order']['memos']}"));
        $printer->append($printer->divide_line());

        $goods_name_width = 18;
        $goods_num_width = 6;
        $goods_price_width = 8;
        $goods_list_header = $printer->padding_space('商品名称', $goods_name_width)
            . $printer->padding_space('数量', $goods_num_width, STR_PAD_LEFT)
            . $printer->padding_space('单价', $goods_price_width, STR_PAD_LEFT);
        $printer->append($goods_list_header);
        $printer->append($printer->divide_line());

        $totalNum = 0;
        foreach ($data['goods_list'] as $item) {
            $line = "";
            $str_len = mb_strlen($item['goods_name']);
            if ($str_len <= $goods_name_width / 2) {
                $line .= $printer->padding_space($item['goods_name'], $goods_name_width)
                    . $printer->padding_space("x{$item['goods_qty']}", $goods_num_width, STR_PAD_LEFT)
                    . $printer->padding_space($printer->price($item['goods_price']), $goods_price_width, STR_PAD_LEFT);
            } else {
                for ($i = 0; $i <= $str_len; $i += $goods_name_width / 2) {
                    if ($i != 0) {
                        $line .= $printer->padding_space(mb_substr($item['goods_name'], $i, $goods_name_width / 2),
                            $goods_name_width + $goods_num_width + $goods_price_width);
                    } else {
                        $line .= $printer->padding_space(mb_substr($item['goods_name'], $i, $goods_name_width / 2), $goods_name_width)
                            . $printer->padding_space("x{$item['goods_qty']}", $goods_num_width, STR_PAD_LEFT)
                            . $printer->padding_space($printer->price($item['goods_price']), $goods_price_width, STR_PAD_LEFT);
                    }
                }
            }
            $printer->append($line);

            $totalNum += $item['goods_qty'];
        }
        $printer->append($printer->divide_line());
        $printer->append('配送费：' . $printer->price($data['order']['shipping_amount']) . " 元");
        if (isset($data['order']['full_cut']) && $data['order']['full_cut']) {
            $printer->append('优惠方式：满减');
            $printer->append('优惠金额：' . $printer->price($data['order']['full_cut']) . " 元");
        }

        $printer->append($printer->black_line());
        $need_pay_text = (trim($data['order']['payment_method_name']) == "货到付款") ? "应付" : "实付";
        $printer->bold()->append("共 {$totalNum} 件，{$need_pay_text} " . $printer->price($data['order']['order_amount']) . " 元");
        $printer->append($printer->divide_line());
        $printer->scale()->append("地址:" . $data['consignee']['address'] . $data['consignee']['room']);
        $printer->bold($data['consignee']['mobile'])->scale()->append();
        $user_tag = (isset($data['order']['history_count']) && $data['order']['history_count'] > 1) ?
            "[下单" . ($data['order']['history_count']) . "次]" : "[新用户]";
        $printer->append($data['consignee']['receiver'] . $user_tag);

        $printer->append($printer->black_line());
        $printer->center()->append("扫描下方二维码，申请售后服务");

        return $printer->get_print_contents();
    }
}