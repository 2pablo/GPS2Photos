/*
MIT License

    Copyright (c) Microsoft Corporation.

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
    SOFTWARE

Created for Geo2 Maps Fullscreen Module based on azure-maps-overview-map.js

    Plugin Name: Geo2 Maps Add-on for NextGEN Gallery
    Plugin URI:  https://wordpress.org/plugins/nextgen-gallery-geo/
    Description: Geo2 Maps Add-on for NextGEN Gallery is a flexible plugin, displaying beautiful maps with your photos by using EXIF data or geocoding.
    Version:     1.1.0
    Author:      Pawel Block
  
    @since       1.1.0 -> 2.1.0 Class FullscreenControlGeo2 added at line:89 and variables geo2FullscreenControl controlGeo2 added to export at line 910. Function _syncMaps amended for smooth Overview Map zooming.
*/

(function (exports, azmaps) {
    'use strict';

    /*! *****************************************************************************
    Copyright (c) Microsoft Corporation. All rights reserved.
    Licensed under the Apache License, Version 2.0 (the "License"); you may not use
    this file except in compliance with the License. You may obtain a copy of the
    License at http://www.apache.org/licenses/LICENSE-2.0

    THIS CODE IS PROVIDED ON AN *AS IS* BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
    KIND, EITHER EXPRESS OR IMPLIED, INCLUDING WITHOUT LIMITATION ANY IMPLIED
    WARRANTIES OR CONDITIONS OF TITLE, FITNESS FOR A PARTICULAR PURPOSE,
    MERCHANTABLITY OR NON-INFRINGEMENT.

    See the Apache Version 2.0 License for specific language governing permissions
    and limitations under the License.
    ***************************************************************************** */

    var __assign = function() {
        __assign = Object.assign || function __assign(t) {
            for (var s, i = 1, n = arguments.length; i < n; i++) {
                s = arguments[i];
                for (var p in s) if (Object.prototype.hasOwnProperty.call(s, p)) t[p] = s[p];
            }
            return t;
        };
        return __assign.apply(this, arguments);
    };

    /**
     * Helper class for merging namespaces.
     */
    var Namespace = /** @class */ (function () {
        function Namespace() {
        }
        Namespace.merge = function (namespace, base) {
            var context = window || global;
            var parts = namespace.split(".");
            for (var _i = 0, parts_1 = parts; _i < parts_1.length; _i++) {
                var part = parts_1[_i];
                if (context[part]) {
                    context = context[part];
                }
                else {
                    return base;
                }
            }
            return __assign(__assign({}, context), base);
        };
        return Namespace;
    }());

    // Added for Geo2 Maps Fullscreen
    var FullscreenControlGeo2 = /** @class */ (function () {
        /****************************
         * Constructor
         ***************************/
        /**
         * A control that control the style of the button.
         * @param options Options for defining how the control is rendered.
         */
        function FullscreenControlGeo2(options) {
            var _this2 = this;
            this._hclStyle = null;
            this._darkColor = '#011c2c';
            this._options = {
                style: 'light',
            };
            /**
             * An event handler for when the map style changes. Used when control style is set to auto.
             */
            this._fullscreenStyleChanged = function () {
                var self = _this2;
                if (self._btnFullscreen && !self._hclStyle) {
                    var c = self._getColorFromMapStyle();
                    self._btnFullscreen.style.backgroundColor = c;
                }
            };
            Object.assign(this._options, options || {});
        }

        /**
         * Get the options for the overview map control.Probably not needed.
         */
        // FullscreenControlGeo2.prototype.getOptions = function () {
        //     return __assign({}, this._options);
        // };
        // /**
        //  * Set the options for the overview map control.
        //  * @param options The options to set.
        //  */
        // FullscreenControlGeo2.prototype.setOptions = function (options) {
        //     var self = this;            
        //     var opt = self._options;
        //     var map = self._parentMap;

        //     if (options.style) {
        //         if (opt.style.indexOf('auto') === 0 && map) {
        //             map.events.remove('styledata', self._fullscreenStyleChanged);
        //         }
        //         opt.style = options.style;
        //         if (self._btnFullscreen) {
        //             var c = self._styleColor();
        //             self._btnFullscreen.style.backgroundColor = c;
        //         }
        //     };
        // };

        FullscreenControlGeo2.prototype.onAdd = function (map, options) {
            var self = this;
            self._parentMap = map;
            var color = self._styleColor();
            var c = document.createElement('div');
            c.classList.add('azure-maps-control-container');
            
            if (map.geo2fullscreenDiv.length) {
                var $btnFullscreen = map.geo2fullscreenDiv.css({ 
                    display: "block",
                    position: "relative",
                    top: "0px",
                    left: "0px",
                    background: "#ffffff",
                    opacity: 1,
                    color: "#83888d" // Azure Maps SVGs fill color: #83888d; 'Gray'
                });
                var btnFullscreen = $btnFullscreen[0];
                btnFullscreen.setAttribute('type', 'button');
                Object.assign(btnFullscreen.style, {
                    backgroundColor: color
                });
                self._btnFullscreen = btnFullscreen;
                c.appendChild(btnFullscreen);
                return c; // Return the container element
            }
        };
        /** Gets the controls background color for the specified style.  */
        FullscreenControlGeo2.prototype._styleColor = function () {
            var self = this;
            var color = 'light';
            if (self._parentMap) {
                var mcl = self._parentMap.getMapContainer().classList;
                if (mcl.contains("high-contrast-dark")) {
                    self._hclStyle = 'dark';
                }
                else if (mcl.contains("high-contrast-light")) {
                    self._hclStyle = 'light';
                }
            }
            if (self._hclStyle) {
                if (self._hclStyle === 'dark') {
                    color = self._darkColor;
                }
            }
            else {
                color = self._options.style;
            }
            if (color === 'light') {
                color = 'white';
            }
            else if (color === 'dark') {
                color = self._darkColor;
            }
            else if (color.indexOf('auto') === 0) {
                if (self._parentMap) {
                    //Color will change between light and dark depending on map style.
                    self._parentMap.events.add('styledata', self._fullscreenStyleChanged);
                    color = self._getColorFromMapStyle();
                }
            }
            return color;
        };
        /**
         * Retrieves the background color for the button based on the map style. This is used when style is set to auto.
         */
        FullscreenControlGeo2.prototype._getColorFromMapStyle = function () {
            var isDark = false;
            //When the style is dark (i.e. satellite, night), show the dark colored theme.
            if (['satellite', 'satellite_road_labels', 'grayscale_dark', 'night'].indexOf(this._parentMap.getStyle().style) > -1) {
                isDark = true;
            }
            if (this._options.style === 'auto-reverse') {
                //Reverse the color.
                return (isDark) ? 'white' : this._darkColor;
            }
            return (isDark) ? this._darkColor : 'white';
        };

        FullscreenControlGeo2.prototype.onRemove = function () {
            // Cleanup code when control is removed
        };

        return FullscreenControlGeo2;
    }());

    // Added for Geo2 Maps Fullscreen
    var geo2FullscreenControl = /*#__PURE__*/Object.freeze({
        __proto__: null,
        FullscreenControlGeo2: FullscreenControlGeo2
    });

    var controlGeo2 = Namespace.merge("atlas.control", geo2FullscreenControl);
    exports.control = controlGeo2;

}(this.atlas = this.atlas || {}, atlas));
