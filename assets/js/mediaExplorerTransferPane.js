/*!
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

define([
  'jquery',
  'translator',
  'transferQueue',
  'transferMethods'
], function(
  $,
  Translator,
  TransferQueue,
  TransferMethods
) {
  'use strict';

  //
  // Transfer Pane, has two main areas/objects: the queue and the methods.
  // Bridges queue and methods.
  //
  return function TransferPane(element, options) {
    var _this = this;

    this.element = $(element);

    var t = (new Translator({
      "de": {
        'Done': 'Fertig',
        'Upload': 'Hochladen'
      }
    })).translate;

    options = $.extend({
      urlUpload: false,
      pdfs: false,
      animatedImages: false
    }, options);

    // Holds a transfer queue object.
    this.queue = null;

    // Holds several transfer method objects.
    this.methods = [];

    var $start = _this.element.find('.start');
    var $cancel = _this.element.find('.cancel');
    var $methods = _this.element.find('.methods');
    var $queue = _this.element.find('.queue');

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
      $start.find('.button-text').text(t('Done'));

      // Overlay previous behavior.
      $start.one('click', function(ev) {
        ev.preventDefault();

        _this.queue.reset();

        $start.removeClass('all-done');
        $start.find('.button-text').text(t('Upload'));
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
