<?php
/**
 * Picture_Manipulation class
 *
 * Manipulates pictures
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\File\Picture;

class Manipulation {

	/**
	 * Contains image resource
	 *
	 * @access private
	 * @var Resource
	 */
	private $image = '';

	/**
	 * Contains resized image resource
	 *
	 * @access private
	 * @var Resource
	 */
	private $image_resized = '';

	/**
	 * Contains mime_type
	 *
	 * @access private
	 * @var string
	 */
	private $mime_type = '';

	/**
	 * Contains width
	 *
	 * @access private
	 * @var int
	 */
	private $width;

	/**
	 * Contains height
	 *
	 * @access private
	 * @var int
	 */
	private $height;

	/**
	 * Crop information
	 *
	 * @access private
	 * @var int
	 */
	private $crop_width;
	private $crop_height;
	private $crop_offset_left;
	private $crop_offset_top;

	/**
	 * Constructor
	 *
	 * @access public
	 */
	public function __construct($file) {
		Config::set_ini_values();
		$this->load($file);
	}

	/**
	 * Load image
	 *
	 * @access private
	 * @param Picture $picture
	 */
	private function load(Picture $picture) {
		$this->width = $picture->width;
		$this->height = $picture->height;
		if (isset($picture->crop_width)) {
			$this->crop_width = $picture->crop_width;
		}
		if (isset($picture->crop_height)) {
			$this->crop_height = $picture->crop_height;
		}
		if (isset($picture->crop_offset_left)) {
			$this->crop_offset_left = $picture->crop_offset_left;
		}
		if (isset($picture->crop_offset_top)) {
			$this->crop_offset_top = $picture->crop_offset_top;
		}
		$this->mime_type = $picture->mime_type;
		$this->image = $this->open($picture->get_path());
		if ($this->image === false) {
			throw new \Exception('Is not a valid picture');
		}
		$this->image_resized = $this->open($picture->get_path());
	}

	/**
	 * Open image
	 *
	 * @access private
	 * @return Resource $img
	 */
	private function open($path) {
		switch ($this->mime_type) {
			case 'image/jpeg':
				$img = imagecreatefromjpeg($path);
				break;
			case 'image/gif':
				$img = imagecreatefromgif($path);
				break;
			case 'image/png':
				$img = imagecreatefrompng($path);
				break;
			case 'image/webp':
				$img = imagecreatefromwebp($path);
				break;
			default:
				$img = false;
		}

		// Correct orientation if necessary
		if ($this->mime_type == 'image/jpeg') {
			try {
				$exif = @exif_read_data($path);
			} catch (\Exception $e) {
				return $img;
			}

			if (!empty($exif['Orientation'])) {
		        switch ($exif['Orientation']) {
		            case 3:
		                $img = imagerotate($img, 180, 0);
		                break;

		            case 6:
		                $img = imagerotate($img, -90, 0);
		                break;

		            case 8:
		                $img = imagerotate($img, 90, 0);
		                break;
		        }
			}
		}

		return $img;
	}

	/**
	 * Output image to file
	 *
	 * @access public
	 * @param string $destination
	 * @param string $format
	 * @param int $quality
	 */
	public function output(string $destination = null, string $format = 'original', int $quality = -1) {

		if ($format === 'original') {
			$format = $this->mime_type;
		}
		if (in_array($format, [ 'original', 'image/jpg', 'image/jpeg', 'image/gif', 'image/png', 'image/webp', ]) === false) {
			throw new \Exception('Unknown format ' . $format);
		}
		switch ($format) {
			case 'image/jpeg':
				imagejpeg($this->image_resized, $destination, $quality);
				break;
			case 'image/gif':
				imagegif($this->image_resized, $destination);
				break;
			case 'image/png':
				$scale_quality = round(($quality/100) * 9);
				$invert_scale_quality = 9 - $scale_quality;
				imagepng($this->image_resized, $destination, $invert_scale_quality);
				break;
			case 'image/webp':
				$scale_quality = round(($quality/100) * 9);
				$invert_scale_quality = 9 - $scale_quality;
				imagewebp($this->image_resized, $destination, $invert_scale_quality);
				break;
			default:
				throw new \Exception("Not support format");
				break;
		}

		imagedestroy($this->image);
		imagedestroy($this->image_resized);
	}

	/**
	 * Calculate output dimensions
	 *
	 * @access private
	 * @param int $width (px)
	 * @param int $height (px)
	 * @param int $new_width (px)
	 * @param int $new_height (px)
	 * @return array $output_dimensions
	 */
	private function get_output_dimensions($new_width = null, $new_height = null, $mode = 'auto') {
		switch ($mode) {
			case 'exact':
				$output_width = $new_width;
				$output_height = $new_height;
				break;
			case 'auto':
				list($output_width, $output_height) = $this->calculate_size($new_width, $new_height);
				break;
			case 'crop':
				list($output_width, $output_height) = $this->calculate_crop_size($new_width, $new_height);
				break;
		}

		return [$output_width, $output_height];
	}

	/**
	 * Calculate output dimensions
	 *
	 * @access private
	 * @param int $new_width
	 * @param int $new_height
	 * @return array $output_dimensions
	 */
	private function calculate_size($new_width, $new_height) {
		$old_aspect_ratio = $this->width / $this->height;

		if (is_null($new_width)) {
			$new_width = round($new_height * $old_aspect_ratio);
		} elseif (is_null($new_height)){
			$new_height = round($new_width / $old_aspect_ratio);
		}

		$new_aspect_ratio = $new_width / $new_height;

		if ($new_width > $this->width AND $new_height > $this->height) {
			$output_width = $this->width;
			$output_height = $this->height;
		} elseif ($new_aspect_ratio == $old_aspect_ratio) {
			$output_width = $new_width;
			$output_height = $new_height;
		} elseif ($new_aspect_ratio < $old_aspect_ratio) {
			$output_height = round($new_width / $old_aspect_ratio);
			$output_width = $new_width;
		} elseif ($new_aspect_ratio > $old_aspect_ratio) {
			$output_width = round($new_height * $old_aspect_ratio);
			$output_height = $new_height;
		}

		return [$output_width, $output_height];
	}

	/**
	 * Calculate output dimensions for cropping
	 *
	 * @access private
	 * @param int $new_width
	 * @param int $new_height
	 * @return array $output_dimensions
	 */
	private function calculate_crop_size($new_width, $new_height) {
		$height_ratio = $this->height / $new_height;
		$width_ratio = $this->width / $new_width;

		if ($height_ratio < $width_ratio) {
			$output_ratio = $height_ratio;
		} else {
			$output_ratio = $width_ratio;
		}

		$output_height = $this->height / $output_ratio;
		$output_width = $this->width / $output_ratio;

		return [$output_width, $output_height];
	}

	/**
	 * Get dimensions
	 *
	 * @access public
	 * @param mixed $path
	 * @return array $dimensions
	 */
	public function get_dimensions($path = null) {
		if (!is_null($path)) {
			$this->path = $path;
		}

		return getimagesize($this->path);
	}

	/**
	 * Crop based on the dimensions saved in the picture table
	 *
	 * @access public
	 */
	public function precise_crop() {
		if ($this->crop_width == 0 AND $this->crop_height == 0) {
			throw new \Exception('No crop information found for this picture');
		}
		$this->image_resized = imagecrop($this->image, [ 'x' => $this->crop_offset_left, 'y' => $this->crop_offset_top, 'width' => $this->crop_width, 'height' => $this->crop_height ]);
		imagecopy($this->image, $this->image_resized, 0, 0, 0, 0, $this->width, $this->height);
	}

	/**
	 * Resize image
	 *
	 * @access public
	 * @param int $new_width (px)
	 * @param int $new_height (px)
	 * @proportional bool $proportional
	 */
	public function resize($new_width = null, $new_height = null, $mode = 'auto') {
		if (is_null($new_width) AND is_null($new_height)) {
			throw new \Exception('specifiy output dimensions');
		}

		if ($mode === 'auto' || isset($this->crop_width) === false || empty($this->crop_width)) {
			list($output_width, $output_height) = $this->get_output_dimensions($new_width, $new_height, $mode);
		} else {
			$output_width = $new_width;
			$output_height = $new_height;
		}

		$this->image_resized = imagecreatetruecolor($output_width, $output_height);

		if ($this->mime_type == 'image/gif' OR $this->mime_type == 'image/png') {
			$transparent_index = imagecolortransparent($this->image);
			if ($transparent_index >= 0) {
				$rgba = imagecolorsforindex($this->image_resized, $transparent_index);
				$transparent = imagecolorallocatealpha($this->image_resized, $rgba['red'], $rgba['green'], $rgba['blue'], 127);
				imagefill($this->image_resized, 0, 0, $transparent);
				imagecolortransparent($this->image_resized, $transparent);
			} else {
				imagealphablending($this->image_resized, false);
				imagesavealpha($this->image_resized,true);
				$transparent = imagecolorallocatealpha($this->image_resized, 255, 255, 255, 127);
				imagefilledrectangle($this->image_resized, 0, 0, $output_width, $output_height, $transparent);
			}
		}

		if (isset($this->crop_width) === false || empty($this->crop_width)) {
			imagecopyresampled($this->image_resized, $this->image, 0, 0, 0, 0, $output_width, $output_height, $this->width, $this->height);
		} else {
			imagecopyresampled($this->image_resized, $this->image, 0, 0, 0, 0, $output_width, $output_height, $this->crop_width, $this->crop_height);
		}
	}

	/**
	 * Crop image
	 *
	 * @access private
	 * @param int $output_width (px)
	 * @param int $output_height (px)
	 * @param int $new_width (px)
	 * @param int $new_height (px)
	 */
	private function crop($output_width, $output_height, $new_width, $new_height) {
		$offset_x = ($output_width / 2) - ($new_width / 2);
		$offset_y = ($output_height / 2) - ($new_height / 2);

		$crop = $this->image_resized;
		$this->image_resized = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($this->image_resized, $crop , 0, 0, $offset_x, $offset_y, $output_width, $output_height , $output_width, $output_height);
	}
}
