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

use lithium\net\http\Router;

$persist = ['persist' => ['admin', 'controller']];

// media
Router::connect(
	'/admin/api/media/{:id:([0-9]+|__ID__)}',
	['controller' => 'media', 'action' => 'api_view', 'library' => 'cms_media', 'admin' => true],
	$persist
);
Router::connect(
	'/admin/api/media',
	['controller' => 'media', 'action' => 'api_index', 'library' => 'cms_media', 'admin' => true],
	$persist
);
Router::connect(
	'/admin/api/media/transfer-preflight',
	['controller' => 'media', 'action' => 'api_transfer_preflight', 'library' => 'cms_media', 'admin' => true]
);
Router::connect(
	'/admin/api/media/transfer-meta',
	['controller' => 'media', 'action' => 'api_transfer_meta', 'library' => 'cms_media', 'admin' => true]
);
Router::connect(
	'/admin/api/media/transfer',
	['controller' => 'media', 'action' => 'api_transfer', 'library' => 'cms_media', 'admin' => true]
);

Router::connect(
	'/admin/media/transfer',
	['controller' => 'media', 'action' => 'transfer', 'library' => 'cms_media', 'admin' => true],
	$persist
);
Router::connect(
	'/admin/media',
	['controller' => 'media', 'action' => 'index', 'library' => 'cms_media', 'admin' => true],
	$persist
);
Router::connect(
	'/admin/media/{:action}/{:id:[0-9]+}',
	['controller' => 'media', 'library' => 'cms_media', 'admin' => true],
	$persist
);
Router::connect(
	'/admin/media/{:action}/{:args}',
	['controller' => 'media', 'library' => 'cms_media', 'admin' => true],
	$persist
);

?>