<?php
$i=0;
?><pre>
<?php foreach($coming_list as $row): ?>
<?php $url=parse_url($row->come_referrer);?>
<?=++$i?>)📲<?=$row->media_name??'нет'?> 🌐<?=$url['host']??'нет'?> 
<?php endforeach;?></pre>