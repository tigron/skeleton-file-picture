<?php
/**
 * Config class
 * Configuration for Skeleton\File\Picture
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace Skeleton\File\Picture;

class Config {

	/**
	 * TMP directory
	 *
	 * This folder will be used to create a cache for resized pictures
	 *
	 * @access public
	 * @deprecated Use tmp_path instead
	 * @var string $tmp_dir
	 */
	public static $tmp_dir = null;

	/**
	 * Tmp path
	 *
	 * This folder will be used to create a cache for resized pictures
	 *
	 * @access public
	 * @var string $tmp_path
	 */
	public static $tmp_path = '/tmp';

	/**
	 * Resize configuration
	 *
	 * @access private
	 * @var array $resize_configuration
	 */
	public static $resize_configurations = [];

	/**
	 * Picture interface class
	 *
	 * This class will provide the Picture functionality, by default a class is defined
	 */
	public static $picture_interface = '\Skeleton\File\Picture\Picture';

	/**
	 * Add Resize Configuration
	 *
	 * Add a configuration for resizing pictures
	 *
	 * @access public
	 * @param string $name
	 * @param int $width
	 * @param int $height
	 * @param string $mode
	 */
	public static function add_resize_configuration($name, $width, $height, $mode = 'auto') {
		$params = [
			'name' => $name,
			'width' => $width,
			'height' => $height,
			'mode' => $mode
		];
		self::add_configuration($params);
	}

	/**
	 * Add Configuration
	 *
	 * @access public
	 * @param array $params
	 * [ 'name' => 'config_name', 'width' => 'resize_width', 'height' => 'resize_height', ]
	 */
	public static function add_configuration($params) {
		$configuration = [];

		// checking name
		if (isset($params['name']) === false) {
			throw new \Exception("Parameter 'name' is missing");
		}
		$configuration['name'] = $params['name'];

		// checking resize
		if (isset($params['width']) === false && isset($params['height']) === false && isset($params['format']) === false) {
			throw new \Exception("A resize of a conversion have to be configured");
		}
		if ((isset($params['width']) && isset($params['height']) === false) || (isset($params['width']) === false && isset($params['height']))) {
			throw new \Exception("Parameters 'width' and 'height' are complementary");
		}
		if (isset($params['width']) && isset($params['height'])) {
			$configuration['width'] = $params['width'];
			$configuration['height'] = $params['height'];
		}

		// checking mode
		if (isset($params['mode']) && in_array($params['mode'], [ 'auto', 'crop' ])) {
			$configuration['mode'] = $params['mode'];
		} else {
			$configuration['mode'] = 'auto';
		}

		// checking format
		if (isset($params['format']) && in_array($params['format'], [ 'image/jpg', 'image/jpeg', 'image/gif', 'image/png', 'image/webp', ])) {
			$configuration['format'] = $params['format'];
		}

		// storing configuration
		self::$resize_configurations[$configuration['name']] = $configuration;
	}

	/**
	 * Get configuration
	 *
	 * @access public
	 * @param string $name
	 * @return array $configuration
	 */
	public static function get_configuration($name) {
		if (!isset(self::$resize_configurations[$name])) {
			throw new \Exception('Resize configuration ' . $name . ' not found');
		}
		return self::$resize_configurations[$name];
	}

	/**
	 * Set some PHP ini values
	 *
	 * @access public
	 */
	public static function set_ini_values() {
		// This will become the default setting from PHP 7.1 onwards
		ini_set('gd.jpeg_ignore_warning', 1);
	}
}
