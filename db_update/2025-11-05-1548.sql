ALTER TABLE `courier_list` 
ADD COLUMN `courier_parttime_notify` ENUM('off', 'silent', 'push', 'ringtone') NULL AFTER `courier_data`;
