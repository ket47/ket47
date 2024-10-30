<table style="width: 100%; height: 100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#F5F7FA">
    <tbody>
        <tr>
            <td style="width: 100%" align="center">
                <table style="width: 100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#F5F7FA">
                    <tbody>
                        <tr style="width: 100%; height: 30px">
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="width: 100%">
                                <table style="width: 100%" border="0" cellspacing="0" cellpadding="0">
                                    <tbody>
                                        <tr>
                                            <td style="width: 5%" bgcolor="#F5F7FA">&nbsp;</td>
                                            <td style="max-width: 420px" align="center">
                                                <table style="width: 100%; max-width: 420px; font-family: Arial, sans-serif; font-size: 16px; line-height: 24px; color: #0b1f33; box-shadow: rgba(0, 0, 0, 0.14) 0px 0px 15px -5px; height: 392px"
                                                    border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff">
                                                    <tbody>
                                                        <tr style="height: 120px">
                                                            <td style="font-size: 20px; line-height: 28px; height: 120px" align="center">
                                                                <img src="cid:<?= $images->header ?>" 
                                                                style="margin-right: 0px; width: 100%"
                                                                alt="logo_top.png" width="500"/>
                                                             </td>
                                                        </tr>
                                                        <tr style="height: 56px">
                                                            <td style="padding: 32px 30px 0px; font-size: 20px; line-height: 28px; height: 56px" align="center">
                                                                <strong><?= $message_subject ?></strong>
                                                            </td>
                                                        </tr>
                                                        <?php if(!empty($images->content)){ ?>
                                                        <tr style="height: 96px" >
                                                            <td style="padding: 0px 30px 0px; height: 96px" align="center">
                                                                <p>
                                                                    <img style="border-radius: 10px"  src="cid:<?= $images->content ?>"  width="450">
                                                                </p>
                                                            </td>
                                                        </tr>
                                                        <?php } ?>
                                                        <tr style="height: 48px">
                                                            <td style="padding: 12px 30px 0px; height: 48px" align="center">
                                                                <?= $message_text ?>
                                                            </td>
                                                        </tr>
                                                        <tr style="height: 24px">
                                                            <td style="padding: 12px 30px 0px; height: 24px"
                                                                align="center">С заботой о Вас, команда Tezkel.</td>
                                                        </tr>
                                                        
                                                        <?php if(!empty($message_data->link)){ ?>
                                                        <tr style="height: 24px">
                                                            <td style="padding: 32px 30px; height: 24px" align="center">
                                                                <a style="text-decoration: none; background-color: #009dcd; padding: 10px 15px; border-radius: 10px; color: white; font-weight: bold"
                                                                    href="<?= $message_data->link ?>"
                                                                    target="_blank" rel="noreferrer">ПОДРОБНЕЕ</a></td>
                                                        </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </td>
                                            <td style="width: 5%" bgcolor="#F5F7FA">&nbsp;</td>
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
            <td style="width: 100%" align="center">
                <table style="width: 100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#F5F7FA">
                    <tbody>
                        <tr>
                            <td style="padding-bottom: 32px" align="center">
                                <table
                                    style="width: 100%; font-family: Arial, sans-serif; font-size: 14px; line-height: 20px; color: #86909c"
                                    border="0" cellspacing="0" cellpadding="0" bgcolor="transparent">
                                    <tbody>
                                        <tr>
                                            <td style="padding: 32px 0px 12px; font-family: Arial, sans-serif; font-size: 14px; line-height: 20px; color: #86909c"
                                                align="center"><span class="v1v1v1gmail-il">Скачать</span> мобильное
                                                приложение <span class="v1v1v1LI v1v1v1ng">Tezkel</span></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0px 20px 20px" align="center">
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
                                        <tr>
                                            <td style="padding: 0px 8%; font-family: Arial, sans-serif; font-size: 14px; line-height: 20px; color: #86909c"
                                                align="center">Вы получили это письмо, потому что зарегистрированы на
                                                <span class="v1v1v1LI v1v1v1ng">Tezkel</span>.</td>
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