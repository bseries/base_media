/*!
 * Data Grid
 *
 * Copyright (c) 2013-2014 David Persson - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

define([
  'jquery'
],
function(
  $
) {

  return function DataGrid(element, options) {
    var _this = this;

    options = $.extend({
      renderItem: function() {},
      index: function(page) {},
      search: function(query, page) {},
      sorters: [function(a, b) {}]
    }, options || {});

    this.$element = $(element);

    // Keeps unique set of items.
    this.data = {};

    this.page = 1;
    this.total = undefined;
    this.mode = 'index'; // index or search
    this.query = undefined; // if mode is search then this holds the current query.
    this.ensure = [];

    this.$items = function(selector) {
      return _this.$element.find('.item' + (selector || ''));
    };

    // Batch dom reades/writes.
    this.render = function() {
      var dfr = new $.Deferred();

      var html = '';
      var data = [];

      // Only arrays are sortable.
      $.each(_this.data, function() {
        data.push(this);
      });

      $.each(options.sorters, function() {
        data.sort(this);
      });
      $.each(data, function() {
        html = html + options.renderItem(this);
      });

      if (data.length < _this.total && data.length > 0) {
        html = html + '<div class="load-more"><a href="#more" class="button large">load more</a></div>';
      }
      _this.$element.html(html);

      return dfr.resolve().promise();
    };

    _this.$element.on('click', '.load-more', function(ev) {
      ev.preventDefault();
      _this.more();
    });

    this.init = function() {
      _this.$element.html('<p class="loading">loading…</p>');

      var main = new $.Deferred();
      var pool = [];
      var req;

      _this.data = {};
      _this.page = 1;
      _this.mode = 'index';

      var full = new $.Deferred();
      pool.push(full);

      options.index(_this.page)
        .done(function(data, meta) {
          _this.total = meta.total;

          $.each(data, function() {
            _this.data[this.id] = this;
          });
          full.resolve();
        });

      $.each(options.ensure(), function() {
        var one = new $.Deferred();
        pool.push(one);

        options.view(this)
          .done(function(item) {
            _this.data[item.id] = item;
            one.resolve();
          });
      });

      $.when.apply($, pool).done(function() {
        _this.render().done(main.resolve);
      });

      return main.promise();
    };

    this.more = function() {
      var dfr = new $.Deferred();
      _this.page++;
      // Not resetting _this.data, augmenting.

      var req;
      if (_this.mode == 'index') {
        req = options.index(_this.page);
      } else if (_this.mode == 'search') {
        req = options.search(_this.query, _this.page);
      }
      req.done(function(data) {
        $.each(data, function() {
          _this.data[this.id] = this;
        });
        _this.render().done(dfr.resolve);
      });

      return dfr.promise();
    };

    this.search = function(q) {
      _this.$element.html('<p class="loading">loading…</p>');

      if (!q) {
        return _this.init();
      }

      var dfr = new $.Deferred();
      _this.data = {};
      _this.page = 1;
      _this.mode = 'search';
      _this.query = q;

      options.search(q, _this.page)
        .done(function(data, meta) {
          _this.total = meta.total;

          $.each(data, function() {
            _this.data[this.id] = this;
          });
          _this.render().done(dfr.resolve);
       });

      return dfr.promise();
    };

    this.insert = function(item) {
      _this.data[item.id] = item;
      _this.total++;
      _this.render();
    };
  };
});