/* exported __app */
/* global BD */

// 

var __app = {};

(function () {
    'use strict';

    var themeName = window.location.pathname.replace(/.*?([^\/]+)\/+__editor__\/+app.html/, '$1');

    __app = new BD.Application(themeName, {
        version: { html: '1.0.0' },
        env: {
            maxRequestSize: 20971520
        },
        path: {
            content: '__content__',
            editor:  '__editor__',
            project: 'data/project/project.json',
            cache:   'data/project/cache.json',
            hashes:  'data/project/hashes.json',
            model:   'data/project/model.json',
            editorConfig:  'data/project/editor.json',
            preview: '__preview__',
            runtime: 'runtime',
            assets: {
                images:  'assets/images',
                fonts:   'assets/css',
                css:     'assets/css',
                js:      'assets/js'
            },
            manifest: '__editor__/themler.manifest'
        },
        url: {
            admin:   'app.html',
            preview: 'runtime/__preview__/index.html',
            backend: 'app/backend/controller.php'
        },
        themes: {}
    });

})();

//