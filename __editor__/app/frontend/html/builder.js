/* jshint node:true */

// 

if (typeof BD === 'undefined') {
    var BD = {};
}

BD.Builder = (function () {
    'use strict';

    var renderKeywords = ['if\\s*\\(', 'for\\s*\\(', 'function\\s', '\\{', '\\('];
    var mediaRE = /(src=(['"]))?(url\(([^\)]+)\))\2?/gi;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = Builder;
        BD.Helper = require('./application').Helper;
    }

    function Builder(config) {
        this.config = config;
        this.media = {};
    }

    Builder.prototype.buildSite = function (fso, project) {
        var changes = project.getChanges();
        var deleted = project.getDeleted();

        this.removeHtml(fso, deleted);

        this.buildHtml(
            fso,
            {
                model: project.model,
                collection: changes
            }
        );
    };

    Builder.prototype.buildMedia = function buildMedia(fso, data, project) {
        this.media = {};

        if (data.iconSetFiles) {
            Object.keys(data.iconSetFiles).forEach(function (key) {
                fso.write(
                    BD.Helper.joinPath(this.config.path.assets.fonts, key),
                    data.iconSetFiles[key],
                    'base64'
                );
            }, this);
        }

        if (data.images) {
            Object.keys(data.images).forEach(function (key) {
                if (data.images[key]) {
                    fso.write(
                        BD.Helper.joinPath(this.config.path.assets.images, key),
                        data.images[key],
                        data.images[key] === '[DELETED]' ? '' : 'base64'
                    );
                }
                this.media[key] = {
                    htmlPath: this.config.path.assets.images,
                    cssPath: pathToRoot(this.config.path.assets.images) + '/' + this.config.path.assets.images
                };
            }, this);
        }

        if (data.thumbnails) {
            data.thumbnails.forEach(function (thumbnail) {
                fso.write(
                    BD.Helper.joinPath(this.config.path.assets.images, thumbnail.name),
                    thumbnail.data.replace('data:image/png;base64,', ''),
                    'base64'
                );
            }, this);
        }

        project.model.search({ postType: 'post' }).forEach(function (post) {
            if (post.image && /^(https?:|data:image)/.test(post.image) === false) {
                this.media[post.image] = {
                    htmlPath: this.config.path.assets.images
                };
            }
        }, this);
    };

    Builder.prototype.buildImages = function buildImages(fso, file) {
        fso.move(file, BD.Helper.joinPath(this.config.path.assets.images, file));
    };

    Builder.prototype.buildCss = function buildCss(fso, file) {
        var content = this.processMedia(fso.read(file), '', 'css');
        fso.write(BD.Helper.joinPath(this.config.path.assets.css, file), content);
    };

    Builder.prototype.buildJs = function buildJs(fso, file) {
        fso.move(file, BD.Helper.joinPath(this.config.path.assets.js, file));
    };

    Builder.prototype.buildProject = function buildEditor(fso, project) {
        fso.write(
            BD.Helper.joinPath(this.config.path.editor, this.config.path.project),
            JSON.stringify(project.data)
        );
        fso.write(
            BD.Helper.joinPath(this.config.path.editor, this.config.path.cache),
            JSON.stringify(project.cache)
        );
        fso.write(
            BD.Helper.joinPath(this.config.path.editor, this.config.path.hashes),
            JSON.stringify(project.hashes)
        );
        fso.write(
            BD.Helper.joinPath(this.config.path.editor, this.config.path.model),
            JSON.stringify(project.model.data, null, 4)
        );
        fso.write(
            BD.Helper.joinPath(this.config.path.editor, this.config.path.editorConfig),
            JSON.stringify(project.editor)
        );
    };

    Builder.prototype.buildApp = function buildApp(fso, project) {
        var appPath = BD.Helper.joinPath(this.config.path.editor, 'app.html');
        var pathToManifest = this.config.path.manifest;

        if (fso.exists(pathToManifest)) {
            fso.copy(pathToManifest, BD.Helper.joinPath('__manifest__', project.editor.version + '.manifest'));
        }

        fso.write(
            BD.Helper.joinPath(this.config.path.editor, this.config.path.editorConfig),
            JSON.stringify(project.editor)
        );

        fso.write(appPath, this.renderApp(project));
    };

    Builder.prototype.buildHtml = function buildHtml(fso, buildData) {
        var model = buildData.model,
            collection = buildData.collection;

        var entityKeys = Object.keys(collection),
            pageTree = [],
            postList = [];

        if (entityKeys.length) {
            BD.Helper.time('[CMS] create page tree');
            pageTree = model.allPageData();
            BD.Helper.timeEnd('[CMS] create page tree');

            BD.Helper.time('[CMS] select posts');
            postList = model.allPostData();
            BD.Helper.timeEnd('[CMS] select posts');
        }

        entityKeys.forEach(function (entityKey) {
            var entity = collection[entityKey],
                template = entity.template,
                previewFolder = this.config.path.preview;

            BD.Helper.time('[CMS] Build page - ' + entity.name);

            BD.Helper.time('[CMS] compose page');
            var finalHtml = (model.getTemplate(template) || ('Empty template: ' + template))
                .replace(/<export:content>/g, entity.content);
            BD.Helper.timeEnd('[CMS] compose page');

            BD.Helper.time('[CMS] prepare page data');
            var page = this.preparePageData(model, entity);
            BD.Helper.timeEnd('[CMS] prepare page data');

            BD.Helper.time('[CMS] render');
            // base
            this.renderPage({
                root: '',
                template: finalHtml,
                data: { pages: pageTree, posts: postList, page: page }
            }, function (content) {
                fso.write(
                    page.filePath,
                    this.processMedia(BD.Builder.removeDataId(content), pathToRoot(page.path), 'html')
                );
            }.bind(this));
            BD.Helper.timeEnd('[CMS] render');

            BD.Helper.time('[CMS] render preview');
            // preview
            this.renderPage({
                root: previewFolder,
                template: finalHtml,
                data: { pages: pageTree, posts: postList, page: page }
            }, function (content) {
                fso.write(
                    BD.Helper.joinPath(previewFolder, page.filePath),
                    this.processMedia(content, pathToRoot(
                        BD.Helper.joinPath(previewFolder, page.path)
                    ), 'html')
                );
            }.bind(this));
            BD.Helper.timeEnd('[CMS] render preview');

            if (page.order === 0) {
                BD.Helper.time('[CMS] create index');
                var indexFile = BD.Helper.joinPath(page.path, 'index.html');
                // base index
                fso.copy(page.filePath, indexFile);
                // preview index
                fso.copy(
                    BD.Helper.joinPath(previewFolder, page.filePath),
                    BD.Helper.joinPath(previewFolder, page.path, 'index.html')
                );
                BD.Helper.timeEnd('[CMS] create index');
            }

            BD.Helper.timeEnd('[CMS] Build page - ' + entity.name);
        }, this);
    };

    Builder.prototype.removeHtml = function removeHtml(fso, collection) {
        Object.keys(collection).forEach(function (filePath) {
            fso.write(filePath, '[DELETED]');
            fso.write(BD.Helper.joinPath(this.config.path.preview, filePath), '[DELETED]');
        }, this);
    };

    Builder.prototype.preparePageData = function preparePageData(model, entity) {
        var data = model.getEntityData(entity);

        data.parents = model.findParents(entity.key).map(function (i) {
            return model.getEntityData(i);
        });

        if (entity.comments) {
            data.comments = BD.Model.createTree('', entity.comments, function (parentPath, item) {
                var commentData = model.getEntityData(item);
                commentData.name = '';
                return commentData;
            });
        }

        return data;
    };

    Builder.prototype.renderPage = function renderPage(params, callback) {
        BD.Helper.time('[CMS] compute site paths');

        var assetsRoot = pathToRoot(BD.Helper.joinPath(params.root, params.data.page.path)),
            pageRoot = pathToRoot(params.data.page.path),
            assets = {};

        Object.keys(this.config.path.assets).forEach(function (k) {
            assets[k] = assetsRoot + '/' + this.config.path.assets[k];
        }, this);

        BD.Helper.timeEnd('[CMS] compute site paths');

        var parse = function parse(content) {
            return renderInternal(argsDef, argsVal, content);
        };

        BD.Helper.time('[CMS] compile');
        var argsDef = ['parse', 'pages', 'posts', 'page', 'assets', 'pathToRoot'],
            argsVal = [
                parse,
                params.data.pages,
                params.data.posts,
                params.data.page,
                assets,
                pageRoot
            ];

        callback(renderInternal(argsDef, argsVal, params.template));
        BD.Helper.timeEnd('[CMS] compile');
    };

    Builder.prototype.renderApp = function renderApp(project) {
        var manifestAttr = project.editor.version ?
                'manifest="../../../manifest/' + project.editor.version + '.manifest"' :
                '',
            def = ['manifest'],
            val = [manifestAttr];

        return renderInternal(def, val, project.editor.template);
    };

    Builder.prototype.processMedia = function processMedia(content, mediaRoot, type) {
        type = type || 'html';
        if (type === 'html') {
            content = content.replace('//fonts.googleapis.com', 'http://fonts.googleapis.com');
        }
        return content.replace(mediaRE, function (str, tag, brace, match, src) {
            tag = tag || '';
            brace = brace || '';

            var mediaInfo = this.media[src];
            src = src.replace(/['"]/g, '');

            if (mediaInfo) {
                match = type === 'css' ?
                    'url(' + (mediaInfo.cssPath ? mediaInfo.cssPath  + '/' : '') + src + ')' :
                    tag + (mediaInfo.htmlPath ? mediaRoot + '/' + mediaInfo.htmlPath + '/' : '') + src + brace;
            } else {
                match = (type === 'html' && tag) ? (tag + src + brace) : match;
            }
            return match;
        }.bind(this));
    };

    Builder.removeDataId = function removeDataId(content) {
        return content.replace(/(data-control-id)(=["'](\d+)["']|-(\d+))/g, '');
    };

    function renderInternal(def, val, content) {
        var bliss = (typeof module !== 'undefined' ? process : window).bliss,
            result;
        try {
            var re = new RegExp('@(?!(' + renderKeywords.join('|') + '))', 'g');

            result = bliss.compile(
                '@!(' + def.join(', ') + ')\n' + content.replace(re, '@@')
            ).apply(null, val).trim();
        } catch (e) {
            e.args = {
                template: content,
                renderParams: JSON.stringify(val)
            };
            throw e;
        }
        return result;
    }

    function pathToRoot(pathFromRoot) {
        if (!pathFromRoot) return '.';
        return pathFromRoot.split('/')
            .filter(function (i) { return i; })
            .map(function () { return '..'; })
            .join('/');
    }

    return Builder;

})();

//