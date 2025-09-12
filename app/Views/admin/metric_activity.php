
  <div style="background-color: #f4f4f4;"> 
    <?php foreach ($timeline as $hour => $sessions): ?>
      <div  style="page-break-after: avoid; break-inside: avoid;">
        <h5>🕒 <?= $sessions['hour_slot'] ?> - <?= count($sessions['list']) ?> чел. </h5>
        <hr>
        <div style="display: flex; flex-wrap: wrap;">
        <?php foreach ($sessions['list'] as $sessionId => $data): ?>
          <div class="block" style="flex: 0 0 23%; margin: 10px; background: white; height: 100%; padding: 10px; border-radius: 15px;  box-shadow: 0px 5px 15px -5px #00000059; page-break-after: avoid; break-inside: avoid;">
          <p style="color: gray; font-size: 12px; margin: 0px; float: right;"><?= date('H:i:s', strtotime($data['session_start'])) ?></p>
            <h6 style="margin: 0px; font-size: 15px;">
              <?= esc($data['user_avatar']) ?> 
              

              <?php if(!empty($data['user_id'])) : ?>
                <a href="/user/user-management?user_id=<?=$data['user_id']?>" style="margin: 5px 0; font-size: 13px; color: gray"><?= esc($data['user']) ?> </a>
              <?php else: ?>
                Гость
              <?php endif; ?>
              
              <?php if($data['user_orders'] > 0) : ?>
              <b style="color: gray">(<?= esc($data['user_orders']) ?>)</b>
              <?php endif; ?>
            </h6>

            <?php if(!empty($data['device_platform'])) : ?>
            <span style="margin: 5px 0; font-size: 13px; color: gray"> <?= esc($data['device_platform']) ?> </span>
            <?php endif; ?>

            <?php if(!empty($data['come_media_id'])) : ?>
            <span style="margin: 5px 0; font-size: 13px; color: gray"> ⬜<?= esc($data['come_media_id']) ?> </span>
            <?php endif; ?>
            
            <?php if(!empty($data['come_referrer'])) : ?>
            <span style="margin: 5px 0; font-size: 12px; color: gray; "> 🌐<?= esc($data['come_referrer']) ?> </span>
            <?php endif; ?>


            <div style="display: flex;min-width:140px">
              <ul style="padding-left: 5px; font-size: 13px; margin-top: 5px; list-style: disclosure-closed;">
                <?php foreach ($data['actions'] as $action): ?>
                  <li>
                    <?= esc($action['type']) ?> 
                    <?php if(!empty($action['desc'])) : ?>
                      <?= esc($action['desc']) ?>
                    <?php endif; ?>
                    <?php if(!empty($action['time'])) : ?>
                    <span style="color: gray">[<?= $action['time'] ?>]</span>
                    <?php endif; ?>
                    <?php if($action['act_result'] == 'error') : ?>⚠️<?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
