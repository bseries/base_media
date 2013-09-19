<?php
/**
 * Bureau Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace cms_media\models;

use \Mime_Type;
use cms_media\models\MediaVersions;
use lithium\core\Environment;
use lithium\analysis\Logger;
use temporary\Manager as Temporary;

class Media extends \lithium\data\Model {

	use \cms_media\models\ChecksumTrait;
	use \cms_media\models\UrlTrait;
	use \li3_behaviors\data\model\Behaviors;

	public $hasMany = ['MediaVersions'];

	protected $_actsAs = [
		'cms_core\extensions\data\behavior\Timestamp'
	];

	protected $_cachedVersions = [];

	protected static function _base($scheme) {
		return Environment::get('media.' . $scheme);
	}

	public function version($entity, $version) {
		if (isset($this->_cachedVersions[$entity->id][$version])) {
			return $this->_cachedVersions[$entity->id][$version];
		}
		return $this->_cachedVersions[$entity->id][$version] = MediaVersions::first([
			'conditions' => [
				'media_id' => $entity->id,
				'version' => $version
			]
		]);
	}

	public function versions($entity) {
		if (isset($this->_cachedVersions[$entity->id])) {
			return $this->_cachedVersions[$entity->id];
		}
		$data = MediaVersions::all([
			'conditions' => [
				'media_id' => $entity->id
			]
		]);
		$results = [];
		foreach ($data as $item) {
			$results[$item->version] = $item;
		}
		return $this->_cachedVersions[$entity->id] = $results;
	}

	public function makeVersions($entity) {
		if (!$entity->type) {
			throw new Exception('Entity has no type.');
		}
		if (!$entity->url) {
			throw new Exception('Entity has no URL.');
		}
		if (parse_url($entity->url, PHP_URL_SCHEME) != 'file') {
			// @fixme Implement versions for non-file schemes by trying to detect their versions
			// and pass them via create url.
			Logger::debug('Skipping making versions of non-file scheme source.');
			return true;
		}
		$versions = array('fix0', 'fix1', 'fix2', 'fix3', 'flux0', 'flux1');

		foreach ($versions as $version) {
			$has = MediaVersions::hasInstructions($entity->type, $version);
			if (!$has) {
				continue;
			}
			$version = MediaVersions::create([
				'media_id' => $entity->id,
				'url' => static::absoluteUrl($entity->url), // just as a safeguard
				'version' => $version
				// Versions don't have an user id as their records are already
				// associated with a media_file record an thus indirectly carry an user
				// id.
			]);
			if (!$target = $version->make()) {
				Logger::debug("Failed to make media version `{$version}`.");
				return false;
			}
			$version->url = $target;

			if (!$version->save()) {
				return false;
			}
		}
		return true;
	}

	public function deleteVersions($entity) {
		foreach ($entity->versions() as $version) {
			if (!$version->delete()) {
				return false;
			}
		}
		return true;
	}

	public function download($entity) {
		$temporary = Temporary::file(['context' => 'download']);

		Logger::debug("Downloading into temporary `{$temporary}`.");

		if (!$result = copy($entity->url, $temporary)) {
			throw new Exception('Could not copy from source to temporary.');
		}
		return $temporary;
	}

	// Tranfers a source to target - aka make the source local.
	// May use streams where appropriate.
	public function transfer($entity) {
		Logger::debug("Transferring from source `{$entity->url}`.");
		$target = static::_generateTargetUrl($entity->url);

		if (!is_dir(dirname($target))) {
			mkdir(dirname($target), 0777, true);
		}
		if (!$result = copy($entity->url, $target)) {
			throw new Exception('Could not copy from source to target.');
		}
		Logger::debug("Transferred to target `{$target}`.");
		return $target;
	}

	// Works with streams.
	protected static function _generateTargetUrl($source) {
		$base      = static::_base('file');
		$extension = Mime_Type::guessExtension($source);

		return static::_uniqueUrl($base, $extension, ['exists' => true]);
	}
}


// Filter running before saving.
Media::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if (!$entity->modified('url')) {
		return $chain->next($self, $params, $chain);
	}
	if (parse_url($entity->url, PHP_URL_SCHEME) == 'file') {
		$entity->checksum = $entity->calculateChecksum();
	}
	$entity->type      = Mime_Type::guessName($entity->url);
	$entity->mime_type = Mime_Type::guessType($entity->url);

	return $chain->next($self, $params, $chain);
});

Media::applyFilter('delete', function($self, $params, $chain) {
	$entity = $params['entity'];

	$entity->deleteUrl();

	return $chain->next($self, $params, $chain);
});

// Filter running before saving; order matters.
// Make URL relative before saving.
Media::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if ($entity->modified('url')) {
		$entity->url = Media::relativeUrl($entity->url);
	}
	return $chain->next($self, $params, $chain);
});

?>