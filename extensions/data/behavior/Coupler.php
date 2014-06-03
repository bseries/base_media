<?php

namespace cms_media\extensions\data\behavior;

use cms_media\models\Media;
use lithium\util\Collection;

class Coupler extends \li3_behaviors\data\model\Behavior {

	protected static function _filters($model, $behavior) {
		$bindings = $behavior->config('bindings');

		// Synchronizes join table with added or updated set of items.
		$model::applyFilter('save', function($self, $params, $chain) use ($behavior, $bindings) {
			$joined = [];

			foreach ($bindings as $alias => $options) {
				if ($options['type'] == 'direct') {
					$alias .= '_media_id';

					if (!isset($params['data'][$alias])) {
						continue;
					}
					if (empty($params['data'][$alias])) {
						// Ensure we save NULL to datqbase.
						$params['entity']->$alias = null;
					}
					// Direct bindings need no further special treatment.
					continue;
				}
				if (!isset($params['data'][$alias])) {
					continue;
				}

				// Prevent mistakenly commiting data to item (i.e. for document based databases).
				$joined[$alias] = $params['data'][$alias];
				unset($params['data'][$alias]);
			}
			if (!$result = $chain->next($self, $params, $chain)) {
				return $result;
			}

			foreach ($joined as $alias => $data) {
				$to = $bindings[$alias]['to'];

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
		$model::applyFilter('delete', function($self, $params, $chain) use ($bindings) {
			$entity = $params['entity'];

			if (!$result = $chain->next($self, $params, $chain)) {
				return $result;
			}
			foreach ($bindings as $alias => $options) {
				if ($options['type'] == 'direct') {
					// Direct bindings need no special treatment.
					continue;
				}
				$to = $options['to'];
				$to::remove([
					'model' => $self,
					'foreign_key' => $entity->id
				]);
			}
			return $result;
		});
	}

	protected static function _methods($model, $behavior) {
		$methods = [];

		foreach ($behavior->config('bindings') as $alias => $options) {
			if ($options['type'] == 'direct') {
				$methods[$alias] = function($entity) use ($options) {
					// $to in this case is the field name i.e. cover_media_id.
					$to = $options['to'];

					return Media::find('first', ['conditions' => ['id' => $entity->{$to}]]);
				};
			} else {
				$methods[$alias] = function($entity) use ($options, $model) {
					// $to in this case is the model name i.e. cms_media\models\MediaAttachments.
					$to = $options['to'];

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
					return new Collection(compact('data'));
				};
			}
		}
		return $methods;
	}
}

?>