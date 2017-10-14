<?php
/**
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace base_media\models;

use base_media\models\Media;

// Polymorphic model.
class MediaAttachments extends \base_core\models\Base {

	public $belongsTo = [
		'Media' => [
			'to' => 'base_media\models\Media',
			'key' => 'media_id'
		]
	];

	public function medium($entity) {
		return Media::find('first', [
			'conditions' => [
				'id' => $entity->media_id
			]
		]);
	}

	public function attachee($entity) {
		$model = $entity->model;

		return $model::find('first', [
			'conditions' => [
				'id' => $entity->foreign_key
			]
		]);
	}
}

?>