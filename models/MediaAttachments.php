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