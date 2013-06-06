/**
 * Plugin Name: ScaleText
 * Plugin URI: http://www.makfak.com
 * Description: Sizes a body of text to fill/fit it's parent.
 * Author: Michael Kafka
 * Author URI: http://www.codecanyon.net/user/makfak?ref=makfak | http://www.makfak.com
 * Version: 0.1
 */


;(function ( $, window, document, undefined ) {

    var pluginName = 'scaleText';

    var Plugin = function ( element, options ) {
        this._name = pluginName;
        this.version = '1.0';
        this.el = element;
        this.obj = $(element);
        this._options = options;
        this._id = (Date.parse(new Date()) + Math.round(Math.random() * 10000)).toString();
        this._templateClass = 'jq-scaletext';

        this.init();
    };

    Plugin.prototype = {

        _defaults: {
            'width' : 0,
            'height' : 0,
            'min' : 12,
            'max' : 150,
            'line_height' : '1.5em',
            'fill_width' : true,
            'callback' : function () {}
        },

        init: function (options) {
            var self = this;

            this.opts = $.extend( {}, this._defaults, this._options );
            this._html = this.obj.html();
            this.text = this.obj.text();

        this._template = '<span class="' + this._templateClass + '" \
                        style="display:inline-block; width:auto; height:auto; font-size:10px; line-height:' + this.opts.line_height + ';" \
                    ></span>';

            this.sizeText();

            $(window).bind('resize.scaleText', function() {
                self.sizeText();
            });
        },

        prepNode: function () {
            // the text needs to be wrapped with an inline element
            this.obj.wrapInner(this._template);
            this.ruler = this.obj.find('.' + this._templateClass);

            this.obj.css({
                'width' : this.target_width,
                'height' : this.target_height,
                'font-size' : this.font_size
            });
        },

        cleanup: function () {
            this.obj.css({
                'font-size' : this.font_size,
                'line-height' : this.opts.line_height
            });

            this.obj.html(this._html);

            this.opts.callback({
                // 'width' : this.text_width,
                // 'height' : this.text_height,
                'font-size' : this.font_size
            });
        },

        sizeText: function () {
            // allows both dimensions to be set explicitly or dynamically
            this.target_width = (this.opts.width) ? this.opts.width : this.obj.width();
            this.target_height = (this.opts.height) ? this.opts.height : this.obj.height();
            this.font_size = Math.max(parseInt(this.obj.css('font-size')), this.opts.min);

            if (!this.target_width && !this.target_height) {
                return;
            }

            this.prepNode();

            this.text_width = this.ruler.width();
            this.text_height = this.ruler.height();

            // increase the font-size until the height or width overflows
            // the extra logic allows scaling to a single vector
            // the 150 upper-bound seems reasonable for a font-size in px
            var lower = this.opts.min;
            var upper = this.opts.max;
            var middle = 0;

            while((upper - lower) > 1) {
                middle = Math.floor((upper - lower) / 2) + lower;
                this.ruler.css('font-size', middle + 'px');
                this.text_width = this.ruler.width();
                this.text_height = this.ruler.height();

                if (
                    ((this.target_width && this.target_height) && ((this.text_width > this.target_width) || (this.text_height > this.target_height)))
                        ||
                    ((this.target_width && !this.target_height) && (this.text_width > this.target_width))
                        ||
                    ((this.target_height && !this.target_width) && (this.text_height > this.target_height))
                ) {
                    upper = middle;
                } else {
                    lower = middle
                }
            }
            this.font_size = lower;
            this.ruler.css('font-size', this.font_size + 'px');
            this.text_width = this.ruler.width();
            this.text_height = this.ruler.height();

            this.cleanup();
        }
    };

    $.fn[pluginName] = function ( options ) {
        options = options || {};
        return this.each(function () {
            if (!$.data(this, pluginName)) {
                $.data(this, pluginName, new Plugin( this, options ));
            } else {
                $.data(this, pluginName).sizeText();
            }
        });
    };

})( jQuery, window, document );