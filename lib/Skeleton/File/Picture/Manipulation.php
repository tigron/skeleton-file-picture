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
	 * The picture this manipulation works on
	 *
	 * @access private
	 * @var Picture $picture
	 */
	private Picture $picture;

	/**
	 * Constructor
	 *
	 * @access public
	 * @param Picture $picture
	 */
	public function __construct(Picture $picture) {
		Config::set_ini_values();
		$this->picture = $picture;
		$this->load($picture);
	}

	/**
	 * Resize the picture to a configured size and write it to the cache
	 *
	 * Applies the resize configuration with the given name: crops the
	 * picture when the saved crop information or the configuration
	 * requires it, resizes it to the configured dimensions and writes
	 * it to the cache directory in the configured format
	 *
	 * @access public
	 * @param string $size
	 * @return void
	 */
	public function resize_to_configuration(string $size): void {
		// for backwards compatibility
		if (Config::$tmp_dir !== null) {
			Config::$tmp_path = Config::$tmp_dir;
		}

		// create cache directory if it doesn't exist
		if (file_exists(Config::$tmp_path . $size . '/') === false) {
			mkdir(Config::$tmp_path . $size . '/', 0755, true);
		}

		// get configuration
		$configuration = Config::get_configuration($size);
		$format = 'original';
		if (isset($configuration['format'])) {
			$format = $configuration['format'];
		}
		$mode = 'auto';
		if (isset($configuration['mode'])) {
			$mode = $configuration['mode'];
		}
		if (isset($configuration['crop']) && empty($configuration['crop']) === false) {
			$mode = 'crop';
		}

		// shall it be cropped ?
		if ($size === 'crop' || $size === 'cropped' || $mode === 'crop') {
			if (empty($this->crop_width) === false && empty($this->crop_height)) {
				$this->precise_crop();
			}
		}

		// shall it be resized ?
		if (isset($configuration['width'])) {
			$this->resize($configuration['width'], $configuration['height'] ?? null, $mode);
		}

		$this->output(Config::$tmp_path . $size . '/' . $this->picture->id, $format);
	}

	/**
	 * Convert the picture to another format directly in the filestore
	 *
	 * Re-encodes the loaded picture in the given format and updates
	 * the name, mime type and size of the picture accordingly
	 *
	 * @access public
	 * @param string $type
	 * 	Available types: jpg, png, gif, webp
	 * @return void
	 */
	public function convert(string $type): void {
		$mime_type = $this->get_mime_type_for_format($type);
		$extensions = [
			'image/jpeg' => 'jpg',
			'image/gif' => 'gif',
			'image/png' => 'png',
			'image/webp' => 'webp',
		];
		$extension = $extensions[$mime_type];

		$pathinfo = pathinfo($this->picture->name);

		// Make sure we have a filename
		if (isset($pathinfo['filename']) === false) {
			$pathinfo['filename'] = $this->picture->name;
		}

		$this->picture->mime_type = $mime_type;
		$this->picture->name = $pathinfo['filename'] . '.' . $extension;

		$this->write($this->image, $this->picture->get_path(), $mime_type, -1);

		$this->picture->size = filesize($this->picture->get_path());
		$this->picture->save();

		imagedestroy($this->image);
		imagedestroy($this->image_resized);
	}

	/**
	 * Output image to file
	 *
	 * @access public
	 * @param string|null $destination
	 * @param string $format
	 * @param int $quality
	 * @return void
	 */
	public function output(?string $destination = null, string $format = 'original', int $quality = -1): void {
		$mime_type = $this->get_mime_type_for_format($format);
		$this->write($this->image_resized, $destination, $mime_type, $quality);

		imagedestroy($this->image);
		imagedestroy($this->image_resized);
	}

	/**
	 * Crop based on the dimensions saved in the picture table
	 *
	 * @access public
	 * @return void
	 */
	public function precise_crop(): void {
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
	 * @param string $mode
	 */
	public function resize(?int $new_width = null, ?int $new_height = null, string $mode = 'auto'): void {
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
	 * Load image
	 *
	 * @access private
	 * @param Picture $picture
	 */
	private function load(Picture $picture): void {
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
	private function open(string $path) {
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
	 * Write an image to a destination in the given format
	 *
	 * @access private
	 * @param \GdImage $image
	 * @param string|null $destination
	 * @param string $mime_type
	 * @param int $quality
	 * @return void
	 */
	private function write($image, ?string $destination, string $mime_type, int $quality): void {
		switch ($mime_type) {
			case 'image/jpeg':
				imagejpeg($image, $destination, $quality);
				break;
			case 'image/gif':
				imagegif($image, $destination);
				break;
			case 'image/png':
				$scale_quality = (int)round(($quality / 100) * 9);
				$invert_scale_quality = 9 - $scale_quality;
				imagepng($image, $destination, $invert_scale_quality);
				break;
			case 'image/webp':
				imagewebp($image, $destination, $quality);
				break;
			default:
				throw new \Exception('Unsupported format ' . $mime_type);
		}
	}

	/**
	 * Resolve the mime type for a given format
	 *
	 * Accepts 'original', the short type names (jpg, jpeg, png, gif,
	 * webp) and mime types
	 *
	 * @access private
	 * @param string $format
	 * @return string $mime_type
	 */
	private function get_mime_type_for_format(string $format): string {
		$supported_formats = [
			'original' => $this->mime_type,
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'image/jpg' => 'image/jpeg',
			'image/jpeg' => 'image/jpeg',
			'gif' => 'image/gif',
			'image/gif' => 'image/gif',
			'png' => 'image/png',
			'image/png' => 'image/png',
			'webp' => 'image/webp',
			'image/webp' => 'image/webp',
		];

		if (isset($supported_formats[$format]) === false) {
			throw new \Exception('Unsupported format "' . $format . '". Available formats: jpg/jpeg/png/gif/webp');
		}

		return $supported_formats[$format];
	}

	/**
	 * Calculate output dimensions
	 *
	 * @access private
	 * @param int $new_width (px)
	 * @param int $new_height (px)
	 * @return array $output_dimensions
	 */
	private function get_output_dimensions(?int $new_width = null, ?int $new_height = null, string $mode = 'auto'): array {
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
	private function calculate_size(int $new_width, int $new_height): array {
		$old_aspect_ratio = $this->width / $this->height;

		if (is_null($new_width)) {
			$new_width = round($new_height * $old_aspect_ratio);
		} elseif (is_null($new_height)){
			$new_height = round($new_width / $old_aspect_ratio);
		}

		$new_aspect_ratio = round($new_width / $new_height);

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
	private function calculate_crop_size(int $new_width, int $new_height): array {
		$height_ratio = $this->height / $new_height;
		$width_ratio = $this->width / $new_width;

		if ($height_ratio < $width_ratio) {
			$output_ratio = $height_ratio;
		} else {
			$output_ratio = $width_ratio;
		}

		$output_height = round($this->height / $output_ratio);
		$output_width = round($this->width / $output_ratio);

		return [$output_width, $output_height];
	}

	/**
	 * Crop image
	 *
	 * @access private
	 * @param int $output_width (px)
	 * @param int $output_height (px)
	 * @param int $new_width (px)
	 * @param int $new_height (px)
	 * @return void
	 */
	private function crop(int $output_width, int $output_height, int $new_width, int $new_height): void {
		$offset_x = ($output_width / 2) - ($new_width / 2);
		$offset_y = ($output_height / 2) - ($new_height / 2);

		$crop = $this->image_resized;
		$this->image_resized = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($this->image_resized, $crop , 0, 0, $offset_x, $offset_y, $output_width, $output_height , $output_width, $output_height);
	}
}
