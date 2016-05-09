SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';


-- -----------------------------------------------------
-- Table `tdz_people`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_people` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment' ,
  `name` VARCHAR(200) NULL DEFAULT NULL ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `updated` DATETIME NOT NULL COMMENT 'timestampable' ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
AUTO_INCREMENT = 37
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_User_Person' ;


-- -----------------------------------------------------
-- Table `tdz_connect`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_connect` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment' ,
  `ns` VARCHAR(100) NOT NULL ,
  `username` VARCHAR(200) NOT NULL ,
  `hash` VARCHAR(100) NULL DEFAULT NULL ,
  `person` BIGINT(20) NOT NULL ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `updated` DATETIME NOT NULL COMMENT 'timestampable' ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`) ,
  INDEX `person_idx` (`person` ASC) ,
  CONSTRAINT `tdz_connect_person_tdz_people_id`
    FOREIGN KEY (`person` )
    REFERENCES `tdz_people` (`id` ))
ENGINE = InnoDB
AUTO_INCREMENT = 36
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_User_Connect' ;


-- -----------------------------------------------------
-- Table `tdz_access`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_access` (
  `person` BIGINT(20) NOT NULL DEFAULT '0' ,
  `connection` BIGINT(20) NOT NULL DEFAULT '0' ,
  `ip` VARCHAR(50) NULL DEFAULT NULL ,
  `client` VARCHAR(250) NULL DEFAULT NULL ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `updated` DATETIME NOT NULL COMMENT 'timestampable' ,
  PRIMARY KEY (`person`, `connection`, `created`) ,
  INDEX `tdz_access_connection_tdz_connect_id` (`connection` ASC) ,
  CONSTRAINT `tdz_access_connection_tdz_connect_id`
    FOREIGN KEY (`connection` )
    REFERENCES `tdz_connect` (`id` ),
  CONSTRAINT `tdz_access_person_tdz_people_id`
    FOREIGN KEY (`person` )
    REFERENCES `tdz_people` (`id` ))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COMMENT = 'className: Tecnodesign_User_Access' ;


-- -----------------------------------------------------
-- Table `tdz_entries`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_entries` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment' ,
  `title` VARCHAR(200) NULL DEFAULT NULL ,
  `summary` TEXT NULL DEFAULT NULL ,
  `link` VARCHAR(200) NULL DEFAULT NULL ,
  `source` VARCHAR(200) NULL DEFAULT NULL ,
  `format` VARCHAR(100) NULL DEFAULT NULL ,
  `published` DATETIME NULL DEFAULT NULL ,
  `language` VARCHAR(10) NULL DEFAULT NULL ,
  `type` VARCHAR(100) NULL DEFAULT NULL ,
  `master` VARCHAR(100) NULL DEFAULT NULL ,
  `version` BIGINT(20) NULL DEFAULT NULL COMMENT 'versionable: tdz_entries_version' ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `updated` DATETIME NOT NULL COMMENT 'timestampable' ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`) ,
  INDEX `link_idx` (`link` ASC) ,
  INDEX `type_idx` (`type` ASC) ,
  INDEX `format_idx` (`format` ASC) ,
  INDEX `published_idx` (`published` ASC) ,
  INDEX `updated_idx` (`updated` ASC) )
ENGINE = InnoDB
AUTO_INCREMENT = 490
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_App_Studio_Entry' ;


-- -----------------------------------------------------
-- Table `tdz_contents`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_contents` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment' ,
  `entry` BIGINT(20) NULL DEFAULT NULL COMMENT 'auto-increment' ,
  `slot` VARCHAR(50) NULL DEFAULT NULL ,
  `content_type` VARCHAR(100) NULL DEFAULT NULL ,
  `content` LONGTEXT NULL DEFAULT NULL ,
  `position` BIGINT(20) NULL DEFAULT NULL COMMENT 'sortable' ,
  `published` DATETIME NULL DEFAULT NULL ,
  `show_at` TEXT NULL DEFAULT NULL ,
  `hide_at` TEXT NULL DEFAULT NULL ,
  `version` BIGINT(20) NULL DEFAULT NULL COMMENT 'versionable: tdz_contents_version' ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `updated` DATETIME NOT NULL COMMENT 'timestampable' ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`) ,
  INDEX `position_idx` (`position` ASC) ,
  INDEX `slot_idx` (`slot` ASC) ,
  INDEX `entry_idx` (`entry` ASC) ,
  INDEX `published_idx` (`published` ASC) ,
  INDEX `updated_idx` (`updated` ASC) ,
  CONSTRAINT `tdz_contents_entry_tdz_entries_id`
    FOREIGN KEY (`entry` )
    REFERENCES `tdz_entries` (`id` ))
ENGINE = InnoDB
AUTO_INCREMENT = 653
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_App_Studio_Content' ;


