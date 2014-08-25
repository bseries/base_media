<?php
/**
 * Bureau Media
 *
 * Copyright (c) 2013-2014 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

use lithium\g11n\Message;
use cms_core\extensions\cms\Widgets;
use cms_media\models\Media;
use cms_media\models\MediaVersions;

extract(Message::aliases());

Widgets::register('media', function() use ($t) {
	$media = Media::find('count');
	$mediaVersions = MediaVersions::find('count');

	return [
		'url' => [
			'controller' => 'media', 'library' => 'cms_media', 'admin' => true, 'action' => 'index'
		],
		'data' => [
			$t('Media') => $media,
			$t('Media Versions') => $mediaVersions
		]
	];
}, [
	'type' => Widgets::TYPE_COUNTER,
	'group' => Widgets::GROUP_DASHBOARD,
]);

?>