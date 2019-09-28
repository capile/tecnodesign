SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';


-- -----------------------------------------------------
-- Table `tdz_entries`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_entries` ;

CREATE TABLE IF NOT EXISTS `tdz_entries` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment',
  `title` VARCHAR(200) NULL DEFAULT NULL,
  `summary` TEXT NULL DEFAULT NULL,
  `link` VARCHAR(200) NULL DEFAULT NULL,
  `source` VARCHAR(200) NULL DEFAULT NULL,
  `format` VARCHAR(100) NULL DEFAULT NULL,
  `published` DATETIME NULL DEFAULT NULL,
  `language` VARCHAR(10) NULL DEFAULT NULL,
  `type` VARCHAR(100) NULL DEFAULT NULL,
  `master` VARCHAR(100) NULL DEFAULT NULL,
  `version` BIGINT(20) NULL DEFAULT NULL COMMENT 'versionable: tdz_entries_version',
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert',
  `updated` DATETIME NOT NULL COMMENT 'timestampable',
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete',
  PRIMARY KEY (`id`),
  INDEX `link_idx` (`link` ASC),
  INDEX `type_idx` (`type` ASC),
  INDEX `format_idx` (`format` ASC),
  INDEX `published_idx` (`published` ASC),
  INDEX `updated_idx` (`updated` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: Tecnodesign_Studio_Entry';


-- -----------------------------------------------------
-- Table `tdz_contents`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_contents` ;

CREATE TABLE IF NOT EXISTS `tdz_contents` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment',
  `entry` BIGINT(20) NULL DEFAULT NULL,
  `slot` VARCHAR(50) NULL DEFAULT NULL,
  `content_type` VARCHAR(100) NULL DEFAULT NULL,
  `content` LONGTEXT NULL DEFAULT NULL,
  `position` BIGINT(20) NULL DEFAULT NULL COMMENT 'sortable',
  `published` DATETIME NULL DEFAULT NULL,
  `show_at` TEXT NULL DEFAULT NULL,
  `hide_at` TEXT NULL DEFAULT NULL,
  `version` BIGINT(20) NULL DEFAULT NULL COMMENT 'versionable: tdz_contents_version',
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert',
  `updated` DATETIME NOT NULL COMMENT 'timestampable',
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete',
  PRIMARY KEY (`id`),
  INDEX `position_idx` (`position` ASC),
  INDEX `slot_idx` (`slot` ASC),
  INDEX `entry_idx` (`entry` ASC),
  INDEX `published_idx` (`published` ASC),
  INDEX `updated_idx` (`updated` ASC),
  CONSTRAINT `tdz_contents_entry_tdz_entries_id`
    FOREIGN KEY (`entry`)
    REFERENCES `tdz_entries` (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: Tecnodesign_Studio_Content';


-- -----------------------------------------------------
-- Table `tdz_contents_version`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_contents_version` ;

CREATE TABLE IF NOT EXISTS `tdz_contents_version` (
  `id` BIGINT(20) NOT NULL DEFAULT '0',
  `entry` BIGINT(20) NULL DEFAULT NULL,
  `slot` VARCHAR(50) CHARACTER SET 'latin1' NULL DEFAULT NULL,
  `content_type` VARCHAR(100) CHARACTER SET 'latin1' NULL DEFAULT NULL,
  `content` LONGTEXT CHARACTER SET 'latin1' NULL DEFAULT NULL,
  `position` BIGINT(20) NULL DEFAULT NULL,
  `published` DATETIME NULL DEFAULT NULL,
  `show_at` TEXT CHARACTER SET 'latin1' NULL DEFAULT NULL,
  `hide_at` TEXT CHARACTER SET 'latin1' NULL DEFAULT NULL,
  `version` BIGINT(20) NOT NULL DEFAULT '0',
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  `expired` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`, `version`),
  INDEX `entry_idx` (`entry` ASC),
  INDEX `updated_idx` (`updated` ASC),
  INDEX `version_idx` (`version` ASC),
  CONSTRAINT `tdz_contents_version_id__tdz_contents_id`
    FOREIGN KEY (`id`)
    REFERENCES `tdz_contents` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: ~';


