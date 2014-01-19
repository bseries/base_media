/*!
 * Bureau Media
 *
 * Copyright (c) 2013-2014 Atelier Disko - All rights reserved.
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
        wrap: null,
        start: null,

        upload: {
          input: null,
          title: null,
          select: null
        },
        url: {
          input: null
        },
        vimeo: {
          input: null
        }
      },
      selection: {
        wrap: null,
        confirm: null,
        cancel: null
      }
    };

    this.endpoints = {
      index: '/files',
      transfer: '/files/transfer'
    };

    this.templates = {
      index: null,
      item: null
    };

    this.init = function(element, options) {
      _this.element = element;

      options = $.extend({
        endpoints: {},
        selectable: null,
        selected: []
      }, options);

      _this.endpoints = $.extend(_this.endpoints, options.endpoints || {});

      _this.selectable = options.selectable || false;

      _this.templates.index = Handlebars.compile(indexTemplate);
      _this.templates.item = Handlebars.compile(itemTemplate);
      Handlebars.registerPartial('item', _this.templates.item);

      _this.populate().done(function() {
          _this.elements.available = _this.element.find('.available');

          var wrap;

          _this.elements.transfer.wrap = wrap = _this.element.find('.transfer');
          _this.elements.transfer.start = wrap.find('.start');

          _this.elements.transfer.upload = {
            input: wrap.find('.upload input'),
            title: wrap.find('.upload .title'),
            select: wrap.find('.upload .select')
          };
          _this.elements.transfer.url = {
            input: wrap.find('.url input')
          };
          _this.elements.transfer.vimeo = {
            input: wrap.find('.vimeo input')
          };

          _this.elements.selection.wrap = _this.element.find('.selection');
          _this.elements.selection.confirm = _this.elements.selection.wrap.find('.confirm');
          _this.elements.selection.cancel = _this.elements.selection.wrap.find('.cancel');

          if (_this.selectable) {
            _this.elements.selection.wrap.removeClass('hide');
          }

          // Preset selected.
          _this.elements.available.find('.item').each(function(k, el) {
            var $el = $(el);

            if ($.inArray($el.data('id'), options.selected) !== -1) {
              $el.addClass('selected');
            }
          });

          // DOM complete now ready to bind events.
          _this.bindEvents();
      });
    };

    // Returns endpoint string; may replace __ID__ placeholder.
    this.endpoint = function(name, id) {
      var item = _this.endpoints[name];

      if (name == 'view') {
        return item.replace('__ID__', id);
      }
      return item;
    };

    this.populate = function() {
      return $.getJSON(_this.endpoint('index'))
        .done(function(data) {
          _this.element.html(_this.templates.index(data));
        });
    };

    this.bindEvents = function() {
      // Pick a file to upload.
      _this.elements.transfer.upload.select.on('click', function(ev) {
        ev.preventDefault();
        _this.elements.transfer.upload.input.trigger('click');

        _this.elements.transfer.upload.input.on('change', function(ev) {
          $('.transfer .upload .title').text(this.files[0].name);
        });
      });

      // Execute transfer.
      _this.elements.transfer.start.on('click', function(ev) {
        ev.preventDefault();
        _this.elements.transfer.start.attr('disabled', 'disabled');

        var ready = _this.ready();
        var req;

        if (ready == 'upload') {
          req = _this.upload(_this.elements.transfer.upload.input.get(0).files[0]);
        } else {
          if (ready == 'url') {
            req = $.ajax({
              type: 'POST',
              url: _this.endpoint('transfer'),
              data: _this.elements.transfer.url.input.serialize()
            });
          } else if (ready == 'vimeo') {
            req = $.ajax({
              type: 'POST',
              url: _this.endpoint('transfer'),
              data: _this.elements.transfer.vimeo.input.serialize()
            });
          } else {
            // FIXME Notify user what went wrong.
            return;
          }
        }

        req.done(function(data) {
          _this.insert(data.file);
          _this.elements.transfer.start.removeAttr('disabled');
          _this.elements.transfer.wrap.slideUp(400);

          // Reset form entirely.
          _this.elements.transfer.upload.input.replaceWith(
            _this.elements.transfer.upload.input = _this.elements.transfer.upload.input.clone(true)
          );
          _this.elements.transfer.upload.title.text('');
          _this.elements.transfer.url.input.val('');
          _this.elements.transfer.vimeo.input.val('');
        });
      });

      if (_this.selectable) {
        _this.elements.selection.confirm.on('click', _this.confirmSelection);
        _this.elements.selection.cancel.on('click', _this.cancelSelection);

        _this.elements.available.on('click', '.item', function() {
          $this = $(this);

          if (_this.selectable === 1) {
            _this.elements.available.find('.item').removeClass('selected');
            $this.addClass('selected');
          } else if (_this.selectable === true) {
            $this.toggleClass('selected');
          } else if (_this.selectable > 1) {
            var current = _this.elements.available.find('.item.selected').length;

            if ($this.hasClass('selected') || current < _this.selectable) {
              $this.toggleClass('selected');
            } else if (current >= _this.selectable) {
              // FIXME Notify user that items must be deselected first to select new ones.
            }
          }
        });
      }

      _this.element.find('.transfer-toggle').on('click', function() {
        _this.elements.transfer.wrap.slideDown(300);
      });
      _this.element.find('.transfer .cancel').on('click', function(ev) {
        ev.preventDefault();
        _this.elements.transfer.wrap.slideUp(200);
      });
    };

    this.insert = function(item) {
      _this.elements.available.prepend(_this.templates.item(item));
    };

    this.ready = function() {
      if (_this.elements.transfer.upload.input.get(0).files.length) {
        return 'upload';
      }
      if (_this.elements.transfer.url.input.val() !== '') {
        return 'url';
      }
      if (_this.elements.transfer.vimeo.input.val() !== '') {
        return 'vimeo';
      }
      return false;
    };

    // Uploads a file using the file form upload method.
    this.upload = function(file) {
      $(document).trigger('transfer:start');

      var reader = new FileReader();
      var xhr = new XMLHttpRequest();

      var dfr = new $.Deferred();
      dfr.done(function() {
        $(document).trigger('transfer:done');
      });

      xhr.open('POST', _this.endpoint('transfer') + '?title=' + file.name);
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
      $(document).trigger('media-explorer:selected', [ids]);
    };
  };
});
