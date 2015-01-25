<?php
/**
 * Base Media
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

Router::connect(
	'/admin/api/media/page:{:page}',
	['controller' => 'media', 'action' => 'index', 'library' => 'base_media', 'admin' => true, 'api' => true],
	$persist
);
Router::connect(
	'/admin/api/media/search/{:q}/page:{:page}',
	['controller' => 'media', 'action' => 'search', 'library' => 'base_media', 'admin' => true, 'api' => true],
	$persist
);

?>