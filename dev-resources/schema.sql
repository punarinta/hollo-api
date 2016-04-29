-- phpMyAdmin SQL Dump
-- version 4.4.0
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Apr 26, 2016 at 08:04 AM
-- Server version: 5.6.28-0ubuntu0.15.10.1
-- PHP Version: 5.6.11-1ubuntu3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `mailless`
--

-- --------------------------------------------------------

--
-- Table structure for table `contact`
--

CREATE TABLE IF NOT EXISTS `contact` (
  `id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `email` varchar(128) NOT NULL,
  `name` varchar(128) DEFAULT NULL,
  `muted` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `count` int(10) unsigned NOT NULL DEFAULT '0',
  `last_ts` int(10) unsigned NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `contact_message`
--

CREATE TABLE IF NOT EXISTS `contact_message` (
  `contact_id` bigint(20) unsigned NOT NULL,
  `message_id` bigint(20) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mail_service`
--

CREATE TABLE IF NOT EXISTS `mail_service` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `domains` varchar(2048) NOT NULL,
  `cfg_in` varchar(255) DEFAULT NULL,
  `cfg_out` varchar(255) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `mail_service`
--

INSERT INTO `mail_service` (`id`, `name`, `domains`, `cfg_in`, `cfg_out`) VALUES
  (1, 'Gmail', '|gmail.com|', '{"type":"imap","oauth":true,"host":"imap.gmail.com","port":993}', '{"type":"smtp","oauth":true,"host":"smtp.gmail.com","port":587,"enc":"tls"}'),
  (2, 'Yandex', '|yandex.ru|yandex.com|ya.ru|', '{"type":"imap","oauth":false,"host":"imap.yandex.com","port":993,"enc":"ssl"}', '{"type":"smtp","oauth":false,"host":"smtp.yandex.com","port":465,"enc":"ssl"}');

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

CREATE TABLE IF NOT EXISTS `message` (
  `id` bigint(20) unsigned NOT NULL,
  `ext_id` char(24) NOT NULL,
  `sender` varchar(128) NOT NULL,
  `subject` varchar(256) DEFAULT NULL,
  `body` mediumtext,
  `files` mediumtext,
  `ts` bigint(20) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `id` bigint(20) unsigned NOT NULL,
  `email` varchar(256) NOT NULL,
  `roles` int(10) unsigned NOT NULL DEFAULT '0',
  `ext_id` char(24) DEFAULT NULL,
  `last_sync_ts` int(10) unsigned NOT NULL DEFAULT '1',
  `is_syncing` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `settings` text,
  `created` bigint(20) unsigned NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contact`
--
ALTER TABLE `contact`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_message`
--
ALTER TABLE `contact_message`
ADD UNIQUE KEY `link` (`contact_id`,`message_id`) USING BTREE;

--
-- Indexes for table `mail_service`
--
ALTER TABLE `mail_service`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `message`
--
ALTER TABLE `message`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contact`
--
ALTER TABLE `contact`
MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `mail_service`
--
ALTER TABLE `mail_service`
MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `message`
--
ALTER TABLE `message`
MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;