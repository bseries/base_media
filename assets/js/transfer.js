/*!
 * Media Explorer
 *
 * Copyright (c) 2013-2014 David Persson - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

define(['jquery'], function($) {
  //
  // Represents a transfer.
  //
  return function Transfer() {
    // Flag to indicate if the run method has been called once.
    this.hasRun = false;

    // Once transfer is running a cancel function may be attached here.
    this.cancel =  false;

    // An element that represents the transfer and allows to control it.
    this.element =  null;

    // Executes the transfer, may notify about progress and status.
    this.run = function() {
      return $.Deferred().reject();
    };

    // Allows to return a dataUrl via a deferred, if the transfer allows this.
    this.image = function() {
      return $.Deferred().reject();
    };

    // The title of the transferred object.
    this.title = null;

    // Filesize in bytes of the transferred object.
    this.size =  null;
  };
});

