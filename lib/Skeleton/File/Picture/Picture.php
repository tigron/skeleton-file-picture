<?php
/**
 * Picture class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace Skeleton\File\Picture;

use Skeleton\File\File;
use Skeleton\Database\Database;

class Picture extends File {

	/**
	 * Details
	 *
	 * @var array $details
	 * @access private
	 */
	protected $local_details = [];

	/**
	 * Local fields
	 *
	 * @access private
	 * @var array $fields
	 */
	protected $local_fields = ['file_id', 'width', 'height', 'crop_offset_left', 'crop_offset_top', 'crop_width', 'crop_height'];

	/**
	 * Get the details of this file
	 *
	 * @access private
	 */
	protected function get_details() {
		parent::get_details();
		if (!isset($this->id) OR $this->id === null) {
			throw new Exception('Could not fetch file data: ID not set');
		}

		$db = Database::Get();
		$details = $db->get_row('SELECT * FROM picture WHERE file_id=?', [$this->id]);

		if ($details === null) {
			$this->save();
		} else {
			$this->local_details = $details;
		}
	}

	/**
	 * Save crop information in the picture table
	 *
	 * @access public
	 * @param upper left position on x axis
	 * @param upper left position on y axis
	 * @param width
	 * @param height
	 * @param save
	 */
	public function set_crop($x, $y, $w, $h, $save = true) {
		$this->crop_offset_left = $x;
		$this->crop_offset_top  = $y;
		$this->crop_width  = $w;
		$this->crop_height = $h;
		if ($save) {
			$this->save();
		}
		$filename = Config::$tmp_dir . 'cropped/' . $this->id;
		if (file_exists($filename)) {
			unlink($filename);
		}
	}

	/**
	 * Save the file
	 *
	 * @access public
	 */
	public function save($get_details = true) {
		if (!isset($this->id)) {
			parent::save(false);
		}

		$this->get_dimensions();

		$db = Database::Get();
		if (!isset($this->local_details['id']) OR $this->local_details['id'] === null) {
			if (!isset($this->local_details['file_id']) OR $this->local_details['file_id'] == 0) {
				$this->file_id = $this->id;
			} else {
				$this->id = $this->file_id;
			}
			$db->insert('picture', $this->local_details);
		} else {
			$where = 'file_id=' . $db->quote($this->id);
			$db->update('picture', $this->local_details, $where);
			parent::save(true);
		}


		$this->get_details();
	}

	/**
	 * Set a detail
	 *
	 * @access public
	 * @param string $key
	 * @param mixex $value
	 */
	public function __set($key, $value) {
		if (in_array($key, $this->local_fields)) {
			$this->local_details[$key] = $value;
		} else {
			parent::__set($key, $value);
		}
	}

	/**
	 * Get a detail
	 *
	 * @access public
	 * @param string $key
	 * @return mixed $value
	 */
	public function __get($key) {
		if (isset($this->local_details[$key])) {
			return $this->local_details[$key];
		} else {
			return parent::__get($key);
		}
	}

	/**
	 * Isset
	 *
	 * @access public
	 * @param string $key
	 * @return bool $isset
	 */
	public function __isset($key) {
		if (isset($this->local_details[$key])) {
			return true;
		} else {
			return parent::__isset($key);
		}
	}

	/**
	 * Get the dimensions of the picture
	 *
	 * @access private
	 */
	private function get_dimensions() {
		if (isset($this->width) AND isset($this->height)) {
			return;
		}
		$path = $this->get_path();
		list($width, $height) = getimagesize($path);
		$this->width = $width;
		$this->height = $height;
	}

	/**
	 * Resize the picture
	 *
	 * @access private
	 * @param string $size
	 */
	private function resize($size) {
		if (Config::$tmp_dir === null) {
			throw new \Exception('Set a path first in "Config::$tmp_dir"');
		}

		if ($size == 'original') {
			throw new \Exception('Do not try to resize with size "original".');
		}

		/**
		 * @TODO: redo the cropping implementation.
		 * Precise cropping should be possible on any picture and resize_configuration
		 * For now ignore fetching the configuration for size 'cropped'
		 */
		if ($size != 'cropped') {
			$resize_info = Config::get_resize_configuration($size);
		}

		if (!file_exists(Config::$tmp_dir . $size . '/')) {
			mkdir(Config::$tmp_dir . $size . '/', 0755, true);
		}

		if ($size != 'cropped') {
			$new_width = null;
			if (isset($resize_info['width'])) {
				$new_width = $resize_info['width'];
			}

			$new_height = null;
			if (isset($resize_info['height'])) {
				$new_height = $resize_info['height'];
			}

			$mode = 'auto';
			if (isset($resize_info['mode'])) {
				$mode = $resize_info['mode'];
			}

			$image = new Manipulation($this);
			$image->resize($new_width, $new_height, $mode);
			$image->output(Config::$tmp_dir . $size . '/' . $this->id);
		} else {
			$image = new Manipulation($this);
			$image->precise_crop();
			$image->output(Config::$tmp_dir . $size . '/' . $this->id);
		}
	}

	/**
	* Convert an image to another type
	*
	* @access public
	* @param string $format
	*	Available formats: jpg, png, gif
	*/
	public function convert($type) {
		Config::set_ini_values();

		$image = imagecreatefromstring(file_get_contents($this->get_path()));
		$pathinfo = pathinfo($this->name);

		// Make sure we have a filename
		if (!isset($pathinfo['filename'])) {
			$pathinfo['filename'] = $this->name;
		}

		switch ($type) {
			case 'jpeg':
			case 'jpg':
				$this->name = $pathinfo['filename'] . '.jpg';
				$this->mime_type = 'image/jpeg';
				imagejpeg($image, $this->get_path());
				break;
			case 'png':
				$this->name = $pathinfo['filename'] . '.jpg';
				$this->mime_type = 'image/png';
				imagepng($image, $this->get_path());
				break;
			case 'gif':
				$this->name = $pathinfo['filename'] . '.jpg';
				$this->mime_type = 'image/gif';
				imagegif($image, $this->get_path());
				break;
			default:
				throw new \Exception('Unsupported type "' . $type . '". Available types: jpg/png/gif');
		}

		$this->size = filesize($this->get_path());
		$this->save();
	}

	/**
	 * Output the picture to the browser
	 *
	 * @access public
	 * @param string $size
	 */
	public function show($size = 'original') {
		if (!file_exists(Config::$tmp_dir . $size . '/' . $this->id) AND $size != 'original') {
			$this->resize($size);
		}

		if ($size == 'original') {
			$filename = $this->get_path();
		} else {
			$filename = Config::$tmp_dir . $size . '/' . $this->id;
		}

		$gmt_mtime = gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT';

		header('Cache-Control: public');
		header('Pragma: public');

		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
			if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime) {
				header('Expires: ');
				header('HTTP/1.1 304 Not Modified');
				exit;
			}
		}

		header('Last-Modified: '. $gmt_mtime);
		header('Expires: '.gmdate('D, d M Y H:i:s', strtotime('+300 minutes')).' GMT');
		header('Content-Type: ' . $this->mime_type);
		readfile($filename);
		exit();
	}

	/**
	 * Return the contents of a resized picture
	 *
	 * @access public
	 * @param string $size
	 * @return string $contents
	 */
	public function get_resized_contents($size) {
		if (!file_exists(Config::$tmp_dir . $size . '/' . $this->id) AND $size != 'original') {
			$this->resize($size);
		}

		if ($size == 'original') {
			$filename = $this->get_path();
		} else {
			$filename = Config::$tmp_dir . $size . '/' . $this->id;
		}

		return file_get_contents($filename);
	}

	/**
	 * Return the code for showing a picture imbeded inline
	 *
	 * @access public
	 * @param string $size
	 * @return string
	 */
	public function get_embed($size = 'original') {
		return sprintf("data:%s;base64,%s", $this->mime_type, base64_encode($this->get_resized_contents($size)));
	}

	/**
	 * Delete the image and its cache
	 *
	 * @access public
	 */
	public function delete() {
		foreach (Config::$resize_configurations as $name => $configuration) {
			if (file_exists(Config::$tmp_dir . $name . '/' . $this->id)) {
				unlink(Config::$tmp_dir . $name . '/' . $this->id);
			}
		}
		$db = Database::Get();
		$db->query('DELETE FROM picture WHERE file_id=?', [$this->id]);

		parent::delete();
	}

	/**
	 * Get cache size
	 *
	 * @access public
	 * @return int $size
	 */
	public function get_cache_size() {
		$size = 0;
		foreach (Config::$resize_configurations as $name => $configuration) {
			if (file_exists(Config::$tmp_dir . $name . '/' . $this->id)) {
				$stat = stat(Config::$tmp_dir . $name . '/' . $this->id);
				$file_size = $stat['blocks'] * 512;
				$size += $file_size;
			}
		}
		return $size;
	}

}
