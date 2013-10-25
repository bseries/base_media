define(['jquery', 'media-explorer', 'modal'],
function($, MediaExplorer, Modal) {

  var mediaExplorerConfig = {
      'showCancelSelection': true
  };

  var init = function(options) {
      this.mediaExplorerConfig = $.extend(this.explorerConfig, options || {});
      Modal.init();
  };

  var open = function() {
      Modal.loading();
      Modal.type('media-explorer');

      MediaExplorer.init(Modal.elements.content, this.mediaExplorerConfig);

      Modal.ready();

      $(document).on('media-explorer:cancel', function() {
        Modal.close();
      });
      $(document).on('modal:isClosing', function() {
        MediaExplorer.destroy();
      });
  };

  var close = function() {
    Modal.close();
    MediaExplorer.destroy();
  };

  return {
    init: init,
    open: open,
    close: close
  };
});
