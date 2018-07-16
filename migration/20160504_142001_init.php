<?php
/**
 * Database migration class
 *
 * @author Lionel Laffineur <lionel@tigron.be>
 */
namespace Skeleton\File\Picture;

use \Skeleton\Database\Database;

class Migration_20160504_142001_Init extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();
		$db->query("CREATE TABLE IF NOT EXISTS `picture` (
					  `id` int(11) NOT NULL AUTO_INCREMENT,
					  `file_id` int(11) NOT NULL,
					  `width` int(11) NOT NULL,
					  `height` int(11) NOT NULL,
					  PRIMARY KEY (`id`),
					  KEY `file_id` (`file_id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down() {

	}
}
