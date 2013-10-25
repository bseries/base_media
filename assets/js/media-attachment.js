define(['jquery', 'media-explorer-modal', 'domready!'],
function($, MediaExplorerModal) {

  var self = this;

  var config = {
    endpoints: {
      view: '/files/__ID__'
    }
  };

  var init = function(options) {
    config = $.extend(config, options || {});
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

    if (elements.idField.val()) {
      buildSelectedItemHtml({
        id: elements.idField.val()
      }).done(function(html) {
        elements.selected.html(html);
      });
    }

    elements.select.on('click', function(ev) {
      ev.preventDefault();
      MediaExplorerModal.init(this.config);
      MediaExplorerModal.open();

      $(document).one('media-explorer:selected', function(ev, data) {
        // Implicitly updates the idField.

        buildSelectedItemHtml({
          id: data.get('id'),
          versions_fix3_url: data.get('versions_fix3_url')
        }).done(function(html) {
          elements.selected.html(html);
          MediaExplorerModal.close();
        });
      });
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

    if (!item.versions_fix3_url) {
      // Need more information; partial item given.
      $.getJSON(config.endpoints.view.replace('__ID__', item.id)).done(function(data) {
        df.resolve([build(data.file)]);
      });
    } else {
      df.resolve([build(item)]);
    }
    return df;
  };

  return {
    init: init,
    one: one
  };
});
