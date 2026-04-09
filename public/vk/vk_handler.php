<?php
// ДЛЯ ЛОКАЛЬНОЙ ОТЛАДКИ
$file = 'vk_events.log';

if (file_exists($file)) {
    $events = file_get_contents($file);
    
    if (!empty($events)) {
        echo $events;
        
        file_put_contents($file, "");
    }
} else {
    echo "";
}