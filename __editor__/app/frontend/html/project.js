/* jshint node:true */

// 

if (typeof BD === 'undefined') {
    var BD = {};
}

BD.Project = (function () {
    'use strict';

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = Project;
        BD.Model = require('./model');
        BD.Helper = require('./application').Helper;
    }

    function Project(name, config) {
        this.name = name;
        this.active = true;
        this.config = config;

        this.data = this.loadJson(this.config.path.project);
        this.cache = this.loadJson(this.config.path.cache);
        this.hashes = this.loadJson(this.config.path.hashes);
        this.model = new BD.Model(this.loadJson(this.config.path.model), config);
        this.editor = this.loadJson(this.config.path.editorConfig);
    }

    Project.prototype.updateProjectData = function updateProjectData(data) {
        if (data.projectData) {
            this.data = data.projectData;
        }

        if (data.cssJsSources) {
            var appCache = this.cache;
            Object.keys(data.cssJsSources).forEach(function (control) {
                var files = data.cssJsSources[control];

                Object.keys(files).forEach(function (name) {
                    var content = files[name];
                    if (content === '[DELETED]' && appCache[control]) {
                        delete appCache[control][name];
                    } else {
                        appCache[control] = appCache[control] || {};
                        appCache[control][name] = content;
                    }
                });

                if (appCache[control] && !Object.keys(appCache[control]).length) {
                    delete appCache[control];
                }
            });
        }

        if (data.md5Hashes) {
            var appHashes = this.hashes;
            Object.keys(data.md5Hashes).forEach(function (key) {
                var hash = data.md5Hashes[key];
                if (hash === '[DELETED]') {
                    delete appHashes[key];
                } else {
                    appHashes[key] = data.md5Hashes[key];
                }
            });
        }
    };

    Project.prototype.endChanges = function trackChanges() {
        this.model._changes = {};
        this.model._deleted = {};
    };

    Project.prototype.getChanges = function getChanges() {
        return this.model._changes;
    };

    Project.prototype.getDeleted = function getDeleted() {
        return this.model._deleted;
    };

    Project.prototype.updateApp = function updateApp(fso) {
        var pathToManifest = this.config.path.manifest;
        var appPath = BD.Helper.joinPath(this.config.path.editor, 'app.html');

        if (fso.exists(appPath)) {
            this.editor.template = fso.read(appPath);
        }

        if (fso.exists(pathToManifest)) {
            var ver = /#ver:(\d+)/i.exec(fso.read(pathToManifest));
            this.editor.version = ver && ver[1] || this.editor.version;
        }
    };

    Project.prototype.updateTemplate = function updateTemplate(fso, file) {
        this.model.updateTemplate(file, fso.read(file));
    };

    Project.prototype.updateContents = function updateContents(fso, dir) {
        fso.enumerate(dir).forEach(function (id) {
            var fileContent = fso.read(BD.Helper.joinPath(dir, id));
            if (fileContent !== '[DELETED]') {
                this.model.updateEntity({
                    id: id,
                    content: fileContent
                });
            }
        }, this);
    };

    Project.prototype.loadJson = function loadJson(url) {
        var data;
        if (typeof module === 'undefined') {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url + '?' + Date.now(), false);
            xhr.send(null);
            data = xhr.responseText;
        } else {
            data = require('fs').readFileSync(url);
        }
        return JSON.parse(data);
    };

    return Project;

})();

//