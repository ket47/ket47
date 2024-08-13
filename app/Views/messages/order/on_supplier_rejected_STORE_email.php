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
            Заказ от клиента  для "<?= $store->store_name ?>", был отменен вами!
        </p>
        <p>
            Магазин <?= $store->store_name ?> будет заблокирован до <?=$store_block_finish_at?>
        </p>
        <p>
            С уважением, команда <?= getenv('app.title') ?>.
        </p>
    </body>
</html>