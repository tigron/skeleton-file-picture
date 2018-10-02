<?php
/**
 * Database migration class
 *
 * @author Lionel Laffineur <lionel@tigron.be>
 */
namespace Skeleton\File\Picture;

use \Skeleton\Database\Database;

class Migration_20180101_010101_Crop extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();

		// check if the columns for crop functionality are present
		$columns = $db->get_all("SHOW COLUMNS FROM `picture`;");
		foreach ($columns as $column) {
			if ($column['Field'] == 'crop_width' || $column['Field'] == 'crop_height') {
				return;
			}
		}

		// add the columns needed for crop feature
		$db->query("ALTER TABLE `picture`
					ADD `crop_width` INT(11) NOT NULL,
					ADD `crop_height` INT(11) NOT NULL,
					ADD `crop_offset_left` INT(11) NOT NULL,
					ADD `crop_offset_top` INT(11) NOT NULL");
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down() {

	}
}
