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

namespace cms_media\models;

use Exception;
use lithium\analysis\Logger;

trait UrlTrait {

	// Assumes when requesting http, https would be ok, too.
	// Always returns absolute URLs.
	public function url($entity, $targetScheme = 'http') {
		$sourceScheme = parse_url($entity->url, PHP_URL_SCHEME);
		$sourceUrl    = static::absoluteUrl($entity->url);

		if ($targetScheme == $sourceScheme) {
			return $sourceUrl;
		}
		if ($targetScheme == 'http' && $sourceScheme == 'https') {
			return $sourceUrl;
		}

		// Fail when URL was originally absolute.
		if ($entity->url == $sourceUrl) {
			throw new Exception('Cannot transition absolute URL to different scheme.');
		}

		// Transition to new scheme by exchanging base.
		if (!$sourceBase = static::_base($sourceScheme)) {
			$message  = "Cannot transition URL `{$sourceUrl}` from scheme `{$scheme}`;";
			$message .= " no base found for scheme `{$sourceScheme}`.";
			throw new Exception($message);
		}
		if (!$targetBase = static::_base($targetScheme)) {
			$message  = "Cannot transition URL `{$sourceUrl}` to scheme `{$targetScheme}`;";
			$message .= " no base found for scheme `{$targetScheme}`.";
			throw new Exception($message);
		}
		return str_replace($sourceBase, $targetBase, $sourceUrl);
	}

	// Ensures an URL is absolute.
	public static function absoluteUrl($url) {
		$scheme = parse_url($url, PHP_URL_SCHEME);
		// Note: parse_url only partially works with relative URLs.

		if ($url[strlen($scheme . '://')] == '/') {
			return $url; // already absolute
		}
		if (!$base = static::_base($scheme)) {
			throw new Exception("Cannot make URL `{$url}` absolute; no base found for scheme `{$scheme}`.");
		}
		return str_replace($scheme . '://', $base . '/', $url);
	}

	// Ensures an URL is relative.
	public static function relativeUrl($url) {
		$scheme = parse_url($url, PHP_URL_SCHEME);
		$path   = parse_url($url, PHP_URL_PATH);

		if ($path[0] != '/') {
			return $url; // already relative
		}
		if (!$base = static::_base($scheme)) {
			throw new Exception("Cannot make URL `{$url}` relative; no base found for scheme `{$scheme}`.");
		}
		return str_replace($base . '/', $scheme . '://', $url);
	}

	// By default doesn't do file_exists checks.
	// @fixme Re-factor this into Media_Util::generatePath()
	protected static function _uniqueUrl($base, $extension, array $options = []) {
		$options += ['exists' => false];

		$chars = 'abcdef0123456789';
		$length = 8;

		// Birthday problem: Likelihood of collision with 1M strings is 0.18%.
		// Prevent collisions. If this happens too "often" expand charset first.
		do {
			// Generate a random string for each round.
			$random = '';
			while (strlen($random) < $length) {
				$random .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
			}
			$path = substr($random, 0, 2) . '/' . substr($random, 2);

			if (!empty($extension)) {
				$path .= '.' . $extension;
	 		}
		} while (!$options['exists'] || file_exists($base . '/' . $path));

		return $base . '/' . $path;
	}

	// Delete only files that are local and within base.
	public function deleteUrl($entity) {
		$url = static::absoluteUrl($entity->url);

		if (strpos($url, static::_base('file')) === false) {
			Logger::warning("Cannot delete URL `{$url}`; is not within base.");
			return false;
		}
		if (!unlink($url)) {
			return false;
		}
		return true;
	}
}

?>