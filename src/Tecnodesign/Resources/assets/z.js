/*! Tecnodesign Z base v2.1 | (c) 2015 Capile Tecnodesign <ti@tecnodz.com> */
if(!('Z' in window)) window.Z={uid:'/_me'};
(function(Z) {
var _ajax={}, _isReady, _onReady=[], _got=0, _langs={};

// load authentication info
function initZ(d)
{
    Z.language = Z.cookie('lang');
    if(!Z.language) {
        var m=document.querySelector('meta[name="language"]');
        if(m) Z.language = m.getAttribute('content');
        else Z.language = 'en';
    }
    if(!('user' in Z)) {
        Z.user=null;
        if(window.location.origin.search(/^https?:\/\//)>-1) {
            Z.ajax(Z.uid, null, initZ, null, 'json');
            return;
        }
    }

    if(!d) return;
    var n, start=false;
    if('plugins' in d) {
        if(!('plugins' in Z)) Z.plugins = {};
        for(n in d.plugins) {
            if(n in Z.plugins) continue;
            Z.plugins[n]=d.plugins[n];
            Z.load.apply(Z, d.plugins[n]);
        }
        delete(d.plugins);
    }
    Z.user = d;
    if(!('modules' in Z)) {
        Z.modules = {};
    } else {
        Z.ready(Z.init);
    }
}

Z.init=function(o)
{
    if(!('modules' in Z)) return;
    var c=(arguments.length>0)?(Z.node(o, this)):(null),n;
    if(!c) {
        c=document;
        n=true;
    }
    for(var i in Z.modules){
        var ifn='init'+i;
        if(Z.modules[i] && (ifn in Z)) {
            if(typeof(Z.modules[i])=='string') {
                var L=c.querySelectorAll(Z.modules[i]), j=0;
                for(j=0;j<L.length;j++) Z[ifn].call(L[j]);
                L=null;
                j=null;
            } else if(Z.modules[i]) {
                Z[ifn](c);
            }
        }
    }
}

Z.addPlugin=function(id, fn, q) {
    id = '_'+id;
    if(!(id in Z.modules)) {
        Z.modules[id]=q;
        Z['init'+id]=fn;
    }
}

Z.get=function(q, o, i)
{
    var r;
    if(!o) { // non-contextual
        if(typeof i ==='undefined') return document.querySelectorAll(q);
        r=document.querySelectorAll(q);
    } else if('length' in o) {
        r=[];
        for(var oi=0;oi<o.length;oi++) {
            var ro=Z.get(q, o[oi]);
            if(ro.length>0) {
                for(var roi=0;roi<ro.length;roi++) {
                    r.push(ro[roi]);
                    if(typeof i !=='undefined' && i in r) return r[i];
                }
            }
        }
    } else if(q.search(/^[#\.]?[^\.\s\[\]\:]+$/)) {
        if(q.substr(0,1)=='#') r=[o.getElementById(q.substr(1))];
        else if(q.substr(0,1)=='.') r=o.getElementsByClassName(q.substr(1));
        else r=o.getElementsByTagName(q);
    } else {
        var id=o.getAttribute('id');
        if(!id) {
            id='_n'+(_got++);
            o.setAttribute('id', id);
            r = document.querySelectorAll('#'+id+' '+q);
            o.removeAttribute('id');
        } else {
            r = document.querySelectorAll('#'+id+' '+q);
        }
    }
    if(typeof i !=='undefined') return (r.length>i)?(r[i]):(false);
    return r;
}

Z.encodeHtml=function (s) {
    return s.replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/'/g, '&apos;')
            .replace(/"/g, '&quot;');
}
Z.decodeHtml=function (s) {
    return s.replace(/&quot;/g, '"')
            .replace(/&apos;/g, '\'')
            .replace(/&gt;/g, '>')
            .replace(/&lt;/g, '<')
            .replace(/&amp;/g, '&');
}
Z.cookie=function(name, value, expires, path, domain, secure) {
    if(arguments.length>1) {
        document.cookie = name + "=" + escape(value)
            + ((arguments.length>2 && expires != null)?("; expires=" + expires.toGMTString()):(''))
            + ((arguments.length>3 && path)?("; path=" + path):(''))
            + ((arguments.length>4 && domain)?("; domain=" + domain):(''))
            + ((arguments.length>5 && secure)?("; secure"):(''));
    } else {
        var a = name + "=", i = 0;
        while (i < document.cookie.length) {
            var j = i + a.length;
            if (document.cookie.substring(i, j) == a) return _cookieValue(j);
            i = document.cookie.indexOf(" ", i) + 1;
            if (i == 0) break;
        }
        return null;

    }
    return value;
}

function _cookieValue(offset) {
    var endstr = document.cookie.indexOf (";", offset);
    if (endstr == -1) { endstr = document.cookie.length; }
    return unescape(document.cookie.substring(offset, endstr));
}
Z.lang=function(s)
{
    if(s) Z.language=s;
    return Z.language;
}

Z.langw=function(ctx,before,after)
{
    var h=Z.get('link[rel="alternate"][hreflang],meta[name="language"]');
    if(h.length>1) {
        var r={e:'span',a:{'class':'lang'},c:[]},l='';
        for(var hi=0;hi<h.length;hi++) {
            if(h[hi].nodeName.toLowerCase()=='meta') {
                l=h[hi].getAttribute('content');
                _langs[l]=true;
                r.c.push({e:'a',a:{'class':l+' selected'},c:l});
            } else {
                l=h[hi].getAttribute('hreflang');
                _langs[l]=false;
                r.c.push({e:'a',a:{'class':l,'data-lang':l,href:'#'+l},c:l,t:{trigger:_setLanguage}});
            }
        }
        if(ctx) return Z.element.call(((typeof ctx) == 'string')?(Z.get(ctx,null,0)):(ctx),r,before,after);
        else return Z.element(r,before,after)
    }
    return false;
}

function _setLanguage(l)
{
    if(typeof l != 'string') {
        if('stopPropagation' in l) {
            l.stopPropagation();
            l.preventDefault();
        }
        l=this.getAttribute('data-lang');
    }
    if(!(l in _langs)) return false;
    Z.cookie('lang', l, null, '/');
    window.location.reload();
    return false;
}
Z.load=function()
{
    //_isReady = true;// fix this
    var i=arguments.length;
    while(i-- >0) {
        if(arguments[i].indexOf('.css')>-1) Z.element.call(document.getElementsByTagName('head')[0], {e:'link',a:{rel:'stylesheet',type:'text/css',href:arguments[i]}});
        else if(arguments[i].indexOf('.js')>-1) Z.element.call(document.body, {e:'script',p:{async:true,src:arguments[i]}});
    }
    delete(i);
};

Z.element=function(o,before,after) {
    var r,n;
    if(typeof(o)=='string') {
        r=document.createTextNode(o);
    } else if(o.e) {
        r=document.createElement(o.e);
        if(o.p) {
            for(n in o.p) {
                r[n]=o.p[n];
                delete(n);
            }
        }
        if(o.a) {
            for(n in o.a) {
                r.setAttribute(n,o.a[n]);
                delete(n);
            }
        }
        delete(o.a);
        if(o.t) {
            for(n in o.t) {
                if(n=='trigger' || n=='fastTrigger') Z[n](r,o.t[n]);
                else Z.addEvent(r,n,o.t[n]);
                delete(n);
            }
        }
    } else {
        if(o instanceof Array) o={c:o};
        r=document.createDocumentFragment();
    }
    if(o.c) {
        if(typeof(o.c)=='string') {
            r.appendChild(document.createTextNode(o.c));
        } else {
            var t=o.c.length,i=0;
            while(i < t) {
                if(typeof(o.c[i])=='string') r.appendChild(document.createTextNode(o.c[i]));
                else Z.element.call(r,o.c[i]);
                i++;
            }
            delete(i);
            delete(t);
        }
    }

    if(before) return before.parentNode.insertBefore(r,before);
    else if(after) after.parentNode.insertBefore(r,after.nextSibling);
    else if(this.appendChild) return this.appendChild(r);
    else return r;
}
Z.addEvent=function(o, tg, fn) {
    if (o.addEventListener) {
        o.addEventListener(tg, fn, false);
    } else if (o.attachEvent) {
        o.attachEvent('on'+tg, fn);
    } else {
        o['on'+tg] = fn;
    }
}
Z.bind=Z.addEvent;

Z.removeEvent=function(o, tg, fn) {
    if (o.addEventListener) {
        o.removeEventListener(tg, fn, false);
    }
    if (o.detachEvent) {
        o.detachEvent('on'+tg, fn);
    }
    if('on'+tg in o) {
        o['on'+tg] = null;
        if('removeAttribute' in o)
            o.removeAttribute('on'+tg);
    }
}
Z.unbind=Z.removeEvent;
Z.fastTrigger=function(o,fn){
    if(o.addEventListener) {
        o.addEventListener('touchstart', fn, false);
        o.addEventListener('mousedown', fn, false);
    } else if(o.attachEvent) {
        o.attachEvent('onclick', fn);
    }
}
Z.trigger=function(o,fn){
    if(o.addEventListener) {
        o.addEventListener('tap', fn, false);
        o.addEventListener('click', fn, false);
    } else if(o.attachEvent) {
        o.attachEvent('onclick', fn);
    }
}
Z.stopEvent=function(e){
    e.preventDefault();
    e.stopPropagation();
    return false;
}

Z.ready=function(fn)
{
    if(arguments.length>0) {
        if(!_isReady) setReady(Z.ready);
        _onReady.push(fn);
    }
    if(_isReady) {
        while(_onReady.length>0) {
            (_onReady.shift())(Z);
        }
    }
}

Z.isNode=function()
{
    for(var i=0;i<arguments.length;i++) {
        o=arguments[i];
        if(typeof(o)=='string' && o) {
            return document.querySelector(o);
        }
        if(typeof(o)=='object' && ('jquery' in o || 'nodeName' in o)) {
            if('eq' in o) return o.eq(0);
            return o;
        }
    }
    return false;
}

Z.node=function()
{
    for(var i=0;i<arguments.length;i++) {
        o=arguments[i];
        if(typeof(o)=='string' && o && (document.querySelector(o))) return o;
        else if('nodeName' in o) return o;
        else if('jquery' in o) return o.get(0);
    }
    return false;
}


Z.parentNode=function(p, q)
{
    if(!p || !(p=Z.node(p))) return false;
    else if((typeof(q)=='string' && p.matchesSelector(q))||p==q) return p;
    else if(p.nodeName.toLowerCase()!='html') return Z.parentNode(p.parentNode, q);
    else return;
}

Z.blur=function(o)
{
    if(o && o.className.search(/\btdz-blur\b/)<0) {
        o.className += ' tdz-blur';
    }
}

Z.focus=function(o)
{
    if(o && o.className.search(/\btdz-blur\b/)>0) {
        o.className = o.className.replace(/\s*\btdz-blur\b/, '');
    }
}


Z.click=function(c)
{
    return Z.fire(c, 'click');
}

Z.fire=function(c, ev)
{
    if('createEvent' in document) {
        var e=document.createEvent('HTMLEvents');
        e.initEvent(ev, true, true);
        return c.dispatchEvent(e);
    } else {
        return c.fireEvent('on'+ev);
    }
}

Z.checkInput=function(e, c, r)
{
    if(arguments.length==1 || c===null) c=e.checked;
    else if(e.checked==c) return;
    if(e.checked!=c) {
        e.checked = c;
        Z.fire(e, 'change');
    }
    if(arguments.length<3 || r) Z.fire(e, 'click');
    var i=3, p=e.parentNode;
    while(p && i-- > 0) {
        if(p.nodeName.toLowerCase()=='tr' || p.className.search(/\binput\b/)>-1) {
            var on=(p.className.search(/\bon\b/)>-1);
            if(c && !on) p.className += (p.className)?(' on'):('on');
            else if(!c && on) p.className = p.className.replace(/\bon\b\s*/, '').trim();
            break;
        }
        p=p.parentNode;
    }

}

var _delayTimers = {};
Z.delay = function (fn, ms, uid) {
    if (!uid) {uid ='dunno';};
    if (uid in _delayTimers) {clearTimeout(_delayTimers[uid]);};
    _delayTimers[uid] = setTimeout(fn, ms);
};

Z.toggleInput=function(q, c)
{
    var f=document.querySelectorAll(q), i=f.length, chk=(Z.isNode(c))?(c.checked):(false);
    while(i-- > 0) {
        if(f[i]==c) continue;
        Z.checkInput(f[i], chk, false);
    }
}

function setReady(fn)
{
    _isReady = (('readyState' in document) && document.readyState=='complete');
    if(_isReady) {
        return fn();
    }
    // Mozilla, Opera, Webkit 
    if (document.addEventListener) {
      document.addEventListener( "DOMContentLoaded", function(){
        document.removeEventListener( "DOMContentLoaded", arguments.callee, false);
        _isReady = true;
        fn();
      }, false );

    // If IE event model is used
    } else if ( document.attachEvent ) {
      // ensure firing before onload
      document.attachEvent("onreadystatechange", function(){
        if ( document.readyState === "complete" ) {
          document.detachEvent( "onreadystatechange", arguments.callee );
          _isReady = true;
          fn();
        }
      });
    }
    // flush if it reached onload event
    window.onload = function() {
        _isReady = true;
        Z.ready();
    }
}

var _v=false, _f={};

Z.val=function(o, val, fire)
{
    if(typeof(o)=='string') {
        o=document.getElementById(o);
        if(!o) return false;
    }
    var v, t=o.type, f=o.getAttribute('data-format'),e;
    if(arguments.length==1) val=false;
    if(t && t.substr(0, 6)=='select') {
        v=[];
        for (var i=0; i<o.options.length; i++) {
            if(val!==false) {
                if(o.options[i].value==val) o.options[i].selected=true;
                else if(o.options[i].selected) o.options[i].selected=false;
            } else if (o.options[i].selected) v.push(o.options[i].value);
        }
        if(val && fire) Z.fire(o, 'change');
        delete(i);
    } else if(t && (t=='checkbox' || t=='radio')) {
        var id=o.name;
        if(val!==false) {
            v=(typeof(val)=='string')?(val.split(/[,;]+/g)):(val);
            var vi={},i=v.length;
            while(i-- > 0) {
                vi[v[i]]=true;
            }
            var L=o.form.querySelectorAll('input[name="'+id+'"]');
            i=L.length;
            while(i-- > 0) {
                if(L[i].getAttribute('value') in vi) {
                    if(!L[i].checked) {
                        L[i].setAttribute('checked','checked');
                        L[i].checked = true;
                        if(fire) Z.fire(L[i], 'change');
                    }
                } else {
                    if(L[i].checked) {
                        L[i].removeAttribute('checked');
                        L[i].checked = false;
                        if(fire) Z.fire(L[i], 'change');
                    }
                }
            }
            delete(vi);
        } else {
            var L=o.form.querySelectorAll('input[name="'+id+'"]:checked'), i=L.length;
            if(i) {
                v=[];
                while(i-- > 0) {
                    v.unshift(L[i].value);
                }
            } else {
                v = '';
            }
        }
        delete(L);
        delete(i);
    } else if(f=='html' && (!(e=o.getAttribute('data-editor')) || e=='tinymce')) {
        Z.fire(o, 'validate');
        v=o.value;
    } else if('value' in o) {
        if(val!==false) {
            o.value=val;
            o.setAttribute('value', val);
            if(fire) Z.fire(o, 'change');
        }
        v = o.value;
    } else {
        if(val!==false) {
            o.setAttribute('value', val);
            if(fire) Z.fire(o, 'change');
        }
        v=o.getAttribute('value');
    }
    delete(t);
    if(v && typeof(v) == 'object' && v.length<2) v=v.join('');
    return v;
}

Z.formData=function(f)
{
    var d;
    if(('id' in f) && (f.id in _f)) {
        d=_f[f.id];
    } else {
        var v, i;
        d={};
        for(i=0;i<f.elements.length;i++) {
            if('name' in f.elements[i] && f.elements[i].name) {
                v = Z.val(f.elements[i]);
                if(v!==null) d[f.elements[i].name] = v;
            }
        }
    }
    var s='', n;
    if(d) {
        for(n in d) {
            s += (s)?('&'):('');
            s += (n+'='+encodeURIComponent(d[n]));
        }
    }
    return s;
}

Z.loggr=null;
Z.log=function()
{
    var i=0;
    while(i < arguments.length) {
        if(Z.loggr) {
            Z.element.call(Z.loggr, {e:'p',p:{className:'msg log'},c:''+arguments[i]});
        }
        console.log(arguments[i]);
        i++;
    }
}

Z.ajax=function(url, data, success, error, dataType, context, headers)
{
    if( typeof error == 'undefined' || !error ) error = this.error;
    if(!context) context = this;
    if (!window.XMLHttpRequest  || (url in _ajax)) {
        // no support for ajax
        error.apply(context);
        return false;
    }
    _ajax[url] = { r: new XMLHttpRequest(),
        success: success,
        error: error,
        context: context,
        type: dataType
    };
    var qs = (data===true)?(((url.indexOf('?')>-1)?('&'):('?'))+(new Date().getTime())):(''),
        m = (data && data!==true)?('post'):('get');

    // make post!!!
    _ajax[url].r.onreadystatechange = ajaxProbe;
    //_ajax[url].r.onload = ajaxOnload;
    _ajax[url].r.open(m, url+qs, true);
    _ajax[url].r.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    if(headers) {
        for(var n in headers) {
            _ajax[url].r.setRequestHeader(n, headers[n]);
        }
    }
    if(m=='post') {
        if(!headers || !('Content-Type' in headers)) {
            _ajax[url].r.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
        }
        if(typeof(data)=='string' || 'length' in data) _ajax[url].r.setRequestHeader('Content-Length', data.length);
        //_ajax[url].r.setRequestHeader('Connection', 'close');
        _ajax[url].r.send(data);
    } else {
        _ajax[url].r.send();
    }
}

function ajaxOnload()
{
    //Z.log('ajaxOnload', arguments);
    return ajaxProbe();
}


function ajaxProbe(e)
{
    //Z.log('ajaxProbe', JSON.stringify(e));
    var u;
    for(u in _ajax) {
        /*
        Z.log(u+': '+JSON.stringify({
            readyState:_ajax[u].r.readyState,
            withCredentials:_ajax[u].r.withCredentials,
            status:_ajax[u].r.status,
            responseType:_ajax[u].r.responseType,
            response:_ajax[u].r.response}));
        */
        if(_ajax[u].r.readyState==4) {
            var d;
            if(_ajax[u].type=='xml' && _ajax[u].r.responseXML) d=_ajax[u].r.responseXML;
            else if(_ajax[u].type=='json') {
                if(_ajax[u].r.responseText) d=JSON.parse(_ajax[u].r.responseText);
                else d=null;
            } else if('responseText' in _ajax[u].r) d=_ajax[u].r.responseText;
            else d=_ajax[u].r.response;
            if(_ajax[u].r.status==200) {
                _ajax[u].success.apply(_ajax[u].context, [ d, _ajax[u].r.status, u ]);
            } else {
                _ajax[u].error.apply(_ajax[u].context, [ d, _ajax[u].r.status, u ]);
            }
            delete(d);
            delete(_ajax[u].r);
            delete(_ajax[u]);
        }
    }
}

window.requestAnimFrame = (function(){
  return  window.requestAnimationFrame       ||
          window.webkitRequestAnimationFrame ||
          window.mozRequestAnimationFrame    ||
          function( callback ){
            return window.setTimeout(callback, 1000 / 60);
          };
})();

if (!document.querySelectorAll) {
    document.querySelectorAll = function(selector) {
        var doc = document,
        head = doc.documentElement.firstChild,
        styleTag = doc.createElement('STYLE');
        head.appendChild(styleTag);
        doc.__qsaels = [];
         
        styleTag.styleSheet.cssText = selector + "{x:expression(document.__qsaels.push(this))}";
        window.scrollBy(0, 0);
         
        return doc.__qsaels;
    }    
};

window.Element && function(ElementPrototype) {
    ElementPrototype.matchesSelector = ElementPrototype.matchesSelector ||
    ElementPrototype.mozMatchesSelector ||
    ElementPrototype.msMatchesSelector ||
    ElementPrototype.oMatchesSelector ||
    ElementPrototype.webkitMatchesSelector ||
    function (selector) {
        var node = this, nodes = (node.parentNode || node.document).querySelectorAll(selector), i = -1;
        while (nodes[++i] && nodes[i] != node);
        return !!nodes[i];
    }
}(Element.prototype);

/*! matchesSelector */
var matchesSelector = function(node, selector) {
    if(!('parentNode' in node) || !node.parentNode) return false;
    return Array.prototype.indexOf.call(node.parentNode.querySelectorAll(selector)) != -1
};

initZ();

})(window.Z);