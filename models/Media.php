<?php
/**
 * Bureau Media
 *
 * Copyright (c) 2013-2014 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace cms_media\models;

use Exception;
use mm\Mime\Type;
use cms_media\models\MediaVersions;
use cms_media\models\MediaAttachments;
use lithium\analysis\Logger;
use Cute\Job;
use Cute\Connection;

class Media extends \cms_core\models\Base {

	use \cms_media\models\ChecksumTrait;
	use \cms_media\models\UrlTrait;
	use \cms_media\models\DownloadTrait;
	use \cms_media\models\SchemeTrait;
	use \cms_media\models\MediaInfoTrait;

	public $hasMany = ['MediaVersions'];

	protected static $_actsAs = [
		'cms_core\extensions\data\behavior\Timestamp'
	];

	protected $_cachedVersions = [];

	protected static $_dependent = [];

	protected static $_cuteConnection;

	public static function init() {
		static::$_cuteConnection = new Connection([
			'scope' => PROJECT_NAME
		]);
	}

	// @fixme Make this part of higher Media/settings abstratiction.
	public static function registerDependent($model, array $bindings) {
		static::$_dependent[$model] = $bindings;
	}

	// Finds out which other records depend on a given media entity.
	// Type can either be count or all.
	public function depend($entity, $type) {
		$depend = $type === 'count' ? 0 : [];

		foreach (static::$_dependent as $model => $bindings) {
			foreach ($bindings as $alias => $binding) {
				if ($binding === 'direct') {
					$results = $model::find($type, [
						'conditions' => [
							$alias . '_media_id' => $entity->id
						]
					]);

					if ($type === 'count') {
						$depend += $results;
					} else {
						foreach ($results as $result) {
							$depend[] = $result;
						}
					}
				} else {
					$results = MediaAttachments::find($type, [
						'conditions' => [
							'model' => $model,
							'media_id' => $entity->id
						]
					]);
					if ($type === 'count') {
						$depend += $results;
					} else {
						foreach ($results as $result) {
							$depend[] = $result->medium();
						}
					}
				}
			}
		}
		return $depend;
	}

	public function version($entity, $version) {
		if (isset($this->_cachedVersions[$entity->id][$version])) {
			return $this->_cachedVersions[$entity->id][$version];
		}
		return $this->_cachedVersions[$entity->id][$version] = MediaVersions::first([
			'conditions' => [
				'media_id' => $entity->id,
				'version' => $version
			]
		]);
	}

	public function versions($entity) {
		if (isset($this->_cachedVersions[$entity->id])) {
			return $this->_cachedVersions[$entity->id];
		}
		$data = MediaVersions::all([
			'conditions' => [
				'media_id' => $entity->id
			]
		]);
		$results = [];
		foreach ($data as $item) {
			$results[$item->version] = $item;
		}
		return $this->_cachedVersions[$entity->id] = $results;
	}

	public function makeVersions($entity) {
		if (!$entity->type) {
			throw new Exception('Entity has no type.');
		}
		if (!$entity->url) {
			throw new Exception('Entity has no URL.');
		}

		// Make all URLs absolute if not already absolute. File URLs
		// come in here in relative form.
		if ($entity->can('relative')) {
			$entity->url = static::absoluteUrl($entity->url);
		}

		// Fetch versions we need to make. We're assembling all
		// possible version strings as we don't know if a certain
		// version applies for an entity. This decicison is made late
		// in the scheme make handler.
		foreach (MediaVersions::assemblyVersions() as $version) {
			// Insert possible version into database, even if this will
			// never be "made". Helpers should consider a version record
			// as non-existent if it doesn't have an url.
			$version = MediaVersions::create([
				'media_id' => $entity->id,
				'url' => null, // Will be set once version is made.
				'version' => $version
				// Versions don't have an user id as their records are
				// already associated with a media_file record an thus
				// indirectly carry an user id.
			]);
			if (!$version->save()) {
				return false;
			}
			$isFix = strpos($version->version, 'fix') !== false;

			$job = new Job(static::$_cuteConnection, [
				// Allow this to be connection less.
				'fallback' => true,

				// Separate queues for fix and flux.
				'queue' => $isFix ? 'fix' : 'flux',

				// Make thumbnails avaialble once we return from here.
				'wait' => strpos($version->version, 'fix3') !== false,

				// Videos need much more time to transcode (max 1h).
				'ttr' => $isFix ? 60 * 5 : 60 * 60
			]);

			if (!$job->run('MediaVersions::make', $version->id)) {
				$message  = "Failed enqueuing `MediaVersions::make`";
				$message .= " job for media version id `{$version->id}`.";
				Logger::notice($message);

				return false;
			}
		}
		return true;
	}

	public function deleteVersions($entity) {
		foreach ($entity->versions() as $version) {
			if (!$version->delete()) {
				return false;
			}
		}
		return true;
	}

	// Tranfers a source to target - aka make the source local and
	// copy it into our "library" space. May use streams where appropriate.
	public function transfer($entity) {
		Logger::debug("Transferring from source `{$entity->url}`.");
		$target = static::generateTargetUrl($entity->url);

		if (!is_dir(dirname($target))) {
			mkdir(dirname($target), 0777, true);
		}
		if (!$result = copy($entity->url, $target)) {
			throw new Exception('Could not copy from source to target.');
		}
		Logger::debug("Transferred to target `{$target}`.");
		return $target;
	}

	// Works with streams. Independently generates
	// a target URL with `file://` base. Needs source just for
	// determining the correct extension of the file.
	public static function generateTargetUrl($source) {
		$base      = static::base('file');
		$extension = Type::guessExtension($source);

		return static::_uniqueUrl($base, $extension, ['exists' => true]);
	}

	public static function regenerateVersions() {
		$data = static::all();

		foreach ($data as $item) {
			$item->deleteVersions();
			$item->makeVersions();
		}
	}
}

Media::init();

// Filter running before saving.
Media::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if (!$entity->modified('url') && $entity->exists()) {
		return $chain->next($self, $params, $chain);
	}
	if ($entity->can('checksum')) {
		$entity->checksum = $entity->calculateChecksum();
	}

	$entity->type = $entity->can('type') ?: Type::guessName($entity->url);
	$entity->mime_type = $entity->can('mime_type') ?: Type::guessType($entity->url);

	return $chain->next($self, $params, $chain);
});

Media::applyFilter('delete', function($self, $params, $chain) {
	$entity = $params['entity'];

	if ($entity->can('delete')) {
		Logger::debug("Deleting corresponding URL `{$entity->url}` of media.");
		$entity->deleteUrl();
	}

	return $chain->next($self, $params, $chain);
});

// Filter running before saving; order matters.
// Make URL relative before saving.
Media::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if ($entity->modified('url') && $entity->can('relative')) {
		$entity->url = Media::relativeUrl($entity->url);
	}
	return $chain->next($self, $params, $chain);
});



?>