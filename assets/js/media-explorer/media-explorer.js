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
  'jquery',
  'ember', 'ember-data',
  'text!media/js/media-explorer/templates/index.hbs',
  'text!media/js/media-explorer/templates/available_file.hbs'
],
function(
  $,
  Em, DS,
  indexTemplate,
  availableFileTemplate
) {
  var self = this;

  var config = {
      'showCancelSelection': false,
      endpoints: {
        namespace: ''
      }
  };

  var init = function(element, options) {
    self.config= $.extend(self.config, options || {});

    // application
    window.ME = Em.Application.create({
      rootElement: $(element)
    });

    ME.ApplicationAdapter = DS.RESTAdapter.extend({});
    ME.ApplicationAdapter.reopen({
      namespace: self.config.endpoints.namespace,
      createRecord: function() {
        return new Em.RSVP.Promise(function(resolve, reject) {
            resolve();
        });
      }
    });

    // templates
    Em.TEMPLATES.index = Em.Handlebars.compile(indexTemplate);
    Em.TEMPLATES.availableFile = Em.Handlebars.compile(availableFileTemplate);

    // routes
    ME.Router.map(function () {
      this.resource('index', {path: '/'});
    });
    ME.IndexRoute = Em.Route.extend({
      renderTemplate: function() {
        this.render('index');
      },
      model: function() {
        return this.store.find('file');
      }
    });

    ME.File = DS.Model.extend({
      title: DS.attr('string'),
      versions_fix0_url: DS.attr('string'),
      versions_fix1_url: DS.attr('string'),
      versions_fix2_url: DS.attr('string'),
      versions_fix3_url: DS.attr('string'),
      created: DS.attr('date')
    });

    // controllers
    ME.IndexController = Ember.ArrayController.extend({
      sortProperties: ['created'],
      sortAscending: false,
      showCancelSelection: self.config.showCancelSelection,

      selected: null,
      newFileTitle: null,

      // newFile: null,
      actions: {
        cancelSelection: function() {
            $(document).trigger('media-explorer:cancel');
        },
        confirmSelection: function() {
          var result = this.store.find('file', this.selected.id);
          result.then(function(item) {
            $(document).trigger('media-explorer:selected', item);
          });
        },
        selectNewFile: function() {
          var action = this;

          $('#new-file').click();
          $('#new-file').on('change', function(ev) {
              action.set('newFileTitle', this.files[0].name);
          });
        },
        createFile: function() {
          $('.media-explorer .transfer-start').attr('disabled', 'disabled');

          // Hack: we cannot set controller's newFile
          // property from view on change.
          var file = $('#new-file').get(0).files[0];

          var reader = new FileReader();
          var xhr = new XMLHttpRequest();

          xhr.open('POST', '/' + self.config.endpoints.namespace + '/files/transfer?title=' + file.name);
          xhr.overrideMimeType('text/plain; charset=x-user-defined-binary');
          $(document).trigger('transfer:start');

          var action = this;
          xhr.upload.addEventListener('progress', function(ev) {
            if (ev.lengthComputable) {
              $(document).trigger('transfer:progress', (ev.loaded * 100) / ev.total);
            }
          }, false);

          xhr.onload = function(done) {
            $(document).trigger('transfer:done');
            $('.media-explorer .transfer-start').removeAttr('disabled');

            var response = $.parseJSON(this.responseText);

            var record = action.store.createRecord('file', {
              id: response.file.id,
              title: response.file.title,
              versions_fix0_url: response.file.versions_fix0_url,
              versions_fix1_url: response.file.versions_fix1_url,
              versions_fix2_url: response.file.versions_fix2_url,
              versions_fix3_url: response.file.versions_fix3_url,
              create: response.file.created
            });
            record.save();
          };

          reader.onload = function(ev) {
            xhr.sendAsBinary(ev.target.result);
          };
          reader.readAsBinaryString(file);
        }
      }
    });

    ME.CreateFileView = Em.View.extend({
      templateName: 'createFile'
    });

    // views
    ME.CreateFileFileField = Em.TextField.extend(Em.ViewTargetActionSupport, {
      action: 'createFile',
      type: 'file',
      elementId: 'new-file'
      // change: function(ev) {
      //   this.get('controller').set('newFile', 'XX');
      // }
    });

    ME.AvailableFileView = Em.View.extend({
      templateName: 'availableFile',
      classNameBindings: ['isSelected:selected'],

      isSelected: function() {
        return this.get('content') === this.get('controller.selected');
      }.property('availableFile', 'controller.selected'),

      click: function() {
        this.get('controller').set('selected', this.get('content'));
      }
    });

    ME.AvailableFilesView = Em.View.extend({
      templateName: 'availableFiles'
    });
  };

  var destroy = function() {
    if (window.ME !== undefined) { // make idempotent
      window.ME.destroy();
    }
  };

  return {
    init: init,
    destroy: destroy
  };
});
