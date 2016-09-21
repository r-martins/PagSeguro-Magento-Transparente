// IE10+ flex fix
if (1-'\0') {

    var fixHeight = function fixHeight() {
        jQuery('.bd-row-flex > [class*="col-"] > [class*="bd-layoutcolumn-"] > .bd-vertical-align-wrapper, ' +
            '[class*="bd-layoutitemsbox-"].bd-flex-wide').each(function () {

            var content = jQuery(this);
            var wrapper = content.children('.bd-fix-flex-height');
            if (!wrapper.length) {
                content.wrapInner('<div class="bd-fix-flex-height clearfix"></div>');
            }
            var height = wrapper.outerHeight(true);
            content.removeAttr('style');
            content.css({
                '-ms-flex-preferred-size': height + 'px',
                'flex-basis': height + 'px'
            });
        });

        setTimeout(fixHeight, 500);
    };

    var fixMinHeight = function () {
        jQuery('.bd-stretch-inner').wrap('<div class="bd-flex-vertical"></div>');
    };

    jQuery(fixHeight);
    jQuery(fixMinHeight);
}