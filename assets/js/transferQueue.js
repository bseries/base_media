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
  'handlebars',
  'text!base-media/js/templates/transferQueueItem.hbs',
],
function(
   $,
   Handlebars,
   itemTemplate
) {

  return function TransferQueue(element) {
    var _this = this;

    // Need to scope helpers per instance.
    var ScopedHandlebars = Handlebars.create();

    this.element = $(element);

    this.template = ScopedHandlebars.compile(itemTemplate);

    this.data = [];

    this.enqueue = function(transfer) {
      _this.element.trigger('transfer-queue:enqueued');

      transfer.element = $(_this.template({
        progress: "0%"
        // Size and preview are set below lazily.
      }));

      transfer.element.find('.remove-item').on('click', function(ev) {
        ev.preventDefault();
        ev.stopPropagation();

        transfer.element.remove();
        transfer.isCancelled = true;
      });

      var $card = transfer.element.find('.card');
      var $preview = transfer.element.find('.preview');
      var $title = transfer.element.find('.title');
      var $size = transfer.element.find('.size');
      var $message = transfer.element.find('.message');

      transfer.meta()
        .done(function(meta) {
          if (meta.size) {
            $size.text(_this._formatSize(meta.size));
          }
          if (meta.title) {
            $title.text(meta.title);
          }
        })
        .fail(function(msg) {
            $card.html('?');
            $message.text(msg);
            _this._updateStatus(transfer, 'error');
        });

      transfer.preview()
        .done(function(dataUrl) {
          transfer.element.addClass('has-visual');

          // We assume preview is always an image.
          $preview.replaceWith($('<img />').attr('src', dataUrl).addClass($preview.attr('class')));
        })
        .fail(function() {
          transfer.meta()
            .done(function(meta) {
              $card.html(meta.title);
            })
            .fail(function(msg) {
              // Possibly already failed above and set message.
            });
        });

      // Initial state.
      _this._updateStatus(transfer, 'pending');

      _this.data.push(transfer);
      _this.element.find('.items').prepend(transfer.element);
    };

    // Starts all transfers that have not been run.
    this.start = function() {
      var dfr = $.Deferred();
      var prm = dfr.promise();

      var $start = _this.element.find('.start');
      var $cancel = _this.element.find('.cancel');

      $start.attr('disabled', 'disabled');
      $cancel.removeAttr('disabled');

      $.each(_this.data, function() {
        var t = this;

        if (!t.hasRun && !t.isCancelled && !t.isFailed) {
          prm = prm.then(function() {
            return _this._start(t);
          });
        }
      });
      dfr.resolve();

      prm.always(function() {
        _this.element.trigger('transfer-queue:allDone');
        $start.prop('disabled', false);
        $cancel.prop('disabled', true);
      });
    };

    this._calcTotalProgress = function() {
      var count = 0;
      var result = 0;

      $.each(_this.data, function() {
        var t = this;
        if (!t.hasRun && !t.isCancelled && t.progress !== null && !t.isFailed) {
          count++;
          result += t.progress;
        }
      });
      return Math.round(result / count);
    };

    this._start = function(transfer) {
      _this._updateStatus(transfer, 'transferring');

      var run = transfer.run();

      var $progress = transfer.element.find('.progress');
      var $message = transfer.element.find('.message');

      run.progress(function(type, value) {
        if (type === 'progress') {
          transfer.progress = value;

          $progress.text(value + '%');
          transfer.element.find('img').css({
            'filter': 'grayscale(' + (100 - value) + '%)',
            '-webkit-filter': 'grayscale(' + (100 - value) + '%)'
          });

          if (value > 99) {
            _this._updateStatus(transfer, 'processing');
          }
          _this.element.trigger('transfer-queue:progress', [_this._calcTotalProgress()]);
        }
        if (type === 'status') {
          _this._updateStatus(transfer, value);
        }
      });

      // Sets status and triggers finished event with item
      // data as returned from endpoint.
      run.done(function(data) {
        _this._updateStatus(transfer, 'success');
        transfer.element.trigger('transfer-queue:finished', [data]);
      });
      run.fail(function(msg) {
        transfer.isFailed = true;
        _this._updateStatus(transfer, 'error');
        $message.text(msg);
      });
      run.always(function() {
        // Some methods may not report progress, so we force
        // it to be 100% on here.
        transfer.progress = 100;
        $progress.text('100%');
        _this.element.trigger('transfer-queue:progress', [_this._calcTotalProgress()]);

        // Must come after progress, as run transfers are not included.
        transfer.hasRun = true;
      });

      return run;
    };

    // Aborts all cancellable transfers.
    this.cancel = function() {
      $.each(_this.data, function() {
        if (this.cancel) {
          this.cancel();
        }
        this.isCancelled = true;
      });
      _this.data = [];
    };

    this.reset = function() {
      _this.element.trigger('transfer-queue:reset');

      _this.data = [];
      _this.element.find('.items').html('');
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

