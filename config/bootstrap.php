<?php

use lithium\core\Environment;
use lithium\core\Libraries;
use lithium\net\http\Media;

Libraries::add('mm', array(
	'bootstrap' => 'bootstrap.php',
	'path' => dirname(__DIR__) . '/libraries/mm'
));

Environment::set(true, array(
	'modules' => array(
		'files' => array(
			'library' => 'cms_media', 'title' => 'Files', 'name' => 'files', 'slug' => 'files'
		)
	)
));

Media_Process::config([
	'audio' => 'SoxShell',
	'document' => 'Imagick',
	'image' => 'Imagick',
	'video' => 'FfmpegShell'
]);
Media_Info::config([
	'document' => ['Imagick'],
	'image' => ['ImageBasic', 'Imagick']
]);

// Use app layout for this library.
/*
Media::applyFilter('view', function($self, $params, $chain) {
	if ($params['options']['library'] == basename(dirname(__DIR__))) {
		$params['handler']['paths']['layout'] = LITHIUM_APP_PATH . '/views/layouts/{:layout}.{:type}.php';
	}
	return $chain->next($self, $params, $chain);
});
*/

?>