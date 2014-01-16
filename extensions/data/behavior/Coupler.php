<?php

namespace cms_media\extensions\data\behavior;

use cms_media\models\Media;

class Coupler extends \li3_behaviors\data\model\Behavior {

	// Holds methods keyed by alias.
	protected $_methods = [];

	protected function _init() {
		parent::_init();

		$this->_initMethods();
		$this->_initFilters();
	}

	protected function _initFilters() {
		$behavior = $this;
		$bindings = $this->_config['bindings'];
		$model = $this->_model;

		// Synchronizes join table with added or updated set of items.
		$model::applyFilter('save', function($self, $params, $chain) use ($behavior, $bindings) {
			$joined = [];

			foreach ($bindings as $alias => $options) {
				if (!isset($params['data'][$alias])) {
					continue;
				}
				if ($options['type'] == 'direct') {
					// Direct bindings need no special treatment.
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

	protected function _initMethods() {
		$model = $this->_model;

		foreach ($this->_config['bindings'] as $alias => $options) {
			if ($options == 'direct') {
				$this->_methods[$alias] = function($entity) use ($options) {
					// $to in this case is the field name i.e. cover_media_id.
					$to = $options['to'];

					return Media::findById($entity->{$to});
				};
			} else {
				$this->_methods[$alias] = function($entity) use ($options, $model) {
					// $to in this case is the model name i.e. cms_media\models\MediaAttachments.
					$to = $options['to'];

					$results = $to::find('all', [
						'conditions' => [
							'model' => $model,
							'foreign_key' => $entity->id
						],
						'with' => ['Media']
					]);

					$data = [];
					foreach ($results as $result) {
						$data[] = $result->media;
					}
					return $data;
				};
			}
		}
	}

	public function respondsTo($method, $internal = false) {
		if (isset($this->_methods[$method])) {
			return true;
		}
		return parent::respondsTo($method, $internal);
	}

	public function __call($method, $params) {
		if (isset($this->_methods[$method])) {
			return $this->_methods[$method]($params[0]);
		}
		parent::__call($method, $params);
	}
}

?>