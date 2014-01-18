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

use lithium\core\Libraries;
use \Media_Process;
use \Media_Info;
use lithium\g11n\Message;
use cms_core\extensions\cms\Modules;
use cms_core\extensions\cms\Features;
use cms_media\models\Media;
use cms_media\models\MediaVersions;

extract(Message::aliases());

Libraries::add('mm', [
	'bootstrap' => 'bootstrap.php',
	'path' => dirname(__DIR__) . '/libraries/mm'
]);

Modules::register('cms_media', 'files', ['title' => $t('Files')]);
Features::register('enableRegenerateVersions', false);

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

// Registers Media and MediaVersions schemes. The `base` key of each
// scheme is intentionally left unset. This must be added by the app
// as we cannot provide sane defaults here.

Media::registerScheme('file', [
	'relative' => true,
	'checksum' => true,
	'transfer' => true,
	'delete' => true
]);

// Original media files are not accessible through the web
// directly - security reasons. That's why we don't give
// a `base` here.
Media::registerScheme('http', [
	'download' => true
]);
Media::registerScheme('https', [
	'download' => true
]);

// When http/https URLs get here, the resources behind them
// are not downloaded or processed in any way. They are
// linked to.
//
// This is useful when you have a remote entity as the `Media`
// i.e. (a vimeo video) that already comes with pregenerated
// versions (posters, images) that are similar to the versions
// that would normally be generated using the instructions and
// you just want to use them by roughly mapping to their
// instructed versions.
//
// That's why linked versions must _not_ necessarily adhere to
// the constraints specified in the instructions.
MediaVersions::registerScheme('http', [
	'download' => true
]);
MediaVersions::registerScheme('https', [
	'download' => true
]);

// Processe a local file an generates versions according
// to instructions. The resulting versions will adhere to
// the constraints specified in the instructions.
MediaVersions::registerScheme('file', [
	'relative' => true,
	'checksum' => true,
	'delete' => true,
	'make' => function($entity) {
		$media = Media_Process::factory(['source' => $entity->url]);
		$target = MediaVersions::generateTargetUrl($entity->url, $entity->version);
		$instructions = MediaVersions::assembly($media->name(), $entity->version);

		if (!is_dir(dirname($target))) {
			mkdir(dirname($target), 0777, true);
		}

		// Process builtin instructions.
		if (isset($instructions['clone'])) {
			$action = $instructions['clone'];

			if (in_array($action, array('copy', 'link', 'symlink'))) {
				if (call_user_func($action, $source, $target)) {
					return $target;
				}
			}
			return false;
		}

		// Process `Media_Process_*` instructions.
		// This part may throw exceptions which are catched by the callee.
		foreach ($instructions as $method => $args) {
			if (is_int($method)) {
				$method = $args;
				$args = null;
			}
			if (method_exists($media, $method)) {
				$result = call_user_func_array(array($media, $method), (array) $args);
			} else {
				$result = $media->passthru($method, $args);
			}
			if ($result === false) {
				return false;
			} elseif (is_a($result, 'Media_Process_Generic')) {
				$media = $result;
			}
		}
		return $media->store($target);
	}
]);

?>