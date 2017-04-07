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

use Cute\Connection;
use Cute\Job;
use Exception;
use InvalidArgumentException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use OutOfBoundsException;
use base_media\models\MediaAttachments;
use base_media\models\MediaVersions;
use lithium\analysis\Logger;
use lithium\aop\Filters;
use lithium\core\Libraries;
use lithium\storage\Cache;
use lithium\util\Collection;
use mm\Mime\Type;

class Media extends \base_core\models\Base {

	use \base_core\models\SchemeTrait;
	use \base_core\models\UrlTrait;
	use \base_core\models\UrlChecksumTrait;
	use \base_core\models\UrlDownloadTrait;
	use \base_media\models\MediaInfoTrait;

	public $hasMany = [
		'MediaVersions'
	];

	public $belongsTo = [
		'Owner' => [
			'to' => 'base_core\models\Users',
			'key' => 'owner_id'
		]
	];

	protected $_actsAs = [
		'base_core\extensions\data\behavior\Ownable',
		'base_core\extensions\data\behavior\Timestamp',
		'base_core\extensions\data\behavior\Searchable' => [
			'fields' => [
				'Owner.name',
				'Owner.number',
				'type',
				'mime_type',
				'title',
				'created'
			]
		]
	];

	protected static $_cuteConnection;

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

	protected static function _cuteConnection() {
		if (!static::$_cuteConnection) {
			$log = new MonologLogger(PROJECT_NAME);
			$log->pushHandler(new StreamHandler(PROJECT_PATH . '/log/app.log'));

			return static::$_cuteConnection = new Connection(
				$log, PROJECT_NAME . '_' . PROJECT_CONTEXT
			);
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

	// Finds out which other records depend on a given media entity.
	// Type can either be count or all.
	//
	// Do not use in performance criticial parts.
	public function depend($entity, $type) {
		$depend = $type === 'count' ? 0 : [];

		if ($type !== 'count' && $type !== 'all') {
			throw new InvalidArgumentException("Invalid depend type `{$type}` given.");
		}

		foreach (static::_dependent() as $model => $bindings) {
			foreach ($bindings as $alias => $binding) {
				if ($binding['type'] === 'direct') {
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
				} elseif ($binding['type'] === 'inline') {
					$results = $model::find($type, [
						'conditions' => [
							$binding['to'] => [
								'LIKE' => '%data-media-id="' . $entity->id . '"%'
							]
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

	protected static function _dependent() {
		$models = array_filter(Libraries::locate('models'), function($v) {
			$skip = [
				'app\models\Base',
				'base_core\models\Base',
			];
			return !in_array($v, $skip) && strpos($v, 'Trait') === false;
		});
		$results = [];

		foreach ($models as $model) {
			// Check if we can call hasBehavior() indirectly.
			if (!is_a($model, '\base_core\models\Base', true)) {
				continue;
			}
			$model::key(); // Hack to activate behaviors.

			if (!$model::hasBehavior('Coupler')) {
				continue;
			}
			$results[$model] = $model::behavior('Coupler')->config('bindings');
		}
		return $results;
	}

	public static function clean() {
		foreach (static::find('all') as $item) {
			if ($item->depend('count') > 0) {
				continue;
			}
			if (!$item->delete()) {
				return false;
			}
		}
		return true;
	}

	public function hasVersion($entity, $version) {
		return isset($entity->versions()[$version]);
	}

	// Simplified as versions method is cached.
	public function version($entity, $version) {
		$results = $entity->versions();

		if (!isset($results[$version])) {
			$message  = "No media version `{$version}` available for media {$entity->type} ";
			$message .= "with id `{$entity->id}` and title `{$entity->title}`. ";
			$message .= "You might have misspelled the version or expect a version that ";
			$message .= "is only available for i.e. videos.";
			throw new OutOfBoundsException($message);
		}
		return $results[$version];
	}

	public function versions($entity) {
		if (!PROJECT_DEBUG) {
			$cacheKey = 'media_' . $entity->id . '_versions';

			if ($cached = Cache::read('default', $cacheKey)) {
				return $cached;
			}
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

		if (!PROJECT_DEBUG) {
			Cache::write('default', $cacheKey, $result, Cache::PERSIST);
		}
		return $result;
	}

	public function verify($entity) {
		if (!$entity->type) {
			return false;
		}
		if (!$entity->url) {
			return false;
		}
		if (parse_url($entity->url, PHP_URL_SCHEME) === 'file' && !file_exists(static::absoluteUrl($entity->url))) {
			return false;
		}
		if (!$entity->can('checksum')) {
			return true;
		}
		return $entity->isConsistent();
	}

	public function makeVersions($entity) {
		if (!$entity->verify()) {
			$message  = "Media with id `{$entity->id}` and URL `{$entity->url}` did not verify. ";
			$message .= "This might be caused by a checksum mismatch or a missing file.";
			throw new Exception($message);
		}
		$selfTransaction = !MediaVersions::pdo()->inTransaction();

		// Fetch versions we need to make. We're assembling all
		// possible version strings as we don't know if a certain
		// version applies for an entity. This decicison is made late
		// in the scheme make handler.
		foreach (MediaVersions::assemblyVersions() as $version) {
			if (!PROJECT_ASYNC_PROCESSING) {
				if ($selfTransaction) {
					MediaVersions::pdo()->beginTransaction();
				}
				$result = MediaVersions::make($entity->id, $version);
				// may be either null, false or true

				if ($result) {
					if ($selfTransaction) {
						MediaVersions::pdo()->commit();
					}
					continue;
				} elseif ($result === null) {
					if ($selfTransaction) {
						MediaVersions::pdo()->rollback();
					}
					continue;
				} else {
					if ($selfTransaction) {
						MediaVersions::pdo()->rollback();
					}
					return false;
				}
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
		Cache::delete('default', 'media_' . $entity->id . '_versions');

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
}

// Filter running before saving.
Filters::apply(Media::class, 'save', function($params, $next) {
	$entity = $params['entity'];

	if (!$entity->modified('url') && $entity->exists()) {
		return $next($params);
	}
	if ($entity->can('checksum')) {
		$entity->checksum = $entity->calculateChecksum();
	}

	$entity->type = $entity->can('type') ?: Type::guessName($entity->url);
	$entity->mime_type = $entity->can('mime_type') ?: Type::guessType($entity->url);

	Cache::delete('default', 'media_versions_' . md5($entity->id));
	return $next($params);
});

Filters::apply(Media::class, 'delete', function($params, $next) {
	$entity = $params['entity'];

	Cache::delete('default', 'media_versions_' . md5($entity->id));

	if ($entity->can('delete')) {
		Logger::debug("Deleting corresponding URL `{$entity->url}` of media.");
		$entity->deleteUrl();
	}
	return $next($params);
});

// Filter running before saving; order matters.
// Make URL relative before saving.
Filters::apply(Media::class, 'save', function($params, $next) {
	$entity = $params['entity'];

	if (($entity->modified('url') || !$entity->exists()) && $entity->can('relative')) {
		$entity->url = Media::relativeUrl($entity->url);
	}
	return $next($params);
});

?>