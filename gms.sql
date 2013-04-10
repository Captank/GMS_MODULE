CREATE TABLE IF NOT EXISTS `gms_shops` (`id` int(11) NOT NULL AUTO_INCREMENT, `owner` varchar(25) NOT NULL, PRIMARY KEY (`id`));
CREATE TABLE IF NOT EXISTS `gms_items` (`id` int(11) NOT NULL AUTO_INCREMENT, `shopid` int(11) NOT NULL, `lowid` int(11) NOT NULL, `highid` int(11) NOT NULL, `ql` smallint(6) NOT NULL, `price` bigint(20) NOT NULL, PRIMARY KEY (`id`));
CREATE TABLE IF NOT EXISTS `gms_item_data` ( `lowid` int(11) NOT NULL DEFAULT '0', `highid` int(11) DEFAULT NULL, `icon` int(11) DEFAULT NULL, `name` VARCHAR(150) DEFAULT NULL, `category` int(11) NOT NULL);
CREATE TABLE IF NOT EXISTS `gms_categories` ( `id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(50) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `name` (`name`));
CREATE TABLE IF NOT EXISTS `gms_contacts` ( `shopid` int(11) NOT NULL, `character` varchar(25) NOT NULL, UNIQUE KEY `character` (`character`));
