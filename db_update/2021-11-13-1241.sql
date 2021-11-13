/*
-- Query: SELECT * FROM tezkel_db.trans_group_list
LIMIT 0, 50000

-- Date: 2021-11-13 12:41
*/
SET names 'utf8';
INSERT INTO `` (`group_id`,`group_parent_id`,`group_name`,`group_type`,`group_path_id`,`group_path`,`owner_id`,`owner_ally_ids`,`is_disabled`,`updated_at`,`deleted_at`) VALUES (1,0,'Поставщики','','/1/','/Поставщики/',0,'',0,'2021-11-02 15:39:59',NULL);
INSERT INTO `` (`group_id`,`group_parent_id`,`group_name`,`group_type`,`group_path_id`,`group_path`,`owner_id`,`owner_ally_ids`,`is_disabled`,`updated_at`,`deleted_at`) VALUES (2,0,'Покупатели','','/2/','/Покупатели/',0,'',0,'2021-11-02 15:40:15',NULL);
INSERT INTO `` (`group_id`,`group_parent_id`,`group_name`,`group_type`,`group_path_id`,`group_path`,`owner_id`,`owner_ally_ids`,`is_disabled`,`updated_at`,`deleted_at`) VALUES (3,2,'Клиенты','customer','/2/3/','/Покупатели/Клиенты/',0,'',0,'2021-11-02 15:55:59',NULL);
INSERT INTO `` (`group_id`,`group_parent_id`,`group_name`,`group_type`,`group_path_id`,`group_path`,`owner_id`,`owner_ally_ids`,`is_disabled`,`updated_at`,`deleted_at`) VALUES (4,1,'Магазины','supplier_store','/1/4/','/Поставщики/Магазины/',0,'',0,'2021-11-02 15:45:04',NULL);
INSERT INTO `` (`group_id`,`group_parent_id`,`group_name`,`group_type`,`group_path_id`,`group_path`,`owner_id`,`owner_ally_ids`,`is_disabled`,`updated_at`,`deleted_at`) VALUES (5,1,'Рестораны','supplier_restaurant','/1/5/','/Поставщики/Рестораны/',0,'',0,'2021-11-02 15:45:21',NULL);
INSERT INTO `` (`group_id`,`group_parent_id`,`group_name`,`group_type`,`group_path_id`,`group_path`,`owner_id`,`owner_ally_ids`,`is_disabled`,`updated_at`,`deleted_at`) VALUES (6,0,'Деньги','','/6/','/Деньги/',0,'',0,'2021-11-02 15:41:56',NULL);
INSERT INTO `` (`group_id`,`group_parent_id`,`group_name`,`group_type`,`group_path_id`,`group_path`,`owner_id`,`owner_ally_ids`,`is_disabled`,`updated_at`,`deleted_at`) VALUES (7,6,'Расчетный счет','money_account','/6/7/','/Деньги/Расчетный счет/',0,'',0,'2021-11-03 10:34:16',NULL);
INSERT INTO `` (`group_id`,`group_parent_id`,`group_name`,`group_type`,`group_path_id`,`group_path`,`owner_id`,`owner_ally_ids`,`is_disabled`,`updated_at`,`deleted_at`) VALUES (8,1,'Услуги','supplier_service','/1/8/','/Поставщики/Услуги/',0,'',0,'2021-11-02 15:45:29',NULL);
INSERT INTO `` (`group_id`,`group_parent_id`,`group_name`,`group_type`,`group_path_id`,`group_path`,`owner_id`,`owner_ally_ids`,`is_disabled`,`updated_at`,`deleted_at`) VALUES (9,0,'Капитал','','/9/','/Капитал/',0,'',0,'2021-11-02 15:52:10',NULL);
INSERT INTO `` (`group_id`,`group_parent_id`,`group_name`,`group_type`,`group_path_id`,`group_path`,`owner_id`,`owner_ally_ids`,`is_disabled`,`updated_at`,`deleted_at`) VALUES (10,9,'Нераспределенная прибыль','profit','/9/10/','/Капитал/Нераспределенная прибыль/',0,'',0,'2021-11-02 15:53:13',NULL);
INSERT INTO `` (`group_id`,`group_parent_id`,`group_name`,`group_type`,`group_path_id`,`group_path`,`owner_id`,`owner_ally_ids`,`is_disabled`,`updated_at`,`deleted_at`) VALUES (11,6,'Cloud Payments','money_cloud','/6/11/','/Деньги/Cloud Payments/',0,'',0,'2021-11-03 10:34:40',NULL);
