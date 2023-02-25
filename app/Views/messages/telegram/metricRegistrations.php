<?php
$i=0;
?><pre>
<?php foreach($user_list as $user): ?>
<?=++$i?>)👦🏻<?=$user->user_name?> ⌚<?=date('d.m.Y',strtotime($user->signed_in_at))?>

<?php endforeach;?></pre>