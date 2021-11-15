<html>
    <head>
        <meta charset="UTF-8">
        <title>Заказ №<?= $order->order_id ?> от <?= getenv('app.title') ?></title>
    </head>
    <body>
        <h2>Заказ №<?= $order->order_id ?> от <?= getenv('app.title') ?></h2>

        <p>
            Добрый день, <?= $reciever->user_name ?>.
        </p>
        <p>
            Вас приветствует служба доставки <?= getenv('app.title') ?>. 
            Рады сообщить, что вам поступил заказ от клиента  для "<?= $store->store_name ?>". 
        </p>
        <p>
            Курьер направляется за этим заказом. Просьба подготовить его.
        </p>
        <?php if ($order->order_description): ?>
            <h3>Комментарий клиента</h3>
            <p>
                <i><?= $order->order_description ?></i>
            </p>
        <?php endif; ?>
        <h3>Состав заказа</h3>
        <style>
            table{
                border-collapse: collapse;
                border:1px solid #ccc;
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
                <td style="text-align: right;font-weight: bold"><?= $order->order_sum_total ?></td>
            </tr>
        </table>
        <p>
            С уважением, команда <?= getenv('app.title') ?>.
        </p>
    </body>
</html>