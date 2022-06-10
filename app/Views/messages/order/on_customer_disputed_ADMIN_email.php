<html>
    <head>
        <meta charset="UTF-8">
        <title>Заказ №<?= $order->order_id ?> от <?= getenv('app.title') ?></title>
    </head>
    <body>
        <h2> Возражение на заказ №<?= $order->order_id ?></h2>

        <p>
            Добрый день, <?=$store->store_name?>.
        </p>
        <p >
            <a href="https://tezkel.com/#/order-<?= $order->order_id ?>">
                новый заказ №<?= $order->order_id ?>.
            </a>
        </p>
        <p>
            По заказу №<?= $order->order_id ?> поступило возражение от клиента. 
        </p>
        <p>
            Свяжитель с Клиентом <?=$customer->user_name??'-'?> <?=$customer->user_phone??'-'?>
        </p>
        <p>
            Свяжитель с Курьером <?=$courier->user_name??'-'?>  <?=$courier->user_phone??'-'?>
        </p>
        <p>
            Свяжитель с Поставщиком <?=$supplier->store_name??'-'?> <?=$supplier->store_phone??'-'?>
        </p>
        <p>
            Больше действий с заказом <a href="https://tezkel.com/#/order-<?= $order->order_id ?>">в приложении</a>
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
            table{
                border-collapse: collapse;
                border:1px solid #ccc;
                width:100%;
            }
            table td,table th{
                padding:10px;
                border-bottom:1px solid #ccc;
            }
            table tr:nth-child(even){
                background-color: #f5fcff;
            }
        </style>
        <table>
            <tr style="border-bottom: 2px #6cf solid;">
                <th></th>
                <th>Товар</th>
                <th style="text-align: right">Количество</th>
                <th style="text-align: right">Цена</th>
                <th style="text-align: right">Сумма</th>
                <th>Комментрий</th>
            </tr>
                <?php $active_count = 0;
                foreach ($order->entries as $entry): ?>
                <?php if ($entry->deleted_at) continue; ?>
                <tr>
                    <td><?= ++$active_count ?></td>
                    <td><?= $entry->entry_text ?></td>
                    <td style="text-align: right"><?= $entry->entry_quantity ?></td>
                    <td style="text-align: right"><?= $entry->entry_price ?></td>
                    <td style="text-align: right"><?= $entry->entry_sum ?></td>
                    <td><?= $entry->entry_comment ?></td>
                </tr>
                <?php endforeach; ?>
            <tr>
                <td colspan="4">Сумма</td>
                <td style="text-align: right;font-weight: bold"><?= $order->order_sum_product ?></td>
            </tr>
        </table>
        <p>
            С уважением,  <?= getenv('app.title') ?> Bot.
        </p>
    </body>
</html>