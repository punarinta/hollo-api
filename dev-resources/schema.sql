-- phpMyAdmin SQL Dump
-- version 4.4.0
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Aug 19, 2016 at 03:49 PM
-- Server version: 5.7.13-0ubuntu0.16.04.2
-- PHP Version: 7.0.8-0ubuntu0.16.04.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `hollo`
--

-- --------------------------------------------------------

--
-- Table structure for table `chat`
--

CREATE TABLE IF NOT EXISTS `chat` (
  `id` bigint(20) unsigned NOT NULL,
  `name` varchar(64) DEFAULT NULL,
  `count` int(10) unsigned NOT NULL DEFAULT '0',
  `last_ts` int(10) unsigned NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `chat_user`
--

CREATE TABLE IF NOT EXISTS `chat_user` (
  `chat_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `muted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `read` tinyint(1) unsigned NOT NULL DEFAULT '1'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

CREATE TABLE IF NOT EXISTS `message` (
  `id` bigint(20) unsigned NOT NULL,
  `ext_id` varchar(24) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `chat_id` bigint(20) unsigned NOT NULL,
  `subject` varchar(256) DEFAULT NULL,
  `body` mediumtext CHARACTER SET utf8mb4,
  `files` mediumtext,
  `ts` bigint(20) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `muted`
--

CREATE TABLE IF NOT EXISTS `muted` (
  `user` varchar(255) DEFAULT NULL,
  `domain` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `id` bigint(20) unsigned NOT NULL,
  `email` varchar(256) NOT NULL,
  `name` varchar(64) DEFAULT NULL,
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
-- Indexes for table `chat`
--
ALTER TABLE `chat`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_user`
--
ALTER TABLE `chat_user`
  ADD UNIQUE KEY `chat_id_user_id` (`chat_id`,`user_id`);

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
-- Indexes for table `muted`
--
ALTER TABLE `muted`
  ADD UNIQUE KEY `pair` (`user`,`domain`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chat`
--
ALTER TABLE `chat`
  MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `mail_service`
--
ALTER TABLE `mail_service`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
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