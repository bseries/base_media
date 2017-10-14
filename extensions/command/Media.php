<?php
/**
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
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

	/**
	 * Removes unused media items, that are not in use by any other entity.
	 */
	public function clean() {
		$this->out('Cleaning media...');
		$this->out(MediaModel::clean() ? 'OK' : 'FAILED');
	}

	/**
	 * Deletes orphaned records and files automatically.
	 *
	 * Finds files that have no corresponding record and records that don't have
	 * a corresponding file. This basically syncs the media directories with the
	 * corresponding tables.
	 */
	public function sync() {
		if ($this->in('Do media files and records?', ['choices' => ['y','n']]) == 'y') {
			$this->out('Discovering orphaned media files...');
			$base = parse_url(MediaModel::base('file'), PHP_URL_PATH);

			foreach (glob($base . '/*/*', GLOB_NOSORT) as $file) {
				$url = 'file://' . str_replace($base . '/', '', $file);

				$hasItem = MediaModel::find('count', [
					'conditions' => compact('url')
				]);
				if ($hasItem) {
					continue;
				}
				$this->out('deleting [orphaned media file] at path ' . $file);
				unlink($file);
			}

			$this->out('Discovering orphaned media records...');
			foreach (MediaModel::find('all') as $item) {
				try {
					$url = $item->url('file');
				} catch (\Exception $e) {
					$this->error('failed checking [media record] with id: ' . $item->id);
					continue;
				}
				if (file_exists($url)) {
					continue;
				}
				if (!$item->depend('count')) {
					continue;
				}
				$this->out('deleting [orphaned media record] with id:' . $item->id . ' title:' . ($item->title  ?: '?') . '');
				$item->delete();
			}
		}

		if ($this->in('Do media version files and records?', ['choices' => ['y','n']]) == 'y') {
			$this->out('Discovering orphaned media version files...');
			$base = parse_url(MediaVersions::base('file'), PHP_URL_PATH);

			foreach (glob($base . '/*/*/*', GLOB_NOSORT) as $file) {
				$url = 'file://' . str_replace($base . '/', '', $file);

				$hasItem = MediaVersions::find('count', [
					'conditions' => compact('url') + [
						'version' => $version = basename(dirname(dirname($file)))
					]
				]);
				if ($hasItem) {
					continue;
				}
				$this->out('deleting [orphaned media versions file] with version '  . $version . ' at path ' . $file);
				unlink($file);
			}

			$this->out('Discovering orphaned media versions records...');
			foreach (MediaVersions::find('all') as $item) {
				try {
					$url = $item->url('file');
				} catch (\Exception $e) {
					$this->error('failed checking [media versions record] with id: ' . $item->id);
					continue;
				}
				if (file_exists($url)) {
					continue;
				}
				$this->out('deleting [orphaned media versions record] with id:' . $item->id);
				$item->delete();
			}
		}
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
		MediaVersions::remove();

		$this->out('Creating dummy media from file...');
		copy(
			PROJECT_PATH . '/assets/app/img/dummy.jpg',
			$file = MediaModel::base('file') . '/' . uniqid('dummy_') . '.jpg'
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