# skeleton-file-picture

## Description

This library can resize images. The image must be of type \Skeleton\File\File

## Installation

Installation via composer:

    composer require tigron/skeleton-file-picture

Create a new table in your database:

	CREATE TABLE IF NOT EXISTS `picture` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `file_id` int(11) NOT NULL,
	  `width` int(11) NOT NULL,
	  `height` int(11) NOT NULL,
	  PRIMARY KEY (`id`),
	  KEY `file_id` (`file_id`)
	) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

## Howto

Check the \Skeleton\File README to initialize the File Store

Initialize the picture library:

	/**
	 * This function adds a picture resize configuration
	 *
	 * $name => A friendly name that can be reused later
	 * $height => height in px
	 * $width => width in px
	 * $mode => exact/crop/auto
	 *		exact => The $height/$width will be used, ratio is ignored
	 *		crop => The image is cropped in in order to fill the $height/$width frame
	 *		auto => The image is resized to fit the $height/$width frame, ratio is kept
	 */
	\Skeleton\File\Picture\Config::add_resize_configuration($name, $height, $width, $mode);

	/**
	 * Set the cache path
	 * This is default set to the system TMP path
     *
     * \Skeleton\File\Picture\Config::$tmp_dir is deprecated
	 */
	\Skeleton\File\Picture\Config::$tmp_path = $your_very_temporary_path

Show a resized picture:

    $picture = Picture::get_by_id(1);
    $picture->resize($configuration_name);
