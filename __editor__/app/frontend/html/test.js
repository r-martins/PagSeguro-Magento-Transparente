function tree() {
'use strict';

console.time('fill');

var pages = [
    {
        name: 1,
        parent: '',
        template: 'home.html'
    },
    {
        name: 2,
        parent: 1,
        template: 'page.html'
    },
    {
        name: 3,
        parent: 1,
        template: 'page.html'
    },
    {
        name: 4,
        parent: 3,
        template: 'page.html'
    },
    {
        name: 5,
        parent: '',
        template: 'page.html'
    },
    {
        name: 6,
        parent: 5,
        template: 'page.html'
    },
    {
        name: 7,
        parent: 3,
        template: 'post.html'
    },
    {
        name: 8,
        parent: 3,
        template: 'post.html'
    }
];

var _coll = {},
    _tpls = {},
    _parents = {},
    _tree = [],
    _iters = 0;

pages.forEach(function(p) {
    _parents[p.parent] = _parents[p.parent] || [];
    _parents[p.parent].push(p);
    _iters++;
});

var _visited = {};

function Entity(data) {
    data = data || {};
    Object.keys(data).forEach(function (k) {
        Object.defineProperty(this, k, {
            get: function () {
                return data[k];
            }
        });
    }.bind(this));

    this.id = '';
    this.path = '';
    this.index = null;
    this.children = [];
}

function fill(parentId, parentName) {
    _iters++;

    var root = [];
        parentId = parentId || '';
        parentName = parentName || '';
    
    if (_visited[parentName]) {
        throw new Error('Circular structure detected');
    }

    if (_parents[parentName]) {
        _visited[parentName] = true;
        _parents[parentName].forEach(function (item) {
            var id = parentId + '/' + item.name;
            var e = new Entity(item);

            _coll[id] = e;
            _tpls[item.template] = _tpls[item.template] || {};
            _tpls[item.template][id] = e;

            e.id = id;
            e.path = id + '.html';
            e.index = pages.indexOf(item);
            e.children = fill(id, item.name);
            
            root.push(e);
        });
    }

    return root;
}


_tree = fill('Root');

console.log(
    JSON.stringify(_tree, null, 4),
    "\n ----------------- \n",
    JSON.stringify(_coll, null, 4),
    "\n ----------------- \n",
    JSON.stringify(_tpls, null, 4),
    "\n ----------------- \n"
);

console.log(_iters);

console.timeEnd('fill');

}

console.log(
    [1, 2, 3].reduce(function (s, i) { return s.concat([i, 'a']); }, [])
);