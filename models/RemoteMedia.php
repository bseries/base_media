<?php
/**
 * Base Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * Licensed under the AD General Software License v1.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *
 * You should have received a copy of the AD General Software
 * License. If not, see http://atelierdisko.de/licenses.
 */

namespace base_media\models;

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
			]
		];
	}

	public static function createFromUrl($url) {
		if (!static::provider($url)) {
			throw new Exception("Remote media `{$url}` not supported.");
		}
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
		$item = current($results);

		$message  = "Extracted oEmbed meta from external media `{$url}`:\n";
		$message .= var_export($item, true);
		Logger::debug($message);

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