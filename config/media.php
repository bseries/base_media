<?php
/**
 * Bureau Media
 *
 * Copyright (c) 2013-2014 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

use mm\Media\Process;
use mm\Media\Info;
use cms_media\models\Media;
use cms_media\models\MediaVersions;
use lithium\core\Libraries;

Process::config([
	// 'audio' => 'SoxShell',
	'document' => 'Imagick',
	'image' => 'Imagick',
	// 'video' => 'FfmpegShell'
]);
Info::config([
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
		$media = Process::factory(['source' => $entity->url]);
		$target = MediaVersions::generateTargetUrl($entity->url, $entity->version);

		// There may not be a flux0 version for an image type.
		if (!$instructions = MediaVersions::assembly($media->name(), $entity->version)) {
			return null;
		};

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

		// Process media `Process` instructions.
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
			} elseif (is_a($result, '\mm\Media\Process\Generic')) {
				$media = $result;
			}
		}
		return $media->store($target);
	}
]);

// Workaround beacause we do not have mm added to libraries, yet.
$sRGB = Libraries::get('app', 'path') . '/libraries/davidpersson/mm/data/sRGB_IEC61966-2-1_black_scaled.icc';

// Base static versions.
$fix = [
	'convert' => 'image/png',
	'compress' => 5.5,
	'colorProfile' => $sRGB,
	'colorDepth' => 8
];
$fix2 = [ // Used in journal index and partially in view.
	'strip' => ['8bim', 'app1', 'app12'],
	'fit' => [500, 500]
];
$fix3 = [ // Used in admin.
	'strip' => ['xmp', '8bim', 'app1', 'app12', 'exif'],
	'fit' => [100, 52]
];

MediaVersions::registerAssembly('document', 'fix2admin', $fix2 + $fix);
MediaVersions::registerAssembly('document', 'fix3admin', $fix3 + $fix);
MediaVersions::registerAssembly('image', 'fix2admin', $fix2 + $fix);
MediaVersions::registerAssembly('image', 'fix3admin', $fix3 + $fix);

?>