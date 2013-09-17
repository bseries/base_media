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

use lithium\core\Environment;
use lithium\core\Libraries;
use lithium\net\http\Media;
use \Media_Process;
use \Media_Info;
use lithium\g11n\Message;

extract(Message::aliases());

Libraries::add('mm', [
	'bootstrap' => 'bootstrap.php',
	'path' => dirname(__DIR__) . '/libraries/mm'
]);

Environment::set(true, [
	'modules' => [
		'files' => [
			'library' => 'cms_media',
			'title' => $t('Files'),
			'name' => 'files',
			'slug' => 'files'
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