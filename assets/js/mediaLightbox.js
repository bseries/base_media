define('mediaLightbox',
['jquery', 'modal', 'underscore', 'domready!'],
function($, Modal, _) {

  Modal.init();

  var _attach = function(el) {
    el.addClass('media-lightboxed');

    el.on('click', function(ev) {
      Modal.loading();

      ev.preventDefault();
      var content = el.clone();
      content.removeClass('media-lightboxed');

      Modal.elements.content.one('click', Modal.close);
      Modal.fill(content, 'media-lightbox');

      Modal.ready();
    });
  };

  var _detach = function(el) {
    el.off('click');
    el.removeClass('media-lightboxed');
  };

  var _checkSize = function(el) {
    var natural = el.get(0).naturalWidth * el.get(0).naturalHeight;
    var current = el.width() * el.height();

    return natural > current;
  };

  var make = function(selector) {
    $(selector).each(function(k, el) {
      el = $(el);

      $(window).on('resize', _.debounce(function() {
        if (_checkSize(el)) {
          _attach(el);
        } else {
          _detach(el);
        }
      }, 500));

      if (_checkSize(el)) {
        _attach(el);
      }
    });
  };

  return {
    make: make
  };
});
