        <p>
            Добрый день, <?=$store->store_name?>.
        </p>
        <p>
            Мы получили заказ для вас. Больше действий с заказом <a href="https://tezkel.com/order/order-<?= $order->order_id ?>">в приложении</a>
        </p>

        <?php if ($order->order_description): ?>
            <h3>Комментарий клиента</h3>
            <p>
                <i><?= $order->order_description ?></i>
            </p>
        <?php endif; ?>
        <?php if ($order->order_objection): ?>
            <h3>Проблемы с заказом</h3>
            <p>
                <b><?= $order->order_objection ?></b>
            </p>
        <?php endif; ?>
        <h3>Состав заказа</h3>
        <style>
            .order_table{
                border-collapse: collapse;
                border:1px solid #ccc;
                width:100%;
            }
            .order_table td,table th{
                padding:10px;
                border-bottom:1px solid #ccc;
            }
            .order_table tr:nth-child(even){
                background-color: #f5fcff;
            }
        </style>
        <table class="order_table">
            <tr style="border-bottom: 2px #6cf solid;">
                <th></th>
                <th>Товар</th>
                <th style="text-align: right">Количество</th>
                <th style="text-align: right">Цена</th>
                <th style="text-align: right">Сумма</th>
            </tr>
                <?php $active_count = 0;
                foreach ($order->entries as $entry): ?>
                <?php if ($entry->deleted_at) continue; ?>
                <tr>
                    <td><?= ++$active_count ?></td>
                    <td>
                        <?= $entry->entry_text ?> 
                        <?php if( !empty($entry->entry_comment) ): ?>
                        [<?= $entry->entry_comment ?>]
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right"><?= $entry->entry_quantity ?></td>
                    <td style="text-align: right"><?= $entry->entry_price ?></td>
                    <td style="text-align: right"><?= $entry->entry_sum ?></td>
                </tr>
                <?php endforeach; ?>
            <tr>
                <td colspan="4">Сумма</td>
                <td style="text-align: right;font-weight: bold"><?= $order->order_sum_product ?></td>
            </tr>
        </table>