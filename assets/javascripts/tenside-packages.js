/**
 * This file is part of tenside/core.
 *
 * (c) Christian Schiffler <https://github.com/discordier>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    tenside/ui
 * @author     Christian Schiffler <https://github.com/discordier>
 * @copyright  Christian Schiffler <https://github.com/discordier>
 * @link       https://github.com/tenside/ui
 * @license    https://github.com/tenside/ui/blob/master/LICENSE MIT
 * @filesource
 */

(function() {
    var app = angular.module('tenside-packages', ['tenside-api', 'pascalprecht.translate']);

    app.controller('tensidePackagesController',
    ['$scope', '$routeParams', '$tensideApi',
    function ($scope, $routeParams, $tensideApi) {
        $scope.packages = {};
        $scope.showDependencies = false;

        $scope.$watch('showDependencies', function() {
            reload();
        });

        var reload = function() {
            $tensideApi.packages.list($scope.showDependencies).success(function(data) {
                $scope.packages = data;
            });
        };

        // Mapping of version descriptors to css classes.
        $scope.versionToClass = function(version) {
            if (version.indexOf('dev-') > -1) {
                return 'default';
            }
            if (version.indexOf('-alpha') > -1) {
                return 'danger';
            }
            if (version.indexOf('-beta') > -1) {
                return 'warning';
            }
            if (version.indexOf('-RC') > -1) {
                return 'info';
            }

            return 'success';
        };
        // FIXME: make this some library and rip this method from package and search controller
        $scope.typeIcon = function(typeName) {
            switch (typeName) {
                case 'library':
                    return 'fa-puzzle-piece';
                case 'component':
                    return 'fa-cog';
                case 'composer-installer':
                    return 'fa-magic';
                case 'composer-plugin':
                    return 'fa-plug';
                case 'legacy-contao-module':
                    return 'fa-thumbs-down';
                case 'meta-package':
                    return 'fa-cubes';
                case 'metapackage':
                    return 'fa-cubes';
                case 'php':
                    return 'fa-code-o';
                case 'symfony-bundle':
                    return 'fa-archive';
                default:
            }

            return 'fa-question';
        };

        $scope.canUpgrade = function(pack) {
            return !pack.locked && pack.upgrade_version;
        };

        $scope.canDelete = function(pack) {
            return !pack.locked && pack.constraint;
        };

        var updatePackage = function(data) {
            $scope.packages[data.name] = data;
        };

        $scope.lock = function (pack) {
            var newPack = jQuery.extend(true, {}, pack);
            newPack.locked = true;
            $tensideApi.packages.put(newPack).success(updatePackage);
        };

        $scope.unlock = function (pack) {
            var newPack = jQuery.extend(true, {}, pack);
            newPack.locked = false;
            $tensideApi.packages.put(newPack).success(updatePackage);
            console.log(pack, newPack);
        };

        $scope.upgrade = function (pack) {
            // pack is optional.
            console.log(pack);
            $tensideApi.tasks.addUpgrade(pack? [pack.name] : undefined);
        };

        $scope.remove = function (pack) {
            $tensideApi.packages.delete(pack).success(function() {
                reload();
            })
        };

        if ($routeParams.packageVendor) {
            $tensideApi.packages.get($routeParams.packageVendor + '/' + $routeParams.packageName).success(function(data) {
                $scope.package = data;
            });
        }
    }]);

    // Late dependency injection
    TENSIDE.requires.push('tenside-packages');

    TENSIDE.config(['$routeProvider', '$translateProvider', function ($routeProvider, $translateProvider) {
        // route to the packages page
        $routeProvider.when('/packages', {
            templateUrl: 'pages/packages.html',
            controller: 'tensidePackagesController'
        });
        $routeProvider.when('/packages/:packageVendor/:packageName', {
            templateUrl: 'pages/package.html',
            controller: 'tensidePackagesController'
        });
    }]);
})();
