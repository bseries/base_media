define(['jquery', 'media-explorer-modal', 'domready!'],
function($, MediaExplorerModal) {

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
      $(this).find('.file').each(function(index, element) {
          elements.idField.val($(element).data('id'));
      });
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
      MediaExplorerModal.open();
    });
    $(document).on('media-explorer:selected', function(ev, data) {
      // Implicitly updates the idField.

      buildSelectedItemHtml({
        id: data.get('id'),
        url: data.get('url')
      }).done(function(html) {
        elements.selected.html(html);
        MediaExplorerModal.close();
      });
    });
  };

  var buildSelectedItemHtml = function(item) {
    var df = $.Deferred();

    var build = function(item) {
      var wrap = $('<article class="file">');
      wrap.append($('<img>').attr('src', item.url));

      var button = $('<button class="remove">remove</button>');
      wrap.append(button);

      button.on('click', function(ev) {
        ev.preventDefault();
        $(this).parent().remove();
      });
      wrap.data('id', item.id);
      return wrap;
    };

    if (!item.url) {
      // Need more information; partial item given.
      $.getJSON('/files/' + item.id).done(function(data) {
        df.resolve([build(data.file)]);
      });
    } else {
      df.resolve([build(item)]);
    }
    return df;
  };

  return {
    one: one
  };
});
