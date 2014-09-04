<?php

namespace base_media\extensions\helper;

use lithium\core\Environment;
use base_media\models\MediaVersions;

// This more of a MediaVersions helper than actually a Media helper.
// The original media versions will never be embedded into markup.
class Media extends \lithium\template\Helper {

	protected $_strings = [
		'image' => '<img src="{:path}"{:options} />',
	];

	public $contentMap = [
		'image' => 'image'
	];

	public function image($path, array $options = []) {
		$path = $this->url($path);

		$defaults = ['alt' => ''];
		$options += $defaults;

		$path = $this->_context->url($path, ['absolute' => true]);
		$params = compact('path', 'options');
		$method = __METHOD__;

		return $this->_filter($method, $params, function($self, $params, $chain) use ($method) {
			return $self->invokeMethod('_render', [$method, 'image', $params]);
		});
	}

	public function video($path) {
		$path = $this->url($path);
	}

	public function url($path) {
		if (is_object($path)) {
			return $path->url($this->_context->request()->is('ssl') ? 'https' : 'http');
		}
		if (strpos($path, '://') !== false) {
			return $path;
		}
		return $this->base() . $path;
	}

	public function base($scheme = null) {
		$scheme = $scheme ?: $this->_context->request()->is('ssl') ? 'https' : 'http';
		return MediaVersions::base($scheme);
	}
}

?>