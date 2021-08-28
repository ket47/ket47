<h2>User currently signed in</h2>

<div style="display: grid;grid-template-columns:1fr 7fr">
<?php foreach($user as $key=>$val): ?>
    <div><?=$key?>:</div> <div><?=$val?></div>
<?php endforeach; ?>
</div>