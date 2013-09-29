define('media-lightbox',
['jquery', 'modal', 'domready!'],
function($, Modal) {

  Modal.init();

  var make = function(selector) {
      $(selector).each(function(k, el) {
        el = $(el);

        var natural = el.get(0).naturalWidth * el.get(0).naturalHeight;
        var current = el.width() * el.height();

        if (natural <= current) {
          return;
        }
        el.addClass('media-lightboxed');

        el.on('click', function(ev) {
        Modal.loading();
          ev.preventDefault();
          var content = el.clone();
          content.removeClass('media-lightboxed');

          Modal.fill(content, 'media-lightbox');
          Modal.ready();
        });
      });
  };

  return {
    make: make
  };
});
