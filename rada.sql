/*
 Navicat Premium Data Transfer

 Source Server         : 虚拟机
 Source Server Type    : MySQL
 Source Server Version : 50547
 Source Host           : 192.168.1.124
 Source Database       : rada

 Target Server Type    : MySQL
 Target Server Version : 50547
 File Encoding         : utf-8

 Date: 02/04/2016 09:33:12 AM
*/

SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
--  Table structure for `tb_account`
-- ----------------------------
DROP TABLE IF EXISTS `tb_account`;
CREATE TABLE `tb_account` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coin` double(30,0) NOT NULL,
  `coin_tic` double(30,0) NOT NULL,
  `status` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `tb_attach`
-- ----------------------------
DROP TABLE IF EXISTS `tb_attach`;
CREATE TABLE `tb_attach` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ctime` datetime NOT NULL,
  `type` varchar(50) NOT NULL,
  `size` varchar(20) NOT NULL,
  `extension` varchar(20) NOT NULL,
  `hash` varchar(40) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `save_name` varchar(255) NOT NULL,
  `width` varchar(20) NOT NULL,
  `height` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `tb_order`
-- ----------------------------
DROP TABLE IF EXISTS `tb_order`;
CREATE TABLE `tb_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `buy_uid` int(11) DEFAULT NULL,
  `sell_uid` int(11) NOT NULL,
  `num` bigint(20) NOT NULL DEFAULT '0',
  `account` bigint(20) DEFAULT '0',
  `status` tinyint(4) DEFAULT '1',
  `ctime` datetime DEFAULT NULL,
  `photo_id` int(11) DEFAULT '0',
  `admin_id` int(11) DEFAULT NULL,
  `admin_info` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `tb_user`
-- ----------------------------
DROP TABLE IF EXISTS `tb_user`;
CREATE TABLE `tb_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password` varchar(32) NOT NULL,
  `recommend_userid` int(11) NOT NULL DEFAULT '0',
  `recommend_leader_userid` int(11) NOT NULL DEFAULT '0',
  `area` enum('A','B','C') NOT NULL DEFAULT 'A',
  `safe_password` varchar(32) NOT NULL,
  `mobile` varchar(11) NOT NULL,
  `ctime` datetime NOT NULL,
  `utime` datetime NOT NULL,
  `c_ip` int(50) NOT NULL DEFAULT '0',
  `status` enum('open','frosen','down') DEFAULT 'open',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

SET FOREIGN_KEY_CHECKS = 1;
