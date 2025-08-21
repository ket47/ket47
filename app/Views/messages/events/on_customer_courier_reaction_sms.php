🚀 <b><?=$user->user_name?></b> оценил курьера <b><?=$order_extended->courier_name?></b> Заказ: #<?=$order_extended->order_id?>

<?php foreach($reaction_list as $reaction):?>
<?php if($reaction->tag_option=='speed'): ?>
Скорость: <?= ($reaction->reaction_is_like?'👍':'👎') ?>
<?php endif; ?>

<?php if($reaction->tag_option=='appearence'): ?>
Опрятность: <?= ($reaction->reaction_is_like?'👍':'👎') ?>

<i><?=$reaction->reaction_comment?></i>
<?php endif; ?>

<?php endforeach; ?>