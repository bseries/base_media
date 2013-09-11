<?php

namespace cms_media\models;

use \Mime_Type;
use \Media_Process;
use cms_media\models\MediaFiles;
use lithium\analysis\Logger;
use temporary\Manager as Temporary;

class MediaFileVersions extends \lithium\data\Model {

	// Returns the assembly instructions for a specific version.
	protected static function _instructions($version) {
		$sRGB = APP . 'plugins/media/libs/mm/data/sRGB_IEC61966-2-1_black_scaled.icc';

		/* Base static versions. */

		$fix = [
			'convert' => 'image/png',
			'compress' => 5.5,
			'colorProfile' => $sRGB,
			'colorDepth' => 8,
			/* @see MediaFile::_crush() */
			'crush' => true
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

		/* Base timebased versions.
		   flux0 is always closed, flux1 is open format. */

		$fluxAudio = [
			'sampleRate' => 48000,
			'channels' => 2
		];
		$fluxVideo = [
			'fit' => [680, 470], // 1280x720 hd, 640x480, 680x470
			'threads' => 2, // 0 to auto-select number of threads
			'ar' => 48000,
			/* @see MediaFile::_faststart() */
			'faststart' => true
		];

		/* Filter and version definitions. */

		Configure::write('Media.filter', [
			'audio' => [
				/* @see MediaFile::_waveform() */
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
		]);

	}

	public function url($entity) {
		if ($entity->scheme == 'file') {
			$base = Environment::get('media.url');
			return $base . '/' . $entity->path;
		}
		return $entity->path;
	}

	public function file($entity) {
		if ($entity->scheme == 'file') {
			$base = Environment::get('media.path');
			return $base . '/' . $entity->path;
		}
	}

	public function isConsistent($entity) {
		return hash_file('md5', $entity->file) === $entity->checksum;
	}

	public static function generateTargetPath($source, $version) {
		$base = Environment::get('media.path') . '/' . $version;

		$path  = $base;
		$path .= '/' . pathinfo($source, PATHINFO_DIRNAME);
		$path .= '/' . pathinfo($source, PATHINFO_FILENAME);

		// Instead of re-using the extension from source we have to take the
		// target extension into account as the target maybe converted.
		$extension = Mime_Type::guessExtension($version['convert']);

		if ($extension) {
			$path .= '.' . $extension;
		}
		return $path;
	}

	public static function make($source, $target, $version) {
		$media = Media_Process::factory(compact('source'));

		if ($media->name() == 'image') {
			$media->convert('image/png');
			$media->fit(200, 600);
			$media->strip('8bim', 'app1', 'app12');
			$media->compress(5.5);
			$media->colorDepth(0);

			$media->store($target);

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
	}
}

MediaFileVersions::applyFilter('save', function($self, $params, $chain) {
	$entity = $params['entity'];

	if (!$entity->source) {
		return $chain->next($self, $params, $chain);
	}
	$entity->path = MediaFileVersions::generateTargetPath($entity->source, $entity->version);

	$source = fopen($entity->source, 'rb');
	$target = fopen($entity->path, 'wb');

	Logger::debug("Generating version `{$entity->version}` of `{$entity->source}` to `{$entity->path}`.");
	try {
		MediaFileVersions::make($source, $target, $entity->version);
	} catch (\ImagickException $e) {
		Logger::debug('Make failed with: ' . $e->getMessage());
		return false;
	}

	return $chain->next($self, $params, $chain);
});

?>