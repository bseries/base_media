<?php
/**
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace base_media\config;

use lithium\g11n\Message;
use base_core\extensions\cms\Panes;

extract(Message::aliases());

Panes::register('media', [
	'title' => $t('Media', ['scope' => 'base_media']),
	'url' => ['controller' => 'media', 'action' => 'index', 'library' => 'base_media', 'admin' => true],
	'weight' => 80
]);

?>