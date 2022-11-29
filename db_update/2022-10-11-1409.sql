/*
-- Query: SELECT * FROM tezkel_db.tariff_list
LIMIT 0, 50000

-- Date: 2022-11-29 14:07
*/
SET NAMES=utf8;
INSERT INTO tariff_list (`tariff_id`,`tariff_name`,`order_allow`,`order_fee`,`order_cost`,`card_allow`,`card_fee`,`cash_allow`,`cash_fee`,`delivery_allow`,`delivery_fee`,`delivery_cost`,`is_disabled`,`is_public`,`created_at`,`updated_at`,`deleted_at`) VALUES (1,'Маркетплэйс',1,7,0,0,0,1,0,NULL,0,0,0,1,NULL,NULL,NULL);
INSERT INTO tariff_list (`tariff_id`,`tariff_name`,`order_allow`,`order_fee`,`order_cost`,`card_allow`,`card_fee`,`cash_allow`,`cash_fee`,`delivery_allow`,`delivery_fee`,`delivery_cost`,`is_disabled`,`is_public`,`created_at`,`updated_at`,`deleted_at`) VALUES (7,'Маркетплэйс + оплата',1,7,0,1,3,0,0,NULL,0,0,0,0,NULL,NULL,NULL);
INSERT INTO tariff_list (`tariff_id`,`tariff_name`,`order_allow`,`order_fee`,`order_cost`,`card_allow`,`card_fee`,`cash_allow`,`cash_fee`,`delivery_allow`,`delivery_fee`,`delivery_cost`,`is_disabled`,`is_public`,`created_at`,`updated_at`,`deleted_at`) VALUES (8,'Свобода (ресторан)',1,0,0,1,25,0,0,1,0,90,0,0,NULL,NULL,NULL);
INSERT INTO tariff_list (`tariff_id`,`tariff_name`,`order_allow`,`order_fee`,`order_cost`,`card_allow`,`card_fee`,`cash_allow`,`cash_fee`,`delivery_allow`,`delivery_fee`,`delivery_cost`,`is_disabled`,`is_public`,`created_at`,`updated_at`,`deleted_at`) VALUES (9,'Свобода (магазин)',1,20,0,1,0,0,0,1,0,90,0,0,NULL,NULL,NULL);
INSERT INTO tariff_list (`tariff_id`,`tariff_name`,`order_allow`,`order_fee`,`order_cost`,`card_allow`,`card_fee`,`cash_allow`,`cash_fee`,`delivery_allow`,`delivery_fee`,`delivery_cost`,`is_disabled`,`is_public`,`created_at`,`updated_at`,`deleted_at`) VALUES (10,'Мини',1,0,0,1,10,1,0,1,0,240,0,0,NULL,NULL,NULL);
