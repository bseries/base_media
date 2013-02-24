<?php

namespace cms_media\models;

use \Mime_Type;
use \Media_Process;
use cms_media\models\Files;
use lithium\analysis\Logger;
use temporary\Manager as Temporary;

class FileVersions extends \cms_media\models\Files {}

FileVersions::applyFilter('save', function($self, $params, $chain) {
	$source = fopen('php://temp', 'wb');
	fwrite($source, $params['entity']->file); // bytes
	rewind($source);

	Logger::debug("Saving file version for file.");

	try {
		$media = Media_Process::factory(compact('source'));
	} catch (\ImagickException $e) {
		Logger::debug('Make failed with: ' . $e->getMessage());

		rewind($source);
		file_put_contents($p = Temporary::file(array('preserve' => true)), $source);
		Logger::debug('Source file saved to: ' . $p);
		throw $e;
	}
	if ($media->name() == 'image') {
		$target = fopen('php://temp', 'wb');

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