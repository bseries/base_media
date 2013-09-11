<?php

namespace cms_media\models;

use \Mime_Type;
use \Media_Process;
use lithium\analysis\Logger;
use temporary\Manager as Temporary;
use lithium\core\Libraries;

class MediaVersions extends \lithium\data\Model {

	use \cms_media\models\ChecksumTrait;
	use \cms_media\models\UrlTrait;

	protected static function _base($scheme) {
		return Environment::get('mediaFileVersions.' . $scheme);
	}

	protected static function _generateTargetUrl($source, $version) {
		$base = static::_base('file') . '/' . $version;
		$instructions = static::_instructions(Mime_Type::guessName($source), $version);

		if (isset($instructions['clone'])) {
			// Guess from source filename or contents.
			$extension = Mime_Type::guessExtension($source);
		} else {
			// Instead of re-using the extension from source we have to take
			// the target extension into account as the target maybe converted;
			// we guess from the MIME type as this is fastest.
			$extension = Mime_Type::guessExtension($instructions['convert']);
		}
		return static::_uniqueUrl($base, $extension, ['exists' => true]);
	}

	// Will (re-)generate version from source and return target path on success.
	public static function make($sourcce, $version) {
		if (parse_url($source, PHP_URL_SCHEME) != 'file') {
			throw new Exception('Can only make from source with file scheme.');
		}

		$media = Media_Process::factory(['source' => $source]);
		$target = static::_generateTargetUrl($source, $version);
		$instructions = static::_instructions($media->name(), $entity->version);

		Logger::debug("Making version `{$version}` of `{$source}` to `{$target}`.");

		// Process builtin instructions.
		if (isset($instructions['clone'])) {
			$action = $instructions['clone'];

			if (in_array($action, array('copy', 'link', 'symlink'))) {
				if (call_user_func($action, $source, $target)) {
					return true;
				}
			}
			return false;
		}
		try {
			// Process `Media_Process_*` instructions
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
			$target = $media->store($target);

		} catch (\ImagickException $e) {
			Logger::debug('Making entity failed with: ' . $e->getMessage());
			return false;
		}
		return $target;
	}

	public static function hasInstructions($type, $version) {
		return (boolean) static::_instructions($type, $version);
	}

	// Returns the assembly instructions for a specific media type and version.
	protected static function _instructions($type, $version) {
		$sRGB = Libraries::get('mm', 'path') . '/data/sRGB_IEC61966-2-1_black_scaled.icc';

		// Base static versions.

		$fix = [
			'convert' => 'image/png',
			'compress' => 5.5,
			'colorProfile' => $sRGB,
			'colorDepth' => 8,
			// @see MediaFile::_crush()
			// 'crush' => true
		];
		$fix0 = [
			'strip' => ['8bim', 'app1', 'app12'],
			'fit' => [680, 470]
		];
		$fix1 = [
			'strip' => ['8bim', 'app1', 'app12'],
			'fit' => [390, 410]
		];
		$fix2 = [
			'strip' => ['8bim', 'app1', 'app12'],
			'fit' => [300, 300]
		];
		$fix3 = [
			'strip' => ['xmp', '8bim', 'app1', 'app12', 'exif'],
			'fit' => [100, 52]
		];

		// Base timebased versions.
		// flux0 is always closed, flux1 is open format.

		$fluxAudio = [
			'sampleRate' => 48000,
			'channels' => 2
		];
		$fluxVideo = [
			'fit' => [680, 470], // 1280x720 hd, 640x480, 680x470
			'threads' => 2, // 0 to auto-select number of threads
			'ar' => 48000,
			// @see MediaFile::_faststart()
			// 'faststart' => true
		];

		// Filter and version definitions.

		$instructions = [
			'audio' => [
				// @see MediaFile::_waveform()
				'fix0' => ['waveform' => [180, 75]],
				'fix1' => ['waveform' => [180, 75]],
				'fix2' => ['waveform' => [180, 75]],
				'fix3' => ['waveform' => $fix3['fit']],
				'flux0' => $fluxAudio + [
					'convert' => 'audio/mpeg'
					// 'bitRate' => '192k'
				],
				'flux1' => $fluxAudio + [
					'convert' => 'audio/ogg'
					// 'bitRate' => '192k',
					// 'q' => 6 // equals a 192 kbit/s bitrate understood by the ogg encoder only
				]
			],
			'document' => [
				'fix0' => $fix + $fix0,
				'fix1' => $fix + $fix1,
				'fix2' => $fix + $fix2,
				'fix3' => $fix + $fix3,
				'flux0' => [
					'clone' => 'symlink'
				]
			],
			'image' => [
				'fix0' => $fix + $fix0,
				'fix1' => $fix + $fix1,
				'fix2' => $fix + $fix2,
				'fix3' => $fix + $fix3
			],
			'video' => [
				'fix0' => $fix + $fix0,
				'fix1' => $fix + $fix1,
				'fix2' => $fix + $fix2,
				'fix3' => $fix + $fix3,
				'flux0' => $fluxVideo + [
					'convert' => 'video/mp4',
					'acodec' => 'libfaac',
					'vcodec' => 'libx264',
					'vpre' => 'libx264-ipod640',
					'ab' => '192k',
					'b' => '512k'
				],
				'flux1' => $fluxVideo + [
					'convert' => 'video/ogg',
					'vcodec' => 'libtheora',
					'acodec' => 'libvorbis',
					'aq' => 6,
					'b' => '1024k',
					'qscale' => 8 // Theora quality, higher is better.
				]
			]
		];
		if (isset($instructions[$type][$version])) {
			return $instructions[$type][$version];
		}
		return false;
	}
}

MediaVersions::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if (!$entity->source || !$entity->version) {
		// @fixme Consider failing here.
		return $chain->next($self, $params, $chain);
	}
	$source = parse_url($entity->source);

	if ($source['scheme'] == 'file') {
		// We can only "make" local file versions.
		if (!$target = MediaVersions::make($entity->source, $entity->version)) {
			return false;
		}
		$entity->url = MediaVersions::relativeUrl($target);
		$entity->checksum = $entity->calculateChecksum();
	} else {
		// Save all other source as-is.
		$entity->url = $source;
		$entity->checksum = null;
	}
	$entity->type      = Mime_Type::guessName($target);
	$entity->mime_type = Mime_Type::guessType($target);

	unset($entity->source);

	return $chain->next($self, $params, $chain);
});

?>