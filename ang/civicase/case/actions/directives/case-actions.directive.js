(function (angular, $, _) {
  var module = angular.module('civicase');

  module.directive('civicaseCaseActions', function ($window, dialogService, PrintMergeCaseAction) {
    return {
      restrict: 'A',
      templateUrl: '~/civicase/case/actions/directives/case-actions.directive.html',
      scope: {
        cases: '=civicaseCaseActions',
        refresh: '=refreshCallback',
        popupParams: '='
      },
      link: civicaseCaseActionsLink
    };

    /**
     * Angular JS's link function for civicaseCaseActions Directive
     *
     * @param {Object} $scope
     * @param {Object} element
     * @param {Object} attributes
     */
    function civicaseCaseActionsLink ($scope, element, attributes) {
      var ts = CRM.ts('civicase');
      var isBulkMode = attributes.isBulkMode;

      $scope.hasSubMenu = function (action) {
        return (action.items && action.items.length);
      };

      $scope.isActionEnabled = function (action) {
        return (!action.number || $scope.cases.length === +action.number);
      };

      $scope.isActionAllowed = function (action) {
        var isLockCaseAction = _.startsWith(action.action, 'lockCases');
        var isCaseLockAllowed = CRM.civicase.allowCaseLocks;

        return (isLockCaseAction && isCaseLockAllowed) ||
          (!isLockCaseAction && (!action.number || ((isBulkMode && action.number > 1) || (!isBulkMode && action.number === 1))));
      };

      // Perform bulk actions
      $scope.doAction = function (action) {
        if (!$scope.isActionEnabled(action)) {
          return;
        }

        var result = $scope.$eval(action.action, {
          editTags: function (item) {
            var keys = ['tags'];
            var model = {
              tags: []
            };

            _.each(CRM.civicase.tagsets, function (tagset) {
              model[tagset.id] = [];
              keys.push(tagset.id);
            });

            // Sort case tags into sets
            _.each(item.tag_id, function (tag, id) {
              if (!tag['tag_id.parent_id'] || !model[tag['tag_id.parent_id']]) {
                model.tags.push(id);
              } else {
                model[tag['tag_id.parent_id']].push(id);
              }
            });

            model.tagsets = CRM.civicase.tagsets;
            model.colorTags = CRM.civicase.tags;
            model.ts = ts;

            dialogService.open('EditTags', '~/civicase/case/actions/directives/edit-tags.html', model, {
              autoOpen: false,
              height: 'auto',
              width: '40%',
              title: action.title,
              buttons: [{
                text: ts('Save'),
                icons: { primary: 'fa-check' },
                click: editTagModalClickEvent
              }]
            });

            /**
             * Handles the click event for the Edit Tag Modal's Click Event
             */
            function editTagModalClickEvent () {
              var calls = [];
              var values = [];

              function tagParams (tagIds) {
                var params = { entity_id: item.id, entity_table: 'civicrm_case' };

                _.each(tagIds, function (id, i) {
                  params['tag_id_' + i] = id;
                });

                return params;
              }

              _.each(keys, function (key) {
                _.each(model[key], function (id) {
                  values.push(id);
                });
              });

              var toRemove = _.difference(_.keys(item.tag_id), values);
              var toAdd = _.difference(values, _.keys(item.tag_id));

              if (toRemove.length) {
                calls.push(['EntityTag', 'delete', tagParams(toRemove)]);
              }

              if (toAdd.length) {
                calls.push(['EntityTag', 'create', tagParams(toAdd)]);
              }

              if (calls.length) {
                calls.push(['Activity', 'create', {
                  case_id: item.id,
                  status_id: 'Completed',
                  activity_type_id: 'Change Case Tags'
                }]);
                $scope.refresh(calls);
              }

              $(this).dialog('close');
            }
          },

          deleteCases: function (cases, mode) {
            var msg;
            var trash = 1;

            switch (mode) {
              case 'delete':
                trash = 0;
                msg = cases.length === 1 ? ts('Permanently delete selected case? This cannot be undone.') : ts('Permanently delete %1 cases? This cannot be undone.', { '1': cases.length });
                break;

              case 'restore':
                msg = cases.length === 1 ? ts('Undelete selected case?') : ts('Undelete %1 cases?', { '1': cases.length });
                break;

              default:
                msg = cases.length === 1 ? ts('This case and all associated activities will be moved to the trash.') : ts('%1 cases and all associated activities will be moved to the trash.', { '1': cases.length });
                mode = 'delete';
            }

            CRM.confirm({ title: action.title, message: msg })
              .on('crmConfirm:yes', function () {
                var calls = [];

                _.each(cases, function (item) {
                  calls.push(['Case', mode, { id: item.id, move_to_trash: trash }]);
                });

                $scope.refresh(calls);
              });
          },

          mergeCases: function (cases) {
            var msg = ts('Merge all activitiy records into a single case?');

            if (cases[0].case_type_id !== cases[1].case_type_id) {
              msg += '<br />' + ts('Warning: selected cases are of different types.');
            }

            if (!angular.equals(cases[0].client, cases[1].client)) {
              msg += '<br />' + ts('Warning: selected cases belong to different clients.');
            }

            CRM.confirm({ title: action.title, message: msg })
              .on('crmConfirm:yes', function () {
                $scope.refresh([['Case', 'merge', { case_id_1: cases[0].id, case_id_2: cases[1].id }]]);
              });
          },

          changeStatus: function (cases) {
            var types = _.uniq(_.map(cases, 'case_type_id'));
            var currentStatuses = _.uniq(_.collect(cases, 'status_id'));
            var currentStatus = currentStatuses.length === 1 ? currentStatuses[0] : null;
            var msg = '<form>' +
                '<div><input name="change_case_status" placeholder="' +
                ts('Select New Status') + '" /></div>' +
                '<label for="change_case_status_details">' + ts('Notes') + '</label>' +
                '<textarea id="change_case_status_details"></textarea>' +
                '</form>';
            var statuses = _.map(CRM.civicase.caseStatuses, function (item, statusId) {
              return {
                id: item.name,
                text: item.label,
                disabled: statusId === currentStatus
              };
            });

            _.each(types, function (caseTypeId) {
              var allowedStatuses = CRM.civicase.caseTypes[caseTypeId].definition.statuses || [];

              if (allowedStatuses.length) {
                _.remove(statuses, function (status) {
                  return allowedStatuses.indexOf(status.id) < 0;
                });
              }
            });

            var dialog = CRM.confirm({
              title: action.title,
              message: msg,
              open: function () {
                $('input[name=change_case_status]', this).crmSelect2({ data: statuses });
                CRM.wysiwyg.create('#change_case_status_details').then(function () {
                  alignDialogBoxCenter(dialog);
                });
              }
            })
              .on('crmConfirm:yes', function () {
                var status = $('input[name=change_case_status]', this).val();
                var details = $('#change_case_status_details').val();
                var calls = [];

                if (status) {
                  _.each(cases, function (item) {
                    var subject = ts('Case status changed from %1 to %2', {
                      1: item.status,
                      2: _.result(_.find(statuses, { id: status }), 'text')
                    });

                    calls.push(['Case', 'create', { id: item.id, status_id: status }]);
                    calls.push(['Activity', 'create', { case_id: item.id, status_id: 'Completed', activity_type_id: 'Change Case Status', subject: subject, details: details }]);
                  });

                  $scope.refresh(calls);
                }
              });
          },

          emailManagers: function (cases) {
            var managers = [];

            _.each(cases, function (item) {
              if (item.manager) {
                managers.push(item.manager.contact_id);
              }
            });

            var popupPath = {
              path: 'civicrm/activity/email/add',
              query: {
                action: 'add',
                reset: 1,
                cid: _.uniq(managers).join(',')
              }
            };

            if (cases.length === 1) {
              popupPath.query.caseid = cases[0].id;
            }

            return popupPath;
          },
          printMerge: PrintMergeCaseAction.getPath,
          exportCases: function (cases) {
            var caseIds = _.collect(cases, 'id');
            var popupPath = {
              path: 'civicrm/export/standalone',
              query: {
                reset: 1,
                entity: 'Case',
                id: caseIds.join()
              }
            };

            return popupPath;
          },

          linkCases: function (case1, case2) {
            var activityTypes = CRM.civicase.activityTypes;
            var link = {
              path: 'civicrm/case/activity',
              query: {
                action: 'add',
                reset: 1,
                cid: case1.client[0].contact_id,
                atype: _.findKey(activityTypes, { name: 'Link Cases' }),
                caseid: case1.id
              }
            };

            if (case2) {
              link.query.link_to_case_id = case2.id;
            }

            return link;
          },

          print: function (selectedCase) {
            var url = CRM.url('civicrm/case/report/print', {
              all: 1,
              redact: 0,
              cid: selectedCase.client[0].contact_id,
              asn: 'standard_timeline',
              caseID: selectedCase.id
            });
            var win = window.open(url, '_blank');

            win.focus();
          },

          /**
           * Opens the Webform in a new tab
           *
           * @param {Object} selectedCase
           * @param {String} path
           * @param {Int/String} clientId
           */
          gotoWebform: function (selectedCase, path, clientId) {
            var window;
            var urlObject = { case1: selectedCase.id };

            if (clientId) {
              urlObject['cid' + clientId] = selectedCase.client[0].contact_id;
            }

            window = $window.open(CRM.url(path, urlObject), '_blank');
            window.focus();
          },

          lockCases: function (currentCase) {
            return {
              path: 'civicrm/case/locked-contacts',
              query: {
                reset: 1,
                case_id: currentCase.id
              }
            };
          }

        });

        // Open popup if callback returns a path & query
        if (result) {
          // Add refresh data
          if ($scope.popupParams) {
            result.query.civicase_reload = $scope.popupParams();
          }

          // Mimic the behavior of CRM.popup()
          var formData = false;
          var dialog = CRM.loadForm(CRM.url(result.path, result.query))
            // Listen for success events and buffer them so we only trigger once
            .on('crmFormSuccess crmPopupFormSuccess', function (e, data) {
              formData = data;
            })
            .on('dialogclose.crmPopup', function (e, data) {
              if (formData) {
                element.trigger('crmPopupFormSuccess', [dialog, formData]);
              }

              element.trigger('crmPopupClose', [dialog, data]);
            });
        }
      };

      $scope.$watchCollection('cases', function (cases) {
        // Special actions when viewing deleted cases
        if (cases.length && cases[0].is_deleted) {
          $scope.caseActions = [
            { action: 'deleteCases(cases, "delete")', title: ts('Delete Permanently') },
            { action: 'deleteCases(cases, "restore")', title: ts('Restore from Trash') }
          ];
        } else {
          $scope.caseActions = _.cloneDeep(CRM.civicase.caseActions);

          if (!isBulkMode) {
            _.remove($scope.caseActions, { action: 'changeStatus(cases)' });
          }
        }
      });

      /**
       * Aligns the dialog box center to the screen
       *
       * @param {jQuery} dialog box to be aligned center
       */
      function alignDialogBoxCenter (dialog) {
        if (dialog && dialog.data('uiDialog')) {
          dialog.parent().position({ 'my': 'center', 'at': 'center', 'of': window });
        }
      }
    }
  });
})(angular, CRM.$, CRM._);
