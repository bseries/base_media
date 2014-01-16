<?php
/**
 * Bureau Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace cms_media\models;

class MediaAttachments extends \lithium\data\Model {

	public $belongsTo = [
		'Media' => [
			'to' => 'cms_media\models\Media',
			'key' => 'media_id'
		]
	];

	// Polymorphic model.
	// public $hasMany = [];
}

?>