<html>
    <head>
        <meta charset="UTF-8">
        <title>Отмена заказа №<?= $order->order_id ?> от <?= getenv('app.title') ?></title>
    </head>
    <body>
        <h2> Заказ №<?= $order->order_id ?> был отменен клиентом</h2>

        <p>
            С уважением,  <?= getenv('app.title') ?> Bot.
        </p>
    </body>
</html>