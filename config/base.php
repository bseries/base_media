<?php
/**
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace base_media\config;

use Exception;
use base_core\extensions\cms\Settings;
use base_media\models\Media;
use base_media\models\MediaVersions;
use base_media\models\RemoteMedia;
use lithium\analysis\Logger;
use lithium\core\Libraries;
use lithium\storage\Cache;
use mm\Media\Info;
use mm\Media\Process;
use mm\Mime\Type;

// Enable triggering of regeneration of media versions through
// the admin.
Settings::register('media.allowRegenerateVersions', false);

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
	'base' => PROJECT_PATH . (PROJECT_WEBROOT_NESTING ? '/app/webroot' : '') . '/media',
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
	'base' => PROJECT_PATH . (PROJECT_WEBROOT_NESTING ? '/app/webroot' : '') . '/media_versions',
	'relative' => true,
	'checksum' => true,
	'delete' => true,
	'make' => function($entity) {
		// Check if we can process the source at all. If not
		// always skip. Needed to support generic files.
		$name = Type::guessName($entity->url);

		// There may i.e. not be a flux0 version for an image type.
		if (!$instructions = MediaVersions::assembly($entity->url, $entity->version)) {
			Logger::debug("No instructions for type `{$name}` and version `{$entity->version}`.");
			return null; // Skip.
		};

		// Reformat instructions so we do not loose animations. Must protect with
		// image pre-condition as videos might also get here and there are no
		// video media info adapters.
		if ($name === 'image' && Info::factory(['source' => $entity->url])->get('isAnimated')) {
			Logger::debug("Detected source `{$entity->url}` as animated.");

			$instructions['convert'] = 'image/gif';
			unset(
				$instructions['background'],
				$instructions['interlace'],
				$instructions['compress']
			);
		}

		// Implements auto-rotation support. Pictures taken with a mobile device are often
		// unrotated and need to be rotated using EXIF data.
		//
		// http://www.daveperrett.com/images/articles/2012-07-28-exif-orientation-handling-is-a-ghetto/EXIF_Orientations.jpg
		if (isset($instructions['rotate']) && $instructions['rotate'] === true) {
			$exifToDegrees = [
				3 => 180,
				6 => -90,
				8 => 90
			];
			$orientation = Info::factory(['source' => $entity->url])->get('orientation');

			if (!$orientation || !isset($exifToDegrees[$orientation])) {
				// Do not raise notice, as probably many images have no exif data, but are
				// already properly rotated.
				Logger::debug('Cannot auto-rotate media, failed to get supported orientation.');
				unset($instructions['rotate']);
			} else {
				$instructions['rotate'] = $exifToDegrees[$orientation];
			}
		}

		// Create target.
		$target = MediaVersions::generateTargetUrl($entity->url, $entity->version, $instructions);

		if (!is_dir(dirname($target))) {
			mkdir(dirname($target), 0777, true);
		}

		// Process builtin instructions. Clone works shortcircuits media processing
		// we can even clone media that does not have any processing adapter.
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

		if (!isset(Process::config()[$name])) {
			Logger::debug("Missing media process adapter for `{$name}`.");
			return null; // Skip.
		}
		$media = Process::factory(['source' => $entity->url]);

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

//
// Remote Media Providers Processing Make Handlers
//
$makeRemoteImage = function($entity) {
	// URL is in <PROVIDER>://<ID> form
	$convert = RemoteMedia::provider($entity->url)['convertToExternalUrl'];
	$ext = RemoteMedia::createFromUrl($convert($entity->url));

	// This changes the scheme of the entity, thus it capabilities.
	$entity->url = $ext->thumbnailUrl;

	if (!$entity->can('download')) {
		$message  = "Can't download image/poster URL `{$entity->url}`. ";
		$message .= "You need to register a http scheme with downloading enabled to do so.";
		throw new Exception($message);
	}
	$entity->url = $entity->download();

	$handler = MediaVersions::registeredScheme('file')['make'];
	return $handler($entity);
};

// Uses provider's thumbnail and generates our local versions off it. Will
// not store/link versions for the video files themselves as those cannot
// be reached through most provider APIs. This handler doesn't actually make the
// files itself but uses a generic file make handler to do so.
$makeRemoteVideo = function($entity) use ($makeRemoteImage) {
	// No video versions for this video are made. Frontend
	// code should use the provider video ID of the Media-Entity to load
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
	return $makeRemoteImage($entity);
};

// Register remote video and image media providers only.
foreach (RemoteMedia::providers() as $provider) {
	if ($provider['type'] === 'video') {
		MediaVersions::registerScheme($provider['name'], ['make' => $makeRemoteVideo]);
	} elseif ($provider['type'] === 'image') {
		MediaVersions::registerScheme($provider['name'], ['make' => $makeRemoteImage]);
	} elseif ($provider['type'] === 'audio') {
		MediaVersions::registerScheme($provider['name'], ['make' => $makeRemoteImage]);
	}
}

//
// ### Setup MM
//
$mm = PROJECT_PATH . '/app/libraries/davidpersson/mm';

Type::config('magic', [
	'adapter' => 'Fileinfo'
]);
if ($cached = Cache::read('default', 'mime_type_glob')) {
	Type::config('glob', [
		'adapter' => 'Memory'
	]);
	foreach ($cached as $item) {
		Type::$glob->register($item);
	}
} else {
	Type::config('glob', [
		'adapter' => 'Freedesktop',
		'file' => $mm . '/data/glob.db'
	]);
	Cache::write('default', 'mime_type_glob', Type::$glob->to('array'));
}
Info::config([
	'image' => PROJECT_HAS_IMAGICK ? ['ImageBasic', 'Imagick', 'Exif'] : ['ImageBasic', 'Exif'],
	'document' => PROJECT_HAS_GHOSTSCRIPT ? 'Imagick' : null,
	'video' => null,
	'audio' => ['NewWave']
]);
Process::config([
	'image' => PROJECT_HAS_IMAGICK ? 'Imagick' : 'Gd',
	'document' => PROJECT_HAS_GHOSTSCRIPT ? 'Imagick' : null,
	'video' => PROJECT_HAS_FFMPEG ? 'FfmpegShell' : null,
	'audio' => PROJECT_HAS_SOX ? 'SoxShell' : null
]);

//
// ### Media Version Assemblies
//
// Defines the instructions for each version. The convention is to use _flux_ as a prefix
// for all timebased media and _fix_ as a prefix for all static versions. For each flux
// and fixed version there are multiple variants. Best quality versions i.e. contain a 0
// whereas `fix3` would denote a static version with lower quality or size. `flux0` is
// always a closed format and `flux1` of open format.
//
// Here we define only the admin versions as we don't want the app versions to interfer
// with the admin.
//

$sRGB  = Libraries::get('app', 'path');
$sRGB .= '/libraries/davidpersson/mm/data/sRGB_IEC61966-2-1_black_scaled.icc';

if (PROJECT_HAS_IMAGICK) {
	$fix = [
		'convert' => 'image/png',
		'compress' => 5.5,
		'colorProfile' => $sRGB,
		'colorDepth' => 8,
		'rotate' => true,
		'strip' => ['xmp', '8bim', 'app1', 'app12', 'exif'],
	];
} else {
	$fix = [
		'convert' => 'image/png',
		'compress' => 5.5,
		'rotate' => true
	];
}

if (PROJECT_HAS_GHOSTSCRIPT) {
	MediaVersions::registerAssembly('document', 'fix2admin', $fix + [
		'fit' => [500, 500]
	]);
	MediaVersions::registerAssembly('document', 'fix3admin', $fix + [
		'fit' => [100, 52]
	]);
}
MediaVersions::registerAssembly('document', 'flux0admin', [
	'clone' => 'symlink'
]);

MediaVersions::registerAssembly('image', 'fix2admin', $fix + [
	'fit' => [500, 500]
]);
MediaVersions::registerAssembly('image', 'fix3admin', $fix + [
	'fit' => [100, 42]
] + $fix);

if (PROJECT_HAS_SOX) {
	$fluxAudio = [
		'sampleRate' => 48000,
		'channels' => 2
	];
	MediaVersions::registerAssembly('audio', 'flux0aadmin', [
		'convert' => 'audio/mpeg'
	] + $fluxAudio);
	MediaVersions::registerAssembly('audio', 'flux0badmin', [
		'convert' => 'audio/ogg'
	] + $fluxAudio);
}

MediaVersions::registerAssembly('video', 'fix2admin',
	MediaVersions::assembly('image', 'fix2admin')
);
MediaVersions::registerAssembly('video', 'fix3admin',
	MediaVersions::assembly('image', 'fix3admin')
);

if (PROJECT_HAS_FFMPEG) {
	$fluxVideo = [
		'fit' => [680, 470], // 1280x720 hd, 640x480, 680x470
		'threads' => 2, // 0 to auto-select number of threads
		'ar' => 48000,
		// 'faststart' => true
	];
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
}

?>