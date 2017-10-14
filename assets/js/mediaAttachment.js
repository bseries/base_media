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
  'mediaExplorerModal',
  'jqueryUi',
  'domready!'
], function(
  $,
  Router,
  MediaExplorerModal
) {
  'use strict';

  // Uses input fields as a store for ids and thus references
  // to full media items. Media items themselves always have
  // a backreference to their id through their `data-id` property.
  function MediaAttachment() {
    var _this = this;

    this.element = null;
    this.elements = {};

    // Either direct or joined.
    this.formBinding = null;

    // Can be used to specify how many items may
    // be selected. As an edge case: there can also
    // be media attachment instances with form binding
    // multi and selectable 1.
    //
    // Not used here directly but passed through to ME.
    this.selectable = true;

    // Enable sorting for items, makes only sense for multiple items.
    this.sortable = false;

    this.init = function(element, options) {
      options = $.extend({
        formBinding: _this.formBinding,
        selectable: _this.selectable,
        sortable: _this.sortable
      }, options);

      _this.formBinding = options.formBinding;
      _this.selectable = options.selectable;
      _this.sortable = options.sortable;

      _this.element = $(element);

      _this.elements = {
        select: _this.element.find('.select'),
        selected: _this.element.find('.selected')
        // Also see inputs() and items().
      };

      _this.populate().done(function() {
        _this.elements.selected.addClass('populated');

        // Needs to come after population, otherwise mutations
        // are triggered initially.
        _this.keepSynced();
      });

      _this.elements.select.on('click', function(ev) {
        ev.preventDefault();
        _this.interactWithMediaExplorer();
      });

      if (_this.sortable) {
        _this.element.sortable({
          placeholder: 'sortable-placeholder',
          items: '.media-item'
        });
      }
    };

    // Returns a current live list of all inputs.
    // Needed as that may change during runtime.
    this.inputs = function() {
      return _this.element.find('input');
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
      var observer = new MutationObserver(function() {
        var items = _this.items();

        if (_this.formBinding === 'direct') {
          // Signaling the backend to attach, update or detach the item works
          // for single attachments by using a single hidde input. When this
          // input contains an empty value the item is detached upon form
          // submit. Name pattern i.e. 'cover_media_id'.

          if (items.length === 1) {
            $(_this.inputs().get(0)).val(
               $(items.get(0)).data('id')
            );
          } else {
            $(_this.inputs().get(0)).val('');
          }
        } else {
          // Multi attachments use different markup than single ones. The
          // backend will always detach any attachment first than attach
          // the ones provided by inputs. That's why we cannot use empty
          // values but need to remove all inputs for detachment. Name
          // pattern i.e. 'media[1][id]'.
          //
          // FIXME Make first part of input name customizable.

          if (items.length) {
            // Must rebuild array of inputs entirely.
            _this.inputs().remove();

            items.each(function(index, el) {
              var id = $(el).data('id');

              var html = '<input type="hidden" name="media[' + id +  '][id]" value="' + id + '">';
              _this.element.append(html);
            });
          } else {
            _this.inputs().remove();

            var html = '<input type="hidden" name="media[_delete]" value="1">';
            _this.element.append(html);
          }
        }
      });

      observer.observe(_this.elements.selected.get(0), {
        childList: true,
        subtree: true
      });
    };

    // Populates the select area with already selected
    // items (with images and handles) when we just have the ids
    // fromt the input fields..
    this.populate = function() {
      var dfrs = [];

      // Preload async but append sequentially to keep order.

      var ids = [];
      _this.inputs().each(function(k, el) {
        var value = $(el).val();

        // Prevent 0/null ids to be added.
        if (value !== '0' && value !== '') {
          ids.push(value);
        }
      });
      return _this.append(ids);
    };

    // Builds and appends items to the select area
    // using just the id of each item. Also keeps
    // order sequence.
    this.append = function(ids) {
      if (ids.length === 0) {
        return (new $.Deferred()).resolve();
      }
      // We batch retrieval of metadata for performance reasons.
      return Router.match('media:view-batch')
        .then(function(url) {
          return $.post(url, {ids: ids});
        })
        .then(function(data) {
          // Append synchronous to keep order.
          $.each(ids, function(k, id) {
            // 1. Check if returned data contains requested ID and do not use it
            //    blindly.
            // 2. We iterate over our set of ids as the returned data is not
            //    necessarily order in the order of ids we provided.
            // 3. Implicitly updates inputes as we add the item and modify the
            //    subtree. See keepSynced().
            if (id in data.data.files) {
              _this.elements.selected.append(
                _this._buildSelectedItemHtml(data.data.files[id])
              );
            } else {
              throw new Error('Incomplete API response, id ' + id + ' missing.');
            }
          });
        });
    };

    this._buildSelectedItemHtml = function(item) {
      var wrap = $('<article class="media-item">');

      if (item.versions.fix2admin) {
        wrap.css('background-image', 'url(' + item.versions.fix2admin.url + ')');
      } else {
        wrap.addClass('no-visual');
      }
      var button = $('<a href="#remove" class="remove-item">×</a>');
      wrap.append(button);

      button.on('click', function(ev) {
        ev.preventDefault();
        $(this).parent().remove();
      });
      wrap.data('id', item.id);

      return wrap;
    };

    this.interactWithMediaExplorer = function() {
      var ids = [];
      $(_this.items()).each(function(k, el) {
        ids.push(parseInt($(el).data('id'), 10));
      });

     $(document).one('media-explorer:selected', function(ev, ids) {
        // We rebuilt selected items completely and replace
        // the current selection with the one we get from ME.
        // Removing all existing first wont be visible as it
        // is assumed that the ME window overlays that area.
        // Also this is a not too slow operation. If yes this
        // part needs a better implementation.
        _this.elements.selected.html('');

        // Wait until all items are built and appended then close ME.
        _this.append(ids).then(MediaExplorerModal.close);
     });
     $(document).one('media-explorer:cancel', function(ev) {
        $(document).off('media-explorer:selected');
     });
     $(document).one('modal:isClosing', function(ev) {
        $(document).off('media-explorer:selected');
     });

      Router.match('media:capabilities')
        .done(function(url) {
          $.getJSON(url)
          .done(function(res) {

            MediaExplorerModal.init({
              'available': {
                selected: ids,
                selectable: _this.selectable
              },
              'transfer': res.data.transfer
            });

            MediaExplorerModal.open();
          });
        });
    };
  }

  return {
    direct: function(element, options) {
      options = options || {};
      var ma = new MediaAttachment();

      options.formBinding = 'direct';
      options.selectable = 1;

      ma.init(element, options);
    },
    joined: function(element, options) {
      options = options || {};
      var ma = new MediaAttachment();

      options.formBinding = 'joined';
      options.selectable = true;
      options.sortable = true;

      ma.init(element, options);
    }
  };
});
