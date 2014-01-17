<?php
/**
 * Bureau Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace cms_media\models;

use \Mime_Type;
use \Media_Process;
use lithium\analysis\Logger;
use temporary\Manager as Temporary;
use lithium\core\Libraries;
use lithium\core\Environment;

class MediaVersions extends \lithium\data\Model {

	use \cms_media\models\ChecksumTrait;
	use \cms_media\models\UrlTrait;
	use \li3_behaviors\data\model\Behaviors;

	public $belongsTo = ['Media'];


	protected $_actsAs = [
		'cms_core\extensions\data\behavior\Timestamp'
	];

	public static function base($scheme) {
		return static::$_schemes[$scheme]['base'];
	}

	protected static function _generateTargetUrl($source, $version) {
		$base = static::base('file') . '/' . $version;
		$instructions = static::_instructions(Mime_Type::guessName($source), $version);

		if (isset($instructions['clone'])) {
			// Guess from source filename or contents.
			$extension = Mime_Type::guessExtension($source);
		} else {
			// Instead of re-using the extension from source we have to take
			// the target extension into account as the target maybe converted;
			// we guess from the MIME type as this is fastest.
			$extension = Mime_Type::guessExtension($instructions['convert']);
		}
		return static::_uniqueUrl($base, $extension, ['exists' => true]);
	}

	protected static $_schemes = [];

	public static function registerScheme($scheme, array $options = []) {
		static::$_schemes[$scheme] = $options + [
			'base' => false,
			'make' => false,
			'delete' => false,
			'checksum' => false,
			'relative' => false
		];
	}

	public function can($entity, $capability) {
		return static::$_schemes[$entity->scheme()][$capability];
	}

	// Will (re-)generate version from source and return target path on success.
	public function make($entity) {
		$handler = static::$_schemes[$entity->scheme()];

		if (!$handler) {
			// Just pass through.
			return $entity->url;
			// throw new Exception('Unhandled make for entity with scheme `' . $entity->scheme() . '`');
		}
		return $handler($entity);
	}

	public static function hasInstructions($type, $version) {
		return (boolean) static::_instructions($type, $version);
	}

	// Returns the assembly instructions for a specific media type and version.
	protected static function _instructions($type, $version) {
		return Environment::get("mediaVersions.instructions.{$type}.{$version}") ?: false;
	}
}

MediaVersions::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if (!$entity->modified('url')) {
		return $chain->next($self, $params, $chain);
	}
	if (parse_url($entity->url, PHP_URL_SCHEME) == 'file') {
		$entity->checksum = $entity->calculateChecksum();
	}
	$entity->type      = Mime_Type::guessName($entity->url);
	$entity->mime_type = Mime_Type::guessType($entity->url);

	return $chain->next($self, $params, $chain);
});

// Filter running before saving; order matters.
// Make URL relative before saving.
MediaVersions::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if ($entity->modified('url') && $entity->can('relative')) {
		$entity->url = MediaVersions::relativeUrl($entity->url);
	}
	return $chain->next($self, $params, $chain);
});

MediaVersions::applyFilter('delete', function($self, $params, $chain) {
	$entity = $params['entity'];

	if ($entity->can('delete')) {
		Logger::debug("Deleting corresponding URL `{$entity->url}` of media version.");
		$entity->deleteUrl();
	}
	return $chain->next($self, $params, $chain);
});


?>