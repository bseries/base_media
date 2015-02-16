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

namespace base_media\models;

use Exception;

trait ChecksumTrait {

	// Will fail with absolute URLs and non-transitionable ones.
	// @fixme support for http apis that respond with a md5 header field.
	// hash_file dose not work with streams
	public function isConsistent($entity) {
		if (!$entity->checksum) {
			throw new Exception('Entity has no checksum to compare against.');
		}
		$file = parse_url($entity->url('file'), PHP_URL_PATH);
		return hash_file('md5', $file) === $entity->checksum;
	}

	// hash_file dose not work with streams
	// @fixme make static
	public function calculateChecksum($entity) {
		$file = parse_url($entity->url('file'), PHP_URL_PATH);
		return hash_file('md5', $file);
	}
}

?>