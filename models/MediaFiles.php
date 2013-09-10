<?php

namespace cms_media\models;

use \Mime_Type;
use \Media_Process;
use cms_media\models\MediaFileVersions;
use lithium\core\Environment;

class MediaFiles extends \lithium\data\Model {

	protected $_cachedVersions = [];

	// @fixme Transfers do not have an URL, yet.
	public function url($entity) {
		if ($entity->scheme == 'file') {
			$base = Environment::get('transfers.url');
			return $base . '/' . $entity->path;
		}
		return $entity->path;
	}

	public function file($entity) {
		if (isset($entity->file)) {
			return $entity->file;
		}
		if ($entity->scheme == 'file') {
			$base = Environment::get('transfers.path');
			return $base . '/' . $entity->path;
		}
	}

	public function version($entity, $version) {
		if (isset($this->_cachedVersions[$type])) {
			return $this->_cachedVersions[$type];
		}
		return $this->_cachedVersions[$type] = MediaFileVersions::first([
			'conditions' => [
				'media_file_id' => $entity->id,
				'version' => $name
			]
		]);
	}

	public function versions($entity) {
		if ($this->_cachedVersions) {
			return $this->_cachedVersions;
		}
		$data = MediaFileVersions::all([
			'conditions' => [
				'media_file_id' => $entity->id
			]
		]);
		$results = [];
		foreach ($data as $item) {
			$results[$item->version] = $item;
		}
		return $this->_cachedVersions = $results;
	}

	public static function generateTargetPath($via, $from) {
		extract(is_file($from['file']) ? $from : $via);

		if (!empty($mimeType)) {
			$extension = Mime_Type::guessExtension($mimeType);
		}

		$chars = 'abcdef0123456789';
		$length = 8;

		// Birthday problem: Likelihood of collision with 1M strings is 0.18%.
		// Prevent collisions. If this happens too "often" expand charset first.
		do {
			// Generate a random string for each round.
			$random = '';
			while (strlen($random) < $length) {
				$random .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
			}
			$path = substr($random, 0, 2) . '/' . substr($random, 2);

			if (!empty($extension)) {
				$path .= '.' . strtolower($extension);
			}
		} while (file_exists(TRANSFERS . $path));

		return $path;
	}
}

MediaFiles::finder('original', function($self, $params, $chain) {
	$params['options']['conditions'] = array(
		'versions' => array('$exists' => true),
	);
	return $chain->next($self, $params, $chain);
});
MediaFiles::finder('listOriginal', function($self, $params, $chain) {
	$params['options']['conditions'] = array(
		'versions' => array('$exists' => true),
	);
	$results = $chain->next($self, $params, $chain);

	$data = array();
	foreach ($results as $result) {
		$data[(string) $result->_id] = $result->filename;
	}
	return $data;
});


MediaFiles::applyFilter('create', function($self, $params, $chain) {
	$data =& $params['data'];

	if (isset($data['file'])) {
		$data['type'] = Mime_Type::guessName($data['file']);
		$data['mime_type'] = Mime_Type::guessType($data['file']);
		$data['checksum'] = hash_file('md5', $data['file']);
	}
	return $chain->next($self, $params, $chain);
});

// Also delete versions.
MediaFiles::applyFilter('delete', function($self, $params, $chain) {
	$data =& $params['data'];
	$versions = $params['entity']->versions();

	foreach ($versions as $version) {
		$version->delete();
	}
	return $chain->next($self, $params, $chain);
});

MediaFiles::applyFilter('save', function($self, $params, $chain) {
	// Check if we already failed earlier.
	if (!$result = $chain->next($self, $params, $chain)) {
		return $result;
	}

	// $entity = MediaFiles::first((string) $params['entity']->id); // refresh?
	$version = MediaFileVersions::create([
		'file' => $entity->file(), // The original "from" file.
		'version' => 'fix0',
		'media_file_id' => $entity->id
	]);
	return $version->save();
});
?>