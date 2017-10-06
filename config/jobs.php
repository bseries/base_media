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
 * License. If not, see https://atelierdisko.de/licenses.
 */

namespace base_media\config;

use Cute\Handlers;
use base_media\models\MediaVersions;

Handlers::register('MediaVersions::make', function($data) {
	if (MediaVersions::pdo()->inTransaction()) {
		MediaVersions::pdo()->rollback();
	}
	MediaVersions::pdo()->beginTransaction();

	if (MediaVersions::make($data['mediaId'], $data['version'])) {
		MediaVersions::pdo()->commit();
		return true;
	}
	MediaVersions::pdo()->rollback();
	return false;
});

?>