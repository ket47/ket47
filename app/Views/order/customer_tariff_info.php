<?php if( isset($order_data->delivery_by_store) || isset($order_data->pickup_by_customer) ): ?>
    <?php if( isset($order_data->pickup_by_customer) ): ?>
        <h1>Самовывоз</h1> 
        <p>Вам необходимо забрать заказ из <?=$order->store->store_name?> самостоятельно.</p>
    <?php endif;?>
    <?php if( isset($order_data->delivery_by_store) ): ?>
        <h1>Доставка продавцом</h1> 
        <p><?=$order->store->store_name?> с вами свяжется и доставит ваш заказ</p>
    <?php endif;?>
<?php endif;?>