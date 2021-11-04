<?php view('home/header');?>
<div class="segment">
    <h2><?=$payment_description?></h2>
    <h3>К оплате: <?=$payment_amount?></h3>
    <form action="/Home/payment_submit">
    <div style="display: grid;grid-template-columns:3fr 1fr">
        <div style="grid-column: 1 / 2 span">
            <input name="card_number" placeholder="CARD NUMBER">
        </div>
        <div><input name="card_name" placeholder="CARD NAME"></div><div><input name="card_exp_date" placeholder="CARD EXPR"></div>
        <div></div><div><input name="card_cvv" placeholder="CVV"></div>
        <div style="grid-column: 1 / 2 span">
            <button>Оплатить</button>
        </div>
    </div>
    </form>
</div>
<?php view('home/footer');?>