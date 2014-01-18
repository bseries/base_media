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

namespace cms_media\models;

use lithium\analysis\Logger;
use temporary\Manager as Temporary;

trait DownloadTrait {

	public function download($entity) {
		$temporary = Temporary::file(['context' => 'download']);

		Logger::debug("Downloading into temporary `{$temporary}`.");

		if (!$result = copy($entity->url, $temporary)) {
			throw new Exception('Could not copy from source to temporary.');
		}
		return 'file://' . $temporary;
	}
}

?>