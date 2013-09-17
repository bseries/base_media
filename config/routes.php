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

Router::connect('/files/preflight', 'Files::preflight');
Router::connect('/files/reserve', 'Files::reserve');
Router::connect('/files/transfer', 'Files::transfer');
Router::connect('/files/import', 'Files::import');

Router::connect('/files', ['controller' => 'files', 'action' => 'index', 'library' => 'cms_media']);

Router::connect('/files/{:action}/{:id:[0-9a-f]+}', [
	'controller' => 'files', 'library' => 'cms_media'
]);
Router::connect('/files/{:action}/{:args}', [
	'controller' => 'files', 'library' => 'cms_media'
]);

?>