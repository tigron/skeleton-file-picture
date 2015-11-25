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
	 * @var string $tmp_directory
	 */
	public static $tmp_dir = null;

	/**
	 * Resize configuration
	 *
	 * @access private
	 * @var array $resize_configuration
	 */
	public static $resize_configurations = [];

	/**
	 * Add Resize Configuration
	 *
	 * Add a configuration for resizing pictures
	 *
	 * @access public
	 * @param string $name
	 * @param int $height
	 * @param int $width
	 * @param string $mode
	 */
	public static function add_resize_configuration($name, $height, $width, $mode = 'auto') {
		$configuration = [
			'height' => $height,
			'width' => $width,
			'mode' => $mode
		];
		self::$resize_configurations[$name] = $configuration;
	}

	/**
	 * Get resize configuration
	 *
	 * @access public
	 * @param string $name
	 * @return array $configuration
	 */
	public static function get_resize_configuration($name) {
		if (!isset(self::$resize_configurations[$name])) {
			throw new \Exception('Resize configuration ' . $name . ' not found');
		}
		return self::$resize_configurations[$name];
	}
}
