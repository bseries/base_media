requirejs.config({
  paths: {
    'media-explorer-modal': 'media/js/media-explorer-modal',
    'media-attachment': 'media/js/media-attachment',
    'media-explorer': 'media/js/media-explorer/media-explorer',
    'media-lightbox': 'media/js/media-lightbox'
  },
  shim: {}
});

require(['compat'], function(Compat) {
  Compat.run([
    'sendAsBinary'
  ]);
});