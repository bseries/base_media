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
use base_media\models\MediaVersions;

class Media extends \lithium\console\Command {

	public function regenerate() {
		$this->out('Regenerating media versions... (this may take a while)');
		$this->out('Note: Please tail the log to see generated messages.');

		foreach (MediaModel::all() as $item) {
			$this->out("Processing item {$item->id}...");
			$item->deleteVersions();
			$item->makeVersions();
		}
		$this->out('COMPLETED, you may need to clear caches to make new media take effect.');
	}

	public function verify() {
		$this->out('Verifying media...');

		foreach (MediaModel::all() as $item) {
			$this->out("Verifying item {$item->id} with url {$item->url}...", false);
			$this->out($item->verify() ? 'OK' : 'FAILED');
		}
		$this->out('COMPLETED');
	}

	public function dummy() {
		$this->out('Adding dummy transfers and versions.');

		MediaModel::find('all', [
			'conditions' => [
				'title' => 'DUMMY'
			]
		])->delete();

		ini_set('memory_limit','500M');
		MediaVersions::find('all')->delete();

		copy(
			PROJECT_PATH . '/assets/app/img/dummy/01.jpg',
			$file = PROJECT_MEDIA_FILE_BASE . '/' . uniqid('dummy_') . '.jpg'
		);

		$image = MediaModel::create([
			'url' => $file,
			'title' => 'DUMMY'
		]);
		if (!$image->save()) {
			return false;
		}
		if (!$image->makeVersions()) {
			return false;
		}

		$this->out('Replacing originals with dummy...');
		foreach (MediaModel::all() as $item) {

			if ($item->id === $image->id) {
				continue; // Do not dummy or dummy.
			}
			$this->out("Processing item {$item->id}...");

			if ($item->can('delete')) {
				$item->deleteUrl();
			}
			$item->deleteVersions();

			$item->url = $image->url;
			$item->save([
				'url' => $image->url,
				'type' => $image->type,
				'checksum' => $image->checksum,
				'mime_type' => $image->mime_type
			], ['callbacks' => false]);

			foreach ($image->versions() as $version) {
				$v = MediaVersions::create([
					'media_id' => $item->id,
					'version' => $version->version,
					'url' => $version->url,
					'type' => $version->type,
					'checksum' => $version->checksum,
					'mime_type' => $version->mime_type,
					'status' => 'processed'
				]);
				$v->save(null, ['callbacks' => false]);
			}
		}
		$this->out('COMPLETED, you may need to clear caches to make new media take effect.');
	}
}

?>