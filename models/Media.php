<?php

namespace cms_media\models;

use \Mime_Type;
use cms_media\models\MediaVersions;
use lithium\core\Environment;
use lithium\analysis\Logger;

class Media extends \lithium\data\Model {

	use \cms_media\models\ChecksumTrait;
	use \cms_media\models\UrlTrait;

	protected $_cachedVersions = [];

	protected static function _base($scheme) {
		return Environment::get('mediaFiles.' . $scheme);
	}

	public function version($entity, $version) {
		if (isset($this->_cachedVersions[$type])) {
			return $this->_cachedVersions[$type];
		}
		return $this->_cachedVersions[$type] = MediaVersions::first([
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
		$data = MediaVersions::all([
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

	// Tranfers a source to target - aka make the source local.
	// May use streams where appropriate.
	public static function transfer($source) {
		Logger::debug("Transferring from source `{$source}`.");

		// Transfer over local file to get access to contents for
		// safe MIME type detection in a seekable stream.
		if (parse_url($source, PHP_URL_SCHEMA) != 'file') {
			Logger::debug('Transferring via temporary stream.');

			$temporary = fopen('php://temp', 'wb');

			if (!$result = copy($source, $temporary)) {
				fclose($temporary);
				throw new Exception('Could not copy from source to temporary stream.');
			}
			rewind($temporary);

			// $source is now a stream and no URL anymore.
			$source = $temporary;
		}
		$target = static::_generateTargetUrl($sourceStream);
		$result = copy($source, $target);

		if (is_resource($source)) {
			fclose($source);
		}
		if (!$result) {
			throw new Exception('Could not copy from source (stream) to target.');
		}
		Logger::debug("Transferred to target `{$target}`.");
		return $target;
	}

	protected static function _generateTargetUrl($source) {
		$base      = static::_base('file');
		$extension = Mime_Type::guessExtension($source);

		return static::_uniqueUrl($base, $extension, ['exists' => true]);
	}
}

// Filter running before saving.
Media::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if (!$entity->source) {
		return $chain->next($self, $params, $chain);
	}
	// Make source local if transfer is true-ish.
	if ($entity->transfer) {
		Logger::debug('Saw request for transfer.');

		if (!$target = Media::transfer($entity->source)) {
			return false;
		}
		// Target of the transfer becomes the new source. We're not cleaning up
		// the source as we cannot be sure about its origin.
		$source = $target;
	}
	$source = parse_url($entity->source);

	if ($source['scheme'] == 'file') {
		$entity->url = Media::relativeUrl($target);
		$entity->checksum = $entity->calculateChecksum();
	} else {
		// Save all other source as-is.
		$entity->url = $source;
	}
	$entity->type      = Mime_Type::guessName($source);
	$entity->mime_type = Mime_Type::guessType($source);

	return $chain->next($self, $params, $chain);
});

// Filter running after save.
// Make versions that dependent on the saved file.
Media::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if (!$result = $chain->next($self, $params, $chain)) {
		return $result;
	}
	$versions = array('fix0', 'fix1', 'fix2', 'flux0', 'flux1');

	foreach ($versions as $version) {
		$has = MediaVersions::hasInstructions($entity->type, $version);
		if (!$has) {
			continue;
		}
		$version = MediaVersions::create([
			'media_id' => $entity->id,
			'source' => $entity->url,
			'version' => $version
			// Versions don't have an user id as their records are already
			// associated with a media_file record an thus indirectly carry an user
			// id.
		]);
		if (!$version->save()) {
			Logger::debug("Failed to save media file version `{$version}`.");
			return false;
		}
	}
	return true;
});

// Also delete dependent versions.
Media::applyFilter('delete', function($self, $params, $chain) {
	$data =& $params['data'];
	$versions = $params['entity']->versions();

	foreach ($versions as $version) {
		$version->delete();
	}
	return $chain->next($self, $params, $chain);
});

/*
Media::finder('original', function($self, $params, $chain) {
	$params['options']['conditions'] = array(
		'versions' => array('$exists' => true),
	);
	return $chain->next($self, $params, $chain);
});
Media::finder('listOriginal', function($self, $params, $chain) {
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