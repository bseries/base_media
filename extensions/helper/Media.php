<?php

namespace cms_media\extensions\helper;

use lithium\core\Environment;

class Media extends \lithium\template\Helper {

	protected $_strings = [
		'image'            => '<img src="{:path}"{:options} />',
	];

	public $contentMap = [
		'image' => 'image',
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
		$base = Environment::get('media.http');
		return $base . $path;
	}
}

?>