<html>
    <head>
        <meta charset="UTF-8">
        <title>ПОЛНОЕ УДАЛЕНИЕ ЗАКАЗА от <?= getenv('app.title') ?></title>
    </head>
    <body>
        <h2 style="color:red"> ПОЛНОЕ УДАЛЕНИЕ ЗАКАЗА</h2>

        <p>
            Добрый день.
        </p>
        <p>
            Заказ был полностью удален. Внимание! У заказа может остаться не отмененный чек и остаток по оплате. В ручном режиме проверить и, при необходимости, вернуть средства  и отменить чек
        </p>

        <?php if ($invoice_link??0): ?>
            <h3 style="color:red">Выбит чек <?=$invoice_date?></h3>
            <p>
                <a href="<?=$invoice_link??''?>"><?= $invoice_link??'' ?></a>
            </p>
        <?php else: ?>
            <p>Чека нет</p>
        <?php endif; ?>

        <?php if ($payment_card_confirm_sum>0): ?>
            <h3 style="color:red">Остаток оплаты покупателя <?=$payment_card_confirm_sum?></h3>
            <p>
                Номер трансакции: <?=$payment_card_fixate_id?>
            </p>
        <?php else: ?>
            <p>Остатка оплаты нет</p>
        <?php endif; ?>
        <p>
            С уважением,  <?= getenv('app.title') ?> Bot.
        </p>
    </body>
</html>