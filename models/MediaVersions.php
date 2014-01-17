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

class MediaVersions extends \cms_core\models\Base {

	use \cms_media\models\ChecksumTrait;
	use \cms_media\models\UrlTrait;
	use \cms_media\models\DownloadTrait;
	use \cms_media\models\SchemeTrait;

	public $belongsTo = ['Media'];

	protected $_actsAs = [
		'cms_core\extensions\data\behavior\Timestamp'
	];

	protected static $_instructions = [];

	public static function generateTargetUrl($source, $version) {
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

	// Registers the assembly instructions for a specific media type and version.
	public static function registerAssembly($type, $versions, $instructions) {
		static::$_instructions[$type][$version] = $instructions;
	}

	// Returns the assembly instructions for a specific media type and version.
	public static function assembly($type, $version = null) {
		return $version ? static::$_instructions[$type][$version] : static::$_instructions[$type];
	}

	// Will (re-)generate version from source and return target path on success.
	public function make($entity) {
		$handler = static::$_schemes[$entity->scheme()];

		if (!$handler) {
			throw new Exception('Unhandled make for entity with scheme `' . $entity->scheme() . '`');
		}
		return $handler($entity);
	}
}

MediaVersions::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if (!$entity->modified('url')) {
		return $chain->next($self, $params, $chain);
	}
	if ($entity->can('checksum')) {
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