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

namespace cms_media\extensions\command;

use cms_media\models\Media as MediaModel;

class Media extends \lithium\console\Command {

	public function regenerate() {
		$this->out('Regenerating versions...');
		MediaModel::regenerateVersions();
		$this->out('done.');
	}
}

?>