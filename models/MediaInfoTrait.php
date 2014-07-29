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

namespace cms_media\models;

use mm\Media\Info;
use Exception;

trait MediaInfoTrait {

	// @fixme This is only a first step into making the media entity work
	//        with non existing items. Abstract this more and allow
	//        us to retrieve more meta data.
	public function size($entity) {
		if ($entity->exists()) {
			return filesize($entity->url('file'));
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
		try {
			$media = Info::factory(['source' => $entity->url('file')]);
			return $name ? $media->get($name) : $media->all();
		} catch (Exception $e) {
			return $name ? null : [];
		}
	}
}

?>