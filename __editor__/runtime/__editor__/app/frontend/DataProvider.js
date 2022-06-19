/* jshint node:true */
/* exported DataProvider */
/* global FileManager, DataProviderHelper, ServerPermissionError */

// 

var DataProvider = {};

(function () {
    'use strict';

    var isNode = typeof module !== 'undefined';
    var __app = (isNode ? process : window).__app;

    DataProvider.validateResponse = function validateResponse(xhr) {
        var error = DataProviderHelper.validateRequest(xhr);
        if (!error) {
            var responseText = xhr.responseText;
            if (typeof responseText === 'string') {
                try {
                    var obj = JSON.parse(responseText);
                    if (obj.status === 'error') {
                        if (obj.type === 'permission') {
                            error = new ServerPermissionError(obj.message);
                        }
                    }
                } catch (e) {}
            }
        }
        return error;
    };

    function ajaxFailHandler(url, xhr, status, callback) {
        var error = DataProvider.validateResponse(xhr);
        if (!error) {
            error = DataProviderHelper.createCmsRequestError(url, xhr, status);
        }
        callback(error);
    }

    DataProvider.load = function load() {
        return __app.project.data;
    };

    function requestExport(data, method, callback) {
        var request = {
            'save': {
                'post': {
                    data: JSON.stringify(data.fso),
                    method: method,
                    publish: data.publish || ''
                },
                'url': __app.backendUrl('export')
            },
            'clear': {
                'post': {},
                'url': __app.backendUrl('clear')
            },
            'errorHandler': DataProvider.validateResponse,
            'zip': true,
            'blob': true
        };
        DataProviderHelper.chunkedRequest(request, callback);
    }

    function getContentType(type) {
        var CONST = (isNode ? process : window).CONST;

        switch (type) {
            case CONST.CMS_CONTENT_TYPE.BLOG:
                return 'post';
            default:
                return '';
        }
    }

    DataProvider.doExport = function doExport(data, callback) {
        requestExport({ fso: __app.run(data) }, 'update', callback);
    };

    DataProvider.save = function save(data, callback) {
        requestExport({ fso: __app.run(data) }, 'save', callback);
    };

    DataProvider.getMd5Hashes = function getMd5Hashes() {
        return __app.project.hashes;
    };

    DataProvider.getAllCssJsSources = function getAllCssJsSources() {
        return __app.project.cache;
    };

    DataProvider.updatePreviewTheme = function updatePreviewTheme(callback) {
        __app.request('start').done(function updatePreviewThemeSuccess() {
            callback(null);
        }).fail(function updatePreviewThemeRequestFail(xhr, status) {
            ajaxFailHandler(__app.backendUrl('start'), xhr, status, callback);
        });
    };

    DataProvider.makeThemeAsActive = function makeThemeAsActive(callback, theme) {
        theme = theme || '';
        callback(null);
    };

    DataProvider.backToAdmin = function backToAdmin() {
        window.location = __app.config.url.admin;
    };

    DataProvider.getMaxRequestSize = function getMaxRequestSize() {
        return __app.config.env.maxRequestSize;
    };

    DataProvider.getVersion = function getVersion() {
        return '0.0.2';
    };

    DataProvider.reloadThemesInfo = function reloadThemesInfo(callback) {
        __app.request('themes').done(function reloadThemesInfoSuccess(response) {
            __app.config.themes = response.themes;
            callback(null, JSON.stringify({themes: response.themes}));
        }).fail(function reloadThemesInfoRequestFail(xhr, status) {
            ajaxFailHandler(__app.backendUrl('themes'), xhr, status, callback);
        });
    };

    DataProvider.getInfo = function getInfo() {
        var originPrefix = !isNode ?
            window.location.origin + window.location.pathname.replace(/(.+\/).+/, '$1'):
            '';
        var startPage = originPrefix + 'runtime/__preview__/index.html';
        var all = Object.keys(__app.project.model.findAll());
        if (all.length) {
            startPage = originPrefix + __app.project.model.getEntityDataByKey(all[0]).url;
        }
        return {
            cmsName: 'Html',
            cmsVersion: __app.config.version,
            adminPage: __app.config.url.admin,
            startPage: startPage,
            templates: __app.project.model.getUsedTemplates(),
            thumbnails: [{name: 'preview.png', width: 200, height: 200}],
            isThemeActive: __app.project.active,
            themeName: __app.project.name,
            uploadImage: __app.backendUrl('image'),
            uploadTheme: __app.backendUrl('theme'),
            unZip: __app.backendUrl('zipToFso'),
            themeArchiveExt: 'zip',
            themes: __app.config.themes,
            includeEditorSupport: true,
            pathToManifest: __app.config.path.manifest || (__app.config.path.editor + '/themler.manifest')
        };
    };

    DataProvider.canRename = function canRename(themeName, callback) {
        __app.request('canRename', {
            themeName: themeName
        }).done(function canRenameSuccess(response) {
            callback(null, response.canRename);
        }).fail(function canRenameRequestFail(xhr, status) {
            ajaxFailHandler(__app.backendUrl('canRename'), xhr, status, callback);
        });
    };

    DataProvider.rename = function rename(targetName, callback) {
        __app.request('rename', {
            source: __app.project.name,
            target: targetName
        }).done(function renameSuccess() {
            var link = window.location.href.replace(
                new RegExp('themes/' + __app.project.name + '/__editor__'),
                'themes/' + targetName + '/__editor__'
            );
            callback(null, link);
        }).fail(function renameRequestFail(xhr, status) {
            ajaxFailHandler(__app.backendUrl('rename'), xhr, status, callback);
        });
    };

    DataProvider.renameTheme = function renameTheme(sourceName, targetName, callback) {
        __app.request('rename', {
            source: sourceName,
            target: targetName
        }).done(function renameThemeSuccess() {
            callback(null);
        }).fail(function renameThemeRequestFail(xhr, status) {
            ajaxFailHandler(__app.backendUrl('rename'), xhr, status, callback);
        });
    };

    DataProvider.removeTheme = function removeTheme(sourceName, callback) {
        __app.request('rename', {
            source: sourceName,
            target: ''
        }).done(function removeThemeSuccess() {
            callback(null);
        }).fail(function removeThemeRequestFail(xhr, status) {
            ajaxFailHandler(__app.backendUrl('rename'), xhr, status, callback);
        });
    };

    DataProvider.copyTheme = function copyTheme(sourceName, targetName, callback) {
        __app.request('copy', {
            source: sourceName,
            target: targetName
        }).done(function copyThemeSuccess() {
            callback(null);
        }).fail(function copyThemeRequestFail(xhr, status) {
            ajaxFailHandler(__app.backendUrl('copy'), xhr, status, callback);
        });
    };

    DataProvider.getTheme = function getTheme(params, callback) {
        var data = {
            sourceName: params.sourceName || '',
            targetName: params.targetName || params.themeName || '',
            editor: !!params.includeEditor
        };

        var urlParams = Object.keys(data).map(function (param) {
            return param + '=' + data[param];
        }).join('&');

        var host = window.location.origin + window.location.pathname.replace(/app\.html$/, '');
        callback(null, host + __app.backendUrl('zip') + '&' + urlParams);
    };

    DataProvider.getFiles = function getFiles(mask, filter, callback) {
        __app.request('getFiles', {
            mask: mask,
            filter: filter
        }).done(function getFilesSuccess(response) {
            callback(null, response.files);
        }).fail(function getFilesFail(xhr, status) {
            ajaxFailHandler(__app.backendUrl('getFiles'), xhr, status, callback);
        });
    };

    DataProvider.setFiles = function setFiles(files, callback) {
        var manifestPath = DataProvider.getInfo().pathToManifest;
        var fso = new FileManager();

        Object.keys(files).forEach(function (filename) {
            if (filename === manifestPath) {
                var manifestFso = new FileManager();
                manifestFso.write(filename, files[filename]);
                manifestFso = __app.run({
                    themeFso: manifestFso
                });
                manifestFso.copyTo('/', fso, '/');
            }
            fso.write(filename, files[filename]);
        });

        requestExport({ fso: fso, publish: true }, 'setFiles', callback);
    };

    DataProvider.zip = function (data, callback) {
        var request = {
            'save': {
                'post': {
                    data: JSON.stringify(data)
                },
                'url': __app.backendUrl('fsoToZip')
            },
            'clear': {
                'post': {},
                'url': __app.backendUrl('clear')
            },
            'errorHandler': DataProvider.validateResponse,
            'zip': true,
            'blob': true
        };
        DataProviderHelper.chunkedRequest(request, callback);
    };

    DataProvider.getPosts = function getPosts(searchObj, callback) {
        if (!callback || typeof callback !== 'function') {
            throw DataProviderHelper.getResultError('Invalid callback');
        }
        if (!searchObj || typeof searchObj !== 'object') {
            throw DataProviderHelper.getResultError('Invalid search object');
        }

        var posts = __app.project.model.search(searchObj).map(function (entity) {
            return __app.project.model.getEntityData(entity, ['content']);
        });

        callback(null, {log: null, data: posts});
    };

    DataProvider.getPostChildren = function getPostChildren(id, callback) {
        if (!callback || typeof callback !== 'function') {
            throw DataProviderHelper.getResultError('Invalid callback');
        }

        if (!id) {
            throw DataProviderHelper.getResultError('Invalid id');
        }

        callback(null, {log: null, data: __app.project.model.findChildren(id).map(function (entity) {
            return __app.project.model.getEntityData(entity, ['content']);
        })});
    };

    DataProvider.updatePost = function updatePost(postType, post, callback) {
        if (!callback || typeof callback !== 'function') {
            throw DataProviderHelper.getResultError('Invalid callback');
        }
        if (!post || typeof post !== 'object' || !post.id) {
            throw DataProviderHelper.getResultError('Invalid post object');
        }

        var updated = __app.project.model.updateEntity(post);

        callback(null, {log: null, data: __app.project.model.getEntityData(updated, ['content'])});
    };

    DataProvider.removePost = function removePost(postType, key, recursive, callback) {
        var list = __app.project.model.removeEntity(key, recursive);
        callback(null, {log: null, data: list});
    };

    DataProvider.copyPost = function copyPost(postType, data, recursive, callback) {
        var list = __app.project.model.cloneEntity(data, recursive);
        callback(null, {log: null, data: list});
    };

    DataProvider.newPost = function newPost(postType, data, callback) {
        var newEntity = __app.project.model.newEntity(postType, data);
        callback(null, {log: null, data: __app.project.model.getEntityData(newEntity, ['content'])});
    };

    DataProvider.getCmsContent = function getCmsContent(getData, callback) {
        if (!callback || typeof callback !== 'function') {
            throw DataProviderHelper.getResultError('Invalid callback');
        }
        if (!getData || typeof getData !== 'object') {
            throw DataProviderHelper.getResultError('Invalid params object');
        }

        var result = {};

        Object.keys(getData).forEach(function (contentType) {
            var postType = getContentType(contentType);
            if (!postType) return;

            var collection = {},
                limit = parseFloat(getData.limit) || 4;

            __app.project.model.search({
                postType: postType,
                pageSize: limit
            }).forEach(function (entity) {
                var postData = __app.project.model.getEntityData(entity);
                var postContent = postData.content.trim();
                if (postData.image) {
                    postData.image = 'url(../' + __app.config.path.assets.images + '/' + postData.image + ')';
                }
                if (postContent && typeof jQuery !== 'undefined') {
                    postContent = jQuery(postContent).find('[class*="bd-customhtml-"]:first').html();
                }
                postData.content = postContent;
                collection[entity.key] = postData;
            });

            result[contentType] = {
                contentJson: collection,
                images: {}
            };
        });

        callback(null, { log: null, data: result });
    };

    DataProvider.putCmsContent = function putCmsContent(putData, callback) {
        callback(null, { log: null });
    };

    if (isNode) {
        module.exports = DataProvider;
    }

})();

//