<p>⏰⏰⏰ Запланирован</p>
<p>
<?=date('<b>H:i</b>',$order_data->finish_plan_scheduled-40*60)?> 📍 Забрать из <?=$order->store->store_name?><br>
<?=date('<b>H:i</b>',$order_data->finish_plan_scheduled)?>  🏁 Привезти к <?=$order->customer->user_name??'-'?> 
</p>
<br>