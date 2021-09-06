(function () {
    'use strict';
    function addScript(src) {
        var tag = document.createElement("script");
        tag.type = "text/javascript";
        tag.src = src;
        document.getElementsByTagName('head')[0].appendChild(tag);
    }

    function loadManifestData(url, callback) {
        if (!url) {
            callback(new Error("manifest url not found"));
            return;
        }
        var client = new XMLHttpRequest();
        client.open('GET', url);
        client.onreadystatechange = function () {
            if (client.readyState !== 4) {
                return;
            }
            if (client.status === 200) {
                var content = client.responseText && typeof client.responseText === "string" ? client.responseText : '';
                var startJsReg = content.match(new RegExp('(http|https)://.*start\.js([^\r\n]*)', 'i'));
                var domainReg = content.match(new RegExp('#Domain:([^\r\n]*)', 'i'));
                callback(null, {
                    content: content,
                    startJsSrc: startJsReg && startJsReg[0],
                    domainSrc: domainReg && domainReg[1]
                });
            }
            else {
                callback(new Error("manifest load error"));
            }
        };
        client.send(null);
    }

    function getQueryVariable(variable) {
        var query = window.location.search.substring(1);
        var vars = query.split("&");
        for (var i = 0; i < vars.length; i++) {
            var pair = vars[i].split("=");
            if (pair[0] === variable) {
                return pair[1];
            }
        }
        return "";
    }

    function getHost(url) {
        if (!url) {
            return null;
        }
        var parser = document.createElement('a');
        parser.href = url.trim();
        return parser.host;
    }

    function applyParam(url, additionalParam) {
        var qidx = url.lastIndexOf('?');
        if (qidx === -1) {
            url += '?';
        } else {
            url += '&';
        }
        url += additionalParam;
        return url;
    }

    function loadApp() {
        var url = document.getElementsByTagName('html')[0].getAttribute('manifest'),
            appUrl = window.location.protocol + "//themler.io/",
            newDomain = decodeURIComponent(getQueryVariable("domain")),
            startJsUrl = "loader/start.js";

        loadManifestData(url, function (error, manifestData) {
            var needLoadLatestManifest = false;
            if (error || !manifestData || !manifestData.content) {
                needLoadLatestManifest = true;
            }
            if (!needLoadLatestManifest && newDomain && getHost(newDomain) !== manifestData.domainSrc) {
                needLoadLatestManifest = true;
            }
            if (needLoadLatestManifest) {
                if (newDomain && window.location.protocol === "https:") {
                    newDomain = newDomain.replace('http://', 'https://');
                }
                var startJsSrc = (newDomain || appUrl).trim();
                if (startJsSrc.lastIndexOf('/') !== startJsSrc.length - 1) {
                    startJsSrc += '/';
                }
                startJsSrc += startJsUrl;
                startJsSrc = applyParam(startJsSrc, 'version=' + (new Date()).getTime());
                addScript(startJsSrc);
            } else {
                window.appManifestContent = manifestData.content;
                addScript(manifestData.startJsSrc);
            }
        });
    }

    loadApp();
})();