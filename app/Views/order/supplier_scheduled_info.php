<p>⏰⏰⏰ Запланирован на <?=date('<b>d.m.Y</b>',$order_data->finish_plan_scheduled)?></p>
<p>
<?=date('<b>H:i</b>',$order_data->finish_plan_scheduled-40*60)?> 📍 Курьер заберет из <?=$order->store->store_name?><br>
<?=date('<b>H:i</b>',$order_data->finish_plan_scheduled)?>  🏁 Курьер привезет к клиенту 
</p>
<br>