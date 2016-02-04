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

use Exception;
use lithium\storage\Cache;
use mm\Media\Info;

trait MediaInfoTrait {

	// @fixme This is only a first step into making the media entity work
	//        with non existing items. Abstract this more and allow
	//        us to retrieve more meta data.
	public function size($entity) {
		if ($entity->exists()) {
			if ($entity->can('relative') && file_exists($file = $entity->url('file'))) {
				return filesize($file);
			}
			return;
		}
		if (strpos($entity->url, 'http') === 0) {
			$curl = curl_init($entity->url);

			curl_setopt($curl, CURLOPT_HEADER, true);
			curl_setopt($curl, CURLOPT_NOBODY, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

			curl_exec($curl);
			$result = curl_getinfo($curl);
			curl_close($curl);

			return $result['download_content_length'];
		}
	}

	public function info($entity, $name = null) {
		$cacheKey = 'media_info_'  . md5($entity->url) . '_' . ($name ?: 'all');

		if ($cached = Cache::read('default', $cacheKey)) {
			return $cached;
		}
		try {
			$media = Info::factory(['source' => $entity->url('file')]);
			$result = $name ? $media->get($name) : $media->all();

			Cache::write('default', $cacheKey, $result);
			return $result;
		} catch (Exception $e) {
			return $name ? null : [];
		}
	}

	public function orientation($entity) {
		$width  = $entity->info('width');
		$height = $entity->info('height');

		// This method is available to all kinds of media.
		if (!is_integer($width) || !is_integer($height)) {
			return null;
		}
		if ($width === $height) {
			return 'square';
		}
		return $width > $height ? 'landscape' : 'portrait';
	}
}

?>