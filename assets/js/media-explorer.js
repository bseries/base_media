/*!
 * Bureau Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

define([
  'jquery',
  'handlebars',
  'compat',
  'text!media/js/templates/media-explorer-index.hbs',
  'text!media/js/templates/media-explorer-item.hbs'
],
function(
  $,
  Handlebars,
  Compat,
  indexTemplate,
  itemTemplate
) {

  Compat.run([
    'sendAsBinary'
  ]);

  return function MediaExplorer() {
    var _this = this;

    // Are we embedded and show select features?
    // true for inifinite multi or an integer for
    // number of items that are selectable. False
    // if selection feature should be disabled.
    this.selectable = false;

    this.element = null;
    this.elements = {
      available: null,
      transfer: {
        file: null,
        start: null,
        select: null
      },
      selection: {
        wrap: null,
        confirm: null,
        cancel: null
      }
    };

    this.endpoints = {
      namespace: ''
    };

    this.templates = {
      index: null,
      item: null
    };

    this.init = function(element, options) {
      _this.element = element;

      _this.endpoints = $.extend(_this.endpoints, options.endpoints || {});
      _this.selectable = options.selectable || false;

      _this.templates.index = Handlebars.compile(indexTemplate);
      _this.templates.item = Handlebars.compile(itemTemplate);
      Handlebars.registerPartial('item', _this.templates.item);

      _this.populate().done(function() {
          _this.elements.available = _this.element.find('.available');

          _this.elements.transfer.file = _this.element.find('.transfer .file');
          _this.elements.transfer.start = _this.element.find('.transfer .start');
          _this.elements.transfer.select = _this.element.find('.transfer .select');

          _this.elements.selection.wrap = _this.element.find('.selection');
          _this.elements.selection.confirm = _this.elements.selection.wrap.find('.confirm');
          _this.elements.selection.cancel = _this.elements.selection.wrap.find('.cancel');

          if (_this.selectable) {
            _this.elements.selection.wrap.removeClass('hide');
          }

          _this.bindEvents();
      });
    };

    this.populate = function() {
      return $.getJSON('/' + _this.endpoints.namespace + '/files')
        .done(function(data) {
          _this.element.html(_this.templates.index(data));
        });
    };

    this.bindEvents = function() {
      _this.elements.transfer.select.on('click', function() {
        _this.elements.transfer.file.trigger('click');

        _this.elements.transfer.file.on('change', function(ev) {
          $('.transfer .title').text(this.files[0].name);
        });
      });
      _this.elements.transfer.start.on('click', function() {
        _this.elements.transfer.start.attr('disabled', 'disabled');

        transfer($fileElement.get(0).files[0])
          .done(function(data) {
            _this.insert(data.file);
            _this.elements.transfer.start.removeAttr('disabled');
          });
      });
      if (_this.selectable) {
        _this.elements.selection.confirm.on('click', _this.confirmSelection);
        _this.elements.selection.cancel.on('click', _this.cancelSelection);

        _this.elements.available.on('click', '.file', function() {
          $this = $(this);

          if (_this.selectable === 1) {
            _this.elements.available.find('.file').removeClass('selected');
            $this.addClass('selected');
          } else if (_this.selectable === true) {
            $this.toggleClass('selected');
          } else if (_this.selectable > 1) {
            var current = _this.elements.available.find('.file.selected').length;

            if ($this.hasClass('selected') || current < _this.selectable) {
              $this.toggleClass('selected');
            } else if (current >= _this.selectable) {
              // Notify user that items must be deselected first to select new ones.
            }
          }
        });
      }
    };

    this.insert = function(item) {
      _this.elements.available.prepend(_this.templates.item(item));
    };

    this.transfer = function(file) {
      $(document).trigger('transfer:start');

      var reader = new FileReader();
      var xhr = new XMLHttpRequest();

      var dfr = new $.Deferred();
      dfr.done(function() {
        $(document).trigger('transfer:done');
      });

      xhr.open('POST', '/' + _this.endpoints.namespace + '/files/transfer?title=' + file.name);
      xhr.overrideMimeType('text/plain; charset=x-user-defined-binary');
      $(document).trigger('transfer:start');

      // Redirect and reformat progress events.
      xhr.upload.addEventListener('progress', function(ev) {
        if (ev.lengthComputable) {
          $(document).trigger('transfer:progress', (ev.loaded * 100) / ev.total);
        }
      }, false);

      xhr.onload = function(done) {
        $(document).trigger('transfer:done');
        dfr.resolve($.parseJSON(this.responseText));
      };

      reader.onload = function(ev) {
        xhr.sendAsBinary(ev.target.result);
      };
      reader.readAsBinaryString(file);

      return dfr.promise();
    };

    this.cancelSelection = function() {
      $(document).trigger('media-explorer:cancel');
    };

    this.confirmSelection = function() {
      var ids = [];

      _this.elements.available.find('.selected').each(function(k, el) {
        ids.push($(el).data('id'));
      });
      console.debug(ids);
      $(document).trigger('media-explorer:selected', [ids]);
    };
  };
});
