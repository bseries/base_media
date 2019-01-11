<?php
/**
 * Copyright 2018 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace base_media\models;

use ColorThief\ColorThief;
use Exception;
use Imagick;
use lithium\storage\Cache;

// Expects $entity to implement url() method, so we can retrieve the underlying physical
// file for analysis.
trait ColorTrait {

	public function dominantColor($entity) {
		$cacheKey = Cache::key('default', 'dominant_color', $entity->id);

		if ($cached = Cache::read('default', $cacheKey)) {
			return $cached;
		}
		$result = ColorThief::getColor($entity->url('file'), 10);

		Cache::write('default', $cacheKey, $result, Cache::PERSIST);
		return $result;
	}

	// Implements and uses the Weighted Distance in 3D RGB Space (or the HSP algorithm)
	// to determine the perceived brightness of the product color, than categorizing it
	// either as `'dark'` or `'bright'`. The binariness of the result can be used to
	// easily find a readable text color.
	//
	// http://www.nbdtech.com/Blog/archive/2008/04/27/Calculating-the-Perceived-Brightness-of-a-Color.aspx
	public function perceivedBrightness($entity, $threshold = 140) {
		if (!extension_loaded('imagick')) {
			throw new Exception('Need imagick extension, to perform analysis');
		}
		$cacheKey = Cache::key('default', 'perceived_brightness', $entity->id);

		if ($cached = Cache::read('default', $cacheKey)) {
			return $cached;
		}
		$image = new Imagick($entity->url('file'));

		$image->scaleImage(1, 1);
		$pixels = $image->getImageHistogram();
		$color = $pixels[0]->getColor();
		$color = [$color['r'], $color['g'], $color['b']];

		$perceivedBrightness = sqrt(
			pow($color[0], 2) * 0.241 +
			pow($color[1], 2) * 0.691 +
			pow($color[2], 2) * 0.068
		);

		$result = $perceivedBrightness < $threshold ? 'dark' : 'bright';
		Cache::write('default', $cacheKey, $result, Cache::PERSIST);
		return $result;
	}
}

?>
