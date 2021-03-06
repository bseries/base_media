/*!
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

define([
  'jquery',
  'translator',
  'mediaExplorerAvailablePane',
  'mediaExplorerTransferPane',
  'handlebars',
  'text!base-media/js/templates/mediaExplorer.hbs',
  'notify'
], function(
  $,
  Translator,
  AvailablePane,
  TransferPane,
  Handlebars,
  template
) {
  'use strict';

  //
  // Main Media Explorer Class, holds avaiable items
  // and transfer panes. Bridges transfer queue and available items.
  //
  return function MediaExplorer(element, options) {
    var _this = this;

    this.element = $(element);

    options = $.extend(true, {
      available: {},
      transfer: {}
    }, options);

    var t = (new Translator({
      "de": {
        "New medium available.": "Neues Medium verfügbar.",
        "Filter (i.e ‘image’, ‘video’, …)": "Filter (z.B. ‘image’, ‘video’, …)",
        "Backgroundcolor": "Hintergrundfarbe",
        "Cancel": "Abbrechen",
        "Confirm": "Bestätigen",
        "Drop files here or <strong>click</string>, to open file browser.": "Dateien hier ablegen oder <strong>Mausklick</strong>, um den Datei–Browser zu öffnen.",
        "Now drop files.": "Dateien jetzt loslassen.",
        "Upload remote media": "Entferntes Medium hochladen",
        "Upload": "Hochladen"
      }
    })).translate;

    // Need to scope helpers per instance.
    var ScopedHandlebars = Handlebars.create();

    ScopedHandlebars.registerHelper('t', function(key) {
      return new ScopedHandlebars.SafeString(t(key));
    });

    // This must come before initializing the panes as they
    // rely on the HTML to be in DOM already.
    _this.element.html(ScopedHandlebars.compile(template)());

    this.availablePane = new AvailablePane(_this.element.find('.available'), options.available);
    this.transferPane = new TransferPane(_this.element.find('.transfer'), options.transfer);

    // Once an item has been transferred within the queue, add
    // it to the list of available items automatically. Item is
    // built from the JSON as returned from transfer endpoint.
    //
    // Bridges transfer queue via transfer pane and available pane.
    _this.element.on('transfer-queue:finished', function(ev, item) {
      _this.availablePane.insert(item);
      $.notify(t('New medium available.'), 'success');
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

    _this.availablePane.element.find('.good-night-switch').on('change', function() {
      _this.element.toggleClass('good-night');
    });
  };
});
