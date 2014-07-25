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
            run: function() {
              return _this.transfer(file);
            },
            image: function() {
              return _this.image(file);
            },
            title: file.name,
            size: file.size,
          })]);
        });
        $input.replaceWith($input.clone(true));
      });
    });

    // This is a "static" method and will be called from the outside once
    // for each file.
    this.transfer = function(file) {
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
    this.image = function(file) {
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

  };

  //
  // Local Drop Method
  //
  TransferMethods.FileLocalDrop = function(element) {
    var _this = this;

    this.element = $(element);

    var noop = function(ev) {
      ev.stopPropagation();
      ev.preventDefault();
    };

    // Use whole modal window content area as drop to reduce
    // possiblity user drops file into window (that would throw her
    // back into viewing the file directly).

    _this.element.on('dragenter', function(ev) {
      noop(ev);
      $(this).addClass('dragged-over');
    });
    _this.element.on('dragexit', function(ev) {
      noop(ev);
      $(this).removeClass('dragged-over');
    });
    _this.element.on('dragover', noop);
    _this.element.on('drop', function(ev) {
      noop(ev);

      var files = ev.originalEvent.dataTransfer.files;

      if (files.length > 0) {
        $.each(files, function() {
          var file = this;

          _this.element.trigger('transfer-method:loaded', [$.extend(new Transfer(), {
            // Reuses methods from other transfer method.
            run: function() {
              return TransferMethods.FileLocal.prototype.transfer(file);
            },
            image: function() {
              TransferMethods.FileLocal.prototype.image(file);
            },
            title: file.name,
            size: file.size,
          })]);
        });
      }
      $(this).removeClass('dragged-over');
    });
  };

  //
  // File URL Method
  //
  // FIXME parse title from URL.
  // FIXME Head request to get meta data?
  TransferMethods.FileUrl = function(element) {
    var _this = this;

    this.element = $(element);

    var $ok = _this.element.find('.confirm');
    var $input = _this.element.find('.input');

    $ok.on('click', function(ev) {
      ev.preventDefault();
      var url = $input.val();

      _this.element.trigger('transfer-method:loaded', [$.extend(new Transfer(), {
        run: function() {
          return _this.transfer(url);
        }
      })]);

      $input.val('');
    });

    this.transfer = function(url) {
       return Router.match('media:transfer', {'title': ''})
        .then(function(_url) {
          return $.ajax({
            type: 'POST',
            url: _url,
            data: 'url=' + url
          });
        });
    };
  };

  //
  // Vimeo (ID) Method
  //
  TransferMethods.Vimeo = function(element) {
    var _this = this;

    this.element = $(element);

    var $ok = _this.element.find('.confirm');
    var $input = _this.element.find('.input');

    $ok.on('click', function(ev) {
      ev.preventDefault();
      var id = $input.val();

      _this.element.trigger('transfer-method:loaded', [$.extend(new Transfer(), {
        run: function() {
          return _this.transfer(id);
        },
        title: id, // Use title as ID for now. We cannot contact the API.
      })]);

      $input.val('');
    });

    this.transfer = function(id) {
       return Router.match('media:transfer', {'title': id})
        .then(function(url) {
          return $.ajax({
            type: 'POST',
            url: url,
            data: 'vimeo_id=' + id
          });
        });
    };
  };

  return TransferMethods;
});

