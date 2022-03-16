
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `ann`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ann` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键',
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '标题',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '内容',
  `status` int NOT NULL COMMENT '0为不显示',
  `created_at` int NOT NULL COMMENT '创建于',
  `updated_at` int NOT NULL COMMENT '更新于',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `azure`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `azure` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT '归属用户id',
  `az_email` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'azure登录邮箱',
  `az_passwd` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'azure登录密码',
  `az_api` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'azure账户api参数',
  `az_sub` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '订阅信息',
  `az_sub_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '订阅id',
  `az_sub_type` text COLLATE utf8mb4_general_ci COMMENT '订阅类型',
  `az_sub_status` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '订阅状态',
  `az_sub_updated_at` int DEFAULT NULL COMMENT '订阅状态更新时间',
  `az_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '访问令牌',
  `az_token_updated_at` int DEFAULT NULL COMMENT '访问令牌生成时间',
  `user_mark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '用户备注',
  `admin_mark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '管理员备注',
  `providers_register` int DEFAULT '0' COMMENT '是否已注册必要提供商',
  `created_at` int NOT NULL COMMENT '账户添加时间',
  `updated_at` int NOT NULL COMMENT '最近一次账户资料更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `azure_server`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `azure_server` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL COMMENT '归属用户id',
  `account_id` int DEFAULT NULL COMMENT '归属账户id',
  `account_email` text COLLATE utf8mb4_general_ci COMMENT '归属账户邮箱',
  `name` text COLLATE utf8mb4_general_ci COMMENT '虚拟机名称',
  `status` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '虚拟机运行状态',
  `location` text COLLATE utf8mb4_general_ci COMMENT '虚拟机地域',
  `vm_size` text COLLATE utf8mb4_general_ci COMMENT '虚拟机大小',
  `os_offer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'os_offer',
  `os_sku` text COLLATE utf8mb4_general_ci COMMENT 'os_sku',
  `disk_size` text COLLATE utf8mb4_general_ci COMMENT '虚拟机磁盘大小',
  `vm_id` text COLLATE utf8mb4_general_ci COMMENT '虚拟机id',
  `resource_group` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '资源组',
  `at_subscription_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '归属订阅',
  `ip_address` text COLLATE utf8mb4_general_ci COMMENT '虚拟机ip地址',
  `network_interfaces` text COLLATE utf8mb4_general_ci COMMENT '网络接口',
  `network_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '网络接口详情',
  `vm_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '虚拟机详情',
  `instance_details` text COLLATE utf8mb4_general_ci COMMENT '虚拟机状态信息',
  `request_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'api请求url',
  `user_remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '用户备注',
  `admin_remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '管理员备注',
  `created_at` int NOT NULL COMMENT '创建时间',
  `updated_at` int NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `azure_server_traffic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `azure_server_traffic` (
  `id` int NOT NULL AUTO_INCREMENT,
  `u` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '上传流量',
  `d` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '下载流量',
  `date` text COLLATE utf8mb4_general_ci NOT NULL COMMENT '对应日期',
  `uuid` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '虚拟机uuid',
  `created_at` int NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item` text COLLATE utf8mb4_general_ci COMMENT '键',
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '值',
  `class` text COLLATE utf8mb4_general_ci COMMENT '类',
  `default_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '默认值',
  `type` text COLLATE utf8mb4_general_ci COMMENT '值类型',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_log` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键',
  `email` text COLLATE utf8mb4_general_ci NOT NULL COMMENT '登录邮箱',
  `ip` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'ip地址',
  `ip_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'ip信息',
  `created_at` int NOT NULL COMMENT '登录时间',
  `status` int NOT NULL COMMENT '1为成功',
  `info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '备注',
  `ua` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '请求ua',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT '提交用户',
  `name` text COLLATE utf8mb4_general_ci COMMENT '任务名称',
  `status` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '任务状态',
  `schedule` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '进度(0-100)',
  `current` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '当前进度提示语',
  `total` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '总进度提示语',
  `created_at` int NOT NULL COMMENT '创建时间',
  `updated_at` int NOT NULL COMMENT '更新时间',
  `total_time` int DEFAULT NULL COMMENT '任务总耗时',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键',
  `email` varchar(128) NOT NULL COMMENT '邮箱地址',
  `passwd` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '密码',
  `status` int NOT NULL DEFAULT '1' COMMENT '账户状态',
  `notify_email` text COLLATE utf8mb4_general_ci COMMENT '用户通知邮箱',
  `notify_tg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '用户通知tg用户名',
  `notify_tgid` text COLLATE utf8mb4_general_ci COMMENT '用户通知tgid',
  `is_admin` int NOT NULL DEFAULT '0' COMMENT '管理员权限',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '用户备注',
  `personalise` text COLLATE utf8mb4_general_ci COMMENT '用户偏好',
  `created_at` int NOT NULL COMMENT '创建时间',
  `updated_at` int NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verify`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verify` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` text COLLATE utf8mb4_general_ci NOT NULL COMMENT '邮箱',
  `type` text COLLATE utf8mb4_general_ci NOT NULL COMMENT '验证码类型',
  `code` text COLLATE utf8mb4_general_ci NOT NULL COMMENT '验证码',
  `ip` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '请求ip',
  `result` int NOT NULL DEFAULT '0' COMMENT '验证结果',
  `created_at` int NOT NULL COMMENT '创建时间',
  `expired_at` int NOT NULL COMMENT '过期时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

