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

namespace base_media\extensions\command;

use base_media\models\Media as MediaModel;
use base_media\models\MediaVersions;

class Media extends \lithium\console\Command {

	/**
	 * Regenerates selected media versions.
	 *
	 * Use the type parameter to select versions. By default `'all'`, use
	 * `'id:<ID>'` to select a single media entity.
	 *
	 * @param string $type
	 */
	public function regenerate($type = 'all') {
		$this->out('Regenerating media versions... (this may take a while)');
		$this->out('Note: Please tail the log to see generated messages.');

		$conditions = [];

		if (strpos($type, 'id:') === 0) {
			if (preg_match('/id:(\d+)-(\d+)/', $type, $matches)) {
				$conditions[] = 'id >= ' . $matches[1];
				$conditions[] = 'id <= ' . $matches[2];
			} else {
				$conditions['id'] = explode(':', $type)[1];
			}
		}

		foreach (MediaModel::find('all', compact('conditions')) as $item) {
			$this->out("Processing media item `{$item->id}` (`{$item->title}`)...", [
				'nl' => false
			]);
			$result = $item->deleteVersions() && $item->makeVersions();
			$this->out($result ? 'OK' : 'FAILED');

		}
		$this->out('COMPLETED:');
		$this->out('- you may need to clear caches to make new media take effect');
		$this->out('- chmod the media directories to make them accessible to the web user');
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

		$this->out('Clearing media versions...');
		MediaVersions::find('all')->delete();

		$this->out('Creating dummy media from file...');
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