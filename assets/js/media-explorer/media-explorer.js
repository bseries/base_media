/*!
 * Bureau Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

define([
  'jquery', 'modal',
  'ember',
  'ember-data',
  'text!media/js/media-explorer/templates/file.hbs',
  'text!media/js/media-explorer/templates/files.hbs',
  'domready!'
],
function($, Modal, Em, DS, fileTemplate, filesTemplate) {
  var ME;

  Modal.init();

  var make = function(element) {
    $(element).find('.select').on('click', function(ev) {
        ev.preventDefault();

        Modal.loading();
        boot(Model.element);
        Modal.ready();
    });
  };

  var boot = function(element) {
    // application
    ME = Em.Application.create({
      rootElement: $('#media-explorer')
    });

  };

  // templates
  Em.TEMPLATES.file = Em.Handlebars.compile(fileTemplate);
  Em.TEMPLATES.files = Em.Handlebars.compile(filesTemplate);

  // routes
  ME.Router.map(function () {
    this.resource('files', {path: '/media-explorer/files'});
  });
  ME.FilesRoute = Em.Route.extend({
    model: function() {
      return this.store.find('file');
    }
  });

  // models
  ME.File = DS.Model.extend({
    title: DS.attr('string'),
    url: DS.attr('string'),
    isSelected: DS.attr('boolean', {defaultValue: false})
  });

  // controllers
  // ME.FileController = Em.ObjectController.extend({
  // });

  ME.FilesController = Ember.ArrayController.extend({
    selected: null,
    actions: {
      confirmSelection: function() {
        $(document).trigger('media-explorer:selected', this.selected);
      }
    }
  });

  // views
  ME.FileView = Em.View.extend({
    templateName: 'file',
    classNameBindings: ['isSelected:selected'],

    isSelected: function() {
      return this.get('content') === this.get('controller.selected');
    }.property('file', 'controller.selected'),

    click: function() {
      this.get('controller').set('selected', this.get('content'));
    }
  });

  ME.FilesView = Em.View.extend({
    templateName: 'files'
  });

/*
  return {
    make: make
  };
  */
});
