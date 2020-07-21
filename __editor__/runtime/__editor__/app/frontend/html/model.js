/* jshint node:true */
/* global ErrorUtility */

// 

if (typeof BD === 'undefined') {
    var BD = {};
}

BD.Model = (function () {
    'use strict';

    var entityRoot = {
            page: '',
            post: 'Blog'
        };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = Model;
        BD.Helper = require('./application').Helper;
    }

    var excludedProps = ['id', 'parent', 'key', 'parentKey', 'url', 'path', 'storage', 'filePath'];

    function Model(data, config) {
        this.data = data;
        this.config = config;

        this._dataByTemplate = {};
        this._changes = {};
        this._deleted = {};

        this._maxId = {};
        this._maxOrder = {};

        this.initStorage('page');
        this.initStorage('post');
    }

    Model.prototype.initStorage = function initStorage(storage) {
        var collection = this.data[storage];
        if (!collection) {
            throw new Error('Invalid collection name');
        }

        this._maxId[storage] = this._maxId[storage] || 0;
        this._maxOrder[storage] = this._maxOrder[storage] || {};
        var maxOrder = this._maxOrder[storage];

        Object.keys(collection).forEach(function (id) {
            var entity = collection[id];

            this.initPage(storage, id, entity);

            var intId = parseInt(id, 10);
            if (intId > this._maxId[storage]) {
                this._maxId[storage] = intId;
            }

            maxOrder[entity.parent] = maxOrder[entity.parent] || 0;

            var order = parseFloat(entity.order || 0);
            if (order > maxOrder[entity.parent]) {
                maxOrder[entity.parent] = order;
            }
        }, this);
    };

    Model.prototype.initPage = function initPage(storage, id, page) {
        var model = this;
        page.parent = page.parent || '';

        Object.defineProperty(page, 'id', {
            value: id,
            enumerable: false,
            writable: false,
            configurable: false
        });

        Object.defineProperty(page, 'storage', {
            value: storage,
            enumerable: false,
            writable: false,
            configurable: false
        });

        Object.defineProperty(page, 'path', {
            get: function () {
                return model.getPath(this);
            },
            enumerable: false,
            configurable: false
        });

        Object.defineProperty(page, 'key', {
            get: function () {
                return this.storage + '.' + this.id;
            },
            enumerable: false,
            configurable: false
        });

        Object.defineProperty(page, 'parentKey', {
            get: function () {
                return this.parent ? this.storage + '.' + this.parent : '';
            },
            enumerable: false,
            configurable: false
        });

        this._dataByTemplate[page.template] = this._dataByTemplate[page.template] || {};
        this._dataByTemplate[page.template][page.key] = page;
    };

    Model.prototype.newEntity = function newEntity(storage, data) {
        if (typeof storage !== 'string' || !storage) {
            throw new Error('Invalid storage name');
        }
        if (typeof data !== 'object' || !data) {
            throw new Error('Invalid entity data');
        }
        var newId = (++this._maxId[storage]) + '';
        this.data[storage] = this.data[storage] || {};
        var collection = this.data[storage];

        var entity = {};

        for (var i in data) {
            if (data.hasOwnProperty(i)) {
                if (excludedProps.indexOf(i) !== -1) continue;
                entity[i] = data[i];
            }
        }

        entity.parent = data.parent ? this.find(data.parent).id : '';
        entity.content = entity.content || '';

        var order = data.order;
        if (order === undefined) {
            var maxOrder = this._maxOrder[storage];
            maxOrder[entity.parent] = maxOrder[entity.parent] || 0;
            maxOrder[entity.parent] += 128.0;
            order = maxOrder[entity.parent];
        }
        entity.order = order;

        if (entity.date) {
            var time = Date.parse(entity.date);

            if (!isNaN(time)) {
                var d = new Date();
                d.setTime(time);
                var formatter = new Intl.DateTimeFormat('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                entity.date = formatter.format(d);
            }
        }

        collection[newId] = entity;
        this.initPage(storage, newId, entity);
        this.validateName(entity);
        this.validateImage(entity);

        this._changes = this.findAll();

        return entity;
    };

    Model.prototype.cloneEntity = function cloneEntity(data, recursive) {
        var sourceEntity = this.find(data.id),
            changes = [];

        var sourceData = this.getEntityData(sourceEntity);
        var cloneData = BD.Helper.mergeObjects(sourceData, data);
        delete cloneData.order;

        var newEntity = this.newEntity(sourceEntity.storage, cloneData);

        changes.push({
            old: this.getEntityData(sourceEntity, ['content']),
            new: this.getEntityData(newEntity, ['content'])
        });

        if (recursive) {
            var cloneList = this.findChildren(sourceEntity.key);
            cloneList.forEach(function (cloneChild) {
                var childData = this.getEntityData(cloneChild);
                childData.parent = newEntity.key;
                changes = changes.concat(this.cloneEntity(childData, recursive));
            }, this);
        }

        this._changes = this.findAll();

        return changes;
    };

    Model.prototype.updateEntity = function updateEntity(data) {
        var contentOnly = true;
        var entity = this.find(data.id);

        if (data.template && data.template !== entity.template) {
            delete this._dataByTemplate[entity.template][entity.key];
            this._dataByTemplate[data.template] = this._dataByTemplate[data.template] || {};
            this._dataByTemplate[data.template][entity.key] = entity;
        }

        if (data.name && data.name !== entity.name) {
            this._deleted[BD.Helper.joinPath(entity.path, entity.name + '.html')] = true;
        }

        for (var prop in data) {
            if (data.hasOwnProperty(prop) && excludedProps.indexOf(prop) === -1) {
                if (prop === 'content' && !data[prop]) continue;
                entity[prop] = data[prop];
                if (contentOnly) {
                    contentOnly = prop === 'content';
                }
            }
        }

        if (data.parent !== undefined) {
            entity.parent = data.parent ? this.find(data.parent).id : '';
        }

        var maxOrder = this._maxOrder[entity.storage];
        maxOrder[entity.parent] = maxOrder[entity.parent] || 0;
        if (entity.order > maxOrder[entity.parent]) {
            maxOrder[entity.parent] = entity.order;
        }

        this.validateName(entity);
        this.validateImage(entity);

        if (contentOnly) {
            if (entity.storage === 'post') {
                var blogs = this._dataByTemplate['blogTemplate.html'];
                if (blogs) {
                    Object.keys(blogs).forEach(function (key) {
                        this._changes[key] = blogs[key];
                    }, this);
                }
            }
            this._changes[entity.key] = entity;
        } else {
            this._changes = this.findAll();
        }

        return entity;
    };

    Model.prototype.removeEntity = function removeEntity(key, recursive) {
        var remove = this.find(key),
            collection = this.data[remove.storage],
            changes = [];

        changes.push({
            id: remove.key,
            template: remove.template
        });

        if (recursive) {
            var removeList = this.findChildren(remove.key);
            removeList.forEach(function (entity) {
                changes = changes.concat(this.removeEntity(entity.key, recursive));
            }, this);
        }

        delete this._dataByTemplate[remove.template][remove.key];
        delete collection[remove.id];

        this._changes = this.findAll();
        this._deleted[BD.Helper.joinPath(remove.path, remove.name + '.html')] = true;
        if (remove.order === 0) {
            this._deleted[BD.Helper.joinPath(remove.path, 'index.html')] = true;
        }

        return changes;
    };

    Model.prototype.validateName = function validateName(entity) {
        var siblings = this.findSiblings(entity.key).map(function (sibling) {
            return sibling.name;
        });

        var name = entity.name || 'page';

        while (siblings.indexOf(name) !== -1) {
            var m = name.match(/(.*?)(\d{0,4})$/),
                suffix = parseInt(m[2], 10) || 1;
            name = m[1] + (++suffix);
        }

        entity.name = name;
    };

    Model.prototype.validateImage = function validateImage(entity) {
        if (entity.image) {
            entity.image = entity.image.replace(/[\s\S]*?([^\/\\\(\)\s]+)[\)\s]*$/, '$1');
        }
    };

    Model.prototype.getPath = function getPath(page) {
        if (!page) {
            throw new Error('Invalid page param');
        }

        var collection = this.data[page.storage],
            pageRoot = entityRoot[page.storage];

        if (!collection) {
            throw new Error('Invalid page collection');
        }

        var path = [],
            visited = [];

        var parent = collection[page.parent];

        while (parent) {
            if (visited.indexOf(parent.parent) !== -1) {
                throw new Error('Circular page structure');
            }
            path.push(parent.name);
            visited.push(parent.parent);
            parent = collection[parent.parent];
        }

        path = path.reverse().join('/');
        return BD.Helper.joinPath(pageRoot, path);
    };

    Model.prototype.updateTemplate = function updateTemplate(template, content) {
        this.data.templates = this.data.templates || {};
        this.data.templates[template] = content;

        var pages = this._dataByTemplate[template];
        if (pages) {
            Object.keys(pages).forEach(function (key) {
                this._changes[key] = pages[key];
            }, this);
        }
    };

    Model.prototype.getTemplate = function getTemplate(name) {
        return this.data.templates[name];
    };

    Model.prototype.parseKey = function parseKey(key) {
        if (typeof key !== 'string' || !key) {
            throw new Error('Invalid key value: ' + key);
        }
        var parts = key.split('.');
        if (parts.length !== 2) {
            throw new Error('Invalid key format: ' + key);
        }
        return { storage: parts[0], id: parts[1] };
    };

    Model.prototype.find = function find(key) {
        var info = this.parseKey(key);

        var collection = this.data[info.storage];
        if (!collection) {
            throw new Error('Invalid collection name');
        }

        var entity = collection[info.id];
        if (!entity) {
            var e = new Error('Invalid entity id');
            if (typeof ErrorUtility === 'undefined') {
                throw e;
            } else {
                e.args = {
                    key: key,
                    changes: this._changes,
                    deleted: this._deleted
                };
                ErrorUtility.logWarning(e);
            }
        }

        return entity;
    };

    Model.prototype.findAll = function findAll() {
        var result = {};
        var fill = function (storage, id) {
            var entity = this.data[storage][id];
            result[entity.key] = entity;
        };
        Object.keys(this.data.page).forEach(fill.bind(this, 'page'));
        Object.keys(this.data.post).forEach(fill.bind(this, 'post'));
        return result;
    };

    Model.prototype.findChildren = function findChildren(key) {
        var target = this.find(key),
            collection = this.data[target.storage];

        return Object.keys(collection).reduce(function (result, id) {
            var entity = collection[id];
            if (entity.parent === target.id) {
                result.push(entity);
            }
            return result;
        }.bind(this), []);
    };

    Model.prototype.findSiblings = function findSiblings(key) {
        var target = this.find(key),
            collection = this.data[target.storage];

        return Object.keys(collection).reduce(function (result, id) {
            var entity = collection[id];
            if (target.id !== id && target.parent === entity.parent) {
                result.push(entity);
            }
            return result;
        }.bind(this), []);
    };

    Model.prototype.findParents = function findParents(key) {
        var target = this.find(key),
            parents = [];

        while (target.parent) {
            target = this.find(target.parentKey);
            parents.push(target);
        }

        return parents;
    };

    Model.prototype.search = function search(params) {
        var result = [];

        if (!params) {
            throw new Error('Missing search object');
        }

        if (params.id) {
            result.push(this.find(params.id));
        } else {
            if (!params.postType || !this.data[params.postType]) {
                throw new Error('Missing required param: postType');
            }

            var collection = this.data[params.postType];

            Object.keys(collection).forEach(function (id) {
                var f = true,
                    entity = collection[id];

                if (params.searchString) {
                    f = f && BD.Helper.keywordCompare('AND', entity.name, params.searchString);
                }

                if (f) {
                    result.push(entity);
                }
            }, this);
        }

        if (params.sortType) {
            var direction = params.sortDirection || 'asc';
            result.sort(entitySort.bind(null, params.sortType, direction));
        }

        if (params.pageSize && params.pageNumber) {
            var pageNumber = parseInt(params.pageNumber, 10) || 1;
            var pageSize = parseInt(params.pageSize, 10) || result.length;

            var start = pageSize * (pageNumber - 1);
            result = result.slice(start, start + pageSize);
        }

        return result;
    };

    Model.prototype.getEntityData = function getEntityData(entity, excludes) {
        var data = {},
            previewUrl = BD.Helper.joinPath(
                this.config.path.runtime,
                this.config.path.preview,
                entity.path,
                entity.name + '.html'
            ).slice(1);

        excludes = excludes || [];

        for (var i in entity) {
            if (!entity.hasOwnProperty(i)) continue;
            if (excludes.indexOf(i) !== -1) continue;
            data[i] = entity[i];
        }

        data.id = entity.key;
        data.url = encodeURI(previewUrl);
        data.parent = entity.parentKey;

        data.path = entity.path || '';
        data.filePath = BD.Helper.joinPath(data.path, data.name + '.html');
        data.storage = entity.storage;

        return data;
    };

    Model.prototype.getEntityDataByKey = function getEntityDataByKey(key, excludes) {
        return this.getEntityData(this.find(key), excludes);
    };

    Model.prototype.allPageData = function allPageData() {
        var collection = this.data.page;

        var list = Model.createTree('', collection, function (parentPath, item) {
            return this.getEntityData(item);
        }.bind(this));

        return list;
    };

    Model.prototype.allPostData = function allPostData() {
        var posts = this.search({
            postType: 'post',
            sortType: 'date',
            sortDirection: 'desc',
            pageSize: 12,
            pageNumber: 1
        });

        posts = posts.map(function (post) {
            return this.getEntityData(post);
        }, this);

        return posts;
    };

    Model.prototype.getUsedTemplates = function getUsedTemplates() {
        var result = {};

        Object.keys(this.data.templates).forEach(function (template) {
            if (template === 'blogTemplate.html' || template === 'home.html' ||
                template === 'pageTemplate.html' || template === 'singlePostTemplate.html') {
                result[template.replace('.html', '')] = 'static/template-warning.html';
            }
        });

        Object.keys(this._dataByTemplate).forEach(function (template) {
            var collection = this._dataByTemplate[template];
            for (var key in collection) {
                if (collection.hasOwnProperty(key)) {
                    var entity = collection[key],
                        data = this.getEntityData(entity);

                    result[template.replace('.html', '')] = data.url + '?' + Date.now();

                    break;
                }
            }
        }, this);

        return result;
    };

    Model.createTree = function createTree(pathPrefix, collection, callback) {
        var _parents = {},
            _visited = {};

        if (Array.isArray(collection)) {
            collection.forEach(function(item) {
                var parent = item.parent || '';
                _parents[parent] = _parents[parent] || [];
                _parents[parent].push({ id: item.id, item: item });
            });
        } else {
            Object.keys(collection).forEach(function(id) {
                var item = collection[id];
                var parent = item.parent || '';
                _parents[parent] = _parents[parent] || [];
                _parents[parent].push({ id: id, item: item });
            });
        }

        var fill = function fill(parentPath, parentId) {
            var list = [];
            parentPath = parentPath || '';
            parentId = parentId || '';

            if (_visited[parentId]) {
                throw new Error('Circular structure detected');
            }

            if (_parents[parentId]) {
                _visited[parentId] = true;
                _parents[parentId].forEach(function (el) {
                    var itemData = callback(parentPath, el.item);
                    itemData.children = fill(parentPath + '/' + itemData.name, el.id);
                    list.push(itemData);
                });
            }

            return list.sort(entitySort.bind(null, 'order', 'asc'));
        };

        return fill(pathPrefix);
    };

    function entitySort(prop, dir, a, b) {
        if (a[prop] === undefined) {
            return 1;
        }
        var v1 = a[prop],
            v2 = b[prop],
            lesser = -1,
            larger = 1;

        if (dir === 'desc') {
            lesser = 1;
            larger = -1;
        }

        if (prop === 'date') {
            v1 = v1 && Date.parse(v1);
            v2 = v2 && Date.parse(v2);
        }

        if (v1 < v2) {
            return lesser;
        } else if (v1 > v2) {
            return larger;
        }
        return 0;
    }

    return Model;

})();

//