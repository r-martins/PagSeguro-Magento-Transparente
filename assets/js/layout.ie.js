jQuery(function ($) {
    'use strict';

    var rows = $('.bd-flex-horizontal.bd-flex-wide');

    fixContentLayoutHeight(rows);

    var t = null;
    $(window).on('resize', function () {
        clearTimeout(t);
        t = setTimeout(fixContentLayoutHeight.bind(null, rows), 100);
    });

    function fixContentLayoutHeight(rows) {
        var loadPromises = [];

        function off() {
            /* jshint validthis: true */
            this.onload = null;
            this.onerror = null;
            this.onabort = null;
        }

        function on(dfd) {
            /* jshint validthis: true */
            off.bind(this)();
            dfd.resolve();
        }

        rows.each(function () {
            $(this).find('img').each(function () {
                if (this.complete) return;
                var deferred = $.Deferred();
                this.onload = on.bind(this, deferred);
                this.onerror = on.bind(this, deferred);
                this.onabort = on.bind(this, deferred);
                loadPromises.push(deferred.promise());
            });

            $.when.apply($, loadPromises).done(function () {
                var columns = $(this).children('[class*="-column "], [class$="-column"]');
                var contentColumn = $(this).children('.bd-flex-vertical.bd-flex-wide');
                var contentArea = contentColumn.children('.bd-flex-wide');

                contentArea.css('min-height', '');
                columns.children().css('min-height', '').each(function () {
                    var sidebar = $(this);
                    ['padding-top', 'padding-right', 'padding-bottom', 'padding-left'].forEach(function (padding) {
                        if (!parseInt(sidebar.css('padding'), 10)) {
                            sidebar.css(padding, '0.01px');
                        }
                    });
                });

                var columnHeight = columns.height();
                columns.children().css('min-height', columnHeight + 'px');

                var siblingsHeight = 0;
                contentArea.siblings().each(function () {
                    siblingsHeight += $(this).outerHeight(true);
                });

                if (contentColumn.height() > siblingsHeight + contentArea.outerHeight(true)) {
                    contentArea.css('min-height', (contentColumn.height() - siblingsHeight) + 'px');
                }
            }.bind(this));
        });
    }
});