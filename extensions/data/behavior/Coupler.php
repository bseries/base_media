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

namespace base_media\extensions\data\behavior;

use base_media\models\Media;
use li3_behaviors\data\model\Behavior;
use lithium\aop\Filters;
use lithium\storage\Cache;
use lithium\util\Collection;

class Coupler extends \li3_behaviors\data\model\Behavior {

	protected static $_defaults = [
		// each binding has a type which is one of "direct", "joined" or "inline".
		'bindings' => [],
		// The cache configuration to use or `false` to disable caching.
		'cache' => 'default'
	];

	protected static function _config($model, Behavior $behavior, array $config, array $defaults) {
		$config += $defaults;

		if (PROJECT_DEBUG) {
			$config['cache'] = false;
		}
		return $config;
	}

	protected static function _filters($model, Behavior $behavior) {
		$bindings = $behavior->config('bindings');
		$cache = $behavior->config('cache');

		// Synchronizes join table with added or updated set of items.
		Filters::apply($model, 'save', function($params, $next) use ($model, $behavior, $bindings, $cache) {
			$normalizedModel = static::_normalizedModel($model);

			$joined = [];
			$direct = [];

			foreach ($bindings as $alias => $options) {
				if ($options['type'] === 'direct') {
					$scoped = $alias . '_media_id';

					if (!isset($params['data'][$scoped])) {
						continue;
					}
					if (empty($params['data'][$scoped])) {
						// Ensure we save NULL to database.
						$params['entity']->$scoped = null;
					}
					$direct[$alias] = $params['data'][$scoped];
				} elseif ($options['type'] === 'inline') {
					// ...
				} else {
					if (!isset($params['data'][$alias])) {
						continue;
					}
					if (!empty($params['data'][$alias]['_delete'])) {
						// When all joined are removed the alias data might not even be set
						// anymore. Signal empty data == entire removal for code further down
						// the road.
						$joined[$alias] = [];
					} else {
						$joined[$alias] = $params['data'][$alias];
					}
					// Prevent mistakenly commiting data to item (i.e. for document based databases).
					unset($params['data'][$alias]);
				}
			}
			if (!$result = $next($params)) {
				return $result;
			}
			$id = $params['entity']->id;

			foreach ($direct as $alias => $data) {
				$to = $bindings[$alias]['to'];

				if ($cache) {
					Cache::delete($cache, [
						static::_cacheKey($normalizedModel, $id, $to, 'first'),
						static::_cacheKey($normalizedModel, $id, $to, 'count')
					]);
				}
			}
			foreach ($joined as $alias => $data) {
				$to = $bindings[$alias]['to'];

				if ($cache) {
					Cache::delete($cache, [
						static::_cacheKey($normalizedModel, $id, $to, 'all'),
						static::_cacheKey($normalizedModel, $id, $to, 'count')
					]);
				}

				// Rebuilt associations entirely.
				$to::remove([
					'model' => $normalizedModel,
					'foreign_key' => $params['entity']->id
				]);
				foreach ($data as $item) {
					$item = $to::create([
						'model' => $normalizedModel,
						'foreign_key' => $params['entity']->id,
						'media_id' => $item['id']
					]);
					$item->save();
				}
			}
			foreach ($bindings as $alias => $options) {
				if ($options['type'] !== 'inline') {
					continue;
				}
				$to = $bindings[$alias]['to']; // i.e. body

				Cache::delete($cache, [
					static::_cacheKey($normalizedModel, $id, $to, 'all'),
					static::_cacheKey($normalizedModel, $id, $to, 'count')
				]);
			}
			return $result;
		});

		// Cleans up join table if an item is deleted.
		Filters::apply($model, 'delete', function($params, $next) use ($model, $bindings, $cache) {
			$entity = $params['entity'];
			$normalizedModel = static::_normalizedModel($model);

			if (!$result = $next($params)) {
				return $result;
			}
			foreach ($bindings as $alias => $options) {
				$to = $options['to'];

				if ($cache) {
					Cache::delete($cache, [
						static::_cacheKey($normalizedModel, $entity->id, $to, 'first'),
						static::_cacheKey($normalizedModel, $entity->id, $to, 'all'),
						static::_cacheKey($normalizedModel, $entity->id, $to, 'count')
					]);
				}

				if ($options['type'] === 'direct' || $options['type'] === 'inline') {
					// Direct bindings need no special treatment.
					continue;
				}
				$to::remove([
					'model' => $normalizedModel,
					'foreign_key' => $entity->id
				]);
			}
			return $result;
		});
	}

