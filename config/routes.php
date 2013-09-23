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

// Router::connect('/files/preflight', 'Files::preflight');
// Router::connect('/files/reserve', 'Files::reserve');
// Router::connect('/files/transfer', 'Files::transfer');
// Router::connect('/files/import', 'Files::import');

$persist = ['persist' => ['admin', 'controller']];

Router::connect(
	'/files/transfer',
	['controller' => 'files', 'action' => 'transfer', 'library' => 'cms_media']
);
Router::connect(
	'/admin/files',
	['controller' => 'files', 'action' => 'index', 'library' => 'cms_media', 'admin' => true],
	$persist
);

Router::connect(
	'/admin/files/{:id:[0-9]+}',
	['controller' => 'files', 'library' => 'cms_media', 'action' => 'view', 'admin' => true],
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