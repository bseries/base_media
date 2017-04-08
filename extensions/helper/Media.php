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
 * License. If not, see https://atelierdisko.de/licenses.
 */

namespace base_media\extensions\helper;

use Exception;
use lithium\core\Environment;
use lithium\g11n\Message;
use base_media\models\MediaVersions;

// This more of a MediaVersions helper than actually a Media helper.
// The original media versions will never be embedded into markup.
class Media extends \lithium\template\Helper {

	protected $_strings = [
		'image' => '<img src="{:path}"{:options} />',
		'mediaAttachmentField' => '<div class="%s">%s%s%s%s</div>'
	];

	public $contentMap = [
		'image' => 'image'
	];

	// Generates image tag HTML for a given medium path.
	public function image($path, array $options = []) {
		$path = $this->url($path);

		$defaults = ['alt' => ''];
		$options += $defaults;

		$path = $this->_context->url($path, ['absolute' => true]);
		$params = compact('path', 'options');
		$method = __METHOD__;

		return $this->_filter($method, $params, function($params, $next) use ($method) {
			return $this->_render($method, 'image', $params);
		});
	}

	// Generates video tag HTML for a given medium path.
	public function video($path) {
		$path = $this->url($path);
	}

	// Returns the full URL for a medium path.
	public function url($path) {
		if (is_object($path)) {
			return $path->url($this->_context->request());
		}
		if (strpos($path, '://') !== false) {
			return $path;
		}
		return $this->base() . $path;
	}

	// Returns the base for given scheme.
	public function base($scheme = null) {
		return MediaVersions::base($scheme ?: $this->_context->request());
	}

	// Generates form field HTML so that media-attachment.js can
	// hook into it.
	public function field($name, array $options = []) {
		extract(Message::aliases());

		$options += [
			'attachment' => null,
			'value' => null
		];

		if (!isset($options['attachment'])) {
			throw new Exception('Option `attachment` for `Media::field()` not provided.');
		}

		if ($options['attachment'] === 'direct') {
			$options += ['label' => $t('Medium', ['scope' => 'base_media'])];

			$values = $this->_context->form->hidden($name, [
				'value' => $options['value'] ? $options['value']->id : null
			]);
		} else {
			$options += ['label' => $t('Media', ['scope' => 'base_media'])];

			$values = '';
			foreach ($options['value'] as $medium) {
				$values .= $this->_context->form->hidden('media.' . $medium->id . '.id', [
					'value' => $medium->id
				]);
			}
		}
		return sprintf($this->_strings['mediaAttachmentField'],
			'media-attachment use-media-attachment-' . $options['attachment'],
			$values,
			$options['label'] ? $this->_context->form->label($name, $options['label']) : null,
			'<div class="selected"></div>',
			$this->_context->html->link($t('select', ['scope' => 'base_media']), '#', [
				'class' => 'button select'
			])
		);
	}
}

?>