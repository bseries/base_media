define(['jquery', 'mediaExplorer', 'modal'],
function($, MediaExplorer, Modal) {

  var mediaExplorerConfig = {
    'available': {
      'selectable': false
    },
    // 'transfer': {}
  };

  var init = function(options) {
    this.mediaExplorerConfig = $.extend(true, this.mediaExplorerConfig, options || {});
    Modal.init();
  };

  var open = function() {
    Modal.loading();
    Modal.type('media-explorer');

    // Render Media Explorer into the content area of the modal window.
    var ME = new MediaExplorer(Modal.elements.content, this.mediaExplorerConfig);

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
