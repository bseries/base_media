<?php
/**
 * Bureau Media
 *
 * Copyright (c) 2013-2014 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace cms_media\models;

use lithium\core\Environment;
use lithium\util\Set;
use OutOfBoundsException;

// Usable in conjunction with an entity having an `url` property, depends
// on a model using the UrlTrait.
trait SchemeTrait {

	protected static $_schemes = [];

	// @fixme Make this part of higher Media/settings abstraction.
	public static function registerScheme($scheme, array $options = []) {
		if (isset(static::$_schemes[$scheme])) {
			$default = static::$_schemes[$scheme];
		} else {
			$default = [
				'base' => false,
				'relative' => false,
				'delete' => false,
				'download' => false,
				'transfer' => false,
				'checksum' => false,
				'mime_type' => null,
				'type' => null
			];
		}
		static::$_schemes[$scheme] = Set::merge($default, $options);
	}

	public static function registeredScheme($scheme, $capability) {
		if (!isset(static::$_schemes[$scheme])) {
			throw new OutOfBoundsException("No registered scheme `{$scheme}`.");
		}
		return static::$_schemes[$scheme][$capability];
	}

	public function can($entity, $capability) {
		$scheme = $entity->scheme();

		if (!isset(static::$_schemes[$scheme])) {
			throw new OutOfBoundsException("No registered scheme `{$scheme}`.");
		}
		return static::$_schemes[$scheme][$capability];
	}

	public static function base($scheme) {
		if (!isset(static::$_schemes[$scheme])) {
			throw new OutOfBoundsException("No registered scheme `{$scheme}`.");
		}
		$bases = static::$_schemes[$scheme]['base'];
		return is_array($bases) ? $bases[Environment::get()] : $bases;
	}
}

?>