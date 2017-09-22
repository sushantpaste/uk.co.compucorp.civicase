(function(angular, $, _) {
  // "civicaseChartOpen" is a basic skeletal directive.
  // Example usage: <div civicase-chart-open="{foo: 1, bar: 2}"></div>
  angular.module('civicase').directive('civicaseChartOpen', function(doNutty) {
    return {
      restrict: 'AE',
      template: '<div></div>',
      scope: {
        civicaseChartOpen: '='
      },
      link: function($scope, $el, $attr) {
        var dc = CRM.visual.dc, d3 = CRM.visual.d3, crossfilter = CRM.visual.crossfilter;
        var ts = $scope.ts = CRM.ts('civicase');
        // $scope.$watch('civicaseChartOpen', function(newValue){
        //   $scope.myOptions = newValue;
        // });

        var csvData ="Type,Count\nHousing Support,64000\nAdult Day Care,3721\nAdult Day Care,7\nSpeaking Engagement,1522";
        var experiments = d3.csv.parse(csvData);
        experiments.forEach(function(x) {
          x.Count = +x.Count;
        });

        var ndx = crossfilter(experiments);
        var caseTypeDimension = ndx.dimension(function(d) { return d.Type; });
        var caseTypeCountGroup = caseTypeDimension.group().reduceSum(function(d){return d.Count;});
        var caseTypeSum = ndx.groupAll().reduceSum(function(d){return d.Count;});

        var chart = dc.pieChart($el[0]);
        chart
            .slicesCap(5)
            .dimension(caseTypeDimension)
            .group(caseTypeCountGroup)
            .label(function(d) {return "";});

        var myFormat = d3.format(".3s");
        doNutty(chart, 250, "total", function(){
          return myFormat(caseTypeSum.value());
        });

        chart.render();

      }
    };
  });
})(angular, CRM.$, CRM._);
