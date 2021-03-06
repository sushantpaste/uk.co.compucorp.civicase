(function (angular, $, _) {
  var module = angular.module('civicase');

  module.service('PrintMergeCaseAction', PrintMergeCaseAction);

  function PrintMergeCaseAction () {
    /**
     * Get path object to print/merge
     *
     * @param {Array} cases
     * @return {Object} popup path object
     */
    this.getPath = function (cases) {
      var contactIds = [];
      var caseIds = [];

      _.each(cases, function (item) {
        caseIds.push(item.id);
        contactIds.push(item.client[0].contact_id);
      });

      return {
        path: 'civicrm/activity/pdf/add',
        query: {
          action: 'add',
          reset: 1,
          context: 'standalone',
          cid: contactIds.join(),
          caseid: caseIds.join()
        }
      };
    };
  }
})(angular, CRM.$, CRM._);
