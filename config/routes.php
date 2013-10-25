<?php
/**
 * Bureau Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

use lithium\net\http\Router;

$persist = ['persist' => ['admin', 'controller']];

// Files
Router::connect(
	'/admin/api/files/{:id:([0-9]+|__ID__)}',
	['controller' => 'files', 'action' => 'api_view', 'library' => 'cms_media', 'admin' => true],
	$persist
);
Router::connect(
	'/admin/api/files',
	['controller' => 'files', 'action' => 'api_index', 'library' => 'cms_media', 'admin' => true],
	$persist
);
Router::connect(
	'/admin/api/files/transfer',
	['controller' => 'files', 'action' => 'api_transfer', 'library' => 'cms_media', 'admin' => true]
);

Router::connect(
	'/admin/files/transfer',
	['controller' => 'files', 'action' => 'transfer', 'library' => 'cms_media', 'admin' => true],
	$persist
);
Router::connect(
	'/admin/files',
	['controller' => 'files', 'action' => 'index', 'library' => 'cms_media', 'admin' => true],
	$persist
);
Router::connect(
	'/admin/files/{:action}/{:id:[0-9]+}',
	['controller' => 'files', 'library' => 'cms_media', 'admin' => true],
	$persist
);
Router::connect(
	'/admin/files/{:action}/{:args}',
	['controller' => 'files', 'library' => 'cms_media', 'admin' => true],
	$persist
);

?>