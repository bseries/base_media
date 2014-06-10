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

trait MediaInfoTrait {

	public function info($entity, $name = null) {
		$media = Info::factory(['source' => $entity->url('file')]);

		if ($name) {
			return $media->get($name);
		}
		return $media->all();
	}
}

?>