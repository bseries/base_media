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

use lithium\storage\Cache;
use lithium\util\Collection;
use base_media\models\Media;
use li3_behaviors\data\model\Behavior;

class Coupler extends \li3_behaviors\data\model\Behavior {

	protected static $_defaults = [
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
		$model::applyFilter('save', function($self, $params, $chain) use ($behavior, $bindings, $cache) {
			$joined = [];
			$direct = [];

			foreach ($bindings as $alias => $options) {
				if ($options['type'] == 'direct') {
					$scoped = $alias . '_media_id';

					if (!isset($params['data'][$scoped])) {
						continue;
					}
					if (empty($params['data'][$scoped])) {
						// Ensure we save NULL to database.
						$params['entity']->$scoped = null;
					}
					$direct[$alias] = $params['data'][$scoped];
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
			if (!$result = $chain->next($self, $params, $chain)) {
				return $result;
			}
			$id = $params['entity']->id;

			foreach ($direct as $alias => $data) {
				$to = $bindings[$alias]['to'];

				if ($cache) {
					Cache::delete($cache, 'media_coupler_' . md5($self . $id . $to));
				}
			}
			foreach ($joined as $alias => $data) {
				$to = $bindings[$alias]['to'];

				if ($cache) {
					Cache::delete($cache, 'media_coupler_' . md5($self . $id . $to));
				}

				// Rebuilt associations entirely.
				$to::remove([
					'model' => $self,
					'foreign_key' => $params['entity']->id
				]);
				foreach ($data as $item) {
					$item = $to::create([
						'model' => $self,
						'foreign_key' => $params['entity']->id,
						'media_id' => $item['id']
					]);
					$item->save();
				}
			}
			return $result;
		});

		// Cleans up join table if an item is deleted.
		$model::applyFilter('delete', function($self, $params, $chain) use ($bindings, $cache) {
			$entity = $params['entity'];

			if (!$result = $chain->next($self, $params, $chain)) {
				return $result;
			}
			foreach ($bindings as $alias => $options) {
				$to = $options['to'];

				if ($cache) {
					Cache::delete($cache, 'media_coupler_' . md5( $self . $entity->id . $to));
				}

				if ($options['type'] == 'direct') {
					// Direct bindings need no special treatment.
					continue;
				}
				$to::remove([
					'model' => $self,
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
				$methods[$alias] = function($entity) use ($options, $model, $cache) {
					// $to in this case is the field name i.e. cover_media_id.
					$to = $options['to'];

					if ($cache) {
						$cacheKey = 'media_coupler_' . md5(
							$model . $entity->id . $to
						);
						if (($cached = Cache::read($cache, $cacheKey)) !== null) {
							return $cached;
						}
					}
					$result = Media::find('first', ['conditions' => ['id' => $entity->{$to}]]);

					if ($cache) {
						Cache::write($cache, $cacheKey, $result, Cache::PERSIST);
					}
					return $result;
				};
			} else {
				$methods[$alias] = function($entity) use ($options, $model, $cache) {
					// $to in this case is the model name i.e. base_media\models\MediaAttachments.
					$to = $options['to'];

					if ($cache) {
						$cacheKey = 'media_coupler_' . md5(
							$model . $entity->id . $to
						);
						if (($cached = Cache::read($cache, $cacheKey)) !== null) {
							return $cached;
						}
					}

					$results = $to::find('all', [
						'conditions' => [
							'model' => $model,
							'foreign_key' => $entity->id
						],
						'order' => ['id' => 'ASC'],
						'with' => ['Media']
					]);
					$data = [];
					foreach ($results as $result) {
						$data[] = $result->media;
					}
					$result = new Collection(compact('data'));

					if ($cache) {
						Cache::write($cache, $cacheKey, $result, Cache::PERSIST);
					}
					return $result;
				};
			}
		}
		return $methods;
	}
}

?>