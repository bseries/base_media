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
  'handlebars',
  'text!cms-media/js/templates/mediaExplorerAvailableItem.hbs',
],
function(
  $,
  Router,
  Handlebars,
  itemTemplate
) {

  //
  // Available Items Pane
  //
  return function AvailablePane(element, options) {
    var _this = this;

    this.element = $(element);

    options = $.extend({
      selectable: null,
      selected: []
    }, options);

    // Are we embedded and show select features?
    // true for inifinite multi or an integer for
    // number of items that are selectable. False
    // if selection feature should be disabled.
    this.selectable = options.selectable || false;

    this.selected = options.selected || [];

    this.template = Handlebars.compile(itemTemplate);

    if (_this.selectable) {
      _this.element.find('.confirm').removeClass('hide');
    }

    this.insert = function(data) {
      _this.element.find('.items').prepend(_this.template(data));
    };

    this.items = function(selector) {
      return _this.element.find('.item' + (selector || ''));
    };

    // Populates existing available files.
    // Preset selected.
    this.populate = function() {
      return Router.match('media:index')
        .then(function(url) {
          return $.getJSON(url);
        })
        .then(function(data) {
          var $items = _this.element.find('.items');

          // Returned data is nested under "files" key.
          $.each(data.files, function() {
            var $el = $(_this.template(this));

            if ($.inArray($el.data('id'), _this.selected) !== -1) {
              $el.addClass('selected');
            }
            $items.prepend($el);
          });
          _this.sort();
      });
    };

    this.sort = function() {
      var items = _this.items().get();

      // Sort already selected items to top.
      items.sort(function(x, y) {
        var $x = $(x);
        var $y = $(y);

        if ($x.hasClass('selected') && $y.hasClass('selected')) {
          // Sub sort by id descending; lower ids come first.
          if ($x.data('id') > $y.data('id')) {
            return -1;
          }
          return 1;
        }
        if ($x.hasClass('selected')) {
          return -1;
        }
        return 1;
      });

      // Replace in place.
      _this.element.find('.items').html(items);
    };

    this.handleSelection = function() {
      // Signals outer world that we're cancelling.
      _this.element.on('click', '.cancel', function() {
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
        var ids = [];

        _this.items('.selected').each(function(k, el) {
          ids.push($(el).data('id'));
        });
        _this.element.trigger('media-explorer:selected', [ids]);
      });

      // Marks an item as selected when clicked on it by assinging class.
      _this.element.on('click', '.item', function() {
        $this = $(this);

        if (_this.selectable === 1) {
          _this.items().removeClass('selected');
          $this.addClass('selected');
        } else if (_this.selectable === true) {
          $this.toggleClass('selected');
        } else if (_this.selectable > 1) {
          var current = _this.items('.selected').length;

          if ($this.hasClass('selected') || current < _this.selectable) {
            $this.toggleClass('selected');
          } else if (current >= _this.selectable) {
            // FIXME Notify user that items must be deselected first to select new ones.
          }
        }
      });
    };

    // Filters existing files using dead simple search.
    this.bindAvailableFilter = function() {
      var $search = _this.element.parent().find('.search');

      $search.on('keyup', function() {
        var val = $(this).val();

        _this.items().each(function() {
          var $item = $(this);
          var haystack = $item.data('type') + '|' + $item.find('.title').text();

          if (haystack.indexOf(val) !== -1) {
            $item.removeClass('hide');
          } else {
            $item.addClass('hide');
          }
        });
      });
    };

    // Further Initialization.
    this.bindAvailableFilter();
    this.populate();
    this.handleSelection();
  };
});
