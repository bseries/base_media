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

Widgets::register('media', function() use ($t, $tn) {
	$media = Media::find('count');
	$size = 0;

	foreach (Media::find('all') as $item) {
		$size += $item->size();
	}
	$formatNiceBytes = function($size) use ($t, $tn) {
		switch (true) {
			case $size < 1024:
				return $tn('{:count} Byte', '{:count} Bytes', [
					'count' => $size,
					'scope' => 'base_media'
				]);
			case round($size / 1024) < 1024:
				return $t('{:count} KB', [
					'count' => round($size / 1024),
					'scope' => 'base_media'
				]);
			case round($size / 1024 / 1024, 2) < 1024:
				return $t('{:count} MB', [
					'count' => round($size / 1024 / 1024, 2),
					'scope' => 'base_media'
				]);
			case round($size / 1024 / 1024 / 1024, 2) < 1024:
				return $t('{:count} GB', [
					'count' => round($size / 1024 / 1024 / 1024, 2),
					'scope' => 'base_media'
				]);
			default:
				return $t('{:count} TB', [
					'count' => round($size / 1024 / 1024 / 1024 / 1024, 2),
					'scope' => 'base_media'
				]);
		}
	};

	return [
		'title' => $t('Media', ['scope' => 'base_media']),
		'url' => [
			'controller' => 'media', 'library' => 'base_media', 'admin' => true, 'action' => 'index'
		],
		'data' => [
			$t('Items', ['scope' => 'base_media']) => $media,
			$t('Size', ['scope' => 'base_media']) => $formatNiceBytes($size)
		]
	];
}, [
	'type' => Widgets::TYPE_COUNTER,
	'group' => Widgets::GROUP_DASHBOARD,
]);

?>