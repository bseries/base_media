define(['jquery', 'mediaExplorer', 'modal'],
function($, MediaExplorer, Modal) {

  var mediaExplorerConfig = {
      'selectable': false
  };

  var init = function(options) {
    this.mediaExplorerConfig = $.extend(this.mediaExplorerConfig, options || {});
    Modal.init();
  };

  var open = function() {
    Modal.loading();
    Modal.type('media-explorer');

    var ME = new MediaExplorer();
    ME.init(Modal.elements.content, this.mediaExplorerConfig);

    Modal.ready();

    $(document).on('media-explorer:cancel', function() {
      Modal.close();
    });
  };

  var close = function() {
    Modal.close();
  };

  return {
    init: init,
    open: open,
    close: close
  };
});
