;(function ( $, window, document, undefined ) {

    var History = window.History;

    var pluginName = 'GoodCopGreatCop';

    var Plugin = function ( element, options ) {
        this._name = pluginName;
        this.el = element;
        this.obj = $(element);
        this._options = options;
        this._id = (Date.parse(new Date()) + Math.round(Math.random() * 10000)).toString();

        this.init();
    };

    Plugin.prototype = {

        _defaults: {
            loading_selector : '.page-spinner',
            fit_text : 'header h1',
            footer : 'footer',
            about : 'footer .text',
            about_text : 'footer .text p',
            footer_orientation : 'horizontal',
            title_base : '{ Good Cop Great Cop }'
        },

        social_template :   '<span class="social"> \
                                <a href="https://www.facebook.com/GoodCopGreatCop" target="_blank" class="facebook" title="Facebook"> \
                                    <i class="icon-facebook"></i> \
                                </a> \
                                <a href="https://twitter.com/GoodCopGreatCop" target="_blank" class="twitter" title="Twitter"> \
                                    <i class="icon-twitter"></i> \
                                </a> \
                                <a href="http://eepurl.com/jRidP" target="_blank" class="subscribe" title="Subscribe"> \
                                    <i class="icon-heart"></i> \
                                </a> \
                                <a href="http://gdata.youtube.com/feeds/base/users/goodcopgreatcop/uploads?v=2&amp;orderby=updated&amp;alt=rss" target="_blank" class="rss" title="RSS"> \
                                    <i class="icon-rss"></i> \
                                </a> \
                                <a href="mailto:goodcopgreatcop@gmail.com" target="_blank" class="contact" title="Contact Us"> \
                                    <i class="icon-envelope-alt"></i> \
                                </a> \
                            <span>',

        video_container_id : 'yt_player',

        init: function (options) {
            var self = this;
            var data;

            this.opts = $.extend( {}, this._defaults, this._options );
            this._options = $.extend(true, {}, this.opts);

            this._fitText();
            this._togglePageSpinner();

            this.obj.find('.item').addClass('loading');

            // the illusion of a front-end ajax call
            setTimeout(function () {
                self.render();
            }, 650);
        },

        render : function (bindEvents) {
            var self = this;
            var view;
            var lastItemIndex;

            this._togglePageSpinner();
            $('body').addClass('rendered');

            this._updateDeviceClass();
            this._bindEvents();
            this._setupFooter();

            /*
                LazyLoad does a shit job of managing the way different browsers fire 'load'
                events on cached images.  ImagesLoaded does this wonderfully.  So wrap the
                LazyLoad 'load' callback in an ImagesLoaded deferred/promise in a 
                hacky-over-coded attempt to make shit pretty.
            */
            this.obj.find('img').lazyload({
                data_attribute : 'src',
                threshold : 100,
                load : function (remaining, settings) {
                    // $(this).parents('.item').removeClass('loading');
                    $(this).imagesLoaded({
                        progress : function (isBroken, $images, $proper, $broken) {
                            $($proper[$proper.length - 1]).parents('.item').removeClass('loading');
                        }
                    });
                }
            });
        },

        _bindEvents : function () {
            var self = this;

            Shadowbox.init({
                skipSetup: true
            }, function () {
                // wait a tick for Shadowbox's markup to be ready
                setTimeout(function () {
                    History.Adapter.trigger(window, 'statechange');
                }, 100);
            });

            this.obj.delegate('.video a', 'click', function(e) {
                self._videoClick(e)
            });

            $(window).bind('resize.gcgc', function(e) {
                self._updateDeviceClass(e);
                self._setupFooter();
                self._fitText();
            });

            History.Adapter.bind(window,'statechange',function() {
                var State = History.getState();
                self._matchHistoryState( State );
                // History.log(State.data, State.title, State.url);
            });

            // LazyLoad doesn't notice if the page auto-scrolls because of
            // a refresh. This grabs it's attention.
            setTimeout(function () {
                $(window).trigger('resize');
            }, 0);
        },

        _videoClick : function (e) {
            var $node = (e.target.tagName.toLowerCase() === 'a') ? $(e.target) : $(e.target).parents('a');

            e.preventDefault();
            e.stopPropagation();

            // the page isn't ready yet
            if ($node.parents('.item').is('.loading')) {
                return false;
            }

            this._pushToHistory($node.attr('href'));
        },

        showEmbed : function (id) {
            var $node = $('#' + id).find('a');

            if (this._isDesktop() || this._isTablet()) {
                this._embedDesktop($node);
            } else {
                this._embedPhone($node);
            }

            return false;
        },

        _embedPhone : function ($node) {
            // we have to do stupid stuff because YT doesn't bubble
            // 'fullscreenchange' to the parent window
            var existing = $('#' + this.video_container_id);

            if (existing.length > 0) {
                existing.remove();
            }

            // begin in earnest
            var self = this;
            var target = $('<div id="' + this.video_container_id + '"></div>')[0];
            var player;

            $node.parents('.video').append(target);
            
            setTimeout(function() {
                player = new YT.Player(self.video_container_id, {
                    videoId : $node.parents('.item').prop('id'),
                    width : '100%',
                    height : '100%',
                    playerVars : {
                        enablejsapi : 0, // disable JS api
                        iv_load_policy : 3, // don't show annotations
                        showinfo : 0 // don't show the toolbar at the top of the video
                    }
                });
            }, 0);
        },

        _embedDesktop : function ($node) {
            var ratio = {
                w : 480,
                h : 270
            };
            var win = {
                w : $(window).width(),
                h : $(window).height()
            };
            var video = {
                w : 0,
                h : 0
            };
            var self = this;

            $node = ($node.is('a')) ? $node : $node.parents('a');

            var url = $node.attr('data-url');

            if ( (win.w / win.h) > (ratio.w / ratio.h) ) {
                video.w = Math.floor((win.h * ratio.w) / ratio.h);
                video.h = win.h;
            } else {
                video.w = win.w;
                video.h = Math.floor((win.w * ratio.h) / ratio.w);
            }

            Shadowbox.open({
                content : url,
                player : 'iframe',
                width : video.w,
                height : video.h,
                options : {
                    onClose : function() {
                        self._pushToHistory('/');
                    }
                }
            });
        },

        _parseURLForHistory : function (url) {
            return url.match(/\/video\/([a-zA-Z0-9_-]{11})\/([a-zA-Z0-9_-]+)/);
        },

        _pushToHistory : function (url) {
            // History.replaceState( data, title, url );
            if (!url || url === '/') {
                History.replaceState({}, this.opts.title_base, History.getRootUrl());
                return false;
            }

            var match = this._parseURLForHistory(url);
            var state = History.getState();
            var new_url = match[0];
            var new_page_title = this.opts.title_base + ' - ' + match[2];
            
            if (state.data.id === match[1]) {
                return false;
            }

            History.replaceState({
                    id : match[1],
                    slug : match[2]
                },
                new_page_title,
                new_url
            );

            if (window._gaq) {
                window._gaq.push(['_trackPageview'], window.location.pathname + new_url);
            }
        },

        _matchHistoryState : function (state) {
            if (state.data.id) {
                this.showEmbed(state.data.id);
                return false;
            }

            if (!state.data.id && Shadowbox.isOpen()) {
                Shadowbox.close();
                return false;
            }

            var matches = this._parseURLForHistory(state.url);

            if ( matches && matches[1] ) {
                this.showEmbed(matches[1]);
            }

            return false;
        },

        _isPhone : function () {
            return (
                Modernizr.mq('only screen and (max-width: 480px)') ||
                Modernizr.mq('only screen and (min-width: 481px) and (max-width: 767px)')
            );
        },

        _isTablet : function () {
            return (
                Modernizr.mq('only screen and (min-width: 768px) and (max-width: 1024px)')
            );
        },

        _isDesktop : function () {
            return (
                Modernizr.mq('only screen and (min-width: 1025px) and (max-width: 1348px)') ||
                Modernizr.mq('only screen and (min-width: 1349px)')
            );
        },

        _updateDeviceClass: function (e) {
            var $body = $('body');
            this.device_class = this.device_class || '';

            $body.removeClass(this.device_class);

            if (this._isPhone()) {
                this.device_class = 'device-phone';
            } else if (this._isTablet()) {
                this.device_class = 'device-tablet';
            } else if (this._isDesktop()) {
                this.device_class = 'device-desktop';
            }

            $body.addClass(this.device_class);
        },

        _updateBodyClass : function (option, prefix, type) {
            var current = this.opts[option];
            type = type || current;

            $('body').removeClass(prefix + current).addClass(prefix + type);

            this.opts[option] = type;
        },

        _togglePageSpinner : function () {
            var $loading = $(this.opts.loading_selector);

            if (this.isLoading) {
                $loading.hide().empty();
                this.isLoading = false;
            } else {
                $loading.show().spin({
                    'length' : 0,
                    'lines' : 12,
                    'radius' : 10,
                    'width' : 3,
                    'speed' : 2.2
                });
                this.isLoading = true;
            }
        },

        _fitText : function (selector, adjust) {
            selector = selector || this.opts.fit_text;
            adjust = (adjust) ? adjust : (this._isPhone()) ? 1 : 0.9;
            $(selector).fitText(adjust);
        },

        _setupFooter : function () {
            var $window = $(window);
            var window_height = $window.height();
            var window_width = $window.width();
            var $footer = $(this.opts.footer);
            var $img = $footer.find('img');
            var left;
            var height;
            var self = this;

            if (this._isPhone() && window.screen && (window.orientation !== undefined)) {
                if (window.orientation > 0 || window.orientation < 0) {
                    // landscape
                    window_height = window.screen.availWidth - 44
                } else {
                    // portrait
                    window_height = window.screen.availHeight - 44;
                }
            } 

            $footer.css({
                'height' : window_height + 'px'
            });

            this._updateBodyClass('footer_orientation', 'footer-', 'horizontal');

            $img.each(function() {
                var $this = $(this);
                var img_width = $this.width();
                var img_height = $this.height();

                // mobile-first - svg, while smaller, causes iOS to crash so we load a png
                if (self._isDesktop()) {
                    $img.attr('src', $img.attr('data-svg-src'));
                }

                /*
                    find img_height if img_width = window_width
                    we want to fill the part of the screen it leaves (+ 3% padding)

                    img = 827 x 407
                    win = 400 x 679
                    newImg = 400 x 196

                    newImg % of win (height) = (196 / 679) * 100 = 29%

                    text % of win (height) = 100 - 29 = 71%

                    text_height (%) = 
                        100 - 3 -   Math.round(
                                        (
                                            (
                                                (window_width * img_height) / img_width
                                            ) / window_height
                                        ) * 100
                                    );
                */
                if (img_width > window_width) {
                    height = (100 - 3 - Math.round((((window_width * img_height) / img_width) / window_height) * 100)).toString() + '%';
                    self._configureFooter($this, 'vertical', '0px', height);
                } else {
                    left = Math.floor((window_width - img_width) / 2).toString() + 'px';
                    self._configureFooter($this, 'horizontal', left, '35%');
                }
            });
        },

        _configureFooter : function ($img, clazz, left, height) {
            var $text = $(this.opts.about);

            this._updateBodyClass('footer_orientation', 'footer-', clazz);

            $img.css({
                'left' : left
            });

            $text.windowBox({
                height : height,
                ratio : {
                    horizontal : 8,
                    vertical : 13
                }
            });
        },

        version : '3.0'
    };

    $.fn[pluginName] = function ( options ) {
        options = options || {};
        return this.each(function () {
            if (!$.data(this, pluginName)) {
                var plugin = new Plugin( this, options );
                $.data(this, pluginName, plugin);
                window.GCGC = plugin;
            }
        });
    };

})( jQuery, window, document );