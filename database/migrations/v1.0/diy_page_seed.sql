-- 转存表中的数据 `lf_diy_page`
--

INSERT IGNORE INTO `lf_diy_page` (`id`, `page_name`, `page_type`, `page_title`, `global_config`, `is_default`, `status`, `create_time`, `update_time`, `delete_time`) VALUES
(1, '测试主页', 'home', '测试主页1', '{\"title_bar\": {\"show\": \"\", \"title\": \"首页\", \"bg_color\": \"#ffffff\", \"text_color\": \"#333\"}, \"background\": {\"type\": \"color\", \"color\": \"rgba(245, 245, 245, 1)\", \"image\": \"\", \"gradient\": {\"type\": \"linear\", \"angle\": \"180\", \"colors\": [\"#ffffff\", \"#f5f5f5\"]}, \"image_mode\": \"cover\"}, \"page_padding\": \"0\", \"pulf_down_refresh\": \"1\"}', 0, 1, '2026-04-06 15:00:56', '2026-04-06 15:00:56', NULL),
(2, '主页1', 'home', '', '{\"title_bar\": {\"show\": true, \"title\": \"首页\", \"bg_color\": \"#ffffff\", \"text_color\": \"#333\"}, \"background\": {\"type\": \"color\", \"color\": \"#f5f5f5\"}, \"page_padding\": 24}', 0, 1, '2026-04-08 10:44:57', '2026-06-24 15:07:32', '2026-06-24 15:07:32'),
(3, '个人中心默认装修', 'user_center', '个人中心', '[]', 1, 1, '2026-05-09 08:49:32', '2026-05-09 08:49:32', NULL),
(4, '主页', 'home', '主页', '{\"title_bar\": {\"show\": \"1\", \"title\": \"首页\", \"bg_color\": \"#ffffff\", \"text_color\": \"#333\"}, \"background\": {\"type\": \"color\", \"color\": \"#f5f5f5\", \"image\": \"\", \"gradient\": {\"type\": \"linear\", \"angle\": \"180\", \"colors\": [\"#f5f5f5\", \"#f5f5f5\"]}, \"image_mode\": \"cover\"}, \"page_padding\": 0, \"pulf_down_refresh\": \"1\"}', 1, 1, '2026-06-24 15:07:57', '2026-06-24 15:07:57', NULL);

-- --------------------------------------------------------

--
