CREATE TABLE `events_manager_event` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `event_name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `event_date` VARCHAR(20) NOT NULL,
  `reg_start_date` VARCHAR(20) NOT NULL,
  `reg_end_date` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `events_manager_registration` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `event_id` INT NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(254) NOT NULL,
  `college` VARCHAR(255) NOT NULL,
  `department` VARCHAR(255) NOT NULL,
  `created` INT NOT NULL,
  PRIMARY KEY (`id`)
);