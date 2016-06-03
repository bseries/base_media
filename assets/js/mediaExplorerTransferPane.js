/*!
 * Media Explorer
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

define([
  'jquery',
  'transferQueue',
  'transferMethods'
],
function(
  $,
  TransferQueue,
  TransferMethods
) {

  //
  // Transfer Pane, has two main areas/objects: the queue and the methods.
  // Bridges queue and methods.
  //
  return function TransferPane(element, options) {
    var _this = this;

    this.element = $(element);

    options = $.extend({
      urlUpload: false,
      pdfs: false,
      animatedImages: false
    }, options);

    // Holds a transfer queue object.
    this.queue = null;

    // Holds several transfer method objects.
    this.methods = [];

    $start = _this.element.find('.start');
    $cancel = _this.element.find('.cancel');
    $methods = _this.element.find('.methods');
    $queue = _this.element.find('.queue');

    _this.queue = new TransferQueue($queue);

    // FIXME Assumes ME is always contained in a modal for drop method.
    // FIXME Allow selecting which methods are available.
    _this.methods.push(new TransferMethods.FileLocal(
      $queue,
      $methods.find('.file-local input')
    ));
    _this.methods.push(new TransferMethods.FileLocalDrop(
      _this.element.parents('#modal'),
      $methods.find('.file-local-drop')
    ));
    if (options.urlUpload) {
      _this.methods.push(new TransferMethods.Url($methods.find('.url')));
      $methods.find('.url').removeClass('hide');
    }

    // Bridge methods with queue.
    $methods.on('transfer-method:loaded', function(ev, transfer) {
      _this.queue.enqueue(transfer);
    });

    // Make room once at least once transfer has been queued.
    $queue.on('transfer-queue:enqueued', function() {
      $queue.addClass('with-items');
    });

    // Handle interaction with main action buttons.
    $cancel.on('click', function(ev) {
      ev.preventDefault();
      _this.element.trigger('media-explorer:cancel');
      _this.queue.cancel();
    });

    $start.one('click', function(ev) {
      ev.preventDefault();
      _this.queue.start();
    });
    $queue.on('transfer-queue:allDone', function(ev) {
      $start.addClass('all-done');
      $start.find('.button-text').text('Fertig');

      // Overlay previous behavior.
      $start.one('click', function(ev) {
        ev.preventDefault();

        _this.queue.reset();

        $start.removeClass('all-done');
        $start.find('.button-text').text('Hochladen');
        $start.find('.progress-bar').css('width', '0%');
        $start.one('click', function(ev) {
          ev.preventDefault();
          _this.queue.start();
        });
      });
    });
    $queue.on('transfer-queue:progress', function(ev, value) {
      $start.find('.progress-bar').css('width', value + '%');
    });
    $queue.on('transfer-queue:reset', function(ev, value) {
      $queue.removeClass('with-items');
    });
  };
});
