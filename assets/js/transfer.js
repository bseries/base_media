/*!
 * Media Explorer
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
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

    // Flag to indicate either meta or transfer failed.
    this.isFailed = false;

    // Flag to indicate this transfer has been cancelled.
    this.isCancelled = false;

    // Progress in percent as an integer - if available.
    this.progress = null;

    // Once transfer is running a cancel function may be attached here.
    this.cancel =  false;

    // An element that represents the transfer and allows to control it.
    this.element =  null;

    this.preflight = function() {
      return $.Deferred().reject();
    };

    // Executes the transfer, may notify about progress and status.
    this.run = function() {
      return {dfr: $.Deferred().reject(), cancel: null};
    };

    // Allows to return a dataUrl via a deferred, if the transfer allows this.
    this.preview = function() {
      return $.Deferred().reject();
    };

    this.title = null;

    // Must return deferred which when resolved must pass an object with `title` and `size` keys.
    this.meta = function() {
      // The title of the transferred object.
      // Filesize in bytes of the transferred object.

      return $.Deferred().reject();
    };
  };

});

