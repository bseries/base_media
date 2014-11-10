<?php
/**
 * Base Media
 *
 * Copyright (c) 2013-2014 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace base_media\models;

use Exception;
use mm\Mime\Type;
use base_media\models\MediaVersions;
use base_media\models\MediaAttachments;
use lithium\analysis\Logger;
use lithium\storage\Cache;
use Cute\Job;
use Cute\Connection;
use lithium\util\Collection;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use ff\Features;

class Media extends \base_core\models\Base {

	use \base_media\models\ChecksumTrait;
	use \base_media\models\UrlTrait;
	use \base_media\models\DownloadTrait;
	use \base_media\models\SchemeTrait;
	use \base_media\models\MediaInfoTrait;

	public $hasMany = ['MediaVersions'];

	protected static $_actsAs = [
		'base_core\extensions\data\behavior\Timestamp'
	];

	protected static $_dependent = [];

	protected static $_cuteConnection;

	protected static function _cuteConnection() {
		if (!static::$_cuteConnection) {
			$log = new MonologLogger(PROJECT_NAME);
			$log->pushHandler(new StreamHandler(PROJECT_PATH . '/log/app.log'));

			return static::$_cuteConnection = new Connection($log, PROJECT_NAME);
		}
		return static::$_cuteConnection;
	}

	public static function search($q, array $query = []) {
		$meta = [
			'total' => null
		];
		$results = static::find('all', [
			'conditions' => [
				'or' => [
					'type' => ['LIKE' => "%{$q}%"],
					'mime_type' => ['LIKE' => "%{$q}%"],
					'title' => ['LIKE' => "%{$q}%"]

				]
			]
		] + $query);

		$meta['total'] = static::find('count', [
			'conditions' => [
				'or' => [
					'type' => ['LIKE' => "%{$q}%"],
					'mime_type' => ['LIKE' => "%{$q}%"],
					'title' => ['LIKE' => "%{$q}%"]

				]
			]
		]);
		return [$results, $meta];
	}

	// Registers a model that uses and depends on media. Bindings define
	// how exactly the model depends on the media.
	//
	// In this example the 2 possible types of bindings are registered:
	//
	// ```
	// Media::registerDependent('cms_post\models\Posts', [
	//	'cover' => 'direct', 'media' => 'joined'
	// ]);
	// ```
	//
	// - For _direct_ bindings the model must have a <NAME>_media_id field.
	//   Direct bindings can just hold one medium.
	//
	// - _Joined_ bindings are used to attach an arbitrary amount of media
	//   to a model.
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

	// Simplified as versions method is cached.
	public function version($entity, $version) {
		if ($results = $entity->versions()) {
			return $results[$version];
		}
		return $results;
	}

	public function versions($entity) {
		$cacheKey = 'media_versions_' . md5(
			$entity->id
		);
		if ($cached = Cache::read('default', $cacheKey)) {
			return $cached;
		}

		$data = MediaVersions::all([
			'conditions' => [
				'media_id' => $entity->id
			],
			'order' => ['version' => 'ASC']
		]);
		$results = [];
		foreach ($data as $item) {
			$results[$item->version] = $item;
		}
		$result = new Collection(['data' => $results]);

		Cache::write('default', $cacheKey, $result, Cache::PERSIST);
		return $result;
	}

	public function makeVersions($entity) {
		if (!$entity->type) {
			throw new Exception('Entity has no type.');
		}
		if (!$entity->url) {
			throw new Exception('Entity has no URL.');
		}

		// Fetch versions we need to make. We're assembling all
		// possible version strings as we don't know if a certain
		// version applies for an entity. This decicison is made late
		// in the scheme make handler.
		foreach (MediaVersions::assemblyVersions() as $version) {
			if (!Features::enabled('asyncProcessing')) {
				MediaVersions::pdo()->beginTransaction();

				if (MediaVersions::make($entity->id, $version)) {
					MediaVersions::pdo()->commit();
				} else {
					MediaVersions::pdo()->rollback();
					return false;
				}
				continue;
			}

			$isFix = strpos($version, 'fix') !== false;

			$priority = Job::PRIORITY_NORMAL;
			if (preg_match('/fix([0-9]{1}).*(admin)?/', $version, $matches)) {

				if (isset($matches[2])) {
					$priority = Job::PRIORITY_NORMAL - ($matches[1] * 2);
				} else {
					$priority = Job::PRIORITY_NORMAL - $matches[1];
				}
			}

			$job = new Job(static::_cuteConnection());
			$options = [
				// Allow this to be connection less.
				'fallback' => true,

				// Separate queues for fix and flux.
				'queue' => $isFix ? 'fix' : 'flux',

				// Make thumbnails avaialble once we return from here.
				'wait' => strpos($version, 'fix3') !== false,

				'priority' => $priority,

				// Videos need much more time to transcode (max 1h).
				'ttr' => $isFix ? 60 * 5 : 60 * 60
			];
			try {
				// Provide all required data to create a valid
				// media version object later.
				$job->run('MediaVersions::make', [
					'mediaId' => $entity->id,
					'version' => $version
				], $options);
			} catch (Exception $e) {
				$message  = "Failed enqueuing `MediaVersions::make`";
				$message .= " job for media version id `{$version->id}` exception message was: ";
				$message .= $e->getMessage();
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

	public static function regenerateVersions($id = null) {
		if (!$id) {
			$data = static::all();
		} else {
			$data = [static::find('first', [
				'conditions' => [
					'id' => $id
				]
			])];
		}

		foreach ($data as $item) {
			$item->deleteVersions();
			$item->makeVersions();
		}
	}
}

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

// Invalidate cache items.
MediaVersions::applyFilter('save', function($self, $params, $chain) {
	$result = $chain->next($self, $params, $chain);

	Cache::delete('default', 'media_versions_' . md5($params['entity']->id));
	return $result;
});
MediaVersions::applyFilter('delete', function($self, $params, $chain) {
	Cache::delete('default', 'media_versions_' . md5($params['entity']->id));
	return $chain->next($self, $params, $chain);
});

?>