-- -----------------------------------------------------
-- Table `tdz_entries_version`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_entries_version` ;

CREATE TABLE IF NOT EXISTS `tdz_entries_version` (
  `id` BIGINT(20) NOT NULL DEFAULT '0',
  `title` VARCHAR(200) NULL DEFAULT NULL,
  `summary` TEXT NULL DEFAULT NULL,
  `link` VARCHAR(200) NULL DEFAULT NULL,
  `source` VARCHAR(200) NULL DEFAULT NULL,
  `format` VARCHAR(100) NULL DEFAULT NULL,
  `published` DATETIME NULL DEFAULT NULL,
  `language` VARCHAR(10) NULL DEFAULT NULL,
  `type` VARCHAR(100) NULL DEFAULT NULL,
  `master` VARCHAR(100) NULL DEFAULT NULL,
  `version` BIGINT(20) NOT NULL DEFAULT '0',
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  `expired` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`, `version`),
  INDEX `entry_idx` (`id` ASC),
  INDEX `link_idx` (`link` ASC),
  INDEX `updated_idx` (`updated` ASC),
  INDEX `version_idx` (`version` ASC),
  INDEX `first_published_idx` (`id` ASC, `published` ASC),
  CONSTRAINT `tdz_entries_version_id_tdz_entries_id`
    FOREIGN KEY (`id`)
    REFERENCES `tdz_entries` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: ~';


-- -----------------------------------------------------
-- Table `tdz_permissions`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_permissions` ;

CREATE TABLE IF NOT EXISTS `tdz_permissions` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment',
  `entry` BIGINT(20) NULL DEFAULT NULL,
  `role` VARCHAR(100) NOT NULL,
  `credentials` TEXT NULL DEFAULT NULL,
  `version` BIGINT(20) NULL DEFAULT NULL COMMENT 'versionable: tdz_permissions_version',
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert',
  `updated` DATETIME NOT NULL COMMENT 'timestampable',
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete',
  PRIMARY KEY (`id`),
  INDEX `entry_idx` (`entry` ASC),
  INDEX `role_idx` (`role` ASC),
  CONSTRAINT `tdz_permissions_entry_tdz_entries_id`
    FOREIGN KEY (`entry`)
    REFERENCES `tdz_entries` (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: Tecnodesign_Studio_Permission';


-- -----------------------------------------------------
-- Table `tdz_permissions_version`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_permissions_version` ;

CREATE TABLE IF NOT EXISTS `tdz_permissions_version` (
  `id` BIGINT(20) NOT NULL DEFAULT '0',
  `entry` BIGINT(20) NULL DEFAULT NULL,
  `role` VARCHAR(100) NOT NULL,
  `credentials` TEXT NULL DEFAULT NULL,
  `version` BIGINT(20) NOT NULL DEFAULT '0',
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  `expired` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`, `version`),
  CONSTRAINT `tdz_permissions_version_id_tdz_permissions_id`
    FOREIGN KEY (`id`)
    REFERENCES `tdz_permissions` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: ~';


-- -----------------------------------------------------
-- Table `tdz_relations`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_relations` ;

CREATE TABLE IF NOT EXISTS `tdz_relations` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment',
  `parent` BIGINT(20) NULL DEFAULT NULL,
  `entry` BIGINT(20) NOT NULL,
  `position` BIGINT(20) NULL DEFAULT '1' COMMENT 'sortable',
  `version` BIGINT(20) NULL DEFAULT NULL COMMENT 'versionable: tdz_relations_version',
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert',
  `updated` DATETIME NOT NULL COMMENT 'timestampable',
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete',
  PRIMARY KEY (`id`),
  INDEX `parent_idx` (`parent` ASC),
  INDEX `position_idx` (`position` ASC),
  INDEX `entry_idx` (`entry` ASC),
  CONSTRAINT `__Child__`
    FOREIGN KEY (`entry`)
    REFERENCES `tdz_entries` (`id`),
  CONSTRAINT `__Parent__`
    FOREIGN KEY (`parent`)
    REFERENCES `tdz_entries` (`id`))
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: Tecnodesign_Studio_Relation';


