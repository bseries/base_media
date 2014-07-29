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
      vimeoUpload: false,
      urlUpload: false
    }, options);

    // Holds a transfer queue object.
    this.queue = null;

    // Holds several transfer method objects.
    this.methods = [];

    $start = _this.element.find('.start');
    $reset = _this.element.find('.reset');
    $cancel = _this.element.find('.cancel');
    $methods = _this.element.find('.methods');
    $queue = _this.element.find('.queue');

    _this.queue = new TransferQueue($queue);

    // FIXME Assumes ME is always contained in a modal for drop method.
    // FIXME Allow selecting which methods are available.
    _this.methods.push(new TransferMethods.FileLocal($methods.find('.file-local')));
    _this.methods.push(new TransferMethods.FileLocalDrop(
      _this.element.parents('#modal'),
      $methods.find('.drop-message')
    ));
    _this.methods.push(new TransferMethods.FileUrl($methods.find('.file-url')));
    _this.methods.push(new TransferMethods.Vimeo($methods.find('.vimeo')));

    // Bridge methods with queue.
    $methods.on('transfer-method:loaded', function(ev, transfer) {
      _this.queue.enqueue(transfer);
    });

    // Make room once at least once transfer has been queued.
    $queue.on('transfer-queue:enqueued', function() {
      $methods.addClass('hide');
    });

    // Handle interaction with main action buttons.
    $start.on('click', function(ev) {
      ev.preventDefault();
      _this.queue.start();
    });
    $cancel.on('click', function(ev) {
      ev.preventDefault();
      _this.queue.cancel();
    });
    $reset.on('click', function(ev) {
      ev.preventDefault();
      _this.queue.reset();
      $methods.removeClass('hide');
    });
  };
});
