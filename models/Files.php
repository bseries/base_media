<?php

namespace cms_media\models;

use \Mime_Type;
use \Media_Process;
use cms_media\models\FileVersions;

class Files extends \lithium\data\Model {

	protected $_meta = array(
		'source' => 'media_files'
	);

	public function url($entity) {
		return '/media/' . $entity->_id . '.' . $entity->extension;
	}

	public function mimeType($entity) {
		return static::_detectMimeType($entity->file->getBytes());
	}

	public function versions($entity, $name = null) {
		if (!$entity->versions) {
			return array();
		}
		$files = array_filter($entity->versions->data());
		$docs = FileVersions::all(array(
			'conditions' => array(
				'_id' => array('$in' => array_values($files))
			)
		));

		$values = array();
		foreach ($docs as $doc) {
			$values[] = $doc;
		}

		if (!$values) {
			// trigger_error("No file versions found for keys " . implode($files) . '.', E_USER_NOTICE);
			return array();
		}
		$result = array_combine(array_keys($files), $values);

		if ($name) {
			return $result[$name];
		}
		return $result;
	}

	public static function _detectMimeType($data) {
		$context = finfo_open(FILEINFO_MIME);

		if (is_resource($data)) {
			rewind($data);
			$peekBytes = 1000000;
			$result = finfo_buffer($context, fread($data, $peekBytes));
		} else {
			$result = finfo_buffer($context, $data);
		}
		list($type, $attributes) = explode(';', $result, 2) + array(null, null);

		finfo_close($context);

		if ($type != 'application/x-empty') {
			return $type;
		}
	}
}

Files::finder('original', function($self, $params, $chain) {
	$params['options']['conditions'] = array(
		'versions' => array('$exists' => true),
	);
	return $chain->next($self, $params, $chain);
});
Files::finder('listOriginal', function($self, $params, $chain) {
	$params['options']['conditions'] = array(
		'versions' => array('$exists' => true),
	);
	$results = $chain->next($self, $params, $chain);

	$data = array();
	foreach ($results as $result) {
		$data[(string) $result->_id] = $result->filename;
	}
	return $data;
});


Files::applyFilter('create', function($self, $params, $chain) {
	$data =& $params['data'];

	if (isset($data['file'])) {
		$data['mime_type'] = Mime_Type::guessType(
			$data['file']
		);
		$data['extension'] = Mime_Type::guessExtension(
			$data['file']
		);
	}

	// stream -> bytes, lihtium interpreting strings as bytes
	rewind($data['file']);
	$data['file'] = stream_get_contents($data['file']);

	return $chain->next($self, $params, $chain);
});

// Also delete versions.
Files::applyFilter('delete', function($self, $params, $chain) {
	$data =& $params['data'];
	$versions = $params['entity']->versions();

	foreach ($versions as $version) {
		$version->delete();
	}
	return $chain->next($self, $params, $chain);
});

Files::applyFilter('save', function($self, $params, $chain) {
	$result = $chain->next($self, $params, $chain);
	$entity = Files::first((string) $params['entity']->_id); // refresh

	$version = FileVersions::create(array(
		'file' => $entity->file->getResource(),
		'filename' => $entity->filename
	));
	if ($version->save()) { // may skip on invalid input
		if (!$entity->versions) {
			$entity->versions = array();
		}
		// @fixme If we directly assign we loose keys.
		$entity->versions = array('fix0' => $version->_id);
		$entity->save(null, array('callbacks' => false)); // prevent recursion
	}
	return $result;
});
?>