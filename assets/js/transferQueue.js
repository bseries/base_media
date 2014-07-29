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
  'handlebars',
  'text!cms-media/js/templates/transferQueueItem.hbs',
],
function(
   $,
   Handlebars,
   itemTemplate
) {

  return function TransferQueue(element) {
    var _this = this;

    this.element = $(element);

    this.template = Handlebars.compile(itemTemplate);

    this.data = [];

    this.enqueue = function(transfer) {
      _this.element.trigger('transfer-queue:enqueued');

      transfer.element = $(_this.template({
        progress: "0%"
        // Size and preview are set below lazily.
      }));

      var $title = transfer.element.find('.title');
      var $size = transfer.element.find('.size');
      transfer.meta().done(function(meta) {
        $title.text(meta.title);
        if (meta.size) {
          $size.text(_this._formatSize(meta.size));
        }
      });

      var $preview = transfer.element.find('.preview');
      transfer.preview().done(function(dataUrl) {
        // We assume preview is always an image.
        $preview.css('background-image', 'url(' + dataUrl + ')');
      });

      // Initial state.
      _this._updateStatus(transfer, 'pending');

      _this.data.push(transfer);
      _this.element.prepend(transfer.element);
    };

    // Starts all transfers that have not been run.
    this.start = function() {
      var chain = $.Deferred().resolve();

      var $start = _this.element.find('.start');
      var $cancel = _this.element.find('.cancel');

      $start.attr('disabled', 'disabled');
      $cancel.removeAttr('disabled');

      $.each(_this.data, function() {
        var t = this;

        if (!t.hasRun) {
          chain.then(function() {
            return _this._start(t);
          });
        }
      });

      chain.done(function() {
        $start.prop('disabled', false);
        $cancel.prop('disabled', true);
      });
    };

    this._start = function(transfer) {
      _this._updateStatus(transfer, 'transferring');

      var run = transfer.run();

      var $progress = transfer.element.find('.progress');
      var $message = transfer.element.find('.message');

      run.dfr.progress(function(type, value) {
        if (type === 'progress') {
          $progress.text(value + '%');

          if (value > 99) {
            _this._updateStatus(transfer, 'processing');
          }
        }
        if (type === 'status') {
          _this._updateStatus(transfer, value);
        }
      });

      // Sets status and triggers finished event with item
      // data as returned from endpoint.
      run.dfr.done(function(data) {
        _this._updateStatus(transfer, 'success');
        transfer.element.trigger('transfer-queue:finished', [data]);
      });
      run.dfr.fail(function(msg) {
        _this._updateStatus(transfer, 'error');
        $message.text(msg);
      });
      // Some methods may not report progress, so we force
      // it to be 100% on here.
      run.dfr.always(function() {
        $progress.text('100%');
      });

      transfer.hasRun = true;
      transfer.cancel = run.cancel;
      return run.dfr;
    };

    // Aborts all cancellable transfers.
    this.cancel = function() {
      $.each(_this.data, function() {
        if (this.cancel) {
          this.cancel();
        }
      });
      _this.data = [];
    };

    this.reset = function() {
      _this.element.trigger('transfer-queue:reset');

      _this.data = [];
      _this.element.html('');
    };

    // Helper function; formats a given file size nicely. Will leave null
    // values untouched.
    this._formatSize = function(value) {
      if (value === null) {
        return value;
      }
      var i = -1;
      var byteUnits = [' kB', ' MB', ' GB', ' TB', 'PB', 'EB', 'ZB', 'YB'];

      do {
          value = value / 1024;
          i++;
      } while (value > 1024);

      return Math.max(value, 0.1).toFixed(1) + byteUnits[i];
    };

    // Helper function; updates text status as well adds correct
    // classes, so that the transfer element can be styled according
    // to status.
    this._updateStatus = function(transfer, status) {
      transfer.element.removeClass(function(k, v) {
        return (v.match(/status-\S+/g) || []).join(' ');
      });
      transfer.element.addClass('status-' + status);
      transfer.element.find('.status').text(status);
    };
  };
});

