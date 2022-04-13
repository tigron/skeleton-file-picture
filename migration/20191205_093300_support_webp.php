<?php
/**
 * Database migration class
 *
 * @author Lionel Laffineur <lionel@tigron.be>
 */
namespace Skeleton\File\Picture;


use \Skeleton\Database\Database;

class Migration_20191205_093300_Support_Webp extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();
		$ids = $db->get_column("SELECT id FROM file WHERE mime_type = 'image/webp'");
		foreach ($ids as $id) {
			$file = \File::get_by_id($id);
			$file->save();
		}
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down() {

	}
}
