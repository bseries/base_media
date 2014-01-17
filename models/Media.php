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

class Media extends \cms_core\models\Base {

	use \cms_media\models\ChecksumTrait;
	use \cms_media\models\UrlTrait;

	public $hasMany = ['MediaVersions'];

	protected $_actsAs = [
		'cms_core\extensions\data\behavior\Timestamp'
	];

	protected $_cachedVersions = [];

	protected static $_schemes = [];

	protected static $_dependent = [];

	public static function base($scheme) {
		return static::$_schemes[$scheme]['base'];
	}

	// @fixme Make this part of higher Media/settings abstratiction.
	public static function registerScheme($scheme, array $options = []) {
		// if (isset(static::$_schemes[$scheme])) {
		//	$options += $static::$_schemes[$scheme];
		//}
		static::$_schemes[$scheme] = $options + [
			'base' => false,
			'relative' => false,
			'delete' => false,
			'download' => false,
			'transfer' => false,
			'checksum' => false,
			'mime_type' => null,
			'type' => null
		];
	}

	// @fixme Make this part of higher Media/settings abstratiction.
	public static function registerDependent($model, array $bindingAliases) {
		static::$_dependent[$model] = $bindingAliases;
	}

	public function can($entity, $capability) {
		return static::$_schemes[$entity->scheme()][$capability];
	}

	// Finds out which other records depend on a given media entity.
	public function depend($entity) {
		$depend = [];

		foreach (static::$_dependent as $model => $aliases) {
			foreach ($aliases as $alias) {
				$depend = array_merge($depend, $model::{$alias}());
			}
		}
		return $depend;
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
		// @fixme Do not hardcode this.
		$versions = array('fix0', 'fix1', 'fix2', 'fix3', 'flux0', 'flux1');

		if (!$entity->type) {
			throw new Exception('Entity has no type.');
		}
		if (!$entity->url) {
			throw new Exception('Entity has no URL.');
		}
		if (is_callable($handler = $entiy->can('versions'))) {
			$url = $handler($entity);
			Media::_makeVersionsBuilitin('', $entity->id, $url);
			Media::_makeVersionsBuilitin($type, $id, $url);
		}

		if ($entity->scheme() != 'file') {
			// @fixme Implement versions for non-file schemes by trying to detect their versions
			// and pass them via create url.
			Logger::debug('Skipping making versions of non-file scheme source.');

			return true;
		}

		foreach ($versions as $version) {
			if (!MediaVersions::canMake($entity, $version)) {
				continue;
			}
			/*
			$has = MediaVersions::hasInstructions($entity->type, $version);
			if (!$has) {
				continue;
			}
			 */

			$version = MediaVersions::create([
				'media_id' => $entity->id,
				'url' => $entity->url,
				// 'url' => static::absoluteUrl($entity->url), // just as a safeguard
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

	// Tranfers a source to target - aka make the source local and
	// copy it into our "library" space. May use streams where appropriate.
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

	// Works with streams. Independently generates
	// a target URL with `file://` base. Needs source just for
	// determining the correct extension of the file.
	protected static function _generateTargetUrl($source) {
		$base      = static::base('file');
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
	if ($entity->can('checksum')) {
		$entity->checksum = $entity->calculateChecksum();
	}

	$entity->type = $entity->can('type') ?: Mime_Type::guessName($entity->url);
	$entity->mime_type = $entity->can('mime_type') ?: Mime_Type::guessType($entity->url);

	return $chain->next($self, $params, $chain);
});

Media::applyFilter('delete', function($self, $params, $chain) {
	$entity = $params['entity'];

	if ($entity->can('delete')) {
		Logger::debug("Deleting corresponding URL `{$entity->url}` of media.");
		$entity->deleteUrl();
	}

	return $chain->next($self, $params, $chain);
});

// Filter running before saving; order matters.
// Make URL relative before saving.
Media::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if ($entity->modified('url') && $entity->can('relative')) {
		$entity->url = Media::relativeUrl($entity->url);
	}
	return $chain->next($self, $params, $chain);
});



?>