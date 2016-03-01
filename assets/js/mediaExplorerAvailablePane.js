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
  'router',
  'handlebars',
  'moment',
  'dataGrid',
  'underscore',
  'text!base-media/js/templates/mediaExplorerAvailableItem.hbs',
],
function(
  $,
  Router,
  Handlebars,
  Moment,
  DataGrid,
  _,
  itemTemplate
) {

  Moment.locale($('html').attr('lang'));
  itemTemplate = Handlebars.compile(itemTemplate);

  //
  // Available Items Pane
  //
  return function AvailablePane(element, options) {
    var _this = this;

    this.element = $(element);

    options = $.extend({
      selectable: true,
      selected: []
    }, options);

    // Are we embedded and show select features?
    // true for inifinite multi or an integer for
    // number of items that are selectable. False
    // if selection feature should be disabled.
    this.selectable = options.selectable || false;

    this.selected = options.selected || [];

    var currentSearchXhr;

    this.grid = new DataGrid(_this.element.find('.items'), {

      ensure: function() {
        return _this.selected;
      },

      sorters: [
        function(a, b) { // First sort newest to top.
          return Moment(a.created).unix() - Moment(b.created).unix();
        },
        function(a, b) { // Then sort selected to top.
          var aIsSelected = $.inArray(a.id, _this.selected) !== -1;
          var bIsSelected = $.inArray(b.id, _this.selected) !== -1;

          if (aIsSelected && bIsSelected) {
            return 0;
          }
          if (aIsSelected) {
            return -1;
          }
          return 1;
        },
      ],

      renderItem: function(item) {
        var $item = $(itemTemplate($.extend(_.clone(item), {
          created: Moment(item.created).format('l')
        })));

        if ($.inArray(item.id, _this.selected) !== -1) {
          $item.addClass('selected');
        }
        return $item.get(0).outerHTML;
      },

      index: function(page) {
        var dfr = new $.Deferred();

        Router.match('media:index', {page: page})
          .then(function(url) {
            $.getJSON(url).done(function(data) {
              dfr.resolve(data.data.files, data.data.meta);
            });
          });

        return dfr.promise();
      },

      view: function(id) {
        var dfr = new $.Deferred();

        Router.match('media:view', {id: id})
          .then(function(url) {
            $.getJSON(url).done(function(data) {
              dfr.resolve(data.data.file);
            });
          });

        return dfr.promise();
      },

      search: function(q, page) {
        var dfr = new $.Deferred();

        Router.match('media:search', {q: q, page: page})
          .then(function(url) {
            if (currentSearchXhr) {
              currentSearchXhr.abort();
            }
            currentSearchXhr = $.getJSON(url).done(function(data) {
              dfr.resolve(data.data.files, data.data.meta);
            });
          });

        return dfr.promise();
      }

    });

    if (_this.selectable) {
      _this.element.find('.confirm').removeClass('hide');
    }

    this.insert = function(item) {
      _this.grid.insert(item);
    };

    this.handleSelection = function() {
      // Signals outer world that we're cancelling.
      _this.element.on('click', '.cancel', function(ev) {
        ev.preventDefault();
        _this.element.trigger('media-explorer:cancel');
      });

      if (!_this.selectable) {
        // Make selection features not available. Also
        // don't even attach to signals we don't need.

        // We don't need to hide the confirm button as it's
        // hidden by default.
        return;
      }

      // Passes an array of selected item ids via :selected event.
      _this.element.on('click', '.confirm', function(ev) {
        ev.preventDefault();
        _this.element.trigger('media-explorer:selected', [_this.selected]);
      });

      // Marks an item as selected when clicked on it by assinging class.
      _this.element.on('click', '.item', function() {
        var $this = $(this);
        var id = $this.data('id');

        if (_this.selectable === 1) {
          _this.selected = [id]; // Replace
          _this.grid.$items().removeClass('selected');
          $this.addClass('selected');

        } else if (_this.selectable === true) {
          if ($this.hasClass('selected')) {
            _this.selected.splice(_this.selected.indexOf(id), 1);
          } else {
            if ($.inArray(id, _this.selected) === -1) {
              _this.selected.push(id);
            }
          }
          $this.toggleClass('selected');

        } else if (_this.selectable > 1) {
          var current = _this.selected.length;

          if ($this.hasClass('selected') || current < _this.selectable) {
            if ($.inArray(id, _this.selected) === -1) {
              _this.selected.push(id);
            }
            $this.toggleClass('selected');

          } else if (current >= _this.selectable) {
            // FIXME Notify user that items must be deselected first to select new ones.
          }
        }
      });
    };

    // Filters existing files using dead simple search.
    this.bindAvailableFilter = function() {
      _this.element.parent().find('.search').on('keyup', _.debounce(function() {
        _this.grid.search($(this).val());
      }, 100));
    };

    // Further Initialization.
    this.bindAvailableFilter();

    this.grid.init();
    this.handleSelection();
  };
});
