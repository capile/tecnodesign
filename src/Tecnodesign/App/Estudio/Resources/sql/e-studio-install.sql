SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';


-- -----------------------------------------------------
-- Table `tdz_content`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_content` ;

CREATE  TABLE IF NOT EXISTS `tdz_content` (
  `id` BIGINT(20) NOT NULL ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `entry` BIGINT(20) NULL DEFAULT NULL ,
  `slot` VARCHAR(50) NULL DEFAULT NULL ,
  `content_type` VARCHAR(100) NULL DEFAULT NULL ,
  `content` LONGTEXT NULL DEFAULT NULL ,
  `position` BIGINT(20) NULL DEFAULT NULL COMMENT 'sortable' ,
  `published` DATETIME NULL DEFAULT NULL ,
  `show_at` TEXT NULL DEFAULT NULL ,
  `hide_at` TEXT NULL DEFAULT NULL ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`, `created`) ,
  INDEX `position_idx` (`position` ASC) ,
  INDEX `slot_idx` (`slot` ASC) ,
  INDEX `published_idx` (`published` ASC) )
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8
COMMENT = 'className: Tecnodesign_App_Studio_Content' ;


-- -----------------------------------------------------
-- Table `tdz_entry`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_entry` ;

CREATE  TABLE IF NOT EXISTS `tdz_entry` (
  `id` BIGINT(20) NOT NULL ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `title` VARCHAR(200) NULL DEFAULT NULL ,
  `summary` TEXT NULL DEFAULT NULL ,
  `link` VARCHAR(200) NULL DEFAULT NULL ,
  `source` VARCHAR(200) NULL DEFAULT NULL ,
  `format` VARCHAR(100) NULL DEFAULT NULL ,
  `published` DATETIME NULL DEFAULT NULL ,
  `language` VARCHAR(10) NULL DEFAULT NULL ,
  `type` VARCHAR(100) NULL DEFAULT NULL ,
  `master` VARCHAR(100) NULL DEFAULT NULL ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`, `created`) ,
  INDEX `link_idx` (`link` ASC) ,
  INDEX `type_idx` (`type` ASC) ,
  INDEX `format_idx` (`format` ASC) ,
  INDEX `published_idx` (`published` ASC) )
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_App_Studio_Entry' ;


-- -----------------------------------------------------
-- Table `tdz_permission`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_permission` ;

CREATE  TABLE IF NOT EXISTS `tdz_permission` (
  `id` BIGINT(20) NOT NULL ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `entry` BIGINT(20) NULL DEFAULT NULL ,
  `role` VARCHAR(100) NOT NULL ,
  `credentials` TEXT NULL DEFAULT NULL ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`, `created`) ,
  INDEX `role_idx` (`role` ASC) )
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_App_Studio_Permission' ;


-- -----------------------------------------------------
-- Table `tdz_relation`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_relation` ;

CREATE  TABLE IF NOT EXISTS `tdz_relation` (
  `id` BIGINT(20) NOT NULL ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `parent` BIGINT(20) NULL DEFAULT NULL ,
  `entry` BIGINT(20) NOT NULL ,
  `position` BIGINT(20) NULL DEFAULT '1' COMMENT 'sortable' ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`, `created`) ,
  INDEX `position_idx` (`position` ASC) )
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_App_Studio_Relation' ;


-- -----------------------------------------------------
-- Table `tdz_tag`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_tag` ;

CREATE  TABLE IF NOT EXISTS `tdz_tag` (
  `id` BIGINT(20) NOT NULL ,
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert' ,
  `entry` BIGINT(20) NULL DEFAULT NULL ,
  `tag` VARCHAR(100) NOT NULL ,
  `slug` VARCHAR(100) NOT NULL ,
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete' ,
  PRIMARY KEY (`id`, `created`) ,
  INDEX `slug_idx` (`slug` ASC) )
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8, 
COMMENT = 'className: Tecnodesign_App_Studio_Tag' ;



SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
