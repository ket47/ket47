<?php
// ДЛЯ ЛОКАЛЬНОЙ ОТЛАДКИ
$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    if ($data['type'] === 'confirmation') {
        echo "b23eb553"; 
        exit;
    }

    file_put_contents('vk_events.log', json_encode($data) . PHP_EOL, FILE_APPEND);

    echo "ok";
}