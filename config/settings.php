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

namespace base_media\config;

use base_core\extensions\cms\Settings;

// If enabled will keep animated images as is and not potentially
// convert them into a static image format.
Settings::register('media.keepAnimatedImages', false);

// Enable triggering of regeneration of media versions through
// the admin.
Settings::register('media.allowRegenerateVersions', false);

?>