-- ============================================================
-- SystemA 后台游戏管理升级迁移
-- 执行时间: 2026-04-10
-- 说明:
--   1. fa_platform_game 表新增定价字段
--   2. 新建轮播图子表、修复方案子表（替代原来的 JSON textarea）
--   3. 资源表已有 fa_platform_game_resource，无需新建
-- ============================================================

-- ---- 1. 游戏主表增加定价和显示设置字段（幂等：已存在则跳过）----
SET @dbname = DATABASE();

-- developer
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'fa_platform_game' AND COLUMN_NAME = 'developer');
SET @sql = IF(@col_exists > 0, "SELECT 0", "ALTER TABLE `fa_platform_game` ADD COLUMN `developer` varchar(120) NOT NULL DEFAULT '' COMMENT '开发者' AFTER `subtitle`");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- genre
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'fa_platform_game' AND COLUMN_NAME = 'genre');
SET @sql = IF(@col_exists > 0, "SELECT 0", "ALTER TABLE `fa_platform_game` ADD COLUMN `genre` varchar(60) NOT NULL DEFAULT '' COMMENT '游戏类型/标签' AFTER `developer`");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- release_date
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'fa_platform_game' AND COLUMN_NAME = 'release_date');
SET @sql = IF(@col_exists > 0, "SELECT 0", "ALTER TABLE `fa_platform_game` ADD COLUMN `release_date` varchar(20) NOT NULL DEFAULT '' COMMENT '发行日期' AFTER `genre`");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- full_description
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'fa_platform_game' AND COLUMN_NAME = 'full_description');
SET @sql = IF(@col_exists > 0, "SELECT 0", "ALTER TABLE `fa_platform_game` ADD COLUMN `full_description` longtext NULL COMMENT '详细介绍(富文本)' AFTER `intro`");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- base_price
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'fa_platform_game' AND COLUMN_NAME = 'base_price');
SET @sql = IF(@col_exists > 0, "SELECT 0", "ALTER TABLE `fa_platform_game` ADD COLUMN `base_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '普通用户价格' AFTER `full_description`");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- member_price
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'fa_platform_game' AND COLUMN_NAME = 'member_price');
SET @sql = IF(@col_exists > 0, "SELECT 0", "ALTER TABLE `fa_platform_game` ADD COLUMN `member_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '会员价格' AFTER `base_price`");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- display_order
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'fa_platform_game' AND COLUMN_NAME = 'display_order');
SET @sql = IF(@col_exists > 0, "SELECT 0", "ALTER TABLE `fa_platform_game` ADD COLUMN `display_order` int(10) NOT NULL DEFAULT '0' COMMENT '展示排序权重' AFTER `weigh`");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- normal_price (兼容旧命名)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'fa_platform_game' AND COLUMN_NAME = 'normal_price');
SET @sql = IF(@col_exists > 0, "SELECT 0", "ALTER TABLE `fa_platform_game` ADD COLUMN `normal_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '普通价(前端别名)' AFTER `member_price`");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---- 2. 轮播图子表（替代原来 carousel JSON 字段）----
CREATE TABLE IF NOT EXISTS `fa_platform_game_carousel` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` int(11) unsigned NOT NULL DEFAULT '0',
  `image_url` varchar(500) NOT NULL DEFAULT '' COMMENT '图片URL',
  `title` varchar(120) DEFAULT '' COMMENT '标题',
  `description` varchar(255) DEFAULT '' COMMENT '描述',
  `link_url` varchar(500) DEFAULT '' COMMENT '跳转链接',
  `sort_weight` int(10) NOT NULL DEFAULT '0' COMMENT '排序权重',
  `status` varchar(30) NOT NULL DEFAULT 'normal',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `status_sort` (`game_id`,`sort_weight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='游戏详情页轮播图';

-- ---- 3. 修复方案子表（替代原来 repair_profile JSON 字段）----
CREATE TABLE IF NOT EXISTS `fa_platform_game_repair` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` int(11) unsigned NOT NULL DEFAULT '0',
  `repair_type` varchar(30) NOT NULL DEFAULT 'common' COMMENT '类型: common/game_specific',
  `name` varchar(120) NOT NULL DEFAULT '' COMMENT '修复名称',
  `description` varchar(500) DEFAULT '' COMMENT '描述',
  `script_url` varchar(500) DEFAULT '' COMMENT '脚本下载地址或命令',
  `risk_level` varchar(20) NOT NULL DEFAULT 'low' COMMENT '风险级别: low/medium/high',
  `auto_run` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否自动执行',
  `sort_weight` int(10) NOT NULL DEFAULT '0',
  `status` varchar(30) NOT NULL DEFAULT 'normal',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_type` (`game_id`,`repair_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='游戏修复方案';

-- ---- 4. 评论表 ----
CREATE TABLE IF NOT EXISTS `fa_platform_game_comment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` int(11) unsigned NOT NULL DEFAULT '0',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0',
  `content` text NOT NULL,
  `rating` tinyint(1) unsigned NOT NULL DEFAULT '5' COMMENT '评分1-5',
  `like_count` int(11) unsigned NOT NULL DEFAULT '0',
  `status` varchar(30) NOT NULL DEFAULT 'normal',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_time` (`game_id`,`createtime`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='游戏评论';

-- ---- 5. 菜单补充：直链资源与天翼云文件为同级菜单（幂等）----
SET @cloud_menu_pid = (SELECT pid FROM `fa_auth_rule` WHERE `name` = 'platform/cloudfile' LIMIT 1);
SET @direct_menu_exists = (SELECT COUNT(*) FROM `fa_auth_rule` WHERE `name` = 'platform/resource/direct');
SET @sql = IF(@cloud_menu_pid IS NULL OR @direct_menu_exists > 0,
  "SELECT 0",
  CONCAT("INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`menutype`,`extend`,`py`,`pinyin`,`createtime`,`updatetime`,`weigh`,`status`) VALUES ('file',", @cloud_menu_pid, ",'platform/resource/direct','直链资源','fa fa-link','platform/resource/index?ref=direct','','',1,'addtabs','','','',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),-81,'normal')")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
