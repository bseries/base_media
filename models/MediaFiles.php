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
		if ($entity->scheme == 'file') {
			$base = Environment::get('transfers.path');
			return $base . '/' . $entity->path;
		}
	}

	public function isConsistent($entity) {
		return hash_file('md5', $entity->file) === $entity->checksum;
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

	public static function generateTargetPath($source) {
		$base = Environment::get('transfers.path');
		$extension = Mime_Type::guessExtension($source);

		return static::_generatePath($base, $extension);
	}

	// @fixme Re-factor this into Media_Util::generatePath()
	protected static function _generatePath($base, $extension) {
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
		} while (file_exists($base . '/' . $path));

		return $base . '/' . $path;
	}
}

// Filter running before saving.
MediaFiles::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if (!$entity->source) {
		return $chain->next($self, $params, $chain);
	}
	$source = parse_url($entity->source);

	if ($source['scheme'] === 'file') {
		$target = MediaFiles::generateTargetPath($source['path']);

		Logger::debug("Copying local (tranferred) file from `{$source['path']}` to `{$target}`.");
		if (!copy($source['path'], $target)) {
			return false;
		}
	}
	$entity->scheme    = $source['scheme'];
	$entity->path      = $source['path'];

	// Get and save meta data once.
	$entity->type      = Mime_Type::guessName($source['path']);
	$entity->mime_type = Mime_Type::guessType($source['path']);
	$entity->checksum  = hash_file('md5', $source['path']);

	return $chain->next($self, $params, $chain);
});

// Filter running after save.
// Make versions that dependent on the saved file.
// @fixme Make multiple versions by configuration.
MediaFiles::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	// Check if we already failed earlier.
	if (!$result = $chain->next($self, $params, $chain)) {
		return $result;
	}
	if (!$entity->source) {
		return $result;
	}
	$version = MediaFileVersions::create([
		'source' => $entity->source,
		'version' => 'fix0',
		'media_file_id' => $entity->id
	]);
	return $version->save();
});

// Also delete dependent versions.
MediaFiles::applyFilter('delete', function($self, $params, $chain) {
	$data =& $params['data'];
	$versions = $params['entity']->versions();

	foreach ($versions as $version) {
		$version->delete();
	}
	return $chain->next($self, $params, $chain);
});

/*
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
 */



?>