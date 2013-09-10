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
		if (isset($entity->file)) {
			return $entity->file;
		}
		if ($entity->scheme == 'file') {
			$base = Environment::get('media.path');
			return $base . '/' . $entity->path;
		}
	}

	public static function generateTargetPath() {

	}
}

MediaFileVersions::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];
	$source = fopen('php://temp', 'wb');
	$target = fopen(, 'wb');

	// Turn original file into source.
	stream_copy_to_stream($entity->file(), $source);
	rewind($source);

	Logger::debug("Saving file version for file.");
	try {
		$media = Media_Process::factory(compact('source'));
	} catch (\ImagickException $e) {
		Logger::debug('Make failed with: ' . $e->getMessage());

//		rewind($source);
//		file_put_contents($p = Temporary::file(array('preserve' => true)), $source);
//		Logger::debug('Source file saved to: ' . $p);
		throw $e;
	}

	if ($media->name() == 'image') {
		$media->convert('image/png');
		$media->fit(200, 600);
		$media->strip('8bim', 'app1', 'app12');
		$media->compress(5.5);
		$media->colorDepth(0);

		$media->store($target);
		rewind($target);

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
	return $chain->next($self, $params, $chain);
});

?>