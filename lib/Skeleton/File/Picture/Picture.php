<?php
/**
 * Picture class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 * @author David Vandemaele <david@tigron.be>
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
	 * Validates the given input before insertion
	 *
	 * @access public
	 * @param ?array<mixed> The array that will contain the errors encountered. Passed by reference.
	 */
	public function validate(&$errors = null) {
		$this->get_dimensions();

		// some image formats require less bytes per pixel, but we're taking 5
		// as a safe maximum; libgd seems to use int32 as PHP_INT_MAX regardless
		// of the platform
		if (($this->width * $this->height * 5) > 2147483647) {
			$errors['width'] = 'image dimensions not supported by libgd ';
			$errors['height'] = 'image dimensions not supported by libgd ';
			return false;
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

		if (Config::$tmp_dir !== null) {
			Config::$tmp_path = Config::$tmp_dir;
		}

		$filename = Config::$tmp_path . 'cropped/' . $this->id;
		if (file_exists($filename)) {
			unlink($filename);
		}
	}

	/**
	 * Output the picture to the browser
	 *
	 * @access public
	 * @param string $name
	 */
	public function show($name = null) {
		// for backwards compatibility
		if (Config::$tmp_dir !== null) {
			Config::$tmp_path = Config::$tmp_dir;
		}

		// shall it be resized or cropped or converted
		if ($name !== null && file_exists(Config::$tmp_path . $name . '/' . $this->id) === false) {
			$this->resize($name);
		}

		// getting configuration if needed
		$configuration = null;
		if ($name !== null && $name !== 'crop' && $name !== 'cropped') {
			$configuration = Config::get_configuration($name);
		}

		// preparing for output
		$mime_type = $this->mime_type;
		if ($name === null) {
			$filename = $this->get_path();
		} else {
			$filename = Config::$tmp_path . $name . '/' . $this->id;
			if (isset($configuration['format'])) {
				$mime_type = $configuration['format'];
			}
		}

		// generating output
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
		header('Content-Type: ' . $mime_type);
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
		if (Config::$tmp_dir !== null) {
			Config::$tmp_path = Config::$tmp_dir;
		}

		if (!file_exists(Config::$tmp_path . $size . '/' . $this->id) AND $size != 'original') {
			$this->resize($size);
		}

		if ($size == 'original') {
			$filename = $this->get_path();
		} else {
			$filename = Config::$tmp_path . $size . '/' . $this->id;
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
		if (Config::$tmp_dir !== null) {
			Config::$tmp_path = Config::$tmp_dir;
		}

		foreach (Config::$resize_configurations as $name => $configuration) {
			if (file_exists(Config::$tmp_path . $name . '/' . $this->id)) {
				unlink(Config::$tmp_path . $name . '/' . $this->id);
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
		if (Config::$tmp_dir !== null) {
			Config::$tmp_path = Config::$tmp_dir;
		}

		$size = 0;
		foreach (Config::$resize_configurations as $name => $configuration) {
			if (file_exists(Config::$tmp_path . $name . '/' . $this->id)) {
				$stat = stat(Config::$tmp_path . $name . '/' . $this->id);
				$file_size = $stat['blocks'] * 512;
				$size += $file_size;
			}
		}
		return $size;
	}

	/**
	 * Convert the picture to another format directly in the filestore
	 * (not for display only)
	 *
	 * @access public
	 * @param string $type
	 * 	Available types: jpg, png, gif, webp
	 */
	public function convert(string $type): void {
		$manipulation = new Manipulation($this);
		$manipulation->convert($type);
	}

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
	 * Get the dimensions of the picture
	 *
	 * @access private
	 */
	private function get_dimensions() {
		if (isset($this->width) AND isset($this->height)) {
			return;
		}

		$path = $this->get_path();

		if ($this->mime_type == 'image/webp' && version_compare(PHP_VERSION, '7.1.0') < 0) {
			$resource = imagecreatefromwebp($path);
			$this->width = imagesx($resource);
			$this->height = imagesy($resource);
		} else {
			list($width, $height) = getimagesize($path);
			$this->width = $width;
			$this->height = $height;
		}
	}

	/**
	 * Resize the picture
	 *
	 * Creates a resized version of this picture by applying the resize
	 * configuration with the given name
	 *
	 * @access private
	 * @param string $size
	 */
	private function resize(string $size): void {
		$manipulation = new Manipulation($this);
		$manipulation->resize_to_configuration($size);
	}
}