-- -----------------------------------------------------
-- Table `tdz_contents_version`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_contents_version` (
  `id` BIGINT(20) NOT NULL DEFAULT '0' ,
  `entry` BIGINT(20) NULL DEFAULT NULL ,
  `slot` VARCHAR(50) CHARACTER SET 'latin1' NULL DEFAULT NULL ,
  `content_type` VARCHAR(100) CHARACTER SET 'latin1' NULL DEFAULT NULL ,
  `content` LONGTEXT CHARACTER SET 'latin1' NULL DEFAULT NULL ,
  `position` BIGINT(20) NULL DEFAULT NULL ,
  `published` DATETIME NULL DEFAULT NULL ,
  `show_at` TEXT CHARACTER SET 'latin1' NULL DEFAULT NULL ,
  `hide_at` TEXT CHARACTER SET 'latin1' NULL DEFAULT NULL ,
  `version` BIGINT(20) NOT NULL DEFAULT '0' ,
  `created` DATETIME NOT NULL ,
  `updated` DATETIME NOT NULL ,
  `expired` DATETIME NULL DEFAULT NULL ,
  PRIMARY KEY (`id`, `version`) ,
  INDEX `entry_idx` (`entry` ASC) ,
  INDEX `updated_idx` (`updated` ASC) ,
  INDEX `version_idx` (`version` ASC) ,
  CONSTRAINT `tdz_contents_version_id_tdz_contents_id`
    FOREIGN KEY (`id` )
    REFERENCES `tdz_contents` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: ~' ;


-- -----------------------------------------------------
-- Table `tdz_details`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_details` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT ,
  `person` BIGINT(20) NOT NULL ,
  `category` VARCHAR(100) NOT NULL ,
  `text` TEXT NULL DEFAULT NULL ,
  `verified` DATETIME NULL DEFAULT NULL ,
  `created` DATETIME NOT NULL ,
  `updated` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `person_idx` (`person` ASC) ,
  CONSTRAINT `tdz_details_person_tdz_people_id`
    FOREIGN KEY (`person` )
    REFERENCES `tdz_people` (`id` ))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_User_Detail' ;


-- -----------------------------------------------------
-- Table `tdz_entries_version`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_entries_version` (
  `id` BIGINT(20) NOT NULL DEFAULT '0' ,
  `title` VARCHAR(200) NULL DEFAULT NULL ,
  `summary` TEXT NULL DEFAULT NULL ,
  `link` VARCHAR(200) NULL DEFAULT NULL ,
  `source` VARCHAR(200) NULL DEFAULT NULL ,
  `format` VARCHAR(100) NULL DEFAULT NULL ,
  `published` DATETIME NULL DEFAULT NULL ,
  `language` VARCHAR(10) NULL DEFAULT NULL ,
  `type` VARCHAR(100) NULL DEFAULT NULL ,
  `master` VARCHAR(100) NULL DEFAULT NULL ,
  `version` BIGINT(20) NOT NULL DEFAULT '0' ,
  `created` DATETIME NOT NULL ,
  `updated` DATETIME NOT NULL ,
  `expired` DATETIME NULL DEFAULT NULL ,
  PRIMARY KEY (`id`, `version`) ,
  INDEX `entry_idx` (`id` ASC) ,
  INDEX `link_idx` (`link` ASC) ,
  INDEX `updated_idx` (`updated` ASC) ,
  INDEX `version_idx` (`version` ASC) ,
  INDEX `first_published_idx` (`id` ASC, `published` ASC) ,
  CONSTRAINT `tdz_entries_version_id_tdz_entries_id`
    FOREIGN KEY (`id` )
    REFERENCES `tdz_entries` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: ~' ;


-- -----------------------------------------------------
-- Table `tdz_groups`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_groups` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT ,
  `name` VARCHAR(200) NOT NULL ,
  `description` TEXT NULL DEFAULT NULL ,
  `created` DATETIME NOT NULL ,
  `updated` DATETIME NOT NULL ,
  `expired` DATETIME NULL DEFAULT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_User_Group' ;


-- -----------------------------------------------------
-- Table `tdz_participation`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_participation` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment' ,
  `person` BIGINT(20) NOT NULL ,
  `groupid` BIGINT(20) NOT NULL ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `updated` DATETIME NOT NULL COMMENT 'timestampable' ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`) ,
  INDEX `person_idx` (`person` ASC) ,
  INDEX `groupid_idx` (`groupid` ASC) ,
  CONSTRAINT `tdz_participation_groupid_tdz_groups_id`
    FOREIGN KEY (`groupid` )
    REFERENCES `tdz_groups` (`id` ),
  CONSTRAINT `tdz_participation_person_tdz_people_id`
    FOREIGN KEY (`person` )
    REFERENCES `tdz_people` (`id` ))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_User_Participation' ;


-- -----------------------------------------------------
-- Table `tdz_permissions`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_permissions` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment' ,
  `entry` BIGINT(20) NULL DEFAULT NULL ,
  `role` VARCHAR(100) NOT NULL ,
  `credentials` TEXT NULL DEFAULT NULL ,
  `version` BIGINT(20) NULL DEFAULT NULL COMMENT 'versionable: tdz_permissions_version' ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `updated` DATETIME NOT NULL COMMENT 'timestampable' ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`) ,
  INDEX `entry_idx` (`entry` ASC) ,
  INDEX `role_idx` (`role` ASC) ,
  CONSTRAINT `tdz_permissions_entry_tdz_entries_id`
    FOREIGN KEY (`entry` )
    REFERENCES `tdz_entries` (`id` ))
