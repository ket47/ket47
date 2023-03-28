<?php
$i=0;
?>
<b>🗺️ Метрика</b><pre>
<?php $s=0; foreach($coming_list as $row): $s+=$row->metric_count;?>
<?=str_pad(++$i,2,' ', STR_PAD_LEFT)?>)[<?=str_pad($row->metric_count,3,' ', STR_PAD_LEFT)?>]<?=$row->media_name??$row->come_media_id??''?> 
<?php endforeach;?>Σ<?=$s?></pre>
<?php
$i=0;
?>

<b>🌐 Сайт</b><pre>
<?php $s=0; foreach($refferrer_list as $row):  $s+=$row->metric_count;?>
<?php $url=parse_url($row->come_referrer);?>
<?=str_pad(++$i,2,' ', STR_PAD_LEFT)?>)[<?=str_pad($row->metric_count,3,' ', STR_PAD_LEFT)?>]<?=($url['host']??'')?"{$url['host']}":''?> 
<?php endforeach;?>Σ<?=$s?></pre>
<?php
$i=0;
?>

<b>📲 Платформы</b><pre>
<?php $s=0; foreach($device_list as $row): $s+=$row->metric_count;?>
<?=++$i?>)[<?=str_pad($row->metric_count,3,' ', STR_PAD_LEFT)?>] <?=$row->device_platform=='iOS'?'🍏':''?><?=$row->device_platform=='Android'?'🤖':''?><?=str_contains($row->device_platform,'Windows')?'🪟':''?><?=str_contains($row->device_platform,'Linux')?'🐧':''?> 
<?php endforeach;?>Σ<?=$s?></pre>
