<?php
/**
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace base_media\models;

use Embera\Embera;
use Exception;
use base_core\models\Assets;
use lithium\analysis\Logger;
use lithium\storage\Cache;

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
				'mimeType' => 'application/x-vimeo',
				'title' => function($url) {
					return static::_oembed($url)['title'];
				},
				// Upgrade thumbnails to highest resolution possible. Vimeo uses a fixed
				// scheme for naming the thumbnail files, which we exploit here. This
				// frees us from going over the offical API.
				'thumbnailUrl' => function($url) {
					if (!$item = static::_oembed($url)) {
						return $item;
					}
					return str_replace('_640.', '_1280.', $item['thumbnail_url']);
				}
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
				'mimeType' => 'application/x-youtube',
				'title' => function($url) {
					return static::_oembed($url)['title'];
				},
				// Upgrade to higher resolution and de-letterbox in one go.
				'thumbnailUrl' => function($url) {
					if (!$item = static::_oembed($url)) {
						return $item;
					}
					return str_replace('hqdefault.', 'maxresdefault.', $item['thumbnail_url']);
				}
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
				'mimeType' => 'application/x-instagram',
				'title' => function($url) {
					return static::_oembed($url)['title'];
				},
				'thumbnailUrl' => function($url) {
					return static::_oembed($url)['thumbnail_url'];
				}
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
				'mimeType' => 'application/x-soundcloud',
				'title' => function($url) {
					return static::_oembed($url)['title'];
				},
				'thumbnailUrl' => function($url) {
					return static::_oembed($url)['thumbnail_url'];
				}
			],
			// Parses:
			// https://www.bundestag.de/mediathek?videoid=7251617
			// https://dbtg.tv/fvid/7251617
			'bundestag' => [
				'name' => 'bundestag',
				'matcher' => '#(bundestag|bundestag\.de|dbtg\.tv)#',
				'convertToInternalUrl' => function($url) {
					if (strpos(parse_url($url, PHP_URL_HOST), 'bundestag.de') !== false) {
						$query = [];
						parse_str(parse_url($url, PHP_URL_QUERY), $query);
						if (!isset($query['videoid'])) {
							throw new Exception("Failed to parse bundestag media URL `{$url}`.");
						}
						$id = $query['videoid'];
					} elseif (strpos(parse_url($url, PHP_URL_HOST), 'dbtg.tv') !== false) {
						$id = basename(parse_url($url, PHP_URL_PATH));
					} else {
						throw new Exception("Failed to parse bundestag media URL `{$url}`.");
					}
					return "bundestag://{$id}";
				},
				'convertToExternalUrl' => function($url) {
					return 'https://dbtg.tv/fvid/' . parse_url($url, PHP_URL_HOST);
				},
				'type' => 'video',
				'mimeType' => 'application/x-bundestag',
				// Construct title from page title + the description. The page by itself
				// does only include the speaker.
				'title' => function($url) {
					$id = basename(parse_url($url, PHP_URL_PATH));
					$url = "https://www.bundestag.de/mediathekoverlay?view=main&videoid={$id}";

					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
					$result = curl_exec($ch);
					curl_close($ch);

					$titles = [];

					if (preg_match("/\<h3.*\>(.*)\<\/h3\>/isU", $result, $matches)) {
						$titles[] = trim(strip_tags($matches[1]));
					}
					if (preg_match("/\<div class=\"bt-video-titel\"\>(.*)\<\/div\>/isU", $result, $matches)) {
						// This adds i.e. "Gesamtaufnahme der Plenarsitzung", the
						// descriptive title is in the meta information.
						$titles[] = trim(strip_tags($matches[1]));
					}
					$titles[] = 'Deutscher Bundestag';

					return implode(' / ', $titles);
				},
				'thumbnailUrl' => function($url, $request) {
					return Assets::base($request ?: 'file') . '/base-media/img/bundestagstv_placeholder.jpg';
				}
			]
		];
	}

	protected static function _oembed($url) {
		$cacheKey = 'oembed_meta_' . md5($url);

		if ($results = Cache::read('default', $cacheKey)) {
			return current($results);
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
		$message  = "Extracted oEmbed meta from external media `{$url}`:\n";
		$message .= var_export(current($results), true);
		Logger::debug($message);

		// Cache to safe us from repeated requests, when making versions. But
		// keep minimal to allow i.e. a remove thumbnail update to propagte.
		Cache::write('default', $cacheKey, $results, '+2 minutes');

		return current($results);
	}

	public static function createFromUrl($url, $request = null) {
		if (!$provider = static::provider($url)) {
			throw new Exception("Remote media `{$url}` not supported by any provider.");
		}
		return static::create([
			'title' => $provider['title']($url),
			'url' => $url,
			'provider' => $provider['name'],
			'thumbnailUrl' => $provider['thumbnailUrl']($url, $request)
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