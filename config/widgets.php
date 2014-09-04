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

use lithium\g11n\Message;
use base_core\extensions\cms\Widgets;
use base_media\models\Media;
use base_media\models\MediaVersions;

extract(Message::aliases());

Widgets::register('media', function() use ($t) {
	$media = Media::find('count');
	$size = 0;

	foreach (Media::find('all') as $item) {
		$size += $item->size();
	}

	return [
		'title' => $t('Media'),
		'url' => [
			'controller' => 'media', 'library' => 'base_media', 'admin' => true, 'action' => 'index'
		],
		'data' => [
			$t('Items') => $media,
			$t('Size') => intval($size / 1014 / 1024) . ' MB'
		]
	];
}, [
	'type' => Widgets::TYPE_COUNTER,
	'group' => Widgets::GROUP_DASHBOARD,
]);

?>