	protected static function _methods($model, Behavior $behavior) {
		$methods = [];
		$cache = $behavior->config('cache');

		foreach ($behavior->config('bindings') as $alias => $options) {
			if ($options['type'] == 'direct') {
				$methods[$alias] = function($entity, $type = null) use ($options, $model, $cache) {
					$normalizedModel = static::_normalizedModel($model);
					$type = $type === 'count' ? 'count' : 'first';

					// $to in this case is the field name i.e. cover_media_id.
					$to = $options['to'];

					if ($cache) {
						$cacheKey = static::_cacheKey($normalizedModel, $entity->id, $to, $type);

						if (($cached = Cache::read($cache, $cacheKey)) !== null) {
							return $cached;
						}
					}
					$result = Media::find($type, [
						'conditions' => ['id' => $entity->{$to}]
					]);

					if ($cache) {
						Cache::write($cache, $cacheKey, $result, Cache::PERSIST);
					}
					return $result;
				};
			} elseif ($options['type'] == 'inline') {
				$methods[$alias] = function($entity, $type = null) use ($options, $model, $cache) {
					$normalizedModel = static::_normalizedModel($model);
					$type = $type === 'count' ? 'count' : 'all';

					// $to in this case is the field name i.e. body.
					$to = $options['to'];

					if ($cache) {
						$cacheKey = static::_cacheKey($normalizedModel, $entity->id, $to, $type);

						if (($cached = Cache::read($cache, $cacheKey)) !== null) {
							return $cached;
						}
					}

					// Extract ids from text
					$regex = '#(<img.*data-media-id="([0-9]+)".*>)#iU';
					if (!preg_match_all($regex, $entity->{$field}, $matches, PREG_SET_ORDER)) {
						return new Collection();
					}
					$ids = array_reduce($matches, function($carry, $item) {
						$carry[] = $item[2];
						return $carry;
					}, []);

					$result = Media::find($type, [
						'conditions' => [
							'id' => $ids
						],
						'order' => ['id' => 'ASC']
					]);

					if ($cache) {
						Cache::write($cache, $cacheKey, $result, Cache::PERSIST);
					}
					return $result;
				};
			} else {
				$methods[$alias] = function($entity, $type = null) use ($options, $model, $cache) {
					$normalizedModel = static::_normalizedModel($model);
					$type = $type === 'count' ? 'count' : 'all';

					// $to in this case is the model name i.e. base_media\models\MediaAttachments.
					$to = $options['to'];

					if ($cache) {
						$cacheKey = static::_cacheKey($normalizedModel, $entity->id, $to, $type);

						if (($cached = Cache::read($cache, $cacheKey)) !== null) {
							return $cached;
						}
					}

					$results = $to::find($type, [
						'conditions' => [
							'model' => $normalizedModel,
							'foreign_key' => $entity->id
						],
						'order' => ['id' => 'ASC'],
						'with' => ['Media']
					]);
					if ($type !== 'count') {
						$data = [];
						foreach ($results as $result) {
							$data[] = $result->media;
						}
						$result = new Collection(compact('data'));
					} else {
						$result = $results;
					}

					if ($cache) {
						Cache::write($cache, $cacheKey, $result, Cache::PERSIST);
					}
					return $result;
				};
			}
		}
		return $methods;
	}

	// Returns the CMS parent model class in case the model
	// is a subclass in app-space.
	protected static function _normalizedModel($model) {
		if (strpos($model, 'app\\') === 0) {
			return get_parent_class($model);
		}
		return $model;
	}

	protected static function _cacheKey($model, $id, $to, $type) {
		return 'media_coupler_' . hash('md5', $model . $id . $to . $type);
	}
}

?>