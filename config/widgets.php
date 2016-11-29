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

namespace base_media\config;

use NumberFormatter;
use base_core\extensions\cms\Widgets;
use base_media\models\Media;
use base_media\models\MediaVersions;
use lithium\core\Environment;
use lithium\g11n\Message;

extract(Message::aliases());

Widgets::register('media', function() use ($t, $tn) {
	$data = Media::find('all', [
		'fields' => ['url']
	]);
	$size = 0;

	foreach ($data as $item) {
		$size += $item->size();
	}
	$locale = Environment::get('locale');

	$formatNiceBytes = function($size) use ($t, $tn, $locale) {
		if (!$size) {
			return $t('0 Bytes', ['scope' => 'base_media']);
		}
		$formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);

		switch (true) {
			case $size < 1024:
				return $tn('{:count} Byte', '{:count} Bytes', [
					'count' => $size,
					'scope' => 'base_media'
				]);
			case round($size / 1024) < 1024:
				return $t('{:count} KB', [
					'count' => $formatter->format(round($size / 1024)),
					'scope' => 'base_media'
				]);
			case round($size / 1024 / 1024, 2) < 1024:
				return $t('{:count} MB', [
					'count' => $formatter->format(round($size / 1024 / 1024, 2)),
					'scope' => 'base_media'
				]);
			case round($size / 1024 / 1024 / 1024, 2) < 1024:
				return $t('{:count} GB', [
					'count' => $formatter->format(round($size / 1024 / 1024 / 1024, 2)),
					'scope' => 'base_media'
				]);
			default:
				return $t('{:count} TB', [
					'count' => $formattter->format(round($size / 1024 / 1024 / 1024 / 1024, 2)),
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
			$t('Total', ['scope' => 'base_media']) => $data->count(),
			$t('Size', ['scope' => 'base_media']) => $formatNiceBytes($size)
		]
	];
}, [
	'type' => Widgets::TYPE_COUNTER,
	'group' => Widgets::GROUP_DASHBOARD,
]);

?>