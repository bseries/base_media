<?php

namespace cms_media\models;

use \Mime_Type;
use \Media_Process;
use cms_media\models\MediaFiles;
use lithium\analysis\Logger;
use temporary\Manager as Temporary;

class MediaFileVersions extends \lithium\data\Model {

	public function url($entity) {
		if ($entity->scheme == 'file') {
			$base = Environment::get('media.url');
			return $base . '/' . $entity->path;
		}
		return $entity->path;
	}

	public function file($entity) {
		if ($entity->scheme == 'file') {
			$base = Environment::get('media.path');
			return $base . '/' . $entity->path;
		}
	}

	public function isConsistent($entity) {
		return hash_file('md5', $entity->file) === $entity->checksum;
	}

	public static function generateTargetPath($source, $version) {
		$base = Environment::get('media.path') . '/' . $version;
		$extension = Mime_Type::guessExtension($source);

		return static::_generatePath($base, $extension);
	}

	public static function make($source, $target, $version) {
		$media = Media_Process::factory(compact('source'));

		if ($media->name() == 'image') {
			$media->convert('image/png');
			$media->fit(200, 600);
			$media->strip('8bim', 'app1', 'app12');
			$media->compress(5.5);
			$media->colorDepth(0);

			$media->store($target);

			$params['entity']->file = stream_get_contents($target);

			$params['entity']->filename = pathinfo($params['entity']->filename, PATHINFO_FILENAME) . '.png';
			$params['entity']->extension = 'png';

			fclose($source);
			fclose($target);
		} else {
			unset($params['entity']->file);
			unset($params['entity']->filename);

			fclose($source);
			return false;
		}


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

MediaFileVersions::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if (!$entity->source) {
		return $chain->next($self, $params, $chain);
	}
	$entity->path = MediaFileVersions::generateTargetPath($entity->source, $entity->version);

	$source = fopen($entity->source, 'rb');
	$target = fopen($entity->path, 'wb');

	Logger::debug("Generating version `{$entity->version}` of `{$entity->source}` to `{$entity->path}`.");
	try {
		MediaFileVersions::make($source, $target, $entity->version);
	} catch (\ImagickException $e) {
		Logger::debug('Make failed with: ' . $e->getMessage());
		return false;
	}

	return $chain->next($self, $params, $chain);
});

?>