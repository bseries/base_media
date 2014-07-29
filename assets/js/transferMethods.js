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
  'router',
  'transfer'
],
function(
   $,
   Router,
   Transfer
) {
  //
  // Transfer Methods Namespace. All methods will trigger the "transfer-method:loaded" event
  // once a transferrable item has been loaded/selected. The event will receive a Transfer
  // object which can then be used to handle the transfer.
  //
  var TransferMethods = {};

  // Reusable methods.

  var fileLocalTransfer = function(file) {
    var reader = new FileReader();
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
    xhr.onload = function() {
      dfr.resolve($.parseJSON(this.responseText));
    };
    xhr.upload.addEventListener('error', function(ev) {
        // FIXME parse json error message
//          dfr.reject($.parseJSON(this.responseText));
        dfr.reject();
    }, false);

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
  TransferMethods.FileLocal = function(element) {
    var _this = this;

    this.element = $(element);

    var $input = _this.element.find('input');
    var $select = _this.element.find('button');

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

          _this.element.trigger('transfer-method:loaded', [$.extend(new Transfer(), {
            run: function() { return fileLocalTransfer(file); },
            preview: function() { return fileLocalPreview(file); },
            meta: function() {
              return $.Deferred().resolve({
                size: file.size,
                title: file.name
              });
            }
          })]);
        });
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

          _this.inner.trigger('transfer-method:loaded', [$.extend(new Transfer(), {
            run: function() { return fileLocalTransfer(file); },
            preview: function() { return fileLocalPreview(file); },
            meta: function() {
              return $.Deferred().resolve({
                size: file.size,
                title: file.name
              });
            }
          })]);
        });
      }
      $(this).removeClass('dragged-over');
    });
  };

  //
  // File URL Method
  //
  TransferMethods.FileUrl = function(element) {
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

      _this.element.trigger('transfer-method:loaded', [$.extend(new Transfer(), {
        run: function() {
          return _this.transfer(url);
        },
        meta: function() {
          var dfr = new $.Deferred();

          Router.match('media:transfer-meta').done(function(_url) {
            $.ajax({
              type: 'POST',
              url: _url,
              data: 'url=' + url
            }).done(function(data) {
              dfr.resolve(data.file);
            });
          });
          return dfr;
        }
      })]);

      $input.val('');
    });

    this.transfer = function(url) {
       var dfr = Router.match('media:transfer', {'title': ''})
        .then(function(_url) {
          return $.ajax({
            type: 'POST',
            url: _url,
            data: 'url=' + url
          });
        });
        return {dfr: dfr.promise(), cancel: null};
    };
  };

  //
  // Vimeo (ID) Method
  //
  // FIXME Preview Image via HEAD Request?
  TransferMethods.Vimeo = function(element) {
    var _this = this;

    this.element = $(element);

    var $ok = _this.element.find('.confirm');
    var $input = _this.element.find('input');

    $input.on('keyup', function(ev) {
      $ok.prop('disabled', !this.validity.valid || !this.value);
    });

    $ok.on('click', function(ev) {
      ev.preventDefault();
      var id = $input.val();

      _this.element.trigger('transfer-method:loaded', [$.extend(new Transfer(), {
        run: function() {
          return _this.transfer(id);
        },
        meta: function() {
          var dfr = new $.Deferred();

          Router.match('media:transfer-meta').done(function(_url) {
            $.ajax({
              type: 'POST',
              url: _url,
              data: 'vimeo_id=' + id
            }).done(function(data) {
              dfr.resolve(data.file);
            });
          });
          return dfr;
        }
      })]);

      $input.val('');
    });

    this.transfer = function(id) {
       var dfr = Router.match('media:transfer', {'title': id})
        .then(function(url) {
          return $.ajax({
            type: 'POST',
            url: url,
            data: 'vimeo_id=' + id
          });
        });
        return {dfr: dfr.promise(), cancel: null};
    };
  };

  return TransferMethods;
});

