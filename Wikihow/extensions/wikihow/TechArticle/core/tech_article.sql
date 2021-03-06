CREATE TABLE `tech_product` (
  `tpr_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tpr_name` varbinary(255) NOT NULL,
  `tpr_enabled` tinyint(1) NOT NULL,
  PRIMARY KEY (`tpr_id`),
  UNIQUE KEY `tpr_name` (`tpr_name`)
)

CREATE TABLE `tech_platform` (
  `tpl_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tpl_name` varbinary(255) NOT NULL,
  `tpl_enabled` tinyint(1) NOT NULL,
  PRIMARY KEY (`tpl_id`),
  UNIQUE KEY `tpl_name` (`tpl_name`)
);

CREATE TABLE `tech_article` (
  `tar_page_id` int(10) unsigned NOT NULL,
  `tar_rev_id` int(10) unsigned NOT NULL,
  `tar_user_id` int(10) unsigned NOT NULL,
  `tar_product_id` int(10) unsigned NOT NULL,
  `tar_platform_id` int(10) unsigned NOT NULL,
  `tar_tested` tinyint(1) NOT NULL,
  `tar_date` varchar(14) NOT NULL,
  PRIMARY KEY (`tar_page_id`,`tar_product_id`,`tar_platform_id`)
);

/* Test data

INSERT INTO tech_product (tpr_id, tpr_name, tpr_enabled) VALUES
(1, '4K', 1),
(2, 'Amazon', 1),
(3, 'Android', 1),
(4, 'AOL', 1),
(5, 'Apple Messages', 1),
(6, 'Apple TV', 1),
(7, 'Aviate', 1),
(8, 'Breathe', 1),
(9, 'Chromecast', 1),
(10, 'Counter Strike', 1),
(11, 'Craigslist', 1),
(12, 'DLink', 1),
(13, 'Dragon NaturallySpeaking', 1),
(14, 'Dropbox', 1),
(15, 'eBay', 1),
(16, 'EPUB', 1),
(17, 'eWallet', 1),
(18, 'Facebook', 1),
(19, 'Facebook Messenger', 1),
(20, 'Firebug', 1),
(21, 'FireFox', 1),
(22, 'Foursquare', 1),
(23, 'GIMP', 1),
(24, 'GitHub', 1),
(25, 'Gmail', 1),
(26, 'Google', 1),
(27, 'Google Chrome', 1),
(28, 'Google Docs', 1),
(29, 'Google Drive', 1),
(30, 'Google Photos', 1),
(31, 'Google Sheets', 1),
(32, 'Google Slides', 1),
(33, 'Grand Theft Auto', 1),
(34, 'GroupMe', 1),
(35, 'Groupon', 1),
(36, 'HTML', 1),
(37, 'iCloud', 1),
(38, 'Illustrator', 1),
(39, 'iMovie', 1),
(40, 'Instagram', 1),
(41, 'iOS (ALL)', 1),
(42, 'Itel', 1),
(43, 'iTunes', 1),
(44, 'Jambox', 1),
(45, 'Java', 1),
(46, 'Kik', 1),
(47, 'Linkedin', 1),
(48, 'Linux', 1),
(49, 'Lyft', 1),
(50, 'MacOS', 1),
(51, 'Magic Keyboard', 1),
(52, 'Marco Polo', 1),
(53, 'Marsbot', 1),
(54, 'Microsoft Edge', 1),
(55, 'Microsoft Excel', 1),
(56, 'Microsoft Office', 1),
(57, 'Microsoft PowerPoint', 1),
(58, 'Microsoft Word', 1),
(59, 'Minecraft', 1),
(60, 'Mondly', 1),
(61, 'MovieStarPlanet', 1),
(62, 'Netflix', 1),
(63, 'Notepad', 1),
(64, 'Outlook', 1),
(65, 'Pandora', 1),
(66, 'PayPal', 1),
(67, 'Photoshop', 1),
(68, 'Pinterest', 1),
(69, 'PlayStation', 1),
(70, 'Pokémon', 1),
(71, 'Pokémon GO', 1),
(72, 'PPSSPP', 1),
(73, 'Prion', 1),
(74, 'Reddit', 1),
(75, 'Safari', 1),
(76, 'Samsung Galaxy', 1),
(77, 'Sims', 1),
(78, 'Skype', 1),
(79, 'Skyrim', 1),
(80, 'Slack', 1),
(81, 'SlideShare', 1),
(82, 'Snapchat', 1),
(83, 'Spotify', 1),
(84, 'Steam Wallet', 1),
(85, 'Subway Surfers', 1),
(86, 'Surfy', 1),
(87, 'Tango', 1),
(88, 'Tecno', 1),
(89, 'Tinder', 1),
(90, 'Trello', 1),
(91, 'Twitter', 1),
(92, 'TwitWipe', 1),
(93, 'Uber', 1),
(94, 'Ubuntu', 1),
(95, 'Venmo', 1),
(96, 'Viber', 1),
(97, 'VOB', 1),
(98, 'Waze', 1),
(99, 'Web Guard', 1),
(100, 'weChat', 1),
(101, 'WhatsApp', 1),
(102, 'Windows', 1),
(103, 'Windows Media Player', 1),
(104, 'Windows Movie Maker', 1),
(105, 'Yahoo!', 1),
(106, 'YouTube', 1);

INSERT INTO tech_platform (tpl_id, tpl_name, tpl_enabled) VALUES
(1, 'Android (Mobile)', 1),
(2, 'iOS 10+ (Mobile)', 1),
(3, 'Mac (Desktop)', 1),
(4, 'Windows (Desktop)', 1),
(5, 'Other', 1);

INSERT INTO tech_article (tar_page_id, tar_rev_id, tar_user_id, tar_product_id, tar_platform_id, tar_tested, tar_date) VALUES
(175304, 19895222, 2749020, 3, 2, 1, '20161219010203'),
(1474296, 19882836, 2749020, 3, 3, 1, '20161219010203'),
(1474296, 19882836, 2749020, 3, 4, 0, '20161219010203'),
(2194695, 19317228, 2749020, 1, 3, 1, '20161219010203'),
(3959, 20169242, 2749020, 4, 5, 1, '20161219010203'),
(2191669, 19835734, 2749020, 5, 1, 1, '20161219010203');

*/
