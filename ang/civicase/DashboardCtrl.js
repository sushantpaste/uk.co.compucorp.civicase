(function(angular, $, _) {

  angular.module('civicase').config(function($routeProvider) {
      $routeProvider.when('/case', {
        reloadOnSearch: false,
        controller: 'CivicaseDashboardCtrl',
        templateUrl: '~/civicase/DashboardCtrl.html'
      });
    }
  );

  angular.module('civicase').controller('CivicaseDashboardCtrl', function($scope, crmApi, formatActivity) {
    var ts = $scope.ts = CRM.ts('civicase');
    $scope.caseStatuses = CRM.civicase.caseStatuses;
    $scope.caseTypes = CRM.civicase.caseTypes;
    $scope.caseTypesLength = _.size(CRM.civicase.caseTypes);

    $scope.$bindToRoute({
      param: 'dtab',
      expr: 'activeTab',
      format: 'int',
      default: 0
    });

    $scope.$bindToRoute({
      param: 'dme',
      expr: 'myCasesOnly',
      format: 'bool',
      default: false
    });

    $scope.$bindToRoute({
      param: 'dbd',
      expr: 'showBreakdown',
      format: 'bool',
      default: false
    });

    // We hide the breakdown when there's only one case type
    if ($scope.caseTypesLength < 2) {
      $scope.showBreakdown = false;
    }

    $scope.summaryData = [];

    $scope.dashboardActivities = {
      recentCommunication: [],
      nextMilestones: []
    };

    $scope.showHideBreakdown = function() {
      $scope.showBreakdown = !$scope.showBreakdown;
    };

    $scope.seeAllLink = function(category, statusFilter) {
      var params = {
        dtab: 1,
        dme: $scope.myCasesOnly ? 1 : 0,
        dbd: 0,
        af: JSON.stringify({
          'activity_type_id.grouping': category,
          status_id: CRM.civicase.activityStatusTypes[statusFilter]
        })
      };
      return '#/case?' + $.param(params);
    };

    $scope.refresh = function(apiCalls) {
      apiCalls = apiCalls || [];
      apiCalls.push(['Case', 'getstats', {my_cases: $scope.myCasesOnly}]);
      var params = _.extend({
        sequential: 1,
        is_current_revision: 1,
        is_test: 0,
        options: {limit: 10, sort: 'activity_date_time DESC'},
        return: ['case_id', 'activity_type_id', 'subject', 'activity_date_time', 'status_id', 'target_contact_name', 'assignee_contact_name', 'is_overdue', 'case_id.case_type_id', 'case_id.status_id', 'case_id.contacts']
      }, $scope.activityFilters);
      // recent communication
      apiCalls.push(['Activity', 'get', _.extend({
        "activity_type_id.grouping": {LIKE: "%communication%"},
        'status_id.filter': 1,
        options: {limit: 10, sort: 'activity_date_time DESC'}
      }, params)]);
      // next milestones
      apiCalls.push(['Activity', 'get', _.extend({
        "activity_type_id.grouping": {LIKE: "%milestone%"},
        'status_id.filter': 0,
        options: {limit: 10, sort: 'activity_date_time ASC'}
      }, params)]);
      crmApi(apiCalls).then(function(data) {
        $scope.summaryData = data[apiCalls.length - 3].values;
        $scope.dashboardActivities.recentCommunication = _.each(data[apiCalls.length - 2].values, formatActivity);
        $scope.dashboardActivities.nextMilestones = _.each(data[apiCalls.length - 1].values, formatActivity);
      });
    };

    // Translate between the dashboard's global filter-options and
    // the narrower, per-section filter-options.
    $scope.$watch('myCasesOnly', function (myCasesOnly) {
      $scope.activityFilters = {
        case_filter: {"case_type_id.is_active": 1}
      };
      $scope.recentCaseFilter = {
        'status_id.grouping': 'Opened'
      };
      if (myCasesOnly) {
        $scope.activityFilters.case_filter.case_manager = CRM.config.user_contact_id;
        $scope.recentCaseFilter.case_manager = CRM.config.user_contact_id;
      }
      $scope.recentCaseLink = '#/case/list?sf=modified_date&sd=DESC&cf=' + JSON.stringify($scope.recentCaseFilter);
      $scope.refresh();
    });
  });

})(angular, CRM.$, CRM._);
