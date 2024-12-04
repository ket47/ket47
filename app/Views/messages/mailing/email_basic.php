<html>
<head>
    <meta charset="UTF-8">
    <title><?= $message_subject??'' ?></title>
</head>
<style>
    html,body{
        padding: 0px;
        margin:0px;
    }
</style>
<body>
<table style="width: 100%; height: 100%;background-color: #F5F7FA;">
    <tbody>
        <tr>
            <td style="width: 100%" align="center">
                <table style="width: 100%">
                    <tbody>
                        <tr style="height: 30px">
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td>
                                <table style="width: 100%;">
                                    <tbody>
                                        <tr>
                                            <td style="width: 5%">&nbsp;</td>
                                            <td align="center">
                                                <table style="width: 100%;max-width: 800px;background-color:white;border-spacing: 0px; border-radius: 8px; font-size: 16px; line-height: 24px; color: #0b1f33; box-shadow: rgba(0, 0, 0, 0.14) 0px 0px 15px -5px; margin: 10px;">
                                                    <tbody>
                                                        <?php if( !empty($images->header) ): ?>
                                                        <tr>
                                                            <td style="padding:0px">
                                                                <img src="cid:<?= $images->header ?>" style="margin-right: 0px; width: 100%; border-radius: 8px 8px 0 0;"/>
                                                             </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <?php if(!empty($message_subject)): ?>
                                                        <tr>
                                                            <td style="padding: 32px 30px 0px; font-size: 20px; line-height: 28px; height: 56px" align="center">
                                                                <strong><?= $message_subject ?></strong>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                        <?php if(!empty($images->content)){ ?>
                                                        <tr>
                                                            <td style="padding: 0px 25px 0px; height: 96px" align="center">
                                                                <p>
                                                                    <img style="border-radius: 10px; width: 100%;"  src="cid:<?= $images->content ?>">
                                                                </p>
                                                            </td>
                                                        </tr>
                                                        <?php } ?>
                                                        <tr>
                                                            <td style="padding: 12px 30px 0px; height: 48px">
                                                                <?= $message_text ?>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding:  30px 30px  30px;">С заботой о Вас, команда <?=getenv('app.title')?>.</td>
                                                        </tr>
                                                        
                                                        <?php if(!empty($message_data->link)): ?>
                                                        <tr>
                                                            <td style="padding: 32px 30px;" align="center">
                                                                <a style="text-decoration: none; background-color: #009dcd; padding: 10px 15px; border-radius: 10px; color: white; font-weight: bold"
                                                                    href="<?= $message_data->link ?>"
                                                                    target="_blank" rel="noreferrer">ПОДРОБНЕЕ</a></td>
                                                        </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </td>
                                            <td style="width: 5%">&nbsp;</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <table style="width: 100%">
                    <tbody>
                        <tr>
                            <td style="padding-bottom: 32px">
                                <table
                                    style="width: 100%; font-family: Arial, sans-serif; font-size: 14px; line-height: 20px; color: #86909c"
                                    border="0" cellspacing="0" cellpadding="0" bgcolor="transparent">
                                    <tbody>
                                        <tr>
                                            <td  style="color: #86909c;text-align:center">
                                                <span class="v1v1v1gmail-il">Скачать</span> мобильное приложение <?=getenv('app.title')?>
                                            </td>
                                        </tr>
                                        <?php if(!empty($images->buttonPM)): ?>
                                        <tr>
                                            <td  style="color: #86909c;text-align:center">
                                                <table style="width: 100%" border="0" cellspacing="0" cellpadding="0">
                                                    <tbody>
                                                        <tr>
                                                            <td style="padding: 0px 26px" align="center">
                                                                <div style="display: inline-block; vertical-align: top; width: 142px; height: 52px; text-align: center">
                                                                    <a style="display: inline-block; text-decoration: none"
                                                                        href="https://play.google.com/store/apps/details?id=com.tezkel.twa"
                                                                        target="_blank" rel="noreferrer"> <img
                                                                            class="v1v1v1gmail-CToWUd"
                                                                            src="cid:<?=$images->buttonPM?>"
                                                                            alt="Google Play" width="135" height="40"
                                                                            border="0"> </a></div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td style="color: #86909c;text-align:center">Вы получили это письмо, потому что зарегистрированы на
                                                <?=getenv('app.title')?>.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
</body>
</html>