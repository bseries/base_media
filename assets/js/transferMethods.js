/*!
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

define([
  'jquery',
  'router',
  'transfer'
], function(
   $,
   Router,
   Transfer
) {
  'use strict';

  //
  // Transfer Methods Namespace. All methods will trigger the "transfer-method:loaded" event
  // once a transferrable item has been loaded/selected. The event will receive a Transfer
  // object which can then be used to handle the transfer.
  //
  var TransferMethods = {};

  // Reusable methods.

  var fileLocalTransfer = function(file) {
    var xhr = new XMLHttpRequest();

    // Outer deferred. Will resolve with response from transfer.
    var dfr = new $.Deferred();

    // Bind all transfer handlers.
    xhr.upload.addEventListener('progress', function(ev) {
      // Redirect and reformat progress events.
      if (ev.lengthComputable) {
        dfr.notify('progress', Math.ceil((ev.loaded * 100) / ev.total));
      }
    }, false);
    xhr.onreadystatechange = function(ev) {
      if (xhr.readyState === 4) {
        var res = $.parseJSON(xhr.responseText);

        if (xhr.status === 200) {
          dfr.resolve(res.data.file);
        } else {
          dfr.reject(res.message);
        }
      }
    };

    Router.match('media:transfer', {'title': file.name})
      .then(function(url) {
        xhr.open('POST', url);
        xhr.overrideMimeType(file.type);
        xhr.send(file);
      });

    return {dfr: dfr.promise(), cancel: xhr.abort};
  };

  // Gets data url to be used for preview image.
  var fileLocalPreview = function(file) {
    var dfr = new $.Deferred();

    if (!file.type.match(/image\/(png|jpeg|gif)/)) {
      return dfr.reject();
    }
    var reader = new FileReader();

    // Wrap event so we get a consistent return value.
    reader.onload = function(ev) {
      dfr.resolve(ev.target.result);
    };
    reader.readAsDataURL(file);
    return dfr;
  };

  //
  // Local Drop Method aka "Upload from Computer"
  //
  TransferMethods.FileLocal = function(trigger, input) {
    var _this = this;

    this.element = $(input);

    var $input = $(input);
    var $select = $(trigger);

    // Bind click handler to button which in turn triggers
    // the actual file input select to pop up. The input
    // will be hidden. This allows us to style the input/button
    // as the actual input cannot be styled.
    //
    // Will automatically trigger action once file is selected.
    $select.on('click', function(ev) {
      ev.preventDefault();

      $input.trigger('click');

      $input.on('change', function(ev) {
        $.each(this.files, function() {
          var file = this;
          var transfer = new Transfer();

          transfer.run = function() {
            var r = fileLocalTransfer(file);

            transfer.cancel = r.cancel;

            r.dfr
              .progress(function(type, value) {
                if (type === 'progress') {
                  transfer.progress = value;
                }
              })
              .fail(function() {
                transfer.isFailed = true;
              });

            return r.dfr;
          };
          transfer.preview = function() {
            return fileLocalPreview(file);
          };
          transfer.meta = function() {
            return $.Deferred().resolve({
              size: file.size,
              title: file.name
            });
          };

          _this.element.trigger('transfer-method:loaded', [transfer]);
        });

        // Reset to initial/empty state, so that following actions do not see old files.
        $input.replaceWith($input.clone(true));
      });
    });
  };

  //
  // Local Drop Method
  //
  TransferMethods.FileLocalDrop = function(outer, inner) {
    var _this = this;

    this.outer = $(outer);
    this.inner = $(inner);

    var noop = function(ev) {
      ev.stopPropagation();
      ev.preventDefault();
    };

    // Use whole modal window content area as drop to reduce
    // possiblity user drops file into window (that would throw her
    // back into viewing the file directly).

    _this.outer.on('dragenter', function(ev) {
      noop(ev);
      $(this).addClass('dragged-over');
    });
    _this.outer.on('dragexit', function(ev) {
      noop(ev);
      $(this).removeClass('dragged-over');
    });
    _this.outer.on('dragover', noop);
    _this.outer.on('drop', function(ev) {
      noop(ev);

      var files = ev.originalEvent.dataTransfer.files;

      if (files.length > 0) {
        $.each(files, function() {
          var file = this;
          var transfer = new Transfer();

          transfer.run = function() {
            var r = fileLocalTransfer(file);

            transfer.cancel = r.cancel;

            r.dfr
              .progress(function(type, value) {
                if (type === 'progress') {
                  transfer.progress = value;
                }
              })
              .fail(function() {
                transfer.isFailed = true;
              });

            return r.dfr;
          };
          transfer.preview = function() {
            return fileLocalPreview(file);
          };
          transfer.meta = function() {
            return $.Deferred().resolve({
              size: file.size,
              title: file.name
            });
          };

          _this.inner.trigger('transfer-method:loaded', [transfer]);
        });
      }
      $(this).removeClass('dragged-over');
    });
  };

  //
  // URL Method
  //
  TransferMethods.Url = function(element) {
    var _this = this;

    this.element = $(element);

    var $ok = _this.element.find('.confirm');
    var $input = _this.element.find('input');

    $input.on('keyup', function(ev) {
      $ok.prop('disabled', !this.validity.valid || !this.value);
    });

    $ok.on('click', function(ev) {
      ev.preventDefault();
      var url = $input.val();
      var transfer = new Transfer();

      transfer.run = function() {
        // Need to wrap as its just a promise.
        // Fake progress.

        var dfr = new $.Deferred();
        var r = _this._transfer(url)
          .done(dfr.resolve)
          .fail(dfr.reject);

        dfr.notify('progress', transfer.progress = 0);
        dfr.done(function() {
          dfr.notify('progress', transfer.progress = 100);
        });

        return dfr.promise();
      };
      transfer.meta = function() {
        var dfr = new $.Deferred();

        Router.match('media:transfer-meta').done(function(_url) {
          $.ajax({
            type: 'POST',
            url: _url,
            data: 'url=' + url
          }).done(function(data) {
            dfr.resolve(data.data.file);
          });
        });
        return dfr.promise();
      };

      // Must come after transfer.meta.
      transfer.preview = function() {
        var dfr = new $.Deferred();

        transfer.meta().done(function(meta) {
          if (meta.preview) {
            dfr.resolve(meta.preview);
          } else {
            dfr.reject();
          }
        });
        return dfr;
      };

      _this.element.trigger('transfer-method:loaded', [transfer]);
      $input.val('');
    });

    this._transfer = function(url) {
      var dfr = new $.Deferred();

       Router.match('media:transfer', {'title': 'undefined'})
        .then(function(_url) {
          $.ajax({
            type: 'POST',
            url: _url,
            data: 'url=' + url
          }).done(function(data) {
            dfr.resolve(data.data.file);
          }).fail(function(res) {
            dfr.reject(res.responseJSON.message);
          });
        });

        return dfr;
    };
  };

  return TransferMethods;
});