-- -----------------------------------------------------
-- Table `tdz_relations_version`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_relations_version` ;

CREATE TABLE IF NOT EXISTS `tdz_relations_version` (
  `id` BIGINT(20) NOT NULL DEFAULT '0',
  `parent` BIGINT(20) NULL DEFAULT NULL,
  `entry` BIGINT(20) NOT NULL,
  `position` BIGINT(20) NULL DEFAULT '1',
  `version` BIGINT(20) NOT NULL DEFAULT '0',
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  `expired` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`, `version`),
  INDEX `entry_idx` (`entry` ASC),
  INDEX `updated_idx` (`updated` ASC),
  INDEX `version_idx` (`version` ASC),
  CONSTRAINT `tdz_relations_version_id_tdz_relations_id`
    FOREIGN KEY (`id`)
    REFERENCES `tdz_relations` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: ~';


-- -----------------------------------------------------
-- Table `tdz_tags`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_tags` ;

CREATE TABLE IF NOT EXISTS `tdz_tags` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'auto-increment',
  `entry` BIGINT(20) NULL DEFAULT NULL,
  `tag` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `version` BIGINT(20) NULL DEFAULT NULL COMMENT 'versionable: tdz_tags_version',
  `created` DATETIME NOT NULL COMMENT 'timestampable: before-insert',
  `updated` DATETIME NOT NULL COMMENT 'timestampable',
  `expired` DATETIME NULL DEFAULT NULL COMMENT 'soft-delete',
  PRIMARY KEY (`id`),
  INDEX `entry_idx` (`entry` ASC),
  INDEX `slug_idx` (`slug` ASC),
  CONSTRAINT `fk_tdz_tags__tdz_entries`
    FOREIGN KEY (`entry`)
    REFERENCES `tdz_entries` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: Tecnodesign_Studio_Tag';


-- -----------------------------------------------------
-- Table `tdz_tags_version`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_tags_version` ;

CREATE TABLE IF NOT EXISTS `tdz_tags_version` (
  `id` BIGINT(20) NOT NULL,
  `entry` BIGINT(20) NULL DEFAULT NULL,
  `tag` VARCHAR(100) NULL DEFAULT NULL,
  `slug` VARCHAR(100) NULL DEFAULT NULL,
  `version` BIGINT(20) NOT NULL,
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  `expired` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`, `version`),
  INDEX `id_idx` (`id` ASC),
  INDEX `entry_idx` (`entry` ASC),
  INDEX `slug_idx` (`slug` ASC),
  INDEX `updated_idx` (`updated` ASC),
  CONSTRAINT `fk_tdz_tags_version_tdz_tags1`
    FOREIGN KEY (`id`)
    REFERENCES `tdz_tags` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: ~';


-- -----------------------------------------------------
-- Table `tdz_contents_display`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_contents_display` ;

CREATE TABLE IF NOT EXISTS `tdz_contents_display` (
  `content` BIGINT NOT NULL,
  `link` VARCHAR(200) NOT NULL,
  `version` BIGINT NULL,
  `show` TINYINT(1) NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  `expired` DATETIME NULL,
  PRIMARY KEY (`content`, `link`),
  CONSTRAINT `fk_tdz_contents_display_tdz_contents`
    FOREIGN KEY (`content`)
    REFERENCES `tdz_contents` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: Tecnodesign_Studio_ContentDisplay';


-- -----------------------------------------------------
-- Table `tdz_contents_display_version`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tdz_contents_display_version` ;

CREATE TABLE IF NOT EXISTS `tdz_contents_display_version` (
  `content` BIGINT NOT NULL,
  `link` VARCHAR(200) NOT NULL,
  `version` BIGINT NOT NULL,
  `show` TINYINT(1) NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  `expired` DATETIME NULL,
  PRIMARY KEY (`content`, `link`, `version`),
  INDEX `fk_tdz_contents_display_version_tdz_contents_version_idx` (`content` ASC, `version` ASC),
  CONSTRAINT `fk_tdz_contents_display_version__tdz_contents_version`
    FOREIGN KEY (`content` , `version`)
    REFERENCES `tdz_contents_version` (`id` , `version`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COMMENT = 'className: ~';

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
