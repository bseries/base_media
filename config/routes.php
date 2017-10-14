<?php
/**
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
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