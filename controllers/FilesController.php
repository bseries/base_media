<?php

namespace cms_media\controllers;

use cms_media\models\Files;
use lithium\core\Libraries;

class FilesController extends \lithium\action\Controller {

	public function admin_index() {
		if ($this->request->data) {
			$source = $this->request->data['transfer'];

			$file = Files::create(array(
				'file' => $source
			));
			$file->save();
		}

		$data = Files::original();
		return compact('data');
	}

	public function admin_delete() {
		Files::find($this->request->id)->delete();
		$this->redirect(array('action' => 'index', 'library' => 'cms_media'));
	}

	public function admin_edit() {
		$item = Files::find($this->request->id);

		if (!$item) {
			$this->redirect(array('action' => 'index', 'library' => 'cms_media'));
		}
		if (($this->request->data) && $item->save($this->request->data)) {
			$this->redirect(array('action' => 'index', 'library' => 'cms_media'));
		}
		$this->_render['template'] = 'admin_form';

		return compact('item');
	}

	public function preflight() {
	}

	public function reserve() {
		// Return status, long-polling
	}

	public function transfer() {
		// Translate received data into a to-be-saved item.
		$transfer = new Transfer();
		$transfer->source = $this->request->data;
		$transfer->target = '/tmp';

		$transfer->run();

		Files::create($transfer->result);
	}

	public function import() {

	}

	public function Xtransfer() {


		CakeLog::write('debug', 'Transfer handler initiated.');

		$this->Gate->requireMagicPower();
		// @fixme Sets content-type to 'json'?
		$this->RequestHandler->setContent('json');
		$this->RequestHandler->respondAs('json');

		if (!$this->RequestHandler->isPost()) {
			return $this->cakeError('error405', array('api' => true));
		}

		if ($this->RequestHandler->requestedWith('application/octet-stream')) { // sendAsBinary
			CakeLog::write('debug', 'Receiving/received file as binary stream.');

			if (!$source = fopen('php://input', 'rb')) {
				return $this->cakeError('error500', array('api'  => true));
			}
			$file = Temporary::file(array('context' => 'npiece'));

			$target = fopen($file, 'wb');
			stream_copy_to_stream($source, $target);
			fclose($source);
			fclose($target);
		} else { // multipart
			CakeLog::write('debug', 'Received file via multipart/form:');
			CakeLog::write('debug', var_export($this->params['form'], true));
			$file = $this->params['form']['file']; // non-cake structure
		}
		// Note that $file is reused latern when saving a sample.

		CakeLog::write('debug', "Transferring from file `{$file}`.");

		// This relies on that we're being able to *always* retrieve a size.
		$meta = $this->MediaFile->transferMeta($file);

		$quota = $this->Quota->checkFiles($this->Gate->user(), array('buffer' => 1));
		$quota = $quota && $this->Quota->checkSpace($this->Gate->user(), array('buffer' => $meta['size']));

		$errorName = null;

		if (!$quota) {
			$errorName = 'quota';
		} else {
			/* Transaction begin. */
			ignore_user_abort(true);

			$this->MediaFile->create();
			$this->data['MediaFile'] = array(
				'file' => $file,
				'user_id' => $this->Gate->user('id')
			);
			if ($this->MediaFile->save($this->data)) {
				$id = $this->MediaFile->getLastInsertID();
				CakeLog::write('debug', "Transfer handled, file bound to `MediaFile@{$id}`.");

				if (connection_aborted()) {
					CakeLog::write('debug', "Request aborted, cleaning up `MediaFile@{$id}`.");
					$this->MediaFile->delete($id); // We want to delete the uploaded file, too.
					exit(); // User doesn't need any output.
				}
			} else {
				$invalidFields = $this->MediaFile->invalidFields();
				$errorName = $invalidFields ? Inflector::underscore($invalidFields['file']) : 'unknown';
			}

			ignore_user_abort(false);
			/* Transaction end. */
		}
		if ($errorName) {
			$message  = "Transfer failed; reason is `{$errorName}`; meta was: \n";
			$message .= var_export($meta, true);
			CakeLog::write('debug', $message);

			if (file_exists($file)) {
				copy($file, $sample = ROOT . '/files/' . basename($file));
				CakeLog::write('debug', "Saved file involved in failed transfer to `{$sample}`.");
			}

			return $this->cakeError('transfer', array('api' => true, 'type' => $errorName));
		}
		$this->autoRender = false;
	}
}

?>