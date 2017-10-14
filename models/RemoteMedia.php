<?php
/**
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace base_media\models;

use lithium\storage\Cache;
use lithium\analysis\Logger;
use Embera\Embera;
use Exception;

class RemoteMedia extends \base_core\models\Base {

	protected $_meta = [
		'connection' => false
	];

	// A provider is either an image or video provider.
	public static function providers() {
		return [
			'vimeo' => [
				'name' => 'vimeo',
				'matcher' => '#vimeo#',
				'convertToInternalUrl' => function($url) {
					preg_match('#vimeo\.com/(\d+)#', $url, $matches);
					return 'vimeo://' . $matches[1];
				},
				'convertToExternalUrl' => function($url) {
					return 'https://vimeo.com/' . parse_url($url, PHP_URL_HOST);
				},
				'type' => 'video',
				'mime_type' => 'application/x-vimeo'
			],
			'youtube' => [
				'name' => 'youtube',
				'matcher' => '#youtube#',
				'convertToInternalUrl' => function($url) {
					parse_str(parse_url($url, PHP_URL_QUERY), $vars);
					return 'youtube://' . $vars['v'];
				},
				'convertToExternalUrl' => function($url) {
					return 'https://www.youtube.com/watch?v=' . parse_url($url, PHP_URL_HOST);
				},
				'type' => 'video',
				'mime_type' => 'application/x-youtube'
			],
			'instagram' => [
				'name' => 'instagram',
				'matcher' => '#instagram#',
				'convertToInternalUrl' => function($url) {
					// Instagram works with shortcodes instead of IDs, these are
					// only present in the links.
					preg_match('#instagr(\.am|am\.com)/p/([^/]+)/?#i', $url, $matches);
					return 'instagram://' . $matches[2];
				},
				'convertToExternalUrl' => function($url) {
					return 'https://instagram.com/p/' . parse_url($url, PHP_URL_HOST);
				},
				'type' => 'image',
				'mime_type' => 'application/x-instagram'
			],
			// This does not use the SC id, as we'd need API access for that.
			// SC IDs must be resolved.
			'soundcloud' => [
				'name' => 'soundcloud',
				'matcher' => '#soundcloud#',
				'convertToInternalUrl' => function($url) {
					preg_match('#soundcloud\.com/([^/]+)/([^/]+)/?$#i', $url, $matches);
					return 'soundcloud://' . $matches[1] . '/' . $matches[2];
				},
				'convertToExternalUrl' => function($url) {
					return 'https://soundcloud.com/' . parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);
				},
				'type' => 'audio',
				'mime_type' => 'application/x-soundcloud'
			]
		];
	}

	public static function createFromUrl($url) {
		if (!static::provider($url)) {
			throw new Exception("Remote media `{$url}` not supported.");
		}
		$cacheKey = 'oembed_meta_' . md5($url);

		if (!$results = Cache::read('default', $cacheKey)) {
			$client = new Embera([
				'allow' => array_keys(static::providers())
			]);
			$results = $client->getUrlInfo($url);

			if (!$results || $client->getErrors()) {
				$message  = "Failed to extract oEmbed meta from external media `{$url}`.\n";
				$message .= "Client results: " . var_export($results) . "\n";
				$message .= "Client errors: " . var_export($client->getErrors());
				throw new Exception($message);
			}
			$message  = "Extracted oEmbed meta from external media `{$url}`:\n";
			$message .= var_export(current($results), true);
			Logger::debug($message);

			// Cache to safe us from repeated requests, when making versions. But
			// keep minimal to allow i.e. a remove thumbnail update to propagte.
			Cache::write('default', $cacheKey, $results, '+2 minutes');
		}
		$item = current($results);


		// Upgrade thumbnails to highest resolution possible. Vimeo uses a fixed
		// scheme for naming the thumbnail files, which we exploit here. This
		// frees us from going over the offical API.
		if ($item['provider_name'] === 'Vimeo') {
			$item['thumbnail_url'] = str_replace('_640.', '_1280.', $item['thumbnail_url']);
		}

		return static::create([
			'title' => $item['title'],
			'url' => key($results),
			'provider' => strtolower($item['provider_name']),
			'thumbnailUrl' => $item['thumbnail_url']
		]);
	}

	public static function provider($url) {
		foreach (static::providers() as $provider) {
			if (is_string($provider['matcher']) && preg_match($provider['matcher'], $url)) {
				return $provider;
			}
			if (is_callable($provider['matcher']) && $provider['matcher']($url)) {
				return $provider;
			}
		}
		return false;
	}

	public function url($entity, array $options = []) {
		$options += [
			'internal' => false
		];
		if ($options['internal']) {
			$method = static::provider($entity->url)['convertToInternalUrl'];
			return $method($entity->url);
		}
		return $entity->url;
	}
}

?>