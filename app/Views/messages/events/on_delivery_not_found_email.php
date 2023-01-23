<html>
    <head>
        <meta charset="UTF-8">
    </head>
    <body>
        <h2>Курьер для покупателя не найден</h2>
        <p>
            Добрый день,
        </p>
        <p>
            Покупатель <?=$customer->user_name?> пытался заказать доставку от продавца <?=$store->store_name?>, но доступных курьеров небыло
        </p>
        <table>
            <tr>
                <td>Продавец</td>
                <td><?=$store->store_name?> <a href="tel:<?=$store->store_phone?>"><?=$store->store_phone?></a></td>
            </tr>
            <tr>
                <td>Покупатель</td>
                <td><?=$customer->user_name?> <a href="tel:<?=$customer->user_phone?>"><?=$customer->user_phone?></a></td>
            </tr>
            <tr>
                <td>Время</td>
                <td><?=date('d.m.Y H:i:s')?></td>
            </tr>
        </table>
        <p>
            С уважением,  <?= getenv('app.title') ?> Bot.
        </p>
    </body>
</html>