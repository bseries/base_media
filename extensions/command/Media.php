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

namespace base_media\extensions\command;

use base_media\models\Media as MediaModel;

class Media extends \lithium\console\Command {

	public function regenerate() {
		$this->out('Regenerating media versions...');
		$this->out('This make take a while. Please tail the log to see generated messages.');

		foreach (MediaModel::all() as $item) {
			$this->out("Processing item {$item->id}...");
			$item->deleteVersions();
			$item->makeVersions();
		}
		$this->out('COMPLETED');
	}

	public function verify() {
		$this->out('Verifying media...');

		foreach (MediaModel::all() as $item) {
			$this->out("Verifying item {$item->id} with url {$item->url}...", false);
			$this->out($item->verify() ? 'OK' : 'FAILED');
		}
		$this->out('COMPLETED');
	}
}

?>