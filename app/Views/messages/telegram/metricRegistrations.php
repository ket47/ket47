<?php
$i=0;
?><pre>
<?php foreach($user_list as $user): ?>
<?=++$i?>)👦🏻<?=$user->user_name?> <?=$user->media_name?"=".($user->media_name??$user->come_media_id):""?> 
<?php endforeach;?></pre>