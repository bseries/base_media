<?php

namespace cms_media\models;

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
		return hash_file('md5', parse_url($file));
	}
}

?>