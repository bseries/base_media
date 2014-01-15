define(['jquery', 'media-explorer-modal', 'domready!'],
function($, MediaExplorerModal) {

  var _this = this;

  var config = {
    endpoints: {
      view: '/files/__ID__'
    }
  };

  var init = function(options) {
    _this.config = $.extend(config, options || {});
  };

  var bindSyncWithId = function() {

  };

  var one = function(element) {
    element = $(element);

    var elements = {
      root: element,
      select: element.find('.select'),
      selected: element.find('.selected'),
      idField: element.find('input[name*=_id]')
    };

    // Sync with id.
    elements.selected.on('DOMSubtreeModified', function() {
      var els = $(this).find('.file');

      if (els.length) {
        els.each(function(index, element) {
          elements.idField.val($(element).data('id'));
        });
      } else {
        // No file element found seems everything is removed.
        elements.idField.val('');
      }
    });

    // Load current item.
    if (elements.idField.val()) {
      buildSelectedItemHtml({
        id: elements.idField.val()
      }).done(function(html) {
        elements.selected.html(html);
      });
    }

    elements.select.on('click', function(ev) {
      ev.preventDefault();
      interactWithMediaExplorer(1);
    });
  };


  // use multi select input element
  var multi = function(element) {
    element = $(element);

    var elements = {
      root: element,
      select: element.find('.select'),
      selected: element.find('.selected')
//      idField: element.find('input[name*=_id]')
    };

    // Sync with id.
    elements.selected.on('DOMSubtreeModified', function() {
      var els = $(this).find('.file');

      if (els.length) {
        els.each(function(index, element) {
          elements.idField.val($(element).data('id'));
        });
      } else {
        // No file element found seems everything is removed.
        elements.idField.val('');
      }
    });

    // Load current item.
    if (elements.idField.val()) {
      buildSelectedItemHtml({
        id: elements.idField.val()
      }).done(function(html) {
        elements.selected.html(html);
      });
    }

    elements.select.on('click', function(ev) {
      ev.preventDefault();
      interactWithMediaExplorer(1);
    });
  };

  var interactWithMediaExplorer = function(selectable) {
    MediaExplorerModal.init($.extend(_this.config, {selectable: selectable}));
    MediaExplorerModal.open();

    $(document).one('media-explorer:selected', function(ev, ids) {
      // Implicitly updates the idField by modifying subtree.

      var dfrs = [];
      elements.selected.html('');

      $.each(ids, function(k, id) {
        var dfr = buildSelectedItemHtml({
          id: id
        }).done(function(html) {
          elements.selected.append(html);
        });
        dfrs.push(dfr);
      });

      $.when.apply($, dfrs).then(MediaExplorerModal.close);
    });
  };

  var buildSelectedItemHtml = function(item) {
    var df = $.Deferred();

    var build = function(item) {
      var wrap = $('<article class="file">');
      wrap.append($('<img>').attr('src', item.versions_fix3_url));

      var button = $('<button class="remove">remove</button>');
      wrap.append(button);

      button.on('click', function(ev) {
        ev.preventDefault();
        $(this).parent().remove();
      });
      wrap.data('id', item.id);
      return wrap;
    };

    // if (!item.versions_fix3_url) {
      // Need more information; partial item given.
      $.getJSON(config.endpoints.view.replace('__ID__', item.id)).done(function(data) {
        df.resolve([build(data.file)]);
      });
    // } else {
    //  df.resolve([build(item)]);
    // }
    return df;
  };

  return {
    init: init,
    one: one
  };
});
