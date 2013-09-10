<?php

namespace cms_media\extensions\helper;

use lithium\core\Environment;

class Media extends \lithium\template\Helper {

	protected $_strings = array(
		'image'            => '<img src="{:path}"{:options} />',
	);

	public $contentMap = array(
		'image' => 'image',
	);

	public function image($path, array $options = array()) {
		$path = $this->url($path);

		$defaults = array('alt' => '');
		$options += $defaults;
		$path = $this->_context->url($path, array('absolute' => true));
		$params = compact('path', 'options');
		$method = __METHOD__;

		return $this->_filter($method, $params, function($self, $params, $chain) use ($method) {
			return $self->invokeMethod('_render', array($method, 'image', $params));
		});
	}

	public function video($path) {
		$path = $this->url($path);
	}

	public function url($path) {
		$base = Environment::get('media.url');
		return $base . $path;
	}
}

?>