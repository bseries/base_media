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
use lithium\aop\Filters;
use lithium\storage\Cache;
use mm\Mime\Type;

class MediaVersions extends \base_core\models\Base {

	use \base_core\models\SchemeTrait;
	use \base_core\models\UrlTrait;
	use \base_core\models\UrlChecksumTrait;
	use \base_core\models\UrlDownloadTrait;
	use \base_media\models\MediaInfoTrait;

	public $belongsTo = ['Media'];

	protected $_actsAs = [
		'base_core\extensions\data\behavior\Timestamp'
	];

	public static $enum = [
		'status' => ['unknown', 'processing', 'processed', 'error']
	];

	protected static $_instructions = [];

	protected static $_defaultScheme = [
		'base' => false,
		'relative' => false,
		'delete' => false,
		'download' => false,
		'transfer' => false,
		'checksum' => false,
		'mime_type' => null,
		'type' => null
	];

	public function media($entity) {
		return Media::find('first', ['conditions' => ['id' => $entity->media_id]]);
	}

	public static function generateTargetUrl($source, $version, array $instructions) {
		$base = static::base('file') . '/' . $version;

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
		if (in_array($source, ['image', 'video', 'audio', 'document', 'generic'])) {
			$type = $source;
		} else {
			$type = Type::guessName($source);
		}


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

		// When make of one version fails, fail the whole media. Otherwise must check
		// before using a version which leads to more checks!
		try {
			$result = $handler($entity);
		} catch (Exception $e) {
			$message  = "Failed make of version `{$entity->version}` of `{$entity->url}` with: ";
			$message .= $e->getMessage();
			Logger::notice($message);

			return false;
		}
		if ($result === false) {
			$message = "Failed make of version `{$entity->version}` of `{$entity->url}`.";
			Logger::notice($message);

			return false;
		}
		if ($result === null) {
			$message = "Skipping make of version `{$entity->version}` of `{$entity->url}`.";
			Logger::debug($message);

			return true;
		}
		$message = "Made version `{$entity->version}` of `{$entity->url}` target is `{$result}`.";
		Logger::debug($message);

		$entity->url = $result;
		$entity->status = 'processed';

		Cache::delete('default', 'media_' . $mediaId . '_versions');
		return $entity->save();
	}

	public function updateStatus($entity, $status) {
		Cache::delete('default', 'media_' . $entity->media_id . '_versions');
		return $entity->save(compact('status'), ['whitelist' => ['status']]);
	}
}

Filters::apply(MediaVersions::class, 'save', function($params, $next) {
	$entity = $params['entity'];
	$whitelist = $params['options']['whitelist'];

	if ($whitelist && !in_array('url', (array) $whitelist)) {
		return $next($params);
	}
	if (!$entity->url || (!$entity->modified('url') && $entity->exists())) {
		return $next($params);
	}
	if ($entity->can('checksum')) {
		$entity->checksum = $entity->calculateChecksum();
	}
	$entity->type      = Type::guessName($entity->url);
	$entity->mime_type = Type::guessType($entity->url);

	return $next($params);
});

// Filter running before saving; order matters.
// Make URL relative before saving.
Filters::apply(MediaVersions::class, 'save', function($params, $next) {
	$entity = $params['entity'];
	$whitelist = $params['options']['whitelist'];

	if ($whitelist && !in_array('url', (array) $whitelist)) {
		return $next($params);
	}
	if ($entity->url && $entity->modified('url') && $entity->can('relative')) {
		$entity->url = MediaVersions::relativeUrl($entity->url);
	}
	return $next($params);
});

Filters::apply(MediaVersions::class, 'delete', function($params, $next) {
	$entity = $params['entity'];

	if ($entity->url && $entity->can('delete')) {
		Logger::debug("Deleting corresponding URL `{$entity->url}` of media version.");
		$entity->deleteUrl();
	}
	return $next($params);
});

?>