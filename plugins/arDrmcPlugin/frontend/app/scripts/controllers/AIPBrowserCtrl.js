'use strict';

module.exports = function ($scope, $modal, SETTINGS, AIPService) {
  // criteria contain GET params used when calling getAIPs to refresh data
  $scope.criteria = {};
  $scope.criteria.limit = 10;
  $scope.criteria.sort = 'name';
  $scope.page = 1; // Don't delete this, it's an important default for the loop

  // Changes in scope.page updates criteria.skip
  $scope.$watch('page', function (value) {
    $scope.criteria.skip = (value - 1) * $scope.criteria.limit;
  });

  // Watch for criteria changes
  $scope.$watch('criteria', function () {
    $scope.pull();
  }, true); // check properties when watching

  $scope.pull = function () {
    // This one is cached, TODO: find a better place?
    AIPService.getTypes()
      .success(function (data) {
        $scope.classifications = data.terms;
      });
    AIPService.getAIPs($scope.criteria)
      .success(function (data) {
        $scope.data = data;
        $scope.$broadcast('pull.success', data.total);
      });
  };

  // Support overview toggling
  $scope.showOverview = true;
  $scope.toggleOverview = function () {
    $scope.showOverview = !$scope.showOverview;
  };

  // Ng-include logic
  $scope.templates = [
    { name: 'List view', url: SETTINGS.viewsPath + '/partials/aips.list-grid.html' },
    { name: 'Browse view', url: SETTINGS.viewsPath + '/partials/aips.list-stacked.html' }
  ];
  $scope.template = $scope.templates[0];

  $scope.openReclassifyModal = function (aip) {
    // Current AIP selected equals to AIP in the modal
    $scope.aip = aip;
    // It happens that $modal.open returns a promise :)
    var modalInstance = $modal.open({
      templateUrl: SETTINGS.viewsPath + '/modals/reclassify-aips.html',
      backdrop: true,
      controller: 'AIPReclassifyCtrl',
      scope: $scope, // TODO: isolate with .new()?
      resolve: {
        classifications: function () {
          return $scope.classifications;
        }
      }
    });
    // This is going to happen only if the $modal succeeded
    modalInstance.result.then(function (result) {
      aip.class = result;
    });
  };
};