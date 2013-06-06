/**
 * Plugin Name: WindowBox
 * Plugin URI: http://www.makfak.com
 * Description: Sizes and centers an element to the window.
 * Author: Michael Kafka
 * Author URI: http://www.codecanyon.net/user/makfak?ref=makfak | http://www.makfak.com
 * Version: 0.1
 */

;(function ( $, window, document, undefined ) {

    var pluginName = 'windowBox';

    var Plugin = function ( element, options ) {
        this._name = pluginName;
        this.version = '1.1';
        this.el = element;
        this.obj = $(element);
        this._options = options;
        this._id = (Date.parse(new Date()) + Math.round(Math.random() * 10000)).toString();

        this.init();
    };

    Plugin.prototype = {

        _defaults: {
            'ratio' : 5, // larger num = smaller gutter
            // 'ratio' : {
            //     'horizontal' : 8,
            //     'vertical' : 12
            // },
            'height' : 0, // int | str, '50%', '50px' (top_gutter + text_height)
        },

        init: function (options) {
            this.opts = $.extend( {}, this._defaults, this._options );
            this.setDims();
            this.bindEvents();
            this.callback();
        },

        bindEvents: function () {
            var self = this;
            $(window).on('resize', function () {
                self.setDims();
                self.callback();
            });
        },

        callback: function () {
            var self = this;

            this.obj.scaleText({
                'callback' : function (resp) {
                    self.adjustBox(resp);
                }
            });
        },

        createDims: function (num) {
            var window_size = this.getWindowDims();
            var gutter;
            var horizontal;
            var vertical;

            if (!$.isPlainObject(this.opts.ratio)) {
                gutter = Math.floor( Math.min(window_size.w, window_size.h) / this.opts.ratio );
                horizontal = Math.floor(window_size.w - (gutter * 2));
                vertical = Math.floor(window_size.h - (gutter * 2));
            } else {
                gutter = Math.floor( window_size.w / this.opts.ratio.horizontal );
                horizontal = Math.floor(window_size.w - (gutter * 2));

                gutter = Math.floor( window_size.h / this.opts.ratio.vertical );
                vertical = Math.floor(window_size.h - (gutter * 2));
            }

            var num = parseInt(this.opts.height);

            if (num && (num > 0)) {
                if (this.opts.height.match(/[\%]/)) {
                    // num = %
                    vertical = Math.floor( window_size.h - gutter - (window_size.h * ((100 - num) / 100)) );
                } else {
                    // num = px
                    vertical = num;
                }
            }

            return {
                'width' : horizontal,
                'height' : vertical,
                'margin' : gutter
            };
        },

        setDims: function (adjusted) {
            var dims = this.createDims();

            if (adjusted) {
                var window_size = this.getWindowDims();
                var new_dims = $.extend({}, adjusted, {
                    'margin' : (window_size.h - adjusted.height) / 2
                });

                if (new_dims.height >= window_size.h) {
                    new_dims.margin = dims.margin;
                }

                dims = new_dims;
            }

            this.obj.css({
                'width' : dims.width + 'px',
                'height' : dims.height + 'px',
                'margin-top' : dims.margin + 'px',
                'margin-bottom' : (dims.margin - 1) + 'px',
                'margin-left' : 'auto',
                'margin-right' : 'auto'
            });
        },

        getWindowDims: function () {
            return {
                'w' : $(window).width(),
                'h' : $(window).height()
            };
        },

        adjustBox: function (resp) {
            this.setDims(resp);
        },

        refresh: function (options) {
            this._options = options;
            this.opts = $.extend( {}, this._opts, this._options );
            this.setDims();
            this.callback();
        }
    };

    $.fn[pluginName] = function ( options ) {
        options = options || {};
        return this.each(function () {
            if (!$.data(this, pluginName)) {
                $.data(this, pluginName, new Plugin( this, options ));
            } else {
                $.data(this, pluginName).refresh(options);
            }
        });
    };

})( jQuery, window, document );