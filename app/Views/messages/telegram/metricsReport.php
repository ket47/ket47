<?php
$i=0;
?><pre>
<?php foreach($coming_list as $row): ?>
<?php $url=parse_url($row->come_referrer);?>
<?=++$i?>)📲<?=$row->media_name??'-'?> <?=($url['host']??'')?"🌐{$url['host']}":''?> <?=$row->device_platform=='iOS'?'🍏':''?><?=$row->device_platform=='Android'?'🤖':''?><?=$row->device_platform=='Windows'?'🪟':''?>
<?php endforeach;?></pre>