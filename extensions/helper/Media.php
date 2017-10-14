<?php
/**
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace base_media\extensions\helper;

use Exception;
use base_media\models\MediaVersions;
use lithium\aop\Filters;
use lithium\core\Environment;
use lithium\g11n\Message;

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

		return Filters::run($this, $method, $params, function($params) use ($method) {
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