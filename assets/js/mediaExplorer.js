/*!
 * Media Explorer
 *
 * Copyright (c) 2013-2014 David Persson - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

define([
  'jquery',
  'mediaExplorerAvailablePane',
  'mediaExplorerTransferPane',
  'handlebars',
  'text!base-media/js/templates/mediaExplorer.hbs',
  'notify'
],
function(
  $,
  AvailablePane,
  TransferPane,
  Handlebars,
  template
) {

  //
  // Main Media Explorer Class, holds avaiable items
  // and transfer panes. Bridges transfer queue and available items.
  //
  return function MediaExplorer(element, options) {
    var _this = this;

    this.element = $(element);

    options = $.extend(true, {
      available: {
        selectable: null,
        selected: [],
      },
      transfer: {
        vimeoUpload: true,
        urlUpload: false
      }
    }, options);

    // This must come before initializing the panes as they
    // rely on the HTML to be in DOM already.
    _this.element.html(Handlebars.compile(template));

    this.availablePane = new AvailablePane(_this.element.find('.available'), options.available);
    this.transferPane = new TransferPane(_this.element.find('.transfer'), options.transfer);

    // Once an item has been transferred within the queue, add
    // it to the list of available items automatically. Item is
    // built from the JSON as returned from transfer endpoint.
    //
    // Bridges transfer queue via transfer pane and available pane.
    _this.element.on('transfer-queue:finished', function(ev, item) {
      _this.availablePane.insert(item);
      $.notify('Neues Medium verfügbar.', 'success');
    });

    // Handle Pane Switching/Tabbing.
    var $search = _this.element.find('.search');

    _this.element.find('.blocktab-h').on('click', function(ev) {
      ev.preventDefault();

     var $tab = $(this);
     var $pane = _this.element.find($tab.attr('href'));

      _this.element.find('.pane.active, .blocktab-h.active').each(function() {
        $(this).removeClass('active');
        $(this).trigger('pane:deactivated');
      });

      $tab.addClass('active');
      $pane.addClass('active');
      $pane.trigger('pane:activated');
    });
    _this.availablePane.element.on('pane:activated', function() {
      $search.show();
    });
    _this.availablePane.element.on('pane:deactivated', function() {
      $search.hide();
    });

    _this.availablePane.element.find('#good-night').on('change', function() {
      _this.element.toggleClass('good-night');
    });
  };
});
