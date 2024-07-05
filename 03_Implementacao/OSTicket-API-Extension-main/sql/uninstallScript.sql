DROP TABLE `%TABLE_PREFIX%api_key_extension`;

DROP TABLE `%TABLE_PREFIX%suspended_ticket`;

DELETE FROM `%TABLE_PREFIX%event` WHERE `name` = 'suspended';

DELETE FROM `%TABLE_PREFIX%ticket_status` WHERE `name` = 'Suspended';

-- Gets the max id and changes the auto increment value based on that, so if the plugin is reeintalled the ids are still the same

SET @next_increment_event = (SELECT IFNULL(MAX(`id`), 0) FROM `%TABLE_PREFIX%event`) + 1;
SET @sql_event = CONCAT('ALTER TABLE `%TABLE_PREFIX%event` AUTO_INCREMENT = ', @next_increment_event);
PREPARE stmt_event FROM @sql_event;
EXECUTE stmt_event;
DEALLOCATE PREPARE stmt_event;

SET @next_increment_ticket_status = (SELECT IFNULL(MAX(`id`), 0) FROM `%TABLE_PREFIX%ticket_status`) + 1;
SET @sql_ticket_status = CONCAT('ALTER TABLE `%TABLE_PREFIX%ticket_status` AUTO_INCREMENT = ', @next_increment_ticket_status);
PREPARE stmt_ticket_status FROM @sql_ticket_status;
EXECUTE stmt_ticket_status;
DEALLOCATE PREPARE stmt_ticket_status;