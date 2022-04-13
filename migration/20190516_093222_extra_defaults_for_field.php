<?php
/**
 * Database migration class
 *
 */
namespace Skeleton\File\Picture;


use \Skeleton\Database\Database;

class Migration_20190516_093222_Extra_defaults_for_field extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();
		$db->query("ALTER TABLE `picture`
					CHANGE `crop_width` `crop_width` int(11) NULL AFTER `height`,
					CHANGE `crop_height` `crop_height` int(11) NULL AFTER `crop_width`,
					CHANGE `crop_offset_left` `crop_offset_left` int(11) NULL AFTER `crop_height`,
					CHANGE `crop_offset_top` `crop_offset_top` int(11) NULL AFTER `crop_offset_left`;");

	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down() {

	}
}
