<?php

use lithium\core\Environment;
use lithium\core\Libraries;
use lithium\net\http\Media;
use lithium\g11n\Catalog;
use \Media_Process;
use \Media_Info;

Libraries::add('mm', [
	'bootstrap' => 'bootstrap.php',
	'path' => dirname(__DIR__) . '/libraries/mm'
]);

Environment::set(true, [
	'modules' => [
		'files' => [
			'library' => 'cms_media', 'title' => 'Files', 'name' => 'files', 'slug' => 'files'
		]
	]
]);
Media_Process::config([
	// 'audio' => 'SoxShell',
	'document' => 'Imagick',
	'image' => 'Imagick',
	// 'video' => 'FfmpegShell'
]);
Media_Info::config([
	'document' => ['Imagick'],
	'image' => ['ImageBasic', 'Imagick']
]);

Catalog::config([
	'cms_media' => [
	 	'adapter' => 'Gettext',
	 	'path' => Libraries::get('cms_media', 'resources') . '/g11n/po'
	 ]
] + Catalog::config());

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