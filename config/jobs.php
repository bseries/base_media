<?php
/**
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace base_media\config;

use Cute\Handlers;
use base_media\models\MediaVersions;

Handlers::register('MediaVersions::make', function($data) {
	MediaVersions::pdo()->beginTransaction();

	if (MediaVersions::make($data['mediaId'], $data['version'])) {
		MediaVersions::pdo()->commit();
		return true;
	}
	MediaVersions::pdo()->rollback();
	return false;
}, [
	'retry' => function() {
		MediaVersions::connection()->connect();
	}
]);

?>