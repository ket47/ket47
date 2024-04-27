DROP function IF EXISTS `IS_STORE_OPEN`;

DELIMITER $$

CREATE FUNCTION `IS_STORE_OPEN` ( _open_hour int,  _close_hour int, _now_hour int )
RETURNS INTEGER
DETERMINISTIC 
BEGIN
    IF _open_hour>=_close_hour THEN
		IF _open_hour<=_now_hour OR _close_hour>_now_hour THEN
			RETURN 1;
		END IF;
		RETURN 0;
	END IF;
    
	IF _open_hour<=_now_hour AND _close_hour>_now_hour THEN
		RETURN 1;
	END IF;
	RETURN 0;
END$$

DELIMITER ;
