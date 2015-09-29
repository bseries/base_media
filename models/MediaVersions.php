<?php
/**
 * Base Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * Licensed under the AD General Software License v1.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *
 * You should have received a copy of the AD General Software
 * License. If not, see http://atelierdisko.de/licenses.
 */

namespace base_media\models;

use Exception;
use OutOfBoundsException;
use lithium\analysis\Logger;
use lithium\storage\Cache;
use mm\Mime\Type;

class MediaVersions extends \base_core\models\Base {

	use \base_media\models\ChecksumTrait;
	use \base_media\models\UrlTrait;
	use \base_media\models\DownloadTrait;
	use \base_media\models\SchemeTrait;
	use \base_media\models\MediaInfoTrait;

	public $belongsTo = ['Media'];

	protected $_actsAs = [
		'base_core\extensions\data\behavior\Timestamp'
	];

	protected static $_instructions = [];

	public static $enum = [
		'status' => ['unknown', 'processing', 'processed', 'error']
	];

	public function media($entity) {
		return Media::find('first', ['conditions' => ['id' => $entity->media_id]]);
	}

	public static function generateTargetUrl($source, $version) {
		$base = static::base('file') . '/' . $version;
		$instructions = static::assembly($source, $version);

		if (isset($instructions['clone'])) {
			// Guess from source filename or contents.
			$extension = Type::guessExtension($source);
		} else {
			// Instead of re-using the extension from source we have to take
			// the target extension into account as the target maybe converted;
			// we guess from the MIME type as this is fastest.
			$extension = Type::guessExtension($instructions['convert']);
		}
		return static::_uniqueUrl($base, $extension, ['exists' => true]);
	}

	// Registers the assembly instructions for a specific media type and version.
	public static function registerAssembly($type, $version, $instructions) {
		static::$_instructions[$type][$version] = $instructions;
	}

	// Returns the assembly instructions for a specific media entity URL (its type) and a version.
	public static function assembly($source, $version) {
		$type = Type::guessName($source);

		if (!isset(static::$_instructions[$type])) {
			return false;
		}
		if (!isset(static::$_instructions[$type][$version])) {
			return false;
		}
		$assembly = static::$_instructions[$type][$version];
		return is_callable($assembly) ? $assembly($source) : $assembly;
	}

	// Returns all available versions.
	public static function assemblyVersions() {
		$versions = [];
		foreach (static::$_instructions as $type => $item) {
			$versions = array_merge($versions, array_keys($item));
		}
		$versions = array_unique($versions);
		sort($versions);

		return $versions;
	}

	// Will (re-)generate version from source and return target path on success.
	// Will be called from the cute handler. Registered in `config/media.php`.
	public static function make($mediaId, $version) {
		$parent = Media::find('first', ['conditions' => ['id' => $mediaId]]);

		if (!$parent) {
			Logger::debug("Parent Media `{$mediaId}` seems gone.");
			return false;
		}

		if ($parent->can('relative')) {
			// Make all URLs absolute if not already absolute. File URLs
			// come in in relative form.
			$parent->url = Media::absoluteUrl($parent->url);
		}

		$entity = static::create([
			'media_id' => $parent->id,
			// Uses the parent's url as the version's source. Also allows us
			// to call url methods like `scheme()` on us.
			'url' => $parent->url,
			'version' => $version,
			'status' => 'processing'
			// Versions don't have an user id as their records are
			// already associated with a media_file record an thus
			// indirectly carry an user id.
		]);
		Logger::debug("Trying to make version `{$entity->version}` of `{$entity->url}`.");

		if (!$handler = static::$_schemes[$entity->scheme()]['make']) {
			throw new Exception('Unhandled make for entity with scheme `' . $entity->scheme() . '`');
		}

		try {
			$result = $handler($entity);
		} catch (Exception $e) {
			$message  = "Failed making version `{$entity->version}` of `{$entity->url}` with:";
			$message .= $e->getMessage();
			Logger::notice($message);

			$entity->status = 'error';
			$entity->url = null;
			$entity->save();

			return false;
		}
		if ($result === false) {
			$message = "Failed making version `{$entity->version}` of `{$entity->url}`.";
			Logger::notice($message);

			$entity->status = 'error';
			$entity->url = null;
			$entity->save();

			return false;
		}
		if ($result === null) {
			$message = "Skipping making version `{$entity->version}` of `{$entity->url}`.";
			Logger::debug($message);

			return true;
		}
		$message = "Made version `{$entity->version}` of `{$entity->url}` target is `{$result}`.";
		Logger::debug($message);

		$entity->url = $result;
		$entity->status = 'processed';

		Cache::delete('default', 'media_versions_' . md5($mediaId));
		return $entity->save();
	}

	public function updateStatus($entity, $status) {
		return $entity->save(compact('status'), ['whitelist' => ['status']]);
	}
}

MediaVersions::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];
	$whitelist = $params['options']['whitelist'];

	if ($whitelist && !in_array('url', (array) $whitelist)) {
		return $chain->next($self, $params, $chain);
	}
	if (!$entity->url || (!$entity->modified('url') && $entity->exists())) {
		return $chain->next($self, $params, $chain);
	}
	if ($entity->can('checksum')) {
		$entity->checksum = $entity->calculateChecksum();
	}
	$entity->type      = Type::guessName($entity->url);
	$entity->mime_type = Type::guessType($entity->url);

	return $chain->next($self, $params, $chain);
});

// Filter running before saving; order matters.
// Make URL relative before saving.
MediaVersions::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];
	$whitelist = $params['options']['whitelist'];

	if ($whitelist && !in_array('url', (array) $whitelist)) {
		return $chain->next($self, $params, $chain);
	}
	if ($entity->url && $entity->modified('url') && $entity->can('relative')) {
		$entity->url = MediaVersions::relativeUrl($entity->url);
	}
	return $chain->next($self, $params, $chain);
});

MediaVersions::applyFilter('delete', function($self, $params, $chain) {
	$entity = $params['entity'];

	if ($entity->url && $entity->can('delete')) {
		Logger::debug("Deleting corresponding URL `{$entity->url}` of media version.");
		$entity->deleteUrl();
	}
	return $chain->next($self, $params, $chain);
});


?>