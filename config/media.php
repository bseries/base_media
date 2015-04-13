<?php
/**
 * Base Media
 *
 * Copyright (c) 2013-2014 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

use base_media\models\Media;
use base_media\models\MediaVersions;
use base_media\models\RemoteMedia;
use lithium\core\Libraries;
use lithium\analyis\Logger;
use mm\Media\Process;
use mm\Media\Info;
use mm\Mime\Type;
use Cute\Handlers;
use \Exception;

// Registers Media and MediaVersions schemes. The `base` key of each
// scheme is intentionally left unset. This must be added by the app
// as we cannot provide sane defaults here.
//
// @see base_media\models\Media::registerScheme()
// @see base_media\models\MediaVersions::registerScheme()

// Original media files are not accessible through the web
// directly - security reasons. That's why we don't give
// a `base` here. Always download http sources.
Media::registerScheme('http', [
	'download' => true
]);
Media::registerScheme('https', [
	'download' => true
]);
Media::registerScheme('file', [
	'base' => PROJECT_MEDIA_FILE_BASE,
	'relative' => true,
	'checksum' => true,
	'transfer' => true,
	'delete' => true
]);

// Allows storing `vimeo://[ID]` style URLs but allow
// grouping the file by video type.
foreach (RemoteMedia::providers() as $provider) {
	Media::registerScheme($provider['name'], [
		'mime_type' => $provider['mime_type'],
		'type' => $provider['type']
	]);
}

// ### Media Version Schemes
//
// When http/https URLs get here, the resources behind them
// are not downloaded or processed in any way. They are
// linked to.
//
// FIXME Clarify if http media versions are downloaded or not.
//
// ### Media Version Make Handlers
//
// Handlers will generate a version off the passed entity. They must
// either return `true`/`false` to indicate success/failure or return
// `null` to indicate that the version doesn't need to be made.
//
// Returning `null` will effectively link the version. This is useful
// when you have a remote entity as the `Media` i.e. (a vimeo video)
// that already comes with pregenerated versions (posters, images)
// that are similar to the versions that would normally be generated
// using the instructions and you just want to use them by roughly
// mapping to their instructed versions.
//
// The handler gets passed a MediaVersion entity and must then retrieve
// instructions via MediaVersions::assembly(). These instructions should
// then be used to generate a version.
//
MediaVersions::registerScheme('http', [
	'base' => PROJECT_MEDIA_VERSIONS_HTTP_BASE,
	'download' => true
]);
MediaVersions::registerScheme('https', [
	'base' => PROJECT_MEDIA_VERSIONS_HTTPS_BASE,
	'download' => true
]);
MediaVersions::registerScheme('file', [
	'base' => PROJECT_MEDIA_VERSIONS_FILE_BASE,
	'relative' => true,
	'checksum' => true,
	'delete' => true,
	'make' => function($entity) {
		$media = Process::factory(['source' => $entity->url]);
		$target = MediaVersions::generateTargetUrl($entity->url, $entity->version);

		// There may i.e. not be a flux0 version for an image type.
		if (!$instructions = MediaVersions::assembly($media->name(), $entity->version)) {
			return null; // Skip.
		};

		if (!is_dir(dirname($target))) {
			mkdir(dirname($target), 0777, true);
		}

		// Process builtin instructions.
		if (isset($instructions['clone'])) {
			$action = $instructions['clone'];

			if (in_array($action, ['copy', 'link', 'symlink'])) {
				if (call_user_func($action, parse_url($entity->url, PHP_URL_PATH), parse_url($target, PHP_URL_PATH))) {
					return $target;
				} else {
					return false;
				}
			}
			throw new Exception("Invalid clone instruction action `{$action}`.");
		}

		// Process media `Process` instructions.
		// This part may throw exceptions which are catched by the callee.
		foreach ($instructions as $method => $args) {
			if (is_int($method)) {
				$method = $args;
				$args = null;
			}
			if (method_exists($media, $method)) {
				$result = call_user_func_array([$media, $method], (array) $args);
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

foreach (RemoteMedia::providers() as $provider) {
	if ($provider['type'] !== 'video') {
		// The handler below works for video only.
		continue;
	}

	// Uses Vimeo's thumbnail and generates our local versions off it. Will
	// not store/link versions for the video files themselves as those cannot
	// be reached through the Vimeo API. This handler doesn't actually make the
	// files itself but uses a generic file make handler to do so.
	MediaVersions::registerScheme($provider['name'], [
		'make' => function($entity) {
			// No video versions for this vimeo video are made. Frontend
			// code should use the Vimeo ID of the Media-Entity to load
			// the actual video.
			if ($assembly = MediaVersions::assembly('video', $entity->version)) {
				if (isset($assembly['convert'])) {
					if (Type::guessName($assembly['convert']) === 'video') {
						return null;
					}
				} else {
					$message  = 'Cannot reliably determine if this is a video version; fallback';
					$message .= 'to heuristics.';
					Logger::debug($message);

					if (strpos($entity->version, 'flux') !== false) {
						return null;
					}
				}
			}
			// URL is in <PROVIDER>://<ID> form
			$covert = RemoteMedia::provider($entity->url)->convertToExternalUrl;
			$ext = RemoteMedia::createFromUrl($convert($entity->url));

			// This changes the scheme of the entity, thus it capabilities.
			$entity->url = $ext->thumbnailUrl;

			if (!$entity->can('download')) {
				$message  = "Can't download video poster URL `{$entity->url}`. ";
				$message .= "You need to register a http scheme with downloading enabled to do so.";
				throw new Exception($message);
			}
			$entity->url = $entity->download();

			$handler = MediaVersions::registeredScheme('file', 'make');
			return $handler($entity);
		}
	]);
}

//
// ### Handlers
//
// Wire cute handler to make function.
Handlers::register('MediaVersions::make', function($data) {
	if (MediaVersions::pdo()->inTransaction()) {
		MediaVersions::pdo()->rollback();
	}
	MediaVersions::pdo()->beginTransaction();

	if (MediaVersions::make($data['mediaId'], $data['version'])) {
		MediaVersions::pdo()->commit();
		return true;
	}

	MediaVersions::pdo()->rollback();
	return false;
});

//
// ### Process Adapter Configuration
//
// Configure processing of media.
Process::config([
	'audio' => 'SoxShell',
	'document' => PROJECT_FEATURE_IMAGICK ? 'Imagick' : null,
	'image' => PROJECT_FEATURE_IMAGICK ? 'Imagick' : 'Gd',
	'video' => 'FfmpegShell'
]);
Info::config([
	'document' => PROJECT_FEATURE_IMAGICK ? 'Imagick' : null,
	'image' => PROJECT_FEATURE_IMAGICK ? ['ImageBasic', 'Imagick'] : ['ImageBasic']
]);

//
// ### Media Version Assemblies
//
// Defines the instructions for each version. The convention is
// to use _flux_ as a prefix for all timebased media and _fix_
// as a prefix for all static versions. For each flux and fixed
// version there are multiple variants. Best quality versions
// i.e. contain a 0 whereas `fix3` would denote a static version
// with lower quality or size. `flux0` is always a closed format
// and `flux1` of open format.
//
// Here we define only the admin versions as we don't want
// the app versions to interfer with the admin.
//

$sRGB  = Libraries::get('app', 'path');
$sRGB .= '/libraries/davidpersson/mm/data/sRGB_IEC61966-2-1_black_scaled.icc';

if (PROJECT_FEATURE_IMAGICK) {
	$fix = [
		'convert' => 'image/png',
		'compress' => 5.5,
		// FIXME Reenable once imagick doesn't segfault applying the profile
		// on PDFs anymore.
		// 'colorProfile' => $sRGB,
		'colorDepth' => 8,
		'strip' => ['xmp', '8bim', 'app1', 'app12', 'exif'],
	];
} else {
	$fix = [
		'convert' => 'image/png',
		'compress' => 5.5
	];
}
$fluxAudio = [
	'sampleRate' => 48000,
	'channels' => 2
];
$fluxVideo = [
	'fit' => [680, 470], // 1280x720 hd, 640x480, 680x470
	'threads' => 2, // 0 to auto-select number of threads
	'ar' => 48000,
	// 'faststart' => true
];

MediaVersions::registerAssembly('document', 'fix2admin', $fix + [
	'fit' => [500, 500]
]);
MediaVersions::registerAssembly('document', 'fix3admin', $fix + [
	'fit' => [100, 52]
]);
MediaVersions::registerAssembly('document', 'flux0admin', [
	'clone' => 'symlink'
]);
MediaVersions::registerAssembly('image', 'fix2admin', $fix + [
	'fit' => [500, 500]
]);
MediaVersions::registerAssembly('image', 'fix3admin', $fix + [
	'fit' => [100, 42]
] + $fix);
MediaVersions::registerAssembly('audio', 'flux0aadmin', [
	'convert' => 'audio/mpeg'
] + $fluxAudio);
MediaVersions::registerAssembly('audio', 'flux0badmin', [
	'convert' => 'audio/ogg'
] + $fluxAudio);

MediaVersions::registerAssembly('video', 'fix2admin',
	MediaVersions::assembly('image', 'fix2admin')
);
MediaVersions::registerAssembly('video', 'fix3admin',
	MediaVersions::assembly('image', 'fix3admin')
);
MediaVersions::registerAssembly('video', 'flux0admin', $fluxVideo + [
	'convert' => 'video/webm',
	'codec:v' => 'libvpx',
	'threads' => 2, // must come after codec:v
	'b:v' => '1024k', // video bitrate
	'maxrate' => '1024k',
	'bufsize' => '2048k', // twice the maxrate, overshooting
	'qmin' => 10, // fixing broken behavior since ffmpeg 0.9
	'qmax' => 42, // -- * --
	'quality' => 'good', // rec. good in combination with cpu-used 0
	'cpu-used' => 0, // speed for quality, lower = better quality
	'codec:a' => 'libvorbis',
	'b:a' => '192k' // audio bitrate
]);

?>