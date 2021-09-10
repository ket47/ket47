<h2>User currently signed in</h2>

<div style="display: grid;grid-template-columns:1fr 7fr">
<?php foreach($user as $key=>$val): ?>
    <div><?=$key?>:</div> <div>
        <?php if(is_array($val) || is_object($val)): ?>
            <?= json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_UNICODE )?>
        <?php else: ?>
            <?=$val?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>