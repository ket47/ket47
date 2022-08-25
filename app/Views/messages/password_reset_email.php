<html>
    <head>
        <meta charset="UTF-8">
    </head>
    <body>
        <h2>Восстановление пароля</h2>
        <p>
            Добрый день,
        </p>
        <p>
            Ваш новый пароль в приложении <?=getenv('app.title')?>
        </p>
        <p>
            <b><?=$new_pass?></b>
        </p>
        <p>
            С уважением,  <?= getenv('app.title') ?> Bot.
        </p>
    </body>
</html>