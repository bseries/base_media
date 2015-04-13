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

namespace base_media\models;

use Essence\Essence;
use Exception;

class RemoteMedia extends \base_core\models\Base {

	protected $_meta = [
		'connection' => false
	];

	public static function providers() {
		return [
			'vimeo' => [
				'name' => 'vimeo',
				'matcher' => '#vimeo#',
				'convertToInternalUrl' => function($url) {
					preg_match('#vimeo\.com/(\d{8})#', $url, $matches);
					return 'vimeo://' . $matches[1];
				},
				'convertToExternalUrl' => function($url) {
					return 'https://vimeo.com/' . parse_url($url, PHP_URL_PATH);
				},
				'type' => 'video',
				'mime_type' => 'application/x-vimeo',
			],
			'youtube' => [
				'name' => 'youtube',
				'matcher' => '#youtube#',
				'convertToInternalUrl' => function($url) {
					parse_str(parse_url($url, PHP_URL_QUERY), $vars);
					return 'youtube://' . $vars['v'];
				},
				'convertToExternalUrl' => function($url) {
					return 'https://www.youtube.com/watch?v=' . parse_url($url, PHP_URL_PATH);
				},
				'type' => 'video',
				'mime_type' => 'application/x-youtube',
			]
		];
	}

	public static function createFromUrl($url) {
		if (!static::provider($url)) {
			throw new Exception("Remote media `{$url}` not supported.");
		}
		$essence = new Essence();

		if (!$item = $essence->extract($url)) {
			throw new Exception("Failed to extract oEmbed meta from external media `{$url}`.");
		}
		return static::create([
			'title' => $item->title,
			'url' => $item->url,
			'provider' => strtolower($item->providerName),
			'thumbnailUrl' => $item->thumbnailUrl
		]);
	}

	public static function provider($url) {
		foreach (static::providers() as $provider) {
			if (is_string($provider['matcher']) && preg_match($provider['matcher'], $url)) {
				return $provider;
			}
			if (is_callable($proivder['matcher']) && $proivder['matcher']($url)) {
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
			$method = static::provider($entity->url)->convertToInternalUrl;
			return $method($entity->url);
		}
		return $entity->url;
	}
}

?>