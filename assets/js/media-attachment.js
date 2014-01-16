define([
  'jquery',
  'media-explorer-modal',
  'domready!'
],
function($, MediaExplorerModal) {

  // Uses input fields as a store for ids and thus references
  // to full media items. Media items themselves always have
  // a backreference to their id through their `data-id` property.
  function MediaAttachment() {
    var _this = this;

    this.element = null;
    this.elements = {};

    // Either one or multi.
    this.formBinding = null;

    // Can be used to specify how many items may
    // be selected. As an edge case: there can also
    // be media attachment instances with form binding
    // multi and selectable 1.
    //
    // Not used here directly but passed through to ME.
    this.selectable = true;

    this.endpoints = {
      // Not used here but passed through to ME.
      index: '/files',
      view: '/files/__ID__'
    };

    this.init = function(element, options) {
      options = $.extend({
        formBinding: _this.formBinding,
        endpoints: _this.endpoints,
        selectable: _this.selectable
      }, options);

      _this.formBinding = options.formBinding;
      _this.selectable = options.selectable;
      _this.endpoints = options.endpoints;

      _this.element = $(element);

      _this.elements = {
        select: _this.element.find('.select'),
        selected: _this.element.find('.selected')
        // Also see inputs() and items().
      };

      _this.elements.select.on('click', function(ev) {
        ev.preventDefault();
        _this.interactWithMediaExplorer();
      });

      _this.populate();
      _this.keepSynced();
    };

    // Returns endpoint string; may replace __ID__ placeholder.
    this.endpoint = function(name, id) {
      var item = _this.endpoints[name];

      if (name == 'view') {
        return item.replace('__ID__', id);
      }
      return item;
    };

    // Returns a current live list of all inputs.
    // Needed as that may change during runtime.
    this.inputs = function() {
        return _this.element.find('input[name*=id]');
    };

    // Returns a current live list of all items.
    // Needed as that may change during runtime.
    this.items = function() {
      return _this.elements.selected.find('.media-item');
    };

    // Synchronizes input fields when an item is added or removed.
    // It is assumed there is/was an initial state of inputs that didn't
    // need any further adaption.
    this.keepSynced = function() {
      _this.elements.selected.on('DOMSubtreeModified', function() {
        var inputs = _this.inputs();
        var items = _this.items();

        if (_this.formBinding === 'one') {
          // Signaling the backend to attach, update or detach the item works
          // for single attachments by using a single hidde input. When this
          // input contains an empty value the item is detached upon form
          // submit.

          if (items.length === 1) {
            $(inputs.get(0)).val(
               $(items.get(0)).data('id')
            );
          } else {
            $(inputs.get(0)).val('');
          }
        } else {
            // Multi attachments use different markup than single ones. The
            // backend will always detach any attachment first than attach
            // the ones provided by inputs. That's why we cannot use empty
            // values but need to remove all inputs for detachment.
            //
            // FIXME Make first part of input name customizable.

            if (items.length) {
              // Must rebuild array of inputs entirely.

              inputs.remove();
              items.each(function(index, el) {
                var id = $(el).data('id');

                var html = '<input type="hidden" name="media[' + id +  '][id]" value="' + id + '">';
                _this.element.append(html);
              });
            } else {
              inputs.remove();
            }
        }
      });
    };

    // Populates the select area with already selected
    // items (with images and handles) when we just have the ids
    // fromt the input fields..
    this.populate = function() {
      _this.inputs().each(function(k, el) {
        var value = $(el).val();

        if (value) {
          _this.append(value);
        }
      });
    };

    // Builds and appends an item to the select area
    // using just its id.
    this.append = function(id) {
      var req = $.getJSON(_this.endpoint('view', id));

      req.done(function(data) {
          // Implicitly updates inputes as we add
          // the item and modify the subtree. See keepSynced().
          _this.elements.selected.append(
            _this.buildSelectedItemHtml(data.file)
          );
        });
    };

    this.buildSelectedItemHtml = function(item) {
      var wrap = $('<article class="media-item">');
      wrap.append($('<img>').attr('src', item.versions.fix2.url));

      var button = $('<button class="remove">remove</button>');
      wrap.append(button);

      button.on('click', function(ev) {
        ev.preventDefault();
        $(this).parent().remove();
      });
      wrap.data('id', item.id);

      return wrap;
    };

    this.interactWithMediaExplorer = function() {
      MediaExplorerModal.init({
        selectable: _this.selectable,
        endpoints: _this.endpoints
      });

      MediaExplorerModal.open();

      $(document).one('media-explorer:selected', function(ev, ids) {
        // Pool deferreds.
        var dfrs = [];

        // We rebuilt selected items completely and replace
        // the current selection with the one we get from ME.
        // Removing all existing first wont be visible as it
        // is assumed that the ME window overlays that area.
        // Also this is a not too slow operation. If yes this
        // part needs a better implementation.
        _this.elements.selected.html('');

        $.each(ids, function(k, id) {
          dfrs.push(_this.append(id));

        });

        // Wait until all items are built and appended then close ME.
        $.when.apply($, dfrs).then(MediaExplorerModal.close);
      });
    };
  }

  return {
    one: function(element, options) {
      var ma = new MediaAttachment();

      options.formBinding = 'one';
      options.selectable = 1;

      ma.init(element, options);
    },
    multi: function(element, options) {
      var ma = new MediaAttachment();

      options.formBinding = 'multi';
      options.selectable = true;

      ma.init(element, options);
    }
  };
});
