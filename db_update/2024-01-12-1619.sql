ALTER TABLE `delivery_job_list` 
DROP INDEX `jorderid` ;
;

ALTER TABLE `delivery_job_list` 
ADD UNIQUE INDEX `jorderid` (`order_id` ASC);
;

