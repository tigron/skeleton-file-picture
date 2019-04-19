<?php
/**
 * Database migration class
 *
 */
namespace Skeleton\File\Picture;


use \Skeleton\Database\Database;

class Migration_20190419_140451_Widht_height_null extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();
		$db->query('ALTER TABLE `picture`
					CHANGE `width` `width` int(11) NULL AFTER `file_id`,
					CHANGE `height` `height` int(11) NULL AFTER `width`;');
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down() {

	}
}
