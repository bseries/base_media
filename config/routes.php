<?php

use lithium\net\http\Router;
use lithium\action\Response;
use cms_media\models\Files;

Router::connect('/files/preflight', 'Files::preflight');
Router::connect('/files/reserve', 'Files::reserve');
Router::connect('/files/transfer', 'Files::transfer');
Router::connect('/files/import', 'Files::import');

Router::connect('/files', array('controller' => 'files', 'action' => 'index', 'library' => 'cms_media'));

Router::connect('/files/{:id:[0-9a-f]{24}}.{:type}', array(), function($request) {
	if (!$file = Files::first($request->id)) {
		return new Response(array('status' => 404));
	}
	$response = new Response();

	$hash = $file->md5;
	$condition = trim($request->get('http:if_none_match'), '"');

	$response->headers['ETag'] = "\"{$hash}\"";

	if ($condition === $hash) {
		$response->status(304);
	} else {
		$response->headers += array(
			'Content-length' => $file->file->getSize(),
			'Content-type' => $file->mimeType(),
		);
		$response->body = $file->file->getBytes();
	}
	return $response;
});

Router::connect('/files/{:action}/{:id:[0-9a-f]{24}}', array(
	'controller' => 'files', 'library' => 'cms_media'
));
Router::connect('/files/{:action}/{:args}', array(
	'controller' => 'files', 'library' => 'cms_media'
));

?>