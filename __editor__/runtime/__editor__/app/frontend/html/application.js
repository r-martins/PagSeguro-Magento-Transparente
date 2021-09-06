/* jshint node:true */

// 

if (typeof BD === 'undefined') {
    var BD = {};
}

BD.Helper = (function () {
    'use strict';

    var Helper = {},
        log = [];

    if (typeof module !== 'undefined' && module.exports) {
        module.exports.Helper = Helper;
    }

    Helper.joinPath = function joinPath() {
        var fullPath = Array.prototype.slice.apply(arguments).join('/');
        return ('/' + fullPath + '/').replace(/[\/\\]+/g, '/').slice(0, -1);
    };

    Helper.fileInfo = function fileInfo(path) {
        path = Helper.joinPath(path);

        var info = {
            ext: '',
            name: '',
            path: ''
        };

        var dot = path.lastIndexOf('.');
        var separator = path.lastIndexOf('/');

        if (dot !== -1) {
            info.ext = path.substring(dot + 1).toLowerCase();
        }

        if (separator === -1) {
            info.name = path;
        } else {
            info.name = path.substring(separator + 1);
            info.path = path.substr(0, separator);
        }

        return info;
    };

    Helper.mergeObjects = function() {
        var result = {};

        for (var i = 0, l = arguments.length; i < l; i++) {
            for (var key in arguments[i]) {
                if (arguments[i].hasOwnProperty(key)) {
                    result[key] = arguments[i][key];
                }
            }
        }

        return result;
    };

    Helper.keywordCompare = function (type, str, kw) {
        type = type || 'AND';
        str = (str || '').toUpperCase().split(/\s+/);
        kw = (kw || '').toUpperCase().split(/\s+/);
        return kw[type === 'AND' ? 'every' : 'some'](function (k) {
            return !k || str.some(function (s) {
                return s.indexOf(k) !== -1;
            });
        });
    };

    Helper.time = function (key) {
        log.push({ key: key, type: 'start', time: Date.now() });
    };

    Helper.timeEnd = function (key) {
        log.push({ key: key, type: 'end', time: Date.now() });
    };

    Helper.endLog = function () {
        var result = log;
        log = [];
        return result;
    };

    return Helper;

})();

BD.Application = (function () {
    'use strict';

    if (typeof module !== 'undefined' && module.exports) {
        module.exports.Application = Application;
        BD.Builder = require('./builder');
        BD.Project = require('./project');
    }

    function Application(themeName, config) {
        this.config = config;
        this.project = new BD.Project(themeName, config);
        this.builder = new BD.Builder(config);
    }

    Application.prototype.run = function run(data) {
        BD.Helper.time('[CMS] html app');

        var fm = (typeof module !== 'undefined' ? process : window).FileManager;

        var fso = data.themeFso || new fm();
        var builtResult = fso.enumerate('');

        BD.Helper.time('[CMS] build media');
        this.builder.buildMedia(fso, data, this.project);
        BD.Helper.timeEnd('[CMS] build media');

        BD.Helper.time('[CMS] fso internals');
        builtResult.forEach(function (file) {
            var info = BD.Helper.fileInfo(file);

            if (info.ext === 'html') {
                BD.Helper.time('[CMS] update template: ' + file);
                this.project.updateTemplate(fso, file);
                BD.Helper.timeEnd('[CMS] update template: ' + file);
            }

            if (['png', 'jpg', 'jpeg', 'gif'].indexOf(info.ext) !== -1) {
                BD.Helper.time('[CMS] build image: ' + file);
                this.builder.buildImages(fso, file);
                BD.Helper.timeEnd('[CMS] build image: ' + file);
            }

            if (['css'].indexOf(info.ext) !== -1) {
                BD.Helper.time('[CMS] build css: ' + file);
                this.builder.buildCss(fso, file);
                BD.Helper.timeEnd('[CMS] build css: ' + file);
            }

            if (['js'].indexOf(info.ext) !== -1) {
                BD.Helper.time('[CMS] build js: ' + file);
                this.builder.buildJs(fso, file);
                BD.Helper.timeEnd('[CMS] build js: ' + file);
            }

            if (file === this.config.path.content) {
                BD.Helper.time('[CMS] update page content');
                this.project.updateContents(fso, file);
                BD.Helper.timeEnd('[CMS] update page content');
            }

            if (file === this.config.path.editor) {
                /* expects manifest or app.html in __editor__ */
                BD.Helper.time('[CMS] update app');
                this.project.updateApp(fso);
                BD.Helper.timeEnd('[CMS] update app');

                BD.Helper.time('[CMS] build app');
                this.builder.buildApp(fso, this.project);
                BD.Helper.timeEnd('[CMS] build app');
            } else {
                fso.remove(file);
            }
        }, this);
        BD.Helper.timeEnd('[CMS] fso internals');

        if (data.projectData) {
            BD.Helper.time('[CMS] build project');
            this.project.updateProjectData(data);
            this.builder.buildProject(fso, this.project);
            BD.Helper.timeEnd('[CMS] build project');
        }

        BD.Helper.time('[CMS] build site');
        this.builder.buildSite(fso, this.project);
        BD.Helper.timeEnd('[CMS] build site');

        BD.Helper.timeEnd('[CMS] html app');

        this.project.endChanges();
        BD.Helper.endLog();

        return fso;
    };

    Application.prototype.backendUrl = function backend(action) {
        return this.config.url.backend + '?action=' + action;
    };

    Application.prototype.request = function request(action, data, dataType) {
        dataType = dataType || 'json';
        data = data || {};
        return $.ajax({
            url: this.backendUrl(action),
            type: 'post',
            dataType: dataType,
            data: data
        });
    };

    Application.prototype.chunkedRequest = function chunkedRequest(action, data) {
        return this.request(action, data);
    };

    return Application;

})();

//