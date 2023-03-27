(function (jQuery, $) {
window.isThemlerIframe = function() {
    'use strict';
    try {
        return "undefined" !== typeof parent.AppController;
    } catch(e) {
        return false;
    }
};

jQuery(function ($) {
    $('[data-social-url]').each(function () {
        var href = $(this).attr('href') || '',
            origin = window.location.origin,
            pathname = window.location.pathname.replace(/[^\/]*$/, $(this).data('pathToRoot'));

        $(this).attr(
            'href',
            (href.replace(/\[URL]/, origin + pathname).replace('/./', '/'))
        );
    });
});

})(window._$, window._$);
(function (jQuery, $) {
(function ($) {
    'use strict';

    window.initAffix = function initAffix(selector) {
        $('.bd-affix-fake').prev(':not([data-fix-at-screen])').next().remove();

        $(selector).each(function () {
            var element = $(this),
                offset = {},
                cachedOffset = null;

            element.off('.affix');
            element.removeAttr('style');
            element.removeClass($.fn.affix.Constructor.RESET);
            element.removeData('bs.affix');

            offset.top = function () {
                var hasAffix = element.hasClass('affix');

                if (cachedOffset === null && hasAffix) {
                    element.removeClass('affix');
                }

                if (!hasAffix) {
                    var elTop = element.offset().top,
                        offset = parseInt(element.data('offset'), 10) || 0,
                        clipAtControl = element.data('clipAtControl'),
                        fixAtScreen = element.data('fixAtScreen'),
                        elHeight = element.outerHeight();

                    var ev = $.Event('affix-calc.theme.affix');
                    element.trigger(ev);
                    ev.offset = ev.offset || 0;
                    offset += ev.offset;

                    if (clipAtControl === 'bottom') {
                        elTop += elHeight;
                    }

                    if (fixAtScreen === 'bottom') {
                        elTop += offset;
                        elTop -= $(window).height();
                    }

                    if (fixAtScreen === 'top') {
                        elTop -= offset;
                    }

                    cachedOffset = elTop;
                }

                if (cachedOffset === null && hasAffix) {
                    element.addClass('affix');
                }

                return cachedOffset;
            };

            element.on('affix.bs.affix', function (e) {
                var el = $(this),
                    fake = el.next('.bd-affix-fake');

                if (!fake.is(':visible')) {
                    e.preventDefault();
                    return;
                }

                if (['absolute', 'fixed'].indexOf(el.css('position')) === -1) {
                    fake.css('height', el.outerHeight(true));
                }

                // fix affix position
                var body = $('body');
                var bodyWidth = body.outerWidth() || 1;
                var elWidth = el.outerWidth();
                var elLeft = el.offset().left;
                el.css('width', (el.outerWidth() / bodyWidth * 100) + '%');
                el.css('left', (elLeft / bodyWidth * 100) + '%');
                el.css('right', ((bodyWidth - elLeft - elWidth) / bodyWidth * 100) + '%');

                var offset = parseInt(element.data('offset'), 10) || 0;
                var ev = $.Event('affix-calc.theme.affix');
                el.trigger(ev);
                ev.offset = ev.offset || 0;
                offset += ev.offset;

                if (element.data('fixAtScreen') === 'bottom') {
                    el.css('top', 'auto');
                    el.css('bottom', offset + 'px');
                } else {
                    el.css('top', offset + 'px');
                    el.css('bottom', 'auto');
                }
            });

            element.on('affixed-top.bs.affix', function () {
                $(this).next('.bd-affix-fake').removeAttr('style');
                $(this).removeAttr('style');
            });

            if (!element.next('.bd-affix-fake').length) {
                element.after('<div class="bd-affix-fake bd-no-margins"></div>');
            }

            $('body').trigger($.Event('affix-init.theme.affix'), [element]);

            element.affix({
                'offset': offset
            });

            element.affix('checkPosition');
        });
    };

    $(function ($) {
        var affixTimeout;

        $(window).on('resize', function (e, param) {
            clearTimeout(affixTimeout);
            if (param && param.force) {
                window.initAffix('[data-affix]');
            } else {
                affixTimeout = setTimeout(function () {
                    window.initAffix('[data-affix]');
                }, 100);
            }
        });

        window.initAffix('[data-affix]');
    });
})(jQuery);
})(window._$, window._$);
(function (jQuery, $) {
(function($){
    'use strict';
    /*jshint -W004 */
    /**
     * Copyright 2012, Digital Fusion
     * Licensed under the MIT license.
     * http://teamdf.com/jquery-plugins/license/
     *
     * @author Sam Sehnert
     * @desc A small plugin that checks whether elements are within
     *       the user visible viewport of a web browser.
     *       only accounts for vertical position, not horizontal.
     */
    var $w = $(window);
    $.fn.visible = function(partial,hidden,direction){

        if (this.length < 1)
            return;

        var $t        = this.length > 1 ? this.eq(0) : this,
            t         = $t.get(0),
            vpWidth   = $w.width(),
            vpHeight  = $w.height(),
            direction = (direction) ? direction : 'both',
            clientSize = hidden === true ? t.offsetWidth * t.offsetHeight : true;

        if (typeof t.getBoundingClientRect === 'function'){

            // Use this native browser method, if available.
            var rec = t.getBoundingClientRect(),
                tViz = rec.top    >= 0 && rec.top    <  vpHeight,
                bViz = rec.bottom >  0 && rec.bottom <= vpHeight,
                lViz = rec.left   >= 0 && rec.left   <  vpWidth,
                rViz = rec.right  >  0 && rec.right  <= vpWidth,
                vVisible   = partial ? tViz || bViz : tViz && bViz,
                hVisible   = partial ? lViz || rViz : lViz && rViz;

            if(direction === 'both')
                return clientSize && vVisible && hVisible;
            else if(direction === 'vertical')
                return clientSize && vVisible;
            else if(direction === 'horizontal')
                return clientSize && hVisible;
        } else {

            var viewTop         = $w.scrollTop(),
                viewBottom      = viewTop + vpHeight,
                viewLeft        = $w.scrollLeft(),
                viewRight       = viewLeft + vpWidth,
                offset          = $t.offset(),
                _top            = offset.top,
                _bottom         = _top + $t.height(),
                _left           = offset.left,
                _right          = _left + $t.width(),
                compareTop      = partial === true ? _bottom : _top,
                compareBottom   = partial === true ? _top : _bottom,
                compareLeft     = partial === true ? _right : _left,
                compareRight    = partial === true ? _left : _right;

            if(direction === 'both')
                return !!clientSize && ((compareBottom <= viewBottom) && (compareTop >= viewTop)) && ((compareRight <= viewRight) && (compareLeft >= viewLeft));
            else if(direction === 'vertical')
                return !!clientSize && ((compareBottom <= viewBottom) && (compareTop >= viewTop));
            else if(direction === 'horizontal')
                return !!clientSize && ((compareRight <= viewRight) && (compareLeft >= viewLeft));
        }
    };
    //=====================================================
    $(function() {
        /**
         * Fix for bootstrap v3.1.1
         * Missing check for event target.
         * Already fixed in newer versions
         */
        if ($.support.transition && !$.event.special.bsTransitionEnd) {
            $.event.special[$.support.transition.end] = {
                handle: function (e) {
                    if ($(e.target).is(this)) {
                        return e.handleObj.handler.apply(this, arguments);
                    }
                }
            };
        }
    });

    function getQueue(effect) {
        if (effect.is('.animated[data-animation-event]') && !effect.data('animation-queue')) {
            effect.data('animation-queue', new AsyncQueue(effect));
        }
        return effect.data('animation-queue');
    }
    function runAnimation(effect, eventName) {
        var queue = getQueue(effect);
        if (queue) {
            queue.push(eventName);
        }
    }
    function abortAnimation(effect) {
        var queue = getQueue(effect);
        if (queue) {
            queue.abort();
        }
    }
    function visibilityImmediate(effect, value) {
        effect.css('transition', value === 'hidden' ? 'none' : '');
        effect.css('visibility', value);
    }

    $(window).resize(function() {
        // onloadinterval
        $('.animated[data-animation-event*="onloadinterval"]').each(function() {
            var effect = $(this);
            runAnimation(effect, 'onloadinterval');
        });
    });
    $(function() {
        // hover
        $(document).on('mouseover', '.animated[data-animation-event*="hover"]', function() {
            runAnimation($(this), 'hover');
        });
        // scroll
        $('.animated[data-animation-event*="scroll"]').each(function() {
            var effect = $(this);
            if(!getMetaData(effect, 'scroll')) {
                return;
            }
            if (needToHide(effect, 'scroll')) {//скрывать, даже если изначально он в поле зрения
                visibilityImmediate(effect, 'hidden');
            }
        });
        $(document).on('scroll', function() {
            $('.animated[data-animation-event*="scroll"]').each(function () {
                var effect = $(this);
                if(!getMetaData(effect, 'scroll')) {
                    return;
                }
                if(effect.visible(true)) {
                    runAnimation(effect, 'scroll');
                }
            });
        });
        // scrollloop
        $(document).on('scroll', function() {
            $('.animated[data-animation-event*="scrollloop"]').each(function () {
                var effect = $(this);
                if(effect.visible(true)) {
                    if(!effect.data('scrollloop-animation-played')) {
                        effect.data('scrollloop-animation-played', true);
                        runAnimation(effect, 'scrollloop');
                    }
                } else {
                    effect.removeData('scrollloop-animation-played');
                }
            });
        });
        // onload
        $('.animated[data-animation-event*="onload"]').each(function() {
            var effect = $(this);
            if(!getMetaData(effect, 'onload')) {
                return;
            }
            runAnimation(effect, 'onload');
        });
        // onloadinterval
        $('.animated[data-animation-event*="onloadinterval"]').each(function() {
            var effect = $(this);
            runAnimation(effect, 'onloadinterval');
        });
        // slidein
        var slideinEffects = $('.animated[data-animation-event*="slidein"]');
        slideinEffects.each(function() {
            var effect = $(this);
            if (needToHide(effect, 'slidein')) {
                visibilityImmediate(effect, 'hidden');
            }
        });
        var carouselsSlidein = slideinEffects.parents('.carousel');
        $(document).on('slid.bs.carousel', '.carousel', function() {
            $(this)
                .find('.item.active')
                .find('.animated[data-animation-event*="slidein"]')
                .each(function() {
                    runAnimation($(this), 'slidein');
                });
            $(this)
                .find('.item:not(.active)')
                .find('.animated[data-animation-event*="slideout"]').each(function() {
                    var effect = $(this);
                    visibilityImmediate(effect, '');
                });
            $(this)
                .find('.item:not(.active)')
                .find('.animated[data-animation-event*="slidein"]')
                .each(function() {
                    var effect = $(this);
                    if (needToHide(effect, 'slidein')) {
                        visibilityImmediate(effect, 'hidden');
                    }
                });
        });
        carouselsSlidein.trigger('slid.bs.carousel');
        // slideout
        var moveSlide = false;
        $(document).on('slide.bs.carousel', '.carousel', function(event) {
            var effects = $(this)
                    .find('.item.active .animated[data-animation-event*="slideout"]');
            if (effects.length && !moveSlide) {
                event.isDefaultPrevented = function () {
                    return true;
                };
                effects.each(function() {
                    var effect = $(this);
                    if (!effect.data('slideout-played')) {
                        effect.data('slideout-played', true);
                        abortAnimation(effect);
                        runAnimation(effect, 'slideout');
                    }
                    setTimeout(function () {
                        if (needToHide(effect, 'slideout')) {
                            visibilityImmediate(effect, 'hidden');
                        }
                    }.bind(effect), getAnimationTime(effect, 'slideout'));
                });
                var eventDirection = event.direction === 'left' ? 'next' : 'prev';
                var maxDuration = getMaxDuration(effects, 'slideout');
                setTimeout(function () {
                    effects.each(function() {
                        var effect = $(this);
                        if (needToHide(effect, 'slideout')) {
                            visibilityImmediate(effect, 'hidden');
                        }
                        if (effect.data('slideout-played')) {
                            effect.data('slideout-played', false);
                        }
                    });
                    moveSlide = true;
                    $(this)
                        .find('.item.active .animated[data-animation-event*="slidein"]')
                        .each(function() {
                            abortAnimation($(this));
                        });
                    $(this).carousel(eventDirection);
                }.bind(this), maxDuration);
            } else {
                moveSlide = false;
            }
        });

    });

    function AsyncQueue(dom) {
        this.dom = dom;
        this.queue = [];
        this.current = null;
        this.push = function(animation) {
            if(this.queue.indexOf(animation) === -1 && (!this.current || this.current.type !== animation)) {
                this.queue.push(animation);
            }
            this.tryStart();
        };
        this.tryStart = function() {
            if(!this.current && this.queue.length) {
                this.current = new animationEvents[this.queue.shift()](this.dom);
                this.current.start(function() {
                    this.current = null;
                    this.tryStart();
                }.bind(this));
            }
        };
        this.abort = function() {
            if(this.queue.indexOf('onloadinterval') !== -1) {
                setTimeout(function() {
                    this.push('onloadinterval');
                }.bind(this), 100);
            }
            this.queue = [];
            if(this.current) {
                this.current.abort();
            }
        };
    }
    var animationEvents = {
        hover: AnimationHover,
        scroll: AnimationScroll,
        scrollloop: AnimationScrollLoop,
        onload: AnimationOnload,
        onloadinterval: AnimationOnloadInterval,
        slidein: AnimationSlidein,
        slideout: AnimationSlideout
    };

    //========================================
    function BaseAnimation() {}
    BaseAnimation.prototype.start = function(nextAnimation) {
        visibilityImmediate(this.dom, '');
        this.nextAnimation = nextAnimation;
        startAnimation(this.dom, this.type);
        this.timer = waitEnd(this.dom, this.type, function() {
            stopAnimation(this.dom, this.type);
            this.nextAnimation();
        }.bind(this));
    };
    BaseAnimation.prototype.abort = function() {
        clearTimeout(this.timer);
        stopAnimation(this.dom, this.type);
        this.nextAnimation();
    };
    //============================
    function AnimationHover(dom) {
        this.dom = dom;
        this.type = 'hover';
    }
    AnimationHover.prototype = Object.create(BaseAnimation.prototype);
    //============================
    function AnimationScroll(dom) {
        this.dom = dom;
        this.type = 'scroll';
    }
    AnimationScroll.prototype = Object.create(BaseAnimation.prototype);
    AnimationScroll.prototype.start = function(nextAnimation) {
        if(this.dom.data('scroll-animation-done')) {
            nextAnimation();
            return;
        }
        BaseAnimation.prototype.start.call(this, function() {
            this.dom.data('scroll-animation-done', true);
            nextAnimation();
        }.bind(this));
    };
    //============================
    function AnimationScrollLoop(dom) {
        this.dom = dom;
        this.type = 'scrollloop';
    }
    AnimationScrollLoop.prototype = Object.create(BaseAnimation.prototype);
    //============================
    function AnimationOnload(dom) {
        this.dom = dom;
        this.type = 'onload';
    }
    AnimationOnload.prototype = Object.create(BaseAnimation.prototype);
    //============================
    function AnimationOnloadInterval(dom) {
        this.dom = dom;
        this.type = 'onloadinterval';
    }
    AnimationOnloadInterval.prototype = Object.create(BaseAnimation.prototype);
    AnimationOnloadInterval.prototype.start = function(nextAnimation) {
        BaseAnimation.prototype.start.call(this, function() {
            setTimeout(function() {
                runAnimation(this.dom, 'onloadinterval');
            }.bind(this), 50);
            nextAnimation();
        }.bind(this));
    };
    //============================
    function AnimationSlidein(dom) {
        this.dom = dom;
        this.type = 'slidein';
    }
    AnimationSlidein.prototype = Object.create(BaseAnimation.prototype);
    //============================
    function AnimationSlideout(dom) {
        this.dom = dom;
        this.type = 'slideout';
    }
    AnimationSlideout.prototype = Object.create(BaseAnimation.prototype);

    //========================================
    function startAnimation(dom, eventName) {
        var data = getMetaData(dom, eventName);
        if(data) {
            dom.addClass(data.name);
        }
    }

    function stopAnimation(dom, eventName) {
        var data = getMetaData(dom, eventName);
        if(data) {
            dom.removeClass(data.name);
        }
    }

    function waitEnd(dom, eventName, cb) {
        var data = getMetaData(dom, eventName);
        if(!data) {
            cb();
            return;
        }
        var duration = isNaN(parseFloat(data.duration)) ? 1000 : parseFloat(data.duration);
        var delay = isNaN(parseFloat(data.delay)) ? 0 : parseFloat(data.delay);
        if(data.infinited === 'true') {
            return;
        }
        return setTimeout(function() {
            cb();
        }, delay + duration);
    }

    //=====================================================



    function getMetaData(dom, eventName) {
        var result;
        dom = $(dom);
        var tmp = {
            name: dom.data('animation-name'),
            event: dom.data('animation-event'),
            duration: dom.data('animation-duration'),
            delay: dom.data('animation-delay'),
            infinited: dom.data('animation-infinited'),
            display: dom.data('animation-display')
        };
        for(var i in tmp) {/*jshint -W089*/
            tmp[i] = String(tmp[i]).split(',');
        }
        for(var i = 0; i < tmp.name.length; i++) {
            if(eventName === tmp.event[i]) {
                result = {
                    name: tmp.name[i],
                    event: tmp.event[i],
                    duration: tmp.duration[i],
                    delay: tmp.delay[i],
                    infinited: tmp.infinited[i],
                    display: tmp.display[i]
                };
                if(eventName === 'slideout') {
                    result.infinited = 'false';
                }
                return result;
            }
        }
    }

    function needToHide(effect, eventName) {
        var data = getMetaData(effect, eventName);
        var visibleAnimations = ['bounce', 'flash', 'pulse', 'rubber', 'band','snake','swing','tada','wobble', 'slideindown' , 'slideinleft' , 'slideinright', 'slideinup',
            'slideoutdown', 'slideoutleft', 'slideoutright', 'slideoutup'];
        return visibleAnimations.indexOf(data.name.toLowerCase()) === -1;
    }

    function getMaxDuration(effects, eventName) {
        var maxDuration = 0;
        effects.each(function () {
            var animationTime = getAnimationTime($(this), eventName);
            maxDuration = maxDuration < animationTime ? animationTime : maxDuration;
        });
        return maxDuration;
    }

    function getAnimationTime(effect, eventName) {
        var data = getMetaData(effect, eventName);
        if(!data) {
            return 0;
        }
        var duration = isNaN(parseFloat(data.duration)) ? 0 : parseFloat(data.duration),
            delay = isNaN(parseFloat(data.delay)) ? 0 : parseFloat(data.delay);
        return duration + delay;
    }

})(jQuery);
})(window._$, window._$);
(function (jQuery, $) {
(function SeparatedGrid($) {
    'use strict';
    var row = [],
        getOffset = function getOffset(el) {
            var isInline = false;
            el.css('position', 'relative');
            if (el.css('display') === 'inline') {
                el.css('display', 'inline-block');
                isInline = true;
            }
            var offset = el.position().top;
            if (isInline) {
                el.css('display', 'inline');
            }
            return offset;
        },
        getCollapsedMargin = function getCollapsedMargin(el) {
            if (el.css('display') === 'block') {
                var m0 = parseFloat(el.css('margin-top'));
                if (m0 > 0) {
                    var p = el.prev();
                    var prop = 'margin-bottom';
                    if (p.length < 1) {
                        p = el.parent();
                        prop = 'margin-top';
                    }
                    if (p.length > 0 && p.css('display') === 'block') {
                        var m = parseFloat(p.css(prop));
                        if (m > 0) {
                            return Math.min(m0, m);
                        }
                    }
                }
            }
            return 0;
        },
        classRE1 = new RegExp('^bd-.*-\\d+$'),
        classRE2 = new RegExp('^bd-.*$'),
        getClass = function getClass(el) {
            var i;
            for (i = 0; i < el.classList.length; i++) {
                if (classRE1.test(el.classList[i])) {
                    return el.classList[i];
                }
            }
            for (i = 0; i < el.classList.length; i++) {
                if (classRE2.test(el.classList[i])) {
                    return el.classList[i];
                }
            }
        },
        childFilter = function childFilter() {
            return !!getClass(this);
        },
        getDeeper = function (roots) {
            while (roots.length && roots.length === roots.children().length) {
                roots = roots.children();
            }
            return roots;
        },
        calcOrder = function calcOrder(items) {
            var roots = getDeeper(items);
            var childrenClasses = [];
            var childrenWeights = {};
            var getNextWeight = function getNextWeight(children, i, l) {
                for (var j = i + 1; j < l; j++) {
                    var cls = getClass(children[j]);
                    if (childrenClasses.indexOf(cls) !== -1) {
                        return childrenWeights[cls];
                    }
                }
                return 100; //%
            };
            roots.each(function calcWeight(i, root) {
                var children = $(root).children().filter(childFilter);
                var previousWeight = 0;
                for (var c = 0, l = children.length; c < l; c++) {
                    var cls = getClass(children[c]);
                    if (!cls || cls.length < 1) {
                        continue;
                    }
                    if (childrenClasses.indexOf(cls) === -1) {
                        var nextWeight = getNextWeight(children, c, l);
                        childrenWeights[cls] = previousWeight + (nextWeight - previousWeight) / 10; //~max unique child
                        childrenClasses.push(cls);
                    }
                    previousWeight = childrenWeights[cls];
                }
            });
            childrenClasses.sort(function sortWeight(a, b) {
                return childrenWeights[a] > childrenWeights[b];
            });
            return childrenClasses;
        };
    var calcRow = function calcRow(helpNodes, last, order) {

        $(row).css({'overflow': 'visible', 'height': 'auto'}).toggleClass('last-row', last);

        if (row.length > 0) {
            var roots = $(row);
            roots.removeClass('last-col').last().addClass('last-col');
            roots = getDeeper(roots);

            var createHelpNode = function createHelpNode(fix) {
                var helpNode = document.createElement('div');
                helpNode.setAttribute('style', 'height:' + fix + 'px');
                helpNode.className = 'bd-empty-grid-item';
                helpNodes.push(helpNode);
                return helpNode;
            };
            var cls = '';
            var maxOffset = 0;
            var calcMaxOffsets = function calcMaxOffsets(i, root) {
                var el = $(root).children('.' + cls + ':visible:first');
                if (el.length < 1 || el.css('position') === 'absolute') {
                    return;
                }
                var offset = getOffset(el);
                if (offset > maxOffset) {
                    maxOffset = offset;
                }
            };
            var setMaxOffsets = function setMaxOffsets(i, root) {
                var el = $(root).children('.' + cls + ':visible:first');
                if (el.length < 1 || el.css('position') === 'absolute') {
                    return;
                }
                var offset = getOffset(el);
                var fix = maxOffset - offset - getCollapsedMargin(el);
                if (fix > 0) {
                    el.before(createHelpNode(fix));
                }
            };
            for (var c = 0; c < order.length; c++) {
                maxOffset = 0;
                cls = order[c];
                roots.each(calcMaxOffsets);
                maxOffset = Math.ceil(maxOffset);
                roots.each(setMaxOffsets);
            }
            var hMax = 0;
            $.each(roots, function calcMaxHeight(i, e) {
                var h = $(e).outerHeight();
                if (hMax < h) {
                    hMax = h;
                }
            });
            hMax = Math.ceil(hMax);
            $.each(roots, function setMaxHeight(i, e) {
                var el = $(e);
                var fix = hMax - el.outerHeight();
                if (fix > 0) {
                    el.append(createHelpNode(fix));
                }
            });

            $(row).css('min-height', (hMax + 1) + 'px');
        }
        row = [];
    };
    var itemsRE = new RegExp('.*(separated-item[^\\s]+).*'),
        resize = function resize(force) {
            var grid = $('.separated-grid');
            grid.each(function eachGrid(i, gridElement) {
                var g = $(gridElement);
                if (!g.is(':visible')) {
                    return;
                }
                if (!gridElement._item || !gridElement._item.length || !gridElement._item.is(':visible')) {
                    gridElement._item = g.find('div[class*=separated-item]:visible:first');
                    if (!gridElement._item.length) {
                        return;
                    }
                    gridElement._items = g.find(
                        'div.' + gridElement._item.attr('class').replace(itemsRE, '$1')
                    ).filter(function () {
                        return $(this).parents('.separated-grid')[0] === gridElement;
                    });
                }
                var items = gridElement._items;
                if (!items.length) {
                    return;
                }
                var h = 0;
                for (var k = 0; k < items.length; k++) {
                    var el = $(items[k]);
                    var _h = el.height();
                    if (el.is('.first-col')) {
                        h = _h;
                    }
                    if (h !== _h) {
                        gridElement._height = 0;
                    }
                }

                // height of inner elements may change (because of height in vh)
                // in this case 'force' argument used
                if (!force && g.innerHeight() === gridElement._height && g.innerWidth() === gridElement._width) {
                    return;
                }

                var windowScrollTop = $(window).scrollTop();
                items.css({'overflow': 'hidden', 'height': '10px', 'min-height': ''}).removeClass('last-row');
                if (gridElement._helpNodes) {
                    $(gridElement._helpNodes).remove();
                }
                gridElement._helpNodes = [];
                var firstLeft = items.position().left;
                var order = calcOrder(items);
                var notDisplayed = [];
                var lastItem = null;
                items.each(function eachItems(i, gridItem) {
                    var item = $(gridItem);
                    var p = item;
                    do {
                        if (p.css('display') === 'none') {
                            p.data('style', p.attr('style')).css('display', 'block');
                            notDisplayed.push(p[0]);
                        }
                        p = p.parent();

                    } while (p.length > 0 && p[0] !== gridElement && !item.is(':visible'));
                    var first = firstLeft >= item.position().left;
                    if (first && row.length > 0) {
                        calcRow(gridElement._helpNodes, lastItem && lastItem.parentNode !== gridItem.parentNode, order);
                    }
                    row.push(gridItem);
                    item.toggleClass('first-col', first);
                    if (i === items.length - 1) {
                        calcRow(gridElement._helpNodes, true, order);
                    }
                    lastItem = gridItem;
                });
                $(notDisplayed).each(function eachHidden(i, e) {
                    var el = $(e);
                    var css = el.data('style');
                    el.removeData('style');
                    if ('undefined' !== typeof css) {
                        el.attr('style', css);
                    } else {
                        el.removeAttr('style');
                    }
                });
                gridElement._width = g.innerWidth();
                gridElement._height = g.innerHeight();
                $(window).scrollTop(windowScrollTop);
                $(window).off('resize', lazy);
                $(window).resize();
                $(window).on('resize', lazy);
            });
        },
        timeoutLazy,
        lazy = function lazy(e, param) {
            clearTimeout(timeoutLazy);
            if (param && param.force) {
                resize();
            } else {
                timeoutLazy = setTimeout(resize, 100, e && e.type === 'resize');
            }
        },
        interval = function interval() {
            lazy();
            setTimeout(interval, 1000);
        };
    $(window).resize(lazy);
    $(interval);
    $(document).bind('force-grids-update', resize);
    $(document).bind('force-grid-update', function (event, grid) {
        if (grid && grid.length) {
            grid.each(function (i, gridElement) {
                delete gridElement._height;
                delete gridElement._width;
                delete gridElement._helpNodes;
            });
            grid.find('.bd-empty-grid-item').remove();
            resize();
        }
    });
})(jQuery);
})(window._$, window._$);
(function (jQuery, $) {
(function ($) {
    'use strict';
    $(onLoad);

    var timeout;
    $(window).on('resize', function (event, param) {
        clearTimeout(timeout);
        if (param && param.force) {
            applyImageScalling();
        } else {
            timeout = setTimeout(function () {
                applyImageScalling();
            }, 100);
        }
    });

    function onLoad() {
        $(".bd-imagescaling").each(function () {
            var c = $(this);
            if (c.length) {
                var img = c.is('img') ? c : c.find('img');
                scaling(img);
                img.on('load', function () {
                    scaling(img);
                });
            }
        });
    }

    function applyImageScalling() {
        $(".bd-imagescaling").each(function () {
            var c = $(this);
            if (c.length) {
                var img = c.is('img') ? c : c.find('img');
                scaling(img);
            }
        });
    }

    function scaling(img) {
        var imgSrc = img.attr('src') || '',
            imgClass = img.attr('class') || '';

        var imgWrapper = img.parent('.bd-imagescaling-img');

        if (!imgWrapper.length || imgClass) {
            if (img.parent().is('.bd-imagescaling-img')) {
                img.unwrap();
            }
            imgWrapper = img.wrap('<div class="' + imgClass + ' bd-imagescaling-img"></div>').parent();
            img.removeAttr('class');
        }

        if (imgSrc.indexOf('.') === 0) {
            imgSrc = combineUrl(window.location.href, imgSrc);
        }

        if (imgWrapper.siblings('.bd-parallax-image-wrapper').length === 0) {
            imgWrapper.css('background-image', 'url(' + imgSrc + ')');
        }
    }

    function combineUrl(base, relative) {
        if (!relative){
            return base;
        }
        var stack = base.split("/"),
            parts = relative.split("/");
        stack.pop();

        for (var i = 0; i < parts.length; i++) {
            if (parts[i] === ".")
                continue;
            if (parts[i] === "..")
                stack.pop();
            else
                stack.push(parts[i]);
        }
        return stack.join("/");
    }
})(jQuery);

})(window._$, window._$);
(function (jQuery, $) {

window.ThemeLightbox = (function ($) {
    'use strict';
    return (function ThemeLightbox(selectors) {
        var selector = selectors;
        var images = $(selector);
        var current;
        var close = function () {
            $(".bd-lightbox").remove();
        };
        this.init = function () {

            $(selector).mouseup(function (e) {
                if (e.which && e.which !== 1) {
                    return;
                }
                current = images.index(this);
                var imgContainer = $('.bd-lightbox');
                if (imgContainer.length === 0) {
                    imgContainer = $('<div class="bd-lightbox">').css('line-height', $(window).height() + "px")
                        .appendTo($("body"));
                    var closeBtn = $('<div class="close"><div class="cw"> </div><div class="ccw"> </div><div class="close-alt">&#10007;</div></div>');
                    closeBtn.appendTo(imgContainer);
                    closeBtn.click(close);
                    showArrows();
                    var scrollDelay = 100;
                    var lastScroll = 0;
                    imgContainer.bind('mousewheel DOMMouseScroll', function (e) {
                        var date  =  new Date();
                        if (date.getTime() > lastScroll + scrollDelay) {
                            lastScroll = date.getTime();
                            var orgEvent = window.event || e.originalEvent;
                            var delta = (orgEvent.wheelDelta ? orgEvent.wheelDelta : orgEvent.detail * -1) > 0 ? 1 : -1;
                            move(current + delta);
                        }
                        e.preventDefault();
                    }).mousedown(function (e) {
                        // close on middle button click
                        if (e.which === 2) {
                            close();
                        }
                        e.preventDefault();
                     });
                }
                move(current);
            });
        };

        function move(index) {

            if (index < 0 || index >= images.length) {
                return;
            }

            showError(false);

            current = index;

            $(".bd-lightbox .lightbox-image:not(.active)").remove();

            var active = $(".bd-lightbox .active");
            var target = $('<img class="lightbox-image" alt="" src="' + getFullImgSrc($(images[current])) + '" />').click(function () {
                if ($(this).hasClass("active")) {
                    move(current + 1);
                }
            });

            if (active.length > 0) {
                active.after(target);
            } else {
                $(".bd-lightbox").append(target);
            }

            showArrows();
            showLoader(true);

            $(".bd-lightbox").add(target);

            target.load(function () {
                showLoader(false);
                active.removeClass("active");
                target.addClass("active");
            });

            target.error(function () {
                showLoader(false);
                active.removeClass("active");
                target.addClass("active");
                target.attr("src", $(images[current]).attr("src"));
                target.unbind('error');
            });
        }

        function showArrows() {
            if ($(".bd-lightbox .arrow").length === 0) {
                var topOffset = $(window).height() / 2 - 40;
                $(".bd-lightbox")
                    .append(
                        $('<div class="arrow left"><div class="arrow-t ccw"> </div><div class="arrow-b cw"> </div><div class="arrow-left-alt">&#8592;</div></div>')
                            .css("top", topOffset)
                            .click(function () {
                                move(current - 1);
                            })
                    )
                    .append(
                        $('<div class="arrow right"><div class="arrow-t cw"> </div><div class="arrow-b ccw"> </div><div class="arrow-right-alt">&#8594;</div></div>')
                            .css("top", topOffset)
                            .click(function () {
                                move(current + 1);
                            })
                    );
            }

            if (current === 0) {
                $(".bd-lightbox .arrow.left").addClass("disabled");
            } else {
                $(".bd-lightbox .arrow.left").removeClass("disabled");
            }

            if (current === images.length - 1) {
                $(".bd-lightbox .arrow.right").addClass("disabled");
            } else {
                $(".bd-lightbox .arrow.right").removeClass("disabled");
            }
        }

        function showError(enable) {
            if (enable) {
                $(".bd-lightbox").append($('<div class="lightbox-error">The requested content cannot be loaded.<br/>Please try again later.</div>')
                    .css({ "top": $(window).height() / 2 - 60, "left": $(window).width() / 2 - 170 }));
            } else {
                $(".bd-lightbox .lightbox-error").remove();
            }
        }

        function showLoader(enable) {
            if (!enable) {
                $(".bd-lightbox .loading").remove();
            } else {
                $('<div class="loading"> </div>').css({ "top": $(window).height() / 2 - 16, "left": $(window).width() / 2 - 16 }).appendTo($(".bd-lightbox"));
            }
        }

        function getFullImgSrc(image) {
            var largeImage = '';
            var parentLink = image.parent('a');
            if (parentLink.length) {
                parentLink.click(function (e) {
                    e.preventDefault();
                });
                largeImage = parentLink.attr('href');
            } else {
                var src = image.attr("src");
                var fileName = src.substring(0, src.lastIndexOf('.'));
                var ext = src.substring(src.lastIndexOf('.'));
                largeImage = fileName + "-large" + ext;
            }
            return largeImage;
        }
    });
})(jQuery);


jQuery(function () {
    'use strict';
    new window.ThemeLightbox('.bd-lightbox, .lightbox').init();
});
})(window._$, window._$);
(function (jQuery, $) {
jQuery(function ($) {
    'use strict';

    $('.collapse-button').each(function () {
        var button = $(this);
        var collapse = button.siblings('.collapse');

        collapse.on('show.bs.collapse', function () {
            if (button.parent().css('position') === 'absolute') {
                var right = collapse.width() - button.width();
                if (button.hasClass('bd-collapse-right')) {
                    $(this).css({
                        'position': 'relative',
                        'right': right
                    });
                } else {
                    $(this).css({
                        'position': '',
                        'right': ''
                    });
                }
            }
        });
    });

    function parseTiming(str) {
        var ms = parseInt(str);
        if (str.indexOf('ms') === -1 && str.indexOf('s') !== -1) {
            ms *= 1000;
        }
        return ms;
    }

    var emulateTransitionEnd = $.fn.emulateTransitionEnd;
    var dummyTransitionEnd = function (ms) {
        return function () {
            return emulateTransitionEnd.call(this, ms);
        };
    };

    var $body = $('body'),
        $html = $('html');

    $body.on('click', '.bd-menu-overlay, .bd-menu-close-icon', function (e) {
        var menu = $(e.target).closest('nav');
        if (menu.length) {
            menu.find('.navbar-collapse').collapse('hide');
        }
    });

    $(document).keyup(function (e) {
        if (e.keyCode === 27) { // esc
            $('nav .navbar-collapse.collapse.in').collapse('hide');
        }
    });

    var prevWidth = window.innerWidth,
        prevHeight = window.innerHeight;
    $(window).on('resize', function () {
        if (prevWidth === window.innerWidth && prevHeight === window.innerHeight) {
            return;
        }
        // close all offcanvas menus
        $('nav .navbar-collapse.collapse.width.in').collapse('hide');
        prevWidth = window.innerWidth;
        prevHeight = window.innerHeight;
    });

    function disableScroll() {
        var overflow = $html[0].clientHeight < $html[0].scrollHeight;
        $body.css('top', '-' + window.scrollY + 'px');
        if (overflow) {
            $html.css('overflow-y', 'scroll');
        }
        $html.css('position', 'fixed').css('width', '100%');
    }

    function enableScroll() {
        if ($html.css('position') !== 'fixed') { // already enabled
            return;
        }
        $html.css('position', '').css('overflow-y', '').css('width', '');
        var scrollY = -parseInt($body.css('top'));
        $body.css('top', '');
        window.scrollTo(window.scrollX, scrollY);
    }

    $body.on('show.bs.collapse', '.navbar-collapse.width', function (event) {
        var menu = $(event.target).closest('nav'),
            overlay = menu.find('.bd-menu-overlay');

        var offcanvasShift = menu.data('responsiveType') === 'offcanvas-shifted';
        if (offcanvasShift) {
            $body
                .css('transition', ['left', menu.data('offcanvasDuration'), menu.data('offcanvasTimingFunction'), menu.data('offcanvasDelay')].join(' '))
                .css('left', '0');
        }

        disableScroll();
        overlay.addClass('show');

        $.fn.emulateTransitionEnd = dummyTransitionEnd(parseTiming(menu.data('offcanvasDuration')) + parseTiming(menu.data('offcanvasDelay')));
        requestAnimationFrame(function () {
            var width = menu.find('.navbar-collapse')[0].style.width;

            overlay
                .css('opacity', 1)
                .css('margin-left', width);

            if (offcanvasShift) {
                $body.css('left', width);
            }
            $.fn.emulateTransitionEnd = emulateTransitionEnd;
        });
    });

    $body.on('shown.bs.collapse', '.navbar-collapse.width', function (event) {
        $(event.target).css('width', '');
    });

    $body.on('hide.bs.collapse', '.navbar-collapse.width', function (event) {
        var menu = $(event.target).closest('nav'),
            overlay = menu.find('.bd-menu-overlay');
        var offcanvasShift = menu.data('responsiveType') === 'offcanvas-shifted';
        overlay
            .css('opacity', '')
            .css('margin-left', '');

        if (offcanvasShift) {
            $body.css('left', '0');
        }

        $.fn.emulateTransitionEnd = dummyTransitionEnd(parseTiming(menu.data('offcanvasDuration')) + parseTiming(menu.data('offcanvasDelay')));
    });

    $body.on('hidden.bs.collapse', '.navbar-collapse.width', function (event) {
        var collapse = $(event.target),
            overlay = collapse.siblings('.bd-menu-overlay');
        $.fn.emulateTransitionEnd = emulateTransitionEnd;
        $body.css('transition', '');
        enableScroll();

        overlay.removeClass('show');
        collapse.css('width', '');
    });

    function isResponsive(menu) {
        var tmpContainer = $('<div>').addClass('responsive-collapsed');
        menu.append(tmpContainer);
        var visible = tmpContainer.is(':visible');
        tmpContainer.remove();
        return !visible;
    }

    $(document).on('touchend click', '[data-responsive-menu] .nav a', function responsiveClick(e) {
        var itemLink = $(this),
            menu = itemLink.closest('[data-responsive-menu]'),
            responsiveLevels = menu.data('responsiveLevels'),
            levels = menu.data('levels'),
            responsive = isResponsive(menu);

        if (responsive && responsiveLevels === 'expand on click' ||
            !responsive && levels === 'expand on click'
        ) {
            var submenu = itemLink.siblings();
            if (submenu.length > 0) {
                if (submenu.css('visibility') === 'visible') {
                    submenu.removeClass('show');
                    submenu.find('.show').removeClass('show');
                    itemLink.removeClass('active');
                } else {
                    itemLink
                        .closest('[class*=bd-menuitem]')
                        .siblings()
                        .find('ul').parent()
                        .removeClass('show');

                    itemLink.closest('[class*=bd-menuitem]')
                        .siblings()
                        .find('> div > a, > a')
                        .removeClass('active');

                    submenu.addClass('show');
                    itemLink.addClass('active');
                }
                e.preventDefault();
                return false;
            }
        }

        if (e.type === 'click' && menu.attr('data-responsive-type') === 'offcanvas' && menu.find('.collapse.in').length > 0) {
            // make anchor-link works
            menu.find('.navbar-collapse').collapse('hide');
            enableScroll();
            e.preventDefault();
            e.currentTarget.click();
            return false;
        }
        return true;
    });

    $(document).on('mouseenter touchstart', 'ul.nav > li, .nav ul > li', function calcSubmenuDirection() {
        var leftClass = 'bd-popup-left';
        var rightClass = 'bd-popup-right';

        var popup = $(this).children('[class$="-popup"], [class*="-popup "]');
        
        if (popup.length) {
            megaMenuOpen($(this), popup);
            popup.removeClass(leftClass + ' ' + rightClass);
            var dir = '';
            if (popup.parents('.' + leftClass).length) {
                dir = leftClass;
            } else if (popup.parents('.' + rightClass).length) {
                dir = rightClass;
            }
            if (dir) {
                popup.addClass(dir);
            } else {
                var left = popup.offset().left;
                var width = popup.outerWidth();
                if (left < 0) {
                    popup.addClass(rightClass);
                } else if (left + width > $(window).width()) {
                    popup.addClass(leftClass);
                }
            }
        }
    });

    function getTextWidth(element, pseudo) {
        var content = window.getComputedStyle(element[0], pseudo).getPropertyValue('content');
        if (!content || content === 'none') {
            return element.children().outerWidth();
        }

        var clone = element.clone().css({display: 'inline', margin: 0, padding: 0});
        element.after(clone);
        var width = clone.width();
        clone.remove();

        return width;
    }

    function getSheetInfo() {
        var tmpDiv = $('<div id="bd-tmp-container" class="bd-container-inner"></div>');
        $('body').append(tmpDiv);
        var width = tmpDiv.width();
        var offset = tmpDiv.offset();
        tmpDiv.remove();
        return {
            width: width,
            left: offset.left,
            right: offset.left + width
        };
    }

    function megaMenuOpen(item, popup) {
        var nav = popup.closest('nav');

        if (popup.parent().closest('[class*=-popup]').hasClass('bd-megamenu-popup')) {
            var aElement = popup.parent().children('a');
            var textElement = aElement.children();
            popup.css('left', textElement.position().left + getTextWidth(aElement, ':after'));
        }

        var isMegaMenu = item.hasClass('bd-has-megamenu');
        if (!isMegaMenu) {
            return;
        }
        var isResponsive = nav.find('.collapse-button').is(':visible');
        if (isResponsive) {
            item.find('.row').removeClass('separated-grid')
                .children().removeAttr('style') // clear styles added by grid script
                .find('.bd-empty-grid-item').remove();

            item.removeClass('bd-megamenu');
        } else {
            item.find('.row').addClass('separated-grid');
            item.addClass('bd-megamenu');
        }

        if (!isResponsive) {
            var megaWidth      = item.data('megaWidth')      || item.children('a').data('megaWidth') || 'sheet',
                megaWidthValue = item.data('megaWidthValue') || item.children('a').data('megaWidthValue'),
                width,
                leftOffset,
                itemPos = item.offset().left,
                sheet = getSheetInfo(),
                navBar = popup.closest('.navbar-collapse'),
                menuLeft = navBar.offset().left,
                menuRight = menuLeft + navBar.outerWidth();

            if (menuLeft < sheet.left || menuRight > sheet.right) {
                megaWidth = 'custom';
                megaWidthValue = sheet.width;
            }

            switch (megaWidth) {
                // sheet, custom
                case 'custom':
                    if (itemPos + megaWidthValue <= menuRight) {
                        leftOffset = 0;
                    } else if (menuLeft < $(window).width() - menuRight) {
                        leftOffset = menuLeft - itemPos;
                    } else {
                        leftOffset = (menuRight - itemPos) - megaWidthValue;
                    }
                    width = megaWidthValue;
                    break;
                default:
                    leftOffset = sheet.left - itemPos;
                    width = sheet.width;
            }

            if (leftOffset) {
                popup.css({
                    'left': leftOffset,
                    'right': 'auto'
                });
            }
            if (width) {
                popup.outerWidth(width);
            }

            $(document).trigger('force-grid-update', [item.find('.separated-grid')]);
        } else {
            popup.css({
                'left': 'auto',
                'width': 'auto',
                'right': 'auto'
            });
        }
    }

});
})(window._$, window._$);
(function (jQuery, $) {
(function ($) {
    'use strict';

    // http://paulirish.com/2011/requestanimationframe-for-smart-animating/
    // http://my.opera.com/emoller/blog/2011/12/20/requestanimationframe-for-smart-er-animating
    // requestAnimationFrame polyfill by Erik M?ller. fixes from Paul Irish and Tino Zijdel
    // MIT license

    if (!/Android|BlackBerry|iPad|iPhone|iPod|Windows Phone/i.test(navigator.userAgent || navigator.vendor || window.opera)) {

        (function () {
            var lastTime = 0;
            var vendors = ['ms', 'moz', 'webkit', 'o'];
            for (var x = 0; x < vendors.length && !window.requestAnimationFrame; ++x) {
                window.requestAnimationFrame = window[vendors[x] + 'RequestAnimationFrame'];
                window.cancelAnimationFrame = window[vendors[x] + 'CancelAnimationFrame'] ||
                    window[vendors[x] + 'CancelRequestAnimationFrame'];
            }

            if (!window.requestAnimationFrame)
                window.requestAnimationFrame = function (callback) {
                    var currTime = new Date().getTime();
                    var timeToCall = Math.max(0, 16 - (currTime - lastTime));
                    var id = window.setTimeout(function () {
                            callback(currTime + timeToCall);
                        },
                        timeToCall);
                    lastTime = currTime + timeToCall;
                    return id;
                };

            if (!window.cancelAnimationFrame)
                window.cancelAnimationFrame = function (id) {
                    clearTimeout(id);
                };
        }());

        var transform = ['transform', 'msTransform', 'webkitTransform', 'mozTransform', 'oTransform'];

        $(function () {
            onLoad();
        });

        var timeout;
        $(window).on('resize', function (e, param) {
            clearTimeout(timeout);
            if (param && param.force) {
                onResize();
            } else {
                timeout = setTimeout(onResize, 100);
            }
        });

        $(window).on('scroll', function () {
            window.requestAnimationFrame(function () {
                onScroll();
            });
        });
    } else {
        $(function () {
            onLoadMobile();
        });
    }

    function onLoad() {
        var elements = document.getElementsByClassName('bd-parallax-bg-effect');
        if (elements.length && window._smoothWheelInstance) {
            window._smoothWheelInstance();
        }

        [].forEach.call(elements, function (element) {
            var that = element,
                controlClass = that.getAttribute('data-control-selector').replace(/\./g, ''),
                controls = document.getElementsByClassName(controlClass),
                isSlider = /bd-slider-\d+($|\s)/g.test(controlClass) || getClassName(controls[0]).indexOf('bd-slider') !== -1,
                isColumn = /bd-layoutcolumn-\d+($|\s)/g.test(controlClass);

            var activeDoms = [], wrapperDiv;

            if (isSlider) {
                controls = findByClass(controls[0], 'bd-slide');
                if (controls.length) {
                    [].forEach.call(controls, function (slide) {
                        activeDoms = findTopLevelDoms(slide, 'bd-parallax-image-wrapper', controlClass);
                        if (!activeDoms.length) {
                            slide.style.backgroundImage = 'none';
                            slide.style.backgroundColor = 'transparent';
                            wrapperDiv = document.createElement('div');
                            wrapperDiv.className = 'bd-parallax-image-wrapper';
                            wrapperDiv.innerHTML = '<div class="bd-parallax-image"></div>';
                            slide.insertBefore(wrapperDiv, slide.firstChild);
                        }
                    });
                }
            }
            else if (isColumn) {
                if (controls.length) {
                    activeDoms = findTopLevelDoms(that, 'bd-parallax-image-wrapper', controlClass);
                    if (!activeDoms.length) {
                        var effectClone = that.cloneNode(true);
                        effectClone.innerHTML = '';

                        var columnNode = controls[0].parentNode;
                        $(columnNode).unwrap();
                        columnNode.insertBefore(effectClone, columnNode.firstChild);

                        wrapperDiv = document.createElement('div');
                        wrapperDiv.className = 'bd-parallax-image-wrapper';
                        wrapperDiv.innerHTML = '<div class="bd-parallax-image"></div>';

                        effectClone.insertBefore(wrapperDiv, effectClone.firstChild);
                    }
                }
            }
            else {
                if (controls.length) {
                    activeDoms = findTopLevelDoms(that, 'bd-parallax-image-wrapper', controlClass);
                    if (!activeDoms.length) {
                        wrapperDiv = document.createElement('div');
                        wrapperDiv.className = 'bd-parallax-image-wrapper';
                        wrapperDiv.innerHTML = '<div class="bd-parallax-image"></div>';
                        that.insertBefore(wrapperDiv, that.firstChild);
                    }
                }
            }

            if (controls.length) {
                [].forEach.call(controls, function (control) {
                    var parallaxWrapper = isColumn ? findByClass(control.parentElement, 'bd-parallax-image-wrapper')[0] : findTopLevelDoms(control, 'bd-parallax-image-wrapper', controlClass)[0];
                    if (parallaxWrapper) {
                        var parallaxImg = parallaxWrapper.getElementsByClassName('bd-parallax-image')[0],
                            controlOffset = $(that).offset().top,
                            controlHeight = that.clientHeight,
                            viewPortHeight = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);

                        if (control.style.backgroundImage === 'none') {
                            control.style.backgroundImage = '';
                        }

                        if (control.style.backgroundColor === 'transparent') {
                            control.style.backgroundColor = '';
                        }

                        var backgroundStyles = getComputedStyle(control);

                        if (backgroundStyles.position === 'static') {
                            control.style.position = 'relative';
                        }

                        if (backgroundStyles.backgroundImage !== 'none' && parallaxImg.style.backgroundImage !== backgroundStyles.backgroundImage) {
                            parallaxImg.style.backgroundImage = backgroundStyles.backgroundImage;
                        }

                        if (backgroundStyles.backgroundColor !== 'transparent' && parallaxImg.style.backgroundColor !== backgroundStyles.backgroundColor) {
                            parallaxImg.style.backgroundColor = backgroundStyles.backgroundColor;
                        }

                        control.style.backgroundImage = 'none';
                        control.style.backgroundColor = 'transparent';
                        parallaxImg.style.backgroundRepeat = backgroundStyles.backgroundRepeat;
                        parallaxImg.style.backgroundPosition = backgroundStyles.backgroundPosition;

                        if (isSlider) {
                            parallaxWrapper.style.setProperty('z-index', '-2', 'important');
                        }

                        if (isColumn) {
                            var containerStyles = getComputedStyle(parallaxWrapper);
                            parallaxImg.style.setProperty('min-width', containerStyles.width, 'important');
                            //parallaxImg.style.setProperty('min-height', Math.min(viewPortHeight, 3 * parseInt(containerStyles.height)) + 'px', 'important');
                        }

                        var positionDifference,
                            controlBottom = controlOffset + controlHeight;

                        if (controlOffset >= viewPortHeight / 2) {
                            //var additionalSpace = controlOffset < viewPortHeight ? (viewPortHeight - controlOffset) / 2 : 0;
                            positionDifference = -viewPortHeight / 2 /*+ additionalSpace*/ + (getCompatibleScrollTop() + viewPortHeight - controlOffset) / 2;
                        }
                        else {
                            positionDifference = /*-controlOffset / 2*/ +(getCompatibleScrollTop() - controlOffset) / 2;
                        }
                        if (getCompatibleScrollTop() + viewPortHeight > controlOffset && getCompatibleScrollTop() < controlBottom) {
                            var transformProperty = getSupportedPropertyName(transform);
                            if (transformProperty) {
                                parallaxImg.style[transformProperty] = 'translate3d(0, ' + positionDifference + 'px, 0)';
                            }
                        }
                    }
                });
            }
        });
    }

    function onLoadMobile() {
        var elements = document.getElementsByClassName('bd-parallax-bg-effect');

        [].slice.call(elements).forEach(function (element) {
            var controlClass = element.getAttribute('data-control-selector').replace(/\./g, ''),
                controls = document.getElementsByClassName(controlClass),
                isSlider = /bd-slider-\d+($|\s)/g.test(controlClass) || getClassName(controls[0]).indexOf('bd-slider') !== -1,
                isColumn = /bd-layoutcolumn-\d+($|\s)/g.test(controlClass);

            if (isColumn) {
                if (controls.length) {
                    var columnNode = controls[0].parentNode;
                    $(columnNode).unwrap();
                }
            }
        });
    }

    function onResize() {
        var elements = document.getElementsByClassName('bd-parallax-bg-effect');
        if (elements.length && window._smoothWheelInstance) {
            window._smoothWheelInstance();
        }

        [].forEach.call(elements, function (element) {
            var that = element,
                controlClass = that.getAttribute('data-control-selector').replace(/\./g, ''),
                controls = document.getElementsByClassName(controlClass),
                isSlider = /bd-slider-\d+($|\s)/g.test(controlClass) || getClassName(controls[0]).indexOf('bd-slider') !== -1,
                isColumn = /bd-layoutcolumn-\d+($|\s)/g.test(controlClass);

            var activeDoms = [], wrapperDiv;

            if (isSlider) {
                controls = findByClass(controls[0], 'bd-slide');
                if (controls.length) {
                    [].forEach.call(controls, function (slide) {
                        activeDoms = findTopLevelDoms(slide, 'bd-parallax-image-wrapper', controlClass);
                        if (!activeDoms.length) {
                            slide.style.backgroundImage = 'none';
                            slide.style.backgroundColor = 'transparent';
                            wrapperDiv = document.createElement('div');
                            wrapperDiv.className = 'bd-parallax-image-wrapper';
                            wrapperDiv.innerHTML = '<div class="bd-parallax-image"></div>';
                            slide.insertBefore(wrapperDiv, slide.firstChild);
                        }
                    });
                }
            }
            else if (isColumn) {
                if (controls.length) {
                    activeDoms = findTopLevelDoms(that, 'bd-parallax-image-wrapper', controlClass);
                    if (!activeDoms.length) {
                        var effectClone = that.cloneNode(true);
                        effectClone.innerHTML = '';

                        var columnNode = controls[0].parentNode;
                        $(columnNode).unwrap();
                        columnNode.insertBefore(effectClone, columnNode.firstChild);

                        wrapperDiv = document.createElement('div');
                        wrapperDiv.className = 'bd-parallax-image-wrapper';
                        wrapperDiv.innerHTML = '<div class="bd-parallax-image"></div>';

                        effectClone.insertBefore(wrapperDiv, effectClone.firstChild);
                    }
                }
            }
            else {
                if (controls.length) {
                    activeDoms = findTopLevelDoms(that, 'bd-parallax-image-wrapper', controlClass);
                    if (!activeDoms.length) {
                        wrapperDiv = document.createElement('div');
                        wrapperDiv.className = 'bd-parallax-image-wrapper';
                        wrapperDiv.innerHTML = '<div class="bd-parallax-image"></div>';
                        that.insertBefore(wrapperDiv, that.firstChild);
                    }
                }
            }

            if (controls.length) {
                [].forEach.call(controls, function (control) {
                    var parallaxWrapper = isColumn ? findByClass(control.parentElement, 'bd-parallax-image-wrapper')[0] : findTopLevelDoms(control, 'bd-parallax-image-wrapper', controlClass)[0];
                    if (parallaxWrapper) {
                        var parallaxImg = parallaxWrapper.getElementsByClassName('bd-parallax-image')[0],
                            controlOffset = $(that).offset().top,
                            controlHeight = that.clientHeight,
                            viewPortHeight = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);

                        if (control.style.backgroundImage === 'none') {
                            control.style.backgroundImage = '';
                        }

                        if (control.style.backgroundColor === 'transparent') {
                            control.style.backgroundColor = '';
                        }

                        var backgroundStyles = getComputedStyle(control);

                        if (backgroundStyles.position === 'static') {
                            control.style.position = 'relative';
                        }

                        if (backgroundStyles.backgroundImage !== 'none' && parallaxImg.style.backgroundImage !== backgroundStyles.backgroundImage) {
                            parallaxImg.style.backgroundImage = backgroundStyles.backgroundImage;
                        }

                        if (backgroundStyles.backgroundColor !== 'transparent' && parallaxImg.style.backgroundColor !== backgroundStyles.backgroundColor) {
                            parallaxImg.style.backgroundColor = backgroundStyles.backgroundColor;
                        }

                        control.style.backgroundImage = 'none';
                        control.style.backgroundColor = 'transparent';
                        parallaxImg.style.backgroundRepeat = backgroundStyles.backgroundRepeat;
                        parallaxImg.style.backgroundPosition = backgroundStyles.backgroundPosition;

                        if (isSlider) {
                            parallaxWrapper.style.setProperty('z-index', '-2', 'important');
                        }

                        if (isColumn) {
                            var containerStyles = getComputedStyle(parallaxWrapper);
                            parallaxImg.style.setProperty('min-width', containerStyles.width, 'important');
                            //parallaxImg.style.setProperty('min-height', Math.min(viewPortHeight, 3 * parseInt(containerStyles.height)) + 'px', 'important');
                        }

                        if (isSlider && control.className.indexOf('active') !== -1) {
                            that.setAttribute('data-sliderTop', $(parallaxImg).offset().top);
                            that.setAttribute('data-imageHeight', parallaxImg.clientHeight);
                        }

                        var positionDifference,
                            imageOffset = isSlider ? parseFloat(that.getAttribute('data-sliderTop')) : $(parallaxImg).offset().top,
                            controlBottom = controlOffset + controlHeight,
                            imageBottom = imageOffset + viewPortHeight,
                            visibleBottom = imageBottom > controlBottom ? controlBottom : imageBottom,
                            spaceArea = controlBottom - visibleBottom;

                        if (spaceArea > 0) {
                            var scaledSize = ((viewPortHeight + spaceArea) / viewPortHeight) * 100;
                            parallaxImg.style.height = scaledSize + 'vh';
                        }

                        var imageHeight = isSlider ? parseFloat(that.getAttribute('data-imageHeight')) : parallaxImg.clientHeight;
                        if (controlOffset >= imageHeight / 2) {
                            //var additionalSpace = controlOffset < viewPortHeight ? (viewPortHeight - controlOffset) / 2 : 0;
                            positionDifference = -imageHeight / 2 /*+ additionalSpace*/ + (getCompatibleScrollTop() + viewPortHeight - controlOffset) / 2;
                        }
                        else {
                            positionDifference = /*-controlOffset / 2*/ +(getCompatibleScrollTop() - controlOffset) / 2;
                        }
                        if (getCompatibleScrollTop() + viewPortHeight > controlOffset && getCompatibleScrollTop() < controlBottom) {
                            var transformProperty = getSupportedPropertyName(transform);
                            if (transformProperty) {
                                parallaxImg.style[transformProperty] = 'translate3d(0, ' + positionDifference + 'px, 0)';
                            }
                        }
                    }
                });
            }
        });
    }

    function onScroll() {
        [].forEach.call(document.getElementsByClassName('bd-parallax-bg-effect'), function (element) {
            var that = element,
                controlClass = that.getAttribute('data-control-selector').replace(/\./g, ''),
                controls = document.getElementsByClassName(controlClass),
                isSlider = /bd-slider-\d+($|\s)/g.test(controlClass) || getClassName(controls[0]).indexOf('bd-slider') !== -1,
                isColumn = /bd-layoutcolumn-\d+($|\s)/g.test(controlClass);

            if (isSlider) {
                controls = findByClass(controls[0], 'bd-slide');
            }

            if (controls.length) {
                [].forEach.call(controls, function (control) {
                    var viewPortHeight = Math.max(document.documentElement.clientHeight, window.innerHeight || 0),
                        controlOffset = $(that).offset().top,
                        controlHeight = that.clientHeight,
                        controlBottom = controlOffset + controlHeight;

                    if (getCompatibleScrollTop() + viewPortHeight > controlOffset && getCompatibleScrollTop() < controlBottom) {
                        var parallaxWrapper = isColumn ? findByClass(control.parentElement, 'bd-parallax-image-wrapper')[0] : findTopLevelDoms(control, 'bd-parallax-image-wrapper', controlClass)[0];
                        if (parallaxWrapper) {
                            var parallaxImg = parallaxWrapper.getElementsByClassName('bd-parallax-image')[0],
                                positionDifference;

                            if (isSlider && control.className.indexOf('active') !== -1) {
                                that.setAttribute('data-imageHeight', parallaxImg.clientHeight);
                            }

                            var imageHeight = isSlider ? parseFloat(that.getAttribute('data-imageHeight')) : parallaxImg.clientHeight;
                            if (controlOffset >= imageHeight / 2) {
                                //var additionalSpace = controlOffset < viewPortHeight ? (viewPortHeight - controlOffset) / 2 : 0;
                                positionDifference = -imageHeight / 2 /*+ additionalSpace*/ + (getCompatibleScrollTop() + viewPortHeight - controlOffset) / 2;
                            }
                            else {
                                positionDifference = /*-controlOffset / 2*/ +(getCompatibleScrollTop() - controlOffset) / 2;
                            }

                            var transformProperty = getSupportedPropertyName(transform);
                            if (transformProperty) {
                                parallaxImg.style[transformProperty] = 'translate3d(0, ' + positionDifference + 'px, 0)';
                            }
                        }
                    }
                });
            }
        });
    }

    function getClassName(element) {
        var className = element ? element.className : null;
        if (className) {
            if (typeof className === 'string') {
                return className;
            } else if (typeof className === 'object' && 'baseVal' in className) { // for SVG elements
                return className.baseVal;
            }
        }
        return '';
    }

    function getSupportedPropertyName(properties) {
        for (var i = 0; i < properties.length; i++) {
            if (typeof document.body.style[properties[i]] !== 'undefined') {
                return properties[i];
            }
        }
        return null;
    }

    function getCompatibleScrollTop() {
        if ("undefined" !== typeof window.scrollY) {
            return window.scrollY;
        }
        else {
            return document.documentElement.scrollTop;
        }
    }

    function findByClass(parentElement, searchClassName) {
        return [].slice.call(parentElement.getElementsByTagName('*')).filter(function (value) {
            var className = getClassName(value);
            return (' ' + className + ' ').indexOf(' ' + searchClassName + ' ') !== -1;
        });
    }

    function findTopLevelDoms(element, searchClassName, controlClassName) {
        var isEffectDom = function (domElement) {
                return getClassName(domElement).indexOf('bd-parallax-bg-effect') !== -1 && domElement.getAttribute('data-control-selector') === '.' + controlClassName;
            },
            findDom = function (domElement) {
                return [].slice.call(domElement.getElementsByClassName(searchClassName)).filter(function (value) {
                    return value.parentNode === domElement;
                });
            };

        var foundDom = findDom(element);
        if (foundDom.length === 0) {
            while (!isEffectDom(element) && element) {
                element = element.parentElement;
            }
        }

        return foundDom.length ? foundDom : findDom(element);
    }
})(jQuery);
})(window._$, window._$);
window.ProductOverview_Class = "bd-productoverview";
(function (jQuery, $) {
jQuery(function($) {
    'use strict';
    function makeCloudZoom1() {
        if ($('.bd-productimage-6 a').length > 0) {
            $('.bd-productimage-6 a').attr('id', 'cloud-zoom-effect-1').addClass('cloud-zoom');
            $('.bd-productimage-6 a').attr('rel', "position:'right', adjustX:0, adjustY:0, tint:'#ffffff', softFocus:1, smoothMove:1, tintOpacity:0.5");

            if ('undefined' !== typeof window.ProductOverview_Class && 'undefined' !== typeof window.ImageThumbnails_Class) {
                var parent = $('.bd-productimage-6')
                            .closest('[class*=" ' + window.ProductOverview_Class + '"], [class^="' + window.ProductOverview_Class + '"]'),
                    thumbnails = $('[class*=" ' + window.ImageThumbnails_Class + '"], [class^="' + window.ImageThumbnails_Class + '"]', parent);

                if (thumbnails.length > 0) {
                    $('a', thumbnails).each(function () {
                        var thumbnail = $(this),
                            rel = thumbnail.attr('rel') === undefined ? '' : thumbnail.attr('rel'),
                            relAttr = (rel.indexOf("cloud-zoom-effect-1") > 0 ? rel : (rel === '' ? '' : rel + ',') + "useZoom: 'cloud-zoom-effect-1'");
                        thumbnail.attr('rel', relAttr);
                        if (!thumbnail.hasClass('with-lightbox')) {
                            thumbnail.addClass('cloud-zoom-gallery');
                        }
                    });
                }
            }

            var parent = $(".bd-productimage-6").parents().filter(function (key, value) {
                return parseInt($(value).css('z-index'), 10).toString() !== 'NaN';
            });

            var minZIndex = 100;
            var zIndex = parent.length > 0 ? parseInt($(parent[0]).css('z-index'), 10) + 1 : 1;
            zIndex = zIndex < minZIndex ? minZIndex : zIndex;

            $('<style type="text/css"> .bd-productimage-6 .mousetrap { z-index: ' + zIndex + '!important;} </style>').appendTo("head");

            $('#cloud-zoom-effect-1, .cloud-zoom-gallery').CloudZoom();
        }
    }
    makeCloudZoom1();
    var resizeTimeout;
    $(window).resize(function(){
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(makeCloudZoom1, 25);
    });
});
})(window._$, window._$);
(function (jQuery, $) {
window.ImageThumbnails_Class = 'bd-imagethumbnails';
jQuery(function () {
    'use strict';
    /* 'active' must be always specified, otherwise slider would not be visible */
    function makeImageThumbnailsCarousel1()
    {
        $('.bd-imagethumbnails-1.carousel.slide').each(function () {
            var slider = jQuery(this);
            if (!slider || !slider.length) {
                return;
            }

            slider.data('resize', function () {
                jQuery('.carousel-inner', slider).equalImageHeight();
            });

            slider.data('resize')();

            jQuery('.bd-left-button .bd-carousel', slider)
                .attr('href', '#')
                .click(function () {
                    slider.carousel('prev');
                    return false;
                });

            jQuery('.bd-right-button .bd-carousel', slider)
                .attr('href', '#')
                .click(function () {
                    slider.carousel('next');
                    return false;
                });
        });
    }
    makeImageThumbnailsCarousel1();
    var resizeTimeoutThumbnails;
    $(window).resize(function(){
        clearTimeout(resizeTimeoutThumbnails);
        resizeTimeoutThumbnails = setTimeout(makeImageThumbnailsCarousel1, 25);
    });
});
})(window._$, window._$);
(function (jQuery, $) {
jQuery(function ($) {
    'use strict';

    window.tabCollapseResize = function () {
        $('.tabbable').each(function () {
            var tabbable = $(this);
            // var tabMenu = tabbable.children('.nav-tabs');
            var tabMenu = tabbable.find('.nav-tabs');
            var tabs = tabMenu.children('li');
            var tabContent = tabbable.find('.tab-content');
            var panels = tabContent.find('.tab-pane');

            if (!tabs.filter('.active').length) {
                tabs.first().addClass('active');
                panels.removeClass('active').first().addClass('active');
            }

            if (!tabbable.data('responsive')) {
                if (tabContent.children('.accordion').length) {
                    tabContent.children('.accordion').children().first().unwrap();
                }
                tabContent.find('.accordion-item').remove();
                panels.each(function () {
                    var wrapper = $(this).children('.accordion-wrap');
                    if (wrapper.children().length) {
                        wrapper.children().first().unwrap();
                    } else {
                        wrapper.remove();
                    }
                });
                return;
            }

            var cls = tabMenu.parent().siblings('.accordion').children('.accordion-content').attr('class');
            var wrapper = tabContent.find('.accordion-wrap');
            if (wrapper.length) {
                tabContent.find('.accordion-wrap').toggleClass(cls, tabContent.find('.accordion-item:visible').length > 0);
                return;
            }

            var accordion = tabbable.find('.accordion');

            accordion.show();
            var accordionTpl = accordion.clone();
            accordion.hide();

            var itemTpl = accordion.find('.accordion-item').clone();
            var contentTpl = accordion.find('.accordion-content').clone();
            accordionTpl.empty();

            tabs.each(function () {
                var tab = $(this);
                var tablink = tab.find('[data-toggle="tab"]');
                var currentId = tablink.attr('href');
                var panel = panels.filter(currentId);

                var collapseLink = $('<a></a>');
                collapseLink.html(tablink.html());
                collapseLink.attr('data-toggle', 'collapse');
                collapseLink.attr('data-target', currentId);

                panel.before(itemTpl.clone().append(collapseLink));
                panel.wrapInner(contentTpl.clone().addClass('accordion-wrap').toggleClass(cls, collapseLink.is(':visible')));

                panel.addClass('collapse');
                if (panel.is('.active')) {
                    panel.addClass('in');
                    collapseLink.addClass('active');
                }

                /* Collapse */

                panel.on('show.bs.collapse', function () {
                    var actives = panels.filter('.in');
                    panels.filter('.collapsing:not(.active)').addClass('bd-collapsing');
                    if (actives && actives.length) {
                        var activesData = actives.data('bs.collapse');
                        if (!activesData || !activesData.transitioning) {
                            actives.collapse('hide');
                            if (!activesData) actives.data('bs.collapse', null);
                        }
                    }
                    panel.css('display', 'block');

                    collapseLink.addClass('active');
                });

                panel.on('shown.bs.collapse', function () {
                    tab.addClass('active');
                    panel.addClass('active');

                    panel.css('display', '');
                    panel.filter('.bd-collapsing').removeClass('bd-collapsing').collapse('hide');
                });

                panel.on('hide.bs.collapse', function () {
                    collapseLink.removeClass('active');
                });

                panel.on('hidden.bs.collapse', function () {
                    tab.removeClass('active');
                    panel.removeClass('active');
                    panel.css('height', '');
                });

                /* Tabs */

                tablink.on('show.bs.tab', function () {
                    panels.removeClass('in');
                    tabContent.find('.accordion > .accordion-item > a').removeClass('active');

                    panel.css('height', '');
                    panel.addClass('in');
                    collapseLink.addClass('active');
                });
            });

            tabContent.wrapInner(accordionTpl);
        });
    };

    $(window).on('resize', function (e, param) {
        window.tabCollapseResize();
    });

    window.tabCollapseResize();
});
})(window._$, window._$);
(function (jQuery, $) {

/*!
 * jQuery Cookie Plugin v1.4.0
 * https://github.com/carhartl/jquery-cookie
 *
 * Copyright 2013 Klaus Hartl
 * Released under the MIT license
 */
(function (factory) {
    'use strict';
    if (typeof define === 'function' && define.amd) {
        define(['jquery'], factory);
    } else {
        factory(jQuery);
    }
}(function ($) {
    'use strict';
    var pluses = /\+/g;

    function encode(s) {
        return config.raw ? s : encodeURIComponent(s);
    }

    function decode(s) {
        return config.raw ? s : decodeURIComponent(s);
    }

    function stringifyCookieValue(value) {
        return encode(config.json ? JSON.stringify(value) : String(value));
    }

    function parseCookieValue(s) {
        if (s.indexOf('"') === 0) {
            s = s.slice(1, -1).replace(/\\"/g, '"').replace(/\\\\/g, '\\');
        }

        try {
            s = decodeURIComponent(s.replace(pluses, ' '));
        } catch(e) {
            return;
        }

        try {
            return config.json ? JSON.parse(s) : s;
        } catch(e) {}
    }

    function read(s, converter) {
        var value = config.raw ? s : parseCookieValue(s);
        return $.isFunction(converter) ? converter(value) : value;
    }

    var config = $.cookie = function (key, value, options) {

        // Write
        if (value !== undefined && !$.isFunction(value)) {
            options = $.extend({}, config.defaults, options);

            if (typeof options.expires === 'number') {
                var days = options.expires, t = options.expires = new Date();
                t.setDate(t.getDate() + days);
            }

            return (document.cookie = [
                encode(key), '=', stringifyCookieValue(value),
                options.expires ? '; expires=' + options.expires.toUTCString() : '',
                options.path    ? '; path=' + options.path : '',
                options.domain  ? '; domain=' + options.domain : '',
                options.secure  ? '; secure' : ''
            ].join(''));
        }

        var result = key ? undefined : {};
        var cookies = document.cookie ? document.cookie.split('; ') : [];

        for (var i = 0, l = cookies.length; i < l; i++) {
            var parts = cookies[i].split('=');
            var name = decode(parts.shift());
            var cookie = parts.join('=');

            if (key && key === name) {
                result = read(cookie, value);
                break;
            }

            if (!key && (cookie = read(cookie)) !== undefined) {
                result[name] = cookie;
            }
        }

        return result;
    };

    config.defaults = {};

    $.removeCookie = function (key, options) {
        if ($.cookie(key) !== undefined) {
            $.cookie(key, '', $.extend({}, options, { expires: -1 }));
            return true;
        }
        return false;
    };

}));

jQuery(function($) {
    'use strict';

    var activeLayoutType = $.cookie('layoutType') || 'grid',
        activeLayoutTypeSelector = $.cookie('layoutSelector') || '.separated-item-4.grid';

    var layoutTypes = [];
    
        layoutTypes.push({
            name:'bd-griditemgrid',
            iconUrl: '',
            iconDataId: '2470',
            iconClassNames: 'bd-icon-65 bd-no-margins'
        });
        layoutTypes.push({
            name:'bd-griditemlist',
            iconUrl: '',
            iconDataId: '2485',
            iconClassNames: 'bd-icon-66'
        });
    if (typeof window.buildTypeSelector === 'function') {
        window.buildTypeSelector(layoutTypes, $('.bd-productsgridbar-35'));
    }

    
        $(document).on('click', '.bd-products i[data-layout-name="bd-griditemgrid"]', function (e) {
            if (activeLayoutType !== 'grid') {
                var grid = $('.bd-grid-45');
                grid.find('.separated-item-4.grid').css('display', 'block');
                grid.find(activeLayoutTypeSelector).css('display', 'none');
                activeLayoutType = 'grid';
                activeLayoutTypeSelector = '.separated-item-4.grid';

                $.cookie('layoutType', activeLayoutType, { path: '/' });
                $.cookie('layoutSelector', activeLayoutTypeSelector, { path: '/' });
            }
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
        $(document).on('click', '.bd-products i[data-layout-name="bd-griditemlist"]', function (e) {
            if (activeLayoutType !== 'list') {
                var grid = $('.bd-grid-45');
                grid.find('.separated-item-5.list').css('display', 'block');
                grid.find(activeLayoutTypeSelector).css('display', 'none');
                activeLayoutType = 'list';
                activeLayoutTypeSelector = '.separated-item-5.list';

                $.cookie('layoutType', activeLayoutType, { path: '/' });
                $.cookie('layoutSelector', activeLayoutTypeSelector, { path: '/' });
            }
            e.preventDefault();
            e.stopPropagation();
            return false;
        });

});
})(window._$, window._$);
(function (jQuery, $) {
window.buildTypeSelector = function(layouts) {
    layouts.map(function(layout){
        var a = document.createElement('a'),
            i = document.createElement('i');
        jQuery(i).addClass(layout.iconClassNames).addClass('bd-icon data-control-id-' + layout.iconDataId);
        jQuery(i).attr('data-layout-name', layout.name);
        jQuery(a).attr('href', '##').append(i);
        jQuery(a).each(function() { this.style.textDecoration = 'none'; });
        jQuery('.bd-typeselector-1').append(a);
    });
}
})(window._$, window._$);
(function (jQuery, $) {
jQuery(function ($) {
    'use strict';

    function getFloat(value){
        return parseFloat(value.replace('px', ''))  ;
    }

    $('.bd-productsslider-1').each(function () {
        var slider = $(this).find('.carousel.slide');
        slider.carousel({
            interval: 3000,
            pause: "",
            wrap: false
        });

        var leftButton = $('.bd-left-button', slider);
        var rightButton = $('.bd-right-button', slider);

        
            var blockSelector = '.bd-block',
                blockHeaderSelector = '.bd-block .bd-blockheader';
            if ($(blockSelector, this).length > 0 && $(blockHeaderSelector, this).length > 0)
            {
                var block = $(blockSelector, this),
                    blockHeader = block.find('*').filter(blockHeaderSelector),
                    blockHeaderTitle = blockHeader.children('h4');

                blockHeader.css('min-height', '35px');
                blockHeader.css('position', 'relative');

                var navigationWrapper = $('<div class="bd-top-navigation-wrapper"></div>');
                blockHeaderTitle.addClass('bd-top-navigation');
                blockHeaderTitle.append(navigationWrapper);

                leftButton.appendTo(navigationWrapper);
                rightButton.appendTo(navigationWrapper);
            }

        leftButton.find('.bd-carousel-2').click(function() {
            slider.carousel('prev');
            return false;
        }).attr('href', '#');

        rightButton.find('.bd-carousel-2').click(function() {
            slider.carousel('next');
            return false;
        }).attr('href', '#');

        
            slider.carousel('pause');
    });
});
})(window._$, window._$);
(function (jQuery, $) {
jQuery(function ($) {
    'use strict';

    var resizeHandler = function () {
        $('.carousel.adjust-slides').each(function () {
            var inner = $(this).find('.carousel-inner'),
                items = inner.children('.item').addClass('clearfix').css('width', '100%');
            var maxH = 0;
            if (items.length > 1) {
                var windowScrollTop = $(window).scrollTop();
                items.css('min-height', '0').each(function(){
                    maxH = Math.max(maxH, parseFloat(getComputedStyle(this).height));
                }).css('min-height', maxH);
                inner.css('height', maxH);
                if ($(window).scrollTop() !== windowScrollTop){
                    $(window).scrollTop(windowScrollTop);
                }
            }
        });
        setTimeout(resizeHandler, 100);
    };
    resizeHandler();
});

(function ($) {
    'use strict';

    $.fn.equalImageHeight = function () {
        return this.each(function() {
            var maxHeight = 0;

            $(this).children('a').children('img').each(function(index, child) {
                var h = $(child).height();
                maxHeight = h > maxHeight ? h : maxHeight;
                $(child).css('height', ''); // clears previous value
            });

            $(this).children('a').each(function(index, child) {
                $(child).height(maxHeight);
            });

        });
    };
})(jQuery);
})(window._$, window._$);
(function (jQuery, $) {
window.initSlider = function initSlider(selector, opt) {
    'use strict';

    opt = opt || {};

    jQuery(selector + '.carousel.slide .carousel-inner > .item:first-child').addClass('active');

    function setSliderInterval() {
        jQuery(selector + '.carousel.slide').carousel({
            interval: opt.carouselInterval,
            pause: opt.carouselPause,
            wrap: opt.carouselWrap
        });

        if (!opt.carouselRideOnStart) {
            jQuery(selector + '.carousel.slide').carousel('pause');
        }
    }

    /* 'active' must be always specified, otherwise slider would not be visible */
    var leftNav = selector + '.carousel.slide .' + opt.leftButtonSelector + ' a' + opt.navigatorSelector,
        rightNav = selector + '.carousel.slide .' + opt.rightButtonSelector + ' a' + opt.navigatorSelector;

    jQuery(leftNav).attr('href', '#');
    jQuery(leftNav).click(function() {
        setSliderInterval();
        jQuery(selector + '.carousel.slide').carousel('prev');
        return false;
    });

    jQuery(rightNav).attr('href', '#');
    jQuery(rightNav).click(function() {
        setSliderInterval();
        jQuery(selector + '.carousel.slide').carousel('next');
        return false;
    });

    jQuery(selector + '.carousel.slide').on('slid.bs.carousel', function () {
        var indicators = jQuery(opt.indicatorsSelector, this);
        indicators.find('.active').removeClass('active');

        var activeSlide = jQuery(this).find('.item.active'),
            activeIndex = activeSlide.parent().children().index(activeSlide),
            activeItem = indicators.children()[activeIndex];

        jQuery(activeItem).children('a').addClass('active');
    });

    setSliderInterval();
};
})(window._$, window._$);
(function (jQuery, $) {
jQuery(function ($) {
    'use strict';

    $(document)
        .on('click.themler', '.bd-overSlide[data-url] a, .bd-slide[data-url] a', function (e) {
            e.stopPropagation();
        })
        .on('click.themler', '.bd-overSlide[data-url], .bd-slide[data-url]', function () {
            var elem = $(this),
                url = elem.data('url'),
                target = elem.data('target');
            window.open(url, target);
        });
});

})(window._$, window._$);
(function (jQuery, $) {
jQuery(function ($) {
    'use strict';

    $('[data-smooth-scroll]').on('click', 'a[href^="#"]:not([data-toggle="collapse"])', function (e) {
        var animationTime = parseInt($(e.delegateTarget).data('animationTime'), 10) || 0;
        var target = this.hash;
        var link = $(this);
        e.preventDefault();

        $('body').data('scroll-animating', true);
        var targetElement = $(target || 'body');

        link.trigger($.Event('theme.smooth-scroll.before'));

        if (!targetElement || !targetElement.length)
            return;

        $('html, body').animate({
            scrollTop: targetElement.offset().top
        }, animationTime, function() {
            $('body').data('scroll-animating', false);
            window.location.hash = target;
            if (targetElement.is(':not(body)') && $('body').data('bs.scrollspy')) {
                link.parent('li').siblings('li').children('a').removeClass('active');
                link.addClass('active');
            }
            link.trigger($.Event('theme.smooth-scroll.after'));
        });
    });
});
})(window._$, window._$);
(function (jQuery, $) {
function SmoothWheel() {
    'use strict';

    this.options = {
        animtime: 500,
        stepsize: 150,
        pulseAlgorithm: false,
        pulseScale: 6,
        keyboardsupport: true,
        arrowscroll: 50,
        useOnWebKit: true,
        useOnMozilla: true,
        useOnIE: true
    };

    var that = this;

    /*global Date */
    function ssc_init() {

        if (!document.body) return;
        var e = document.body;
        var t = document.documentElement;
        var n = window.innerHeight;
        var r = e.scrollHeight;
        ssc_root = document.compatMode.indexOf("CSS") >= 0 ? t : e;
        ssc_activeElement = e;
        ssc_initdone = true;
        if (top !== self) {
            ssc_frame = true;
        } else if (r > n && (e.offsetHeight <= n || t.offsetHeight <= n)) {
            ssc_root.style.height = "auto";
            if (ssc_root.offsetHeight <= n) {
                var i = document.createElement("div");
                i.style.clear = "both";
                e.appendChild(i);
            }
        }
        if (!ssc_fixedback) {
            e.style.backgroundAttachment = "scroll";
            t.style.backgroundAttachment = "scroll";
        }
        if (that.options.keyboardsupport) {
            ssc_addEvent("keydown", ssc_keydown);
        }
    }

    function ssc_scrollArray(e, t, n, r) {
        r || (r = 1e3);
        ssc_directionCheck(t, n);
        ssc_que.push({
            x: t,
            y: n,
            lastX: t < 0 ? 0.99 : -0.99,
            lastY: n < 0 ? 0.99 : -0.99,
            start: +(new Date())
        });
        if (ssc_pending) {
            return;
        }
        var i = function() {
            var s = +(new Date());
            var o = 0;
            var u = 0;
            for (var a = 0; a < ssc_que.length; a++) {
                var f = ssc_que[a];
                var l = s - f.start;
                var c = l >= that.options.animtime;
                var h = c ? 1 : l / that.options.animtime;
                if (that.options.pulseAlgorithm) {
                    h = ssc_pulse(h);
                }
                var p = f.x * h - f.lastX >> 0;
                var d = f.y * h - f.lastY >> 0;
                o += p;
                u += d;
                f.lastX += p;
                f.lastY += d;
                if (c) {
                    ssc_que.splice(a, 1);
                    a--;
                }
            }
            if (t) {
                var v = e.scrollLeft;
                e.scrollLeft += o;
                if (o && e.scrollLeft === v) {
                    t = 0;
                }
            }
            if (n) {
                var m = e.scrollTop;
                e.scrollTop += u;
                if (u && e.scrollTop === m) {
                    n = 0;
                }
            }
            if (!t && !n) {
                ssc_que = [];
            }
            if (ssc_que.length) {
                setTimeout(i, r / ssc_framerate + 1);
            } else {
                ssc_pending = false;
            }
        };
        setTimeout(i, 0);
        ssc_pending = true;
    }

    function ssc_wheel(e) {
        if (!ssc_initdone) {
            ssc_init();
        }
        var t = e.target;
        var n = ssc_overflowingAncestor(t);
        if (!n || e.defaultPrevented || ssc_isNodeName(ssc_activeElement, "embed") || ssc_isNodeName(t, "embed") && /\.pdf/i.test(t.src)) {
            return true;
        }
        var r = e.wheelDeltaX || e.deltaX || 0;
        var i = e.wheelDeltaY || e.deltaY || 0;
        if (n.nodeName === 'BODY' && (currentBrowser === 'firefox' || currentBrowser === "msie" || currentBrowser === "netscape")) {
            n = document.documentElement;
            r = -r;
            i = -i;
            if (currentBrowser === 'firefox') {
                r *= 40;
                i *= 40;
            }
        }

        if (!r && !i) {
            i = e.wheelDelta || 0;
        }
        if (Math.abs(r) > 1.2) {
            r *= that.options.stepsize / 120;
        }
        if (Math.abs(i) > 1.2) {
            i *= that.options.stepsize / 120;
        }
        ssc_scrollArray(n, -r, -i);
    }

    function ssc_keydown(e) {
        var t = e.target;
        var n = e.ctrlKey || e.altKey || e.metaKey;
        if (/input|textarea|embed/i.test(t.nodeName) || t.isContentEditable || e.defaultPrevented || n) {
            return true;
        }
        if (ssc_isNodeName(t, "button") && e.keyCode === ssc_key.spacebar) {
            return true;
        }
        var r, i = 0,
            s = 0;
        var o = ssc_overflowingAncestor(ssc_activeElement);
        var u = o.clientHeight;
        if (o === document.body) {
            u = window.innerHeight;
        }
        switch (e.keyCode) {
            case ssc_key.up:
                s = -that.options.arrowscroll;
                break;
            case ssc_key.down:
                s = that.options.arrowscroll;
                break;
            case ssc_key.spacebar:
                r = e.shiftKey ? 1 : -1;
                s = -r * u * 0.9;
                break;
            case ssc_key.pageup:
                s = -u * 0.9;
                break;
            case ssc_key.pagedown:
                s = u * 0.9;
                break;
            case ssc_key.home:
                s = -o.scrollTop;
                break;
            case ssc_key.end:
                var a = o.scrollHeight - o.scrollTop - u;
                s = a > 0 ? a + 10 : 0;
                break;
            case ssc_key.left:
                i = -that.options.arrowscroll;
                break;
            case ssc_key.right:
                i = that.options.arrowscroll;
                break;
            default:
                return true;
        }
        ssc_scrollArray(o, i, s);
        e.preventDefault();
    }

    function ssc_mousedown(e) {
        ssc_activeElement = e.target;
    }

    function ssc_setCache(e, t) {
        for (var n = e.length; n--;) ssc_cache[ssc_uniqueID(e[n])] = t;
        return t;
    }

    function ssc_overflowingAncestor(e) {
        var t = [];
        var n = ssc_root.scrollHeight;
        do {
            var r = ssc_cache[ssc_uniqueID(e)];
            if (r) {
                return ssc_setCache(t, r);
            }
            t.push(e);
            if (n === e.scrollHeight) {
                if (!ssc_frame || ssc_root.clientHeight + 10 < n) {
                    return ssc_setCache(t, currentScrollingElement);
                }
            } else if (e.clientHeight + 10 < e.scrollHeight) {
                overflow = getComputedStyle(e, "").getPropertyValue("overflow");
                if (overflow === "scroll" || overflow === "auto") {
                    return ssc_setCache(t, e);
                }
            }
        } while ((e = e.parentNode));
    }

    function ssc_addEvent(e, t, n) {
        window.addEventListener(e, t, n || false);
    }

    function ssc_removeEvent(e, t, n) {
        window.removeEventListener(e, t, n || false);
    }

    function ssc_isNodeName(e, t) {
        return e.nodeName.toLowerCase() === t.toLowerCase();
    }

    function ssc_directionCheck(e, t) {
        e = e > 0 ? 1 : -1;
        t = t > 0 ? 1 : -1;
        if (ssc_direction.x !== e || ssc_direction.y !== t) {
            ssc_direction.x = e;
            ssc_direction.y = t;
            ssc_que = [];
        }
    }

    function ssc_pulse_(e) {
        var t, n, r;
        e = e * that.options.pulseScale;
        if (e < 1) {
            t = e - (1 - Math.exp(-e));
        } else {
            n = Math.exp(-1);
            e -= 1;
            r = 1 - Math.exp(-e);
            t = n + r * (1 - n);
        }
        return t * ssc_pulseNormalize;
    }

    function ssc_pulse(e) {
        if (e >= 1) return 1;
        if (e <= 0) return 0;
        if (ssc_pulseNormalize === 1) {
            ssc_pulseNormalize /= ssc_pulse_(1);
        }
        return ssc_pulse_(e);
    }
    var overflow = '';
    var ssc_framerate = 150;
    var ssc_pulseNormalize = 1;
    var ssc_frame = false;
    var ssc_direction = {
        x: 0,
        y: 0
    };
    var ssc_initdone = false;
    var ssc_fixedback = true;
    var ssc_root = document.documentElement;
    var ssc_activeElement;
    var ssc_key = {
        left: 37,
        up: 38,
        right: 39,
        down: 40,
        spacebar: 32,
        pageup: 33,
        pagedown: 34,
        end: 35,
        home: 36
    };
    var ssc_que = [];
    var ssc_pending = false;
    var ssc_cache = {};
    var currentBrowser = '';
    var versionBrowser = '';
    var currentScrollingElement = document.body;

    setInterval(function() {
        ssc_cache = {};
    }, 10 * 1e3);

    var ssc_uniqueID = function() {
        var e = 0;
        return function(t) {
            return t.ssc_uniqueID || (t.ssc_uniqueID = e++);
        };
    }();

    jQuery(document).ready(function() {
        function t() {
            var ua = navigator.userAgent, tem,
                M = ua.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
            if(/trident/i.test(M[1])){
                tem =  /\brv[ :]+(\d+)/g.exec(ua) || [];
                return 'IE '+(tem[1] || '');
            }
            if(M[1] === 'Chrome'){
                tem = ua.match(/\b(OPR|Edge)\/(\d+)/);
                if(tem != null) return tem.slice(1).join(' ').replace('OPR', 'Opera');
            }
            M = M[2]? [M[1], M[2]]: [navigator.appName, navigator.appVersion, '-?'];
            if((tem = ua.match(/version\/(\d+)/i))!= null) M.splice(1, 1, tem[1]);
            return M;
        }

        currentBrowser = t()[0].toLowerCase();
        versionBrowser = t()[1];

        var webKit = 'safari;chrome';
        var IE = 'netscape;msie';
        var mozilla = 'firefox';

        var browserName = [
            (that.options.useOnMozilla ? mozilla : ''),
            (that.options.useOnWebKit ? webKit : ''),
            (that.options.useOnIE ? IE : '')
        ].join(';');

        var neededBrowser = browserName.indexOf(currentBrowser) !== -1;

        if (neededBrowser) {
            ssc_addEvent("mousedown", ssc_mousedown);
            if (currentBrowser === 'firefox' || currentBrowser === "msie" || currentBrowser === "netscape") {
                ssc_addEvent("wheel", ssc_wheel);
            } else {
                if (currentBrowser === 'chrome' && parseInt(versionBrowser) >= 61) {
                    // Use scrollingElement for smooth scrolling using keyboard
                    currentScrollingElement  = document.scrollingElement;
                    // Here is used native chrome smooth scrolling for wheel.
                } else {
                    ssc_addEvent("mousewheel", ssc_wheel);
                }
            }
            ssc_addEvent("load", ssc_init);
        }
    });

    this.update = function update(newOptions) {
        if (!that.options.keyboardsupport) {
            ssc_removeEvent("keydown", ssc_keydown);
        }
        $.extend(this.options, newOptions);
    };

}

(function () {
    'use strict';

    var _instance;

    window._smoothWheelInstance = function () {
        if (!_instance) {
            _instance = new SmoothWheel();
        }

        return _instance;
    };
})();
})(window._$, window._$);
(function (jQuery, $) {
(function ($) {
    'use strict';

    var timeout;
    $(window).on('resize', function (e, param) {
        clearTimeout(timeout);
        if (param && param.force) {
            stretchToBottom();
        } else {
            timeout = setTimeout(stretchToBottom, 25);
        }
    });

    $(stretchToBottom);

    function stretchToBottom() {
        var html = document.documentElement,
            prevHeight = html.style.height,
            body = $('body');

        html.style.height = '100%';

        $('.bd-stretch-to-bottom').each(function() {
            var c = $(this),
                bh,
                mh = 0,
                parent;

            var target = c.find(c.data('controlSelector'))
                .add(c.find(c.data('controlSelector') + ' .bd-stretch-inner').first());

            if (target.length === 0) {
                return;
            }

            target.removeAttr('style');
            bh = body.height();

            var prevMargin = 0;
            body.children().each(function() {
                var $node = $(this);
                if ($node.is(':visible') && $node.css('float') !== 'left' && $node.css('float') !== 'right' &&
                    $node.css('position') !== 'absolute' && $node.css('position') !== 'fixed') {

                    if (!prevMargin) {
                        mh += parseFloat($node.css('margin-top'));
                    } else {
                        mh += Math.max(parseFloat($node.css('margin-bottom')), prevMargin);
                    }

                    mh += $node.outerHeight();

                    prevMargin = parseFloat($node.css('margin-bottom'));

                    if ($.contains(this, target[0]) || this === target[0]) {
                        parent = $node;
                    }
                }
            });

            if (mh < bh && parent) {
                var r = bh - mh;
                target.css('min-height', (target.outerHeight(true) + r) + 'px');
            }
        });

        html.style.height = prevHeight;
    }

})(jQuery);
})(window._$, window._$);
(function (jQuery, $) {
(function ($) {
    'use strict';

    if (!window.isThemlerIframe || !window.isThemlerIframe()) {
        $(document).ready(function () {
            var controls = $('[data-autoplay=true]');
            $(controls).each(function (index, item) {
                if (item.src)
                    item.src = item.src + (item.src.indexOf("?") === -1 ? "?" : "&") + "autoplay=1";
            });
        });
    }
})(jQuery);
})(window._$, window._$);
(function (jQuery, $) {

jQuery(function ($) {
    'use strict';
    // hide #back-top first
    $(".bd-backtotop-1").hide();
    // fade in #back-top
    $(function () {
        $(window).scroll(function () {
            if ($(this).scrollTop() > 100) {
                $('.bd-backtotop-1').fadeIn().css('display', 'block');
            } else {
                $('.bd-backtotop-1').fadeOut();
            }
        });
    });
});

})(window._$, window._$);
(function (jQuery, $) {



jQuery(function () {
    'use strict';
    new window.ThemeLightbox('.bd-postcontent-1 img:not(.no-lightbox)').init();
});
})(window._$, window._$);