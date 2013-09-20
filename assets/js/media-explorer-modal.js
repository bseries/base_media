define(['jquery', 'media-explorer', 'modal'],
function($, MediaExplorer, Modal) {

  var open = function() {
      Modal.init();

      Modal.loading();
      Modal.type('media-explorer');

      MediaExplorer.init(Modal.elements.content);

      Modal.ready();

      $(document).on('modal:isClosing', function() {
        MediaExplorer.destroy();
      });
  };

  var close = function() {
    Modal.close();
    MediaExplorer.destroy();
  };

  return {
    open: open,
    close: close
  };
});