ENGINE = InnoDB
AUTO_INCREMENT = 75
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_App_Studio_Permission' ;


-- -----------------------------------------------------
-- Table `tdz_permissions_version`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_permissions_version` (
  `id` BIGINT(20) NOT NULL DEFAULT '0' ,
  `entry` BIGINT(20) NULL DEFAULT NULL ,
  `role` VARCHAR(100) NOT NULL ,
  `credentials` TEXT NULL DEFAULT NULL ,
  `version` BIGINT(20) NOT NULL DEFAULT '0' ,
  `created` DATETIME NOT NULL ,
  `updated` DATETIME NOT NULL ,
  `expired` DATETIME NULL DEFAULT NULL ,
  PRIMARY KEY (`id`, `version`) ,
  CONSTRAINT `tdz_permissions_version_id_tdz_permissions_id`
    FOREIGN KEY (`id` )
    REFERENCES `tdz_permissions` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: ~' ;


-- -----------------------------------------------------
-- Table `tdz_relations`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_relations` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment' ,
  `parent` BIGINT(20) NULL DEFAULT NULL ,
  `entry` BIGINT(20) NOT NULL ,
  `position` BIGINT(20) NULL DEFAULT '1' COMMENT 'sortable' ,
  `version` BIGINT(20) NULL DEFAULT NULL COMMENT 'versionable: tdz_relations_version' ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `updated` DATETIME NOT NULL COMMENT 'timestampable' ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`) ,
  INDEX `parent_idx` (`parent` ASC) ,
  INDEX `position_idx` (`position` ASC) ,
  INDEX `entry_idx` (`entry` ASC) ,
  CONSTRAINT `tdz_relations_entry_tdz_entries_id`
    FOREIGN KEY (`entry` )
    REFERENCES `tdz_entries` (`id` ),
  CONSTRAINT `tdz_relations_parent_tdz_entries_id`
    FOREIGN KEY (`parent` )
    REFERENCES `tdz_entries` (`id` ))
ENGINE = InnoDB
AUTO_INCREMENT = 274
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_App_Studio_Relation' ;


-- -----------------------------------------------------
-- Table `tdz_relations_version`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_relations_version` (
  `id` BIGINT(20) NOT NULL DEFAULT '0' ,
  `parent` BIGINT(20) NULL DEFAULT NULL ,
  `entry` BIGINT(20) NOT NULL ,
  `position` BIGINT(20) NULL DEFAULT '1' ,
  `version` BIGINT(20) NOT NULL DEFAULT '0' ,
  `created` DATETIME NOT NULL ,
  `updated` DATETIME NOT NULL ,
  `expired` DATETIME NULL DEFAULT NULL ,
  PRIMARY KEY (`id`, `version`) ,
  INDEX `entry_idx` (`entry` ASC) ,
  INDEX `updated_idx` (`updated` ASC) ,
  INDEX `version_idx` (`version` ASC) ,
  CONSTRAINT `tdz_relations_version_id_tdz_relations_id`
    FOREIGN KEY (`id` )
    REFERENCES `tdz_relations` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: ~' ;


-- -----------------------------------------------------
-- Table `tdz_tags`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_tags` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment' ,
  `entry` BIGINT(20) NULL DEFAULT NULL ,
  `tag` VARCHAR(100) NOT NULL ,
  `slug` VARCHAR(100) NOT NULL ,
  `version` BIGINT(20) NULL DEFAULT NULL COMMENT 'versionable: tdz_tags_version' ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `updated` DATETIME NOT NULL COMMENT 'timestampable' ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`) ,
  INDEX `entry_idx` (`entry` ASC) ,
  INDEX `slug_idx` (`slug` ASC) ,
  CONSTRAINT `fk_tdz_tags__tdz_entries`
    FOREIGN KEY (`entry` )
    REFERENCES `tdz_entries` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 7973
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_App_Studio_Tag' ;


-- -----------------------------------------------------
-- Table `tdz_tags_version`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `tdz_tags_version` (
  `id` BIGINT(20) NOT NULL ,
  `entry` BIGINT(20) NULL DEFAULT NULL ,
  `tag` VARCHAR(100) NULL DEFAULT NULL ,
  `slug` VARCHAR(100) NULL DEFAULT NULL ,
  `version` BIGINT(20) NOT NULL ,
  `created` DATETIME NOT NULL ,
  `updated` DATETIME NOT NULL ,
  `expired` DATETIME NULL DEFAULT NULL ,
  PRIMARY KEY (`id`, `version`) ,
  INDEX `id_idx` (`id` ASC) ,
  INDEX `entry_idx` (`entry` ASC) ,
  INDEX `slug_idx` (`slug` ASC) ,
  INDEX `updated_idx` (`updated` ASC) ,
  CONSTRAINT `fk_tdz_tags_version_tdz_tags1`
    FOREIGN KEY (`id` )
    REFERENCES `tdz_tags` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: ~' ;



SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
