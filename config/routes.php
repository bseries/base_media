<?php
/**
 * Base Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * Licensed under the AD General Software License v1.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *
 * You should have received a copy of the AD General Software
 * License. If not, see http://atelierdisko.de/licenses.
 */

namespace base_media\config;

use lithium\net\http\Router;
use base_core\extensions\net\http\ClientRouter;

Router::scope('admin', function() {
	$persist = ['admin', 'controller'];
	$defaults = ['type' => 'json'];

	$base = ['controller' => 'media', 'library' => 'base_media', 'admin' => true, 'api' => true];

	Router::connect(
		'/api/base-media/media',
		$base + ['action' => 'index'],
		compact('persist', 'defaults')
	);
	Router::connect(
		'/api/base-media/media/page:{:page:(\d+|__PAGE__)}',
		$base + ['action' => 'index'],
		compact('persist', 'defaults')
	);
	Router::connect(
		'/api/base-media/media/search/{:q}/page:{:page:(\d+|__PAGE__)}',
		$base + ['action' => 'search'],
		compact('persist', 'defaults')
	);
	Router::connect(
		'/api/base-media/media/transfer/title:{:title}',
		$base + ['action' => 'transfer'],
		compact('persist', 'defaults')
	);

	ClientRouter::provide('media:view-batch',
		$base + ['action' => 'index']
	);

	ClientRouter::provide('media:index',
		$base + ['action' => 'index', 'page' => '__PAGE__']
	);
	ClientRouter::provide('media:search',
		$base + ['action' => 'search', 'q' => '__Q__', 'page' => '__PAGE__']
	);
	ClientRouter::provide('media:view',
		$base + ['action' => 'view', 'id' => '__ID__']
	);
	ClientRouter::provide('media:transfer-preflight',
		$base + ['action' => 'transfer_preflight']
	);
	ClientRouter::provide('media:transfer-meta',
		$base + ['action' => 'transfer_meta']
	);
	ClientRouter::provide('media:transfer',
		$base + ['action' => 'transfer', 'title' => '__TITLE__']
	);
	ClientRouter::provide('media:capabilities',
		$base + ['action' => 'capabilities']
	);
});

?>