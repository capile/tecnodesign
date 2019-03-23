/*! Tecnodesign Z v2.2 | (c) 2018 Capile Tecnodesign <ti@tecnodz.com> */
if(!('Z' in window)) window.Z={uid:'/_me',timeout:0,headers:{}};
(function(Z) {
"use strict";
var _ajax={}, _isReady, _onReady=[], _got=0, _langs={}, _assetUrl, _assets={},
  defaultModules={
    Callback:'*[data-callback]',
    'Form.Form': 'form.z-form',
    'Form.CheckLabel':'.i-check-label input[type=radio],.i-check-label input[type=checkbox]',
    'Interface.startup': '.tdz-i[data-url]',
  };

// load authentication info
var _reWeb=/^https?:\/\//;
function initZ(d)
{
    Z.lang();

    if(!('modules' in Z)) {
        Z.modules = defaultModules;
    }

    var store=true;
    if(!('user' in Z)) {
        Z.user=null;
        d=Z.storage('z-auth');
        if(d && String(d)) {
            if(('token' in d) && d.token) {
                if(!('headers' in Z)) Z.headers = {};
                Z.headers['z-token']=d.token;
            }
            if(String(window.location).search('reload')<0) {
                Z.uid=null;
                store = false;
           }
        }
        if(Z.uid && (_reWeb.test(window.location.origin) || _reWeb.test(Z.uid))) {
            Z.ajax(Z.uid, null, initZ, null, 'json');
            return;
        }
    }
    if(d) {
        if(Object.prototype.toString.call(d)=='[object Array]') {
            Z.user = false;
        } else {
            var n; //, start=false;
            if('plugins' in d) {
                if(!('plugins' in Z)) Z.plugins = {};
                for(n in d.plugins) {
                    if(n in Z.plugins) continue;
                    Z.plugins[n]=d.plugins[n];
                    if('load' in Z.plugins[n]) {
                        Z.load.apply(Z, d.plugins[n].load);
                    }
                }
                delete(d.plugins);
            }
            Z.user = d;
        }
    } else if(Z.uid) {
        return;
    }
    if(!('timeout' in Z)) Z.timeout = 0;
    if(store && Z.timeout) Z.storage('Z-Auth', d, Z.timeout);
    
    Z.ready(Z.init);
}

Z.storage=function(n, v, e)
{
    if(!('localStorage' in window)) return; // add new storage types
    var r=window.localStorage.getItem(n);
    var t=(new Date().getTime())/1000;
    if(arguments.length>1) {
        if(arguments.length<3) e=0;
        else if(e<100000000) e+=t;
        window.localStorage.setItem(n, parseInt(e)+','+JSON.stringify(v));
    } else if(r && r.search(/^([0-9]+),.+/)>-1) {
        var a=parseInt(r.substr(0,r.indexOf(',')));
        if(a > 0 && a < t) r=null;
        else r=JSON.parse(r.substr(r.indexOf(',')+1));
    }
    return r;
};

Z.init=function(o)
{
    if(!('modules' in Z)) {
        Z.modules = defaultModules;
    }
    if(!('modules' in Z)) return;

    if('ZModules' in window) {
        var fn;
        for(var q in ZModules) {
            if(typeof ZModules[q]=='function') {
                fn=('name' in ZModules[q])?(ZModules[q].name):(q);
                Z.addPlugin(fn, ZModules[q], q);
            }
            ZModules[q]=null;
            delete(ZModules[q]);
        }
        delete(window.ZModules);
    }

    var c=(arguments.length>0)?(Z.node(o, this)):(null),n;
    if(!c) {
        c=document;
        n=true;
    }
    for(var i in Z.modules){
        var ifn='init'+i;
        if(!Z.modules[i]) continue;
        var L=c.querySelectorAll(Z.modules[i]), j=L.length;

        if(!(ifn in Z) && j && i.search(/\./)>-1) {
            // must load component, then initialize the object
            var p='Z.'+i, a=i.split(/\./);
            if(p in window) {
                if(typeof(window[p])=='function') {
                    ifn=Z.addPlugin(i, window[p], Z.modules[i]);    
                    window[p]=null;
                    delete(window[p]);
                }
            } else {
                var u='z-'+Z.slug(a[0])+'.js';
                if(!(u in _assets)) {
                    loadAsset('z-'+Z.slug(a[0])+'.js', Z.init, arguments, this);
                }
            }
        }
        if(ifn in Z) {
            if(typeof(Z.modules[i])=='string') {
                for(j=0;j<L.length;j++) Z[ifn].call(L[j]);
                L=null;
                j=null;
            } else if(Z.modules[i]) {
                Z[ifn](c);
            }
        }
    }
};

var _delayed={};
function loadAsset(f, fn, args, ctx)
{
    var T, o, r;
    if(f.indexOf('/')<0) {
        if(!_assetUrl) {
            var e=document.querySelector('script[src*="/z.js"]');
            if(e) _assetUrl = e.getAttribute('src').replace(/\/z\.js.*/, '/');
            else _assetUrl = '/';
        }
        f=_assetUrl+f;
    }
    if(f.indexOf('.css')>-1) {
        T=document.getElementsByTagName('head')[0];
        if(!T.querySelector('link[href^="'+f+'"]')) {
            o={e:'link',a:{rel:'stylesheet',type:'text/css',href:f}};
        } else {
            T=null;
            r=true;
        }
    } else if(f.indexOf('.js')>-1) {
        T=document.body;
        if(!document.querySelector('script[src^="'+f+'"]')) {
            o={e:'script',p:{async:true,src:f}};
        } else {
            T=null;
            r=true;
        }
    }

    if(T && o) {
        Z.element.call(T, o);
        T=null;
        o=null;
    }

    if(r && (f in _delayed)) {
        var a;
        while(_delayed[f].length>0) {
            a=_delayed[f].shift();
            a[0].apply(a[1], a[2]);
        }
        delete(_delayed[f]);
    }
    if(arguments.length>1) {
        if(r) {
            fn.apply(args, ctx);
        } else {
            if(!(f in _delayed)) _delayed[f]=[];
            _delayed[f].push([fn, args, ctx]);
            setTimeout(loadAssetDelayed, 500);
        }

    }
    return r;
}

function loadAssetDelayed()
{
    for(var n in _delayed) loadAsset(n);
}

Z.load=function()
{
    //_isReady = true;// fix this
    var i=arguments.length;
    while(i--) {
        loadAsset(arguments[i]);
    }
    i=null;
};

Z.addPlugin=function(id, fn, q) {
    var pid = '_'+id;
    if(!(pid in Z.modules)) {
        if((id in Z.modules) && Z.modules[id]==q) {
            pid=id;
        }
        Z.modules[pid]=q;
        Z['init'+pid]=fn;
        return 'init'+pid;
    }
};

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
};

Z.encodeHtml=function (s) {
    return s.replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/'/g, '&apos;')
            .replace(/"/g, '&quot;');
};
Z.decodeHtml=function (s) {
    return s.replace(/&quot;/g, '"')
            .replace(/&apos;/g, '\'')
            .replace(/&gt;/g, '>')
            .replace(/&lt;/g, '<')
            .replace(/&amp;/g, '&');
};
Z.cookie=function(name, value, expires, path, domain, secure) {
    if(arguments.length>1) {
        document.cookie = name + "=" + escape(value) + ((arguments.length>2 && expires != null)?("; expires=" + expires.toGMTString()):('')) + ((arguments.length>3 && path)?("; path=" + path):('')) + ((arguments.length>4 && domain)?("; domain=" + domain):('')) + ((arguments.length>5 && secure)?("; secure"):(''));
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
};

Z.slug=function(s)
{
    return s.toLowerCase()
      .replace(/[ąàáäâãåæă]/g, 'a')
      .replace(/[ćčĉç]/g, 'c')
      .replace(/[ęèéëê]/g, 'e')
      .replace(/ĝ/g, 'g')
      .replace(/ĥ/g, 'h')
      .replace(/[ìíïî]/g, 'i')
      .replace(/ĵ/g, 'j')
      .replace(/[łľ]/g, 'l')
      .replace(/[ńňñ]/g, 'n')
      .replace(/[òóöőôõðø]/g, 'o')
      .replace(/[śșşšŝ]/g, 's')
      .replace(/[ťțţ]/g, 't')
      .replace(/[ŭùúüűû]/g, 'u')
      .replace(/[ÿý]/g, 'y')
      .replace(/[żźž]/g, 'z')
      .replace(/[^\w\s-]/g, '') // remove non-word [a-z0-9_], non-whitespace, non-hyphen characters
      .replace(/[\s_-]+/g, '-') // swap any length of whitespace, underscore, hyphen characters with a single -
      .replace(/^-+|-+$/g, ''); // remove leading, trailing -
};

Z.unique=function(array) {
    var a = array.concat();
    for(var i=0; i<a.length; ++i) {
        for(var j=i+1; j<a.length; ++j) {
            if(a[i] === a[j])
                a.splice(j--, 1);
        }
    }
    return a;
};

function _cookieValue(offset) {
    var endstr = document.cookie.indexOf (";", offset);
    if (endstr == -1) { endstr = document.cookie.length; }
    return unescape(document.cookie.substring(offset, endstr));
}

Z.lang=function(s)
{
    if(s) Z.language=s;
    else {
        if(!Z.language) {
            Z.language = Z.cookie('lang');
        }
        Z.language = Z.cookie('lang');

        if(!Z.language) {
            var m=document.querySelector('meta[name="language"]');
            if(m) Z.language = m.getAttribute('content');
            else {
                if((m=document.querySelector('html[lang]'))) {
                    Z.language = m.getAttribute('lang');
                } else {
                    Z.language = 'en';
                }
            }
        }
    }

    if(Z.language.length>2 && !(Z.language in Z.l)) {
        Z.language = Z.language.substr(0,2);
    }

    if(!(Z.language in Z.l)) {
        Z.language = 'en'; 
    }
    return Z.language;
};

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
        else return Z.element(r,before,after);
    }
    return false;
};

function _setLanguage(l)
{
    /*jshint validthis: true */
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

Z.element=function(o,before,after) {
    var r,n;
    if(typeof(o)=='string') {
        r=document.createTextNode(o);
    } else if(o.e) {
        r=document.createElement(o.e);
        if(o.p) {
            for(n in o.p) {
                r[n]=o.p[n];
                n=null;
            }
        }
        if(o.a) {
            for(n in o.a) {
                r.setAttribute(n,o.a[n]);
                n=null;
            }
        }
        if(o.t) {
            for(n in o.t) {
                if(n=='trigger' || n=='fastTrigger') Z[n](r,o.t[n]);
                else Z.addEvent(r,n,o.t[n]);
                n=null;
            }
        }
    } else if(Z.isNode(o)) {
        r=o;
        o={};
    } else {
        if(o instanceof Array) o={c:o};
        r=document.createDocumentFragment();
    }
    if(o.c) {
        if(typeof(o.c)=='string') {
            if(('x' in o) && o.x) {
                r.innerHTML = o.c;
            } else {
                r.appendChild(document.createTextNode(o.c));
            }
        } else {
            var t=o.c.length,i=0;
            while(i < t) {
                if(typeof(o.c[i])=='string') r.appendChild(document.createTextNode(o.c[i]));
                else Z.element.call(r,o.c[i]);
                i++;
            }
            i=null;
            t=null;
        }
    }

    if(before) return before.parentNode.insertBefore(r,before);
    else if(after) return after.parentNode.insertBefore(r,after.nextSibling);
    else if(this.appendChild) return this.appendChild(r);
    else return r;
};

Z.addEvent=function(o, tg, fn) {
    if (o.addEventListener) {
        o.addEventListener(tg, fn, false);
    } else if (o.attachEvent) {
        o.attachEvent('on'+tg, fn);
    } else {
        o['on'+tg] = fn;
    }
};

Z.bind=Z.addEvent;

Z.removeEvent=function(o, tg, fn) {
    if (o.addEventListener) {
        o.removeEventListener(tg, fn, false);
    } else if (o.detachEvent) {
        o.detachEvent('on'+tg, fn);
    } else if('on'+tg in o) {
        o['on'+tg] = null;
        if('removeAttribute' in o)
            o.removeAttribute('on'+tg);
    }
};

Z.unbind=Z.removeEvent;
Z.fastTrigger=function(o,fn){
    if(o.addEventListener) {
        o.addEventListener('touchstart', fn, false);
        o.addEventListener('mousedown', fn, false);
    } else if(o.attachEvent) {
        o.attachEvent('onclick', fn);
    }
};

Z.trigger=function(o,fn){
    if(o.addEventListener) {
        o.addEventListener('tap', fn, false);
        o.addEventListener('click', fn, false);
    } else if(o.attachEvent) {
        o.attachEvent('onclick', fn);
    }
};

Z.stopEvent=function(e){
    e.preventDefault();
    e.stopPropagation();
    return false;
};

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
};

Z.isReady=function()
{
    return _isReady;
};

Z.isNode=function()
{
    for(var i=0;i<arguments.length;i++) {
        var o=arguments[i];
        if(typeof(o)=='string' && o) {
            return document.querySelector(o);
        }
        if(typeof(o)=='object' && ('jquery' in o || 'nodeName' in o)) {
            if('eq' in o) return o.eq(0);
            return o;
        }
    }
    return false;
};

Z.node=function()
{
    for(var i=0;i<arguments.length;i++) {
        var o=arguments[i];
        var t=typeof(o);
        if(t=='undefined' || !o) continue;
        else if(t=='string' && (o=document.querySelector(o))) return o;
        else if(t=='object' && ('nodeName' in o)) return o;
        else if('jquery' in o) return o.get(0);
    }
    return false;
};

Z.parentNode=function(p, q)
{
    if(!p || !(p=Z.node(p))) return false;
    else if((typeof(q)=='string' && p.matchesSelector(q))||p==q) return p;
    else if(p.nodeName.toLowerCase()!='html') return Z.parentNode(p.parentNode, q);
    else return;
};

Z.blur=function(o)
{
    if(o && o.className.search(/\btdz-blur\b/)<0) {
        o.className += ' tdz-blur';
    }
};

Z.focus=function(o)
{
    if(o && o.className.search(/\btdz-blur\b/)>0) {
        o.className = o.className.replace(/\s*\btdz-blur\b/, '');
    }
};

Z.text=function(o, s)
{
    if(!o) return;
    var n=(arguments.length>1)?(o.querySelector(s)):(o);
    return n.textContent || n.innerText;
};


Z.click=function(c)
{
    return Z.fire(c, 'click');
};

Z.fire=function(c, ev)
{
    if('createEvent' in document) {
        var e=document.createEvent('HTMLEvents');
        e.initEvent(ev, true, true);
        return c.dispatchEvent(e);
    } else {
        return c.fireEvent('on'+ev);
    }
};

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
            var on=(p
                .className.search(/\bon\b/)>-1);
            if(c && !on) p.className += (p.className)?(' on'):('on');
            else if(!c && on) p.className = p.className.replace(/\bon\b\s*/, '').trim();
            break;
        }
        p=p.parentNode;
    }

};

var _delayTimers = {};
Z.delay = function (fn, ms, uid) {
    if (!uid) uid ='dunno';
    if (uid in _delayTimers) clearTimeout(_delayTimers[uid]);
    _delayTimers[uid] = setTimeout(fn, ms);
};

Z.toggleInput=function()
{
    var f, t=(Z.isNode(this))?(this.getAttribute('data-target')):(null);
    if(t && this.form) {
        f=this.form.querySelectorAll(t+' input[type="checkbox"],input[type="checkbox"]'+t);
    } else if(this.parentNode) {
        if(this.parentNode.nodeName.toLowerCase()=='th') {
            f=Z.parentNode(this,'table').querySelectorAll('td > input[type="checkbox"]');
        } else {
            f=Z.parentNode(this,'div').querySelectorAll('input[name][type="checkbox"]');
        }
    }
    if(!f) return;
    var i=f.length, chk=(Z.isNode(this))?(this.checked):(false);
    while(i-- > 0) {
        if(f[i]==this) continue;
        Z.checkInput(f[i], chk, false);
    }
};

function setReady(fn)
{
    _isReady = (('readyState' in document) && document.readyState=='complete');
    if(_isReady) {
        if(!('time' in Z)) Z.time = new Date().getTime();
        return fn();
    }
    // Mozilla, Opera, Webkit 
    if (document.addEventListener) {
        var _rel=function(){
            document.removeEventListener("DOMContentLoaded", _rel, false);
            _isReady = true;
            fn();
        };
        document.addEventListener( "DOMContentLoaded", _rel, false );
        _rel = null;
    // If IE event model is used
    } else if ( document.attachEvent ) {
        // ensure firing before onload
        var _dev=function(){
            if ( document.readyState === "complete" ) {
                document.detachEvent( "onreadystatechange", _dev);
                _isReady = true;
                fn();
            }
        };
        document.attachEvent("onreadystatechange", _dev);
    }
    // flush if it reached onload event
    window.onload = function() {
        _isReady = true;
        Z.ready();
    };
}

var _v=false, _f={};

Z.val=function(o, val, fire)
{
    if(typeof(o)=='string') {
        o=document.getElementById(o);
        if(!o) return false;
    }
    var v, t=o.type, f=o.getAttribute('data-format'),e, i, L;
    if(arguments.length==1) val=false;
    if(t && t.substr(0, 6)=='select') {
        v=[];
        for (i=0; i<o.options.length; i++) {
            if(val!==false) {
                if(o.options[i].value==val) o.options[i].selected=true;
                else if(o.options[i].selected) o.options[i].selected=false;
            } else if (o.options[i].selected) v.push(o.options[i].value);
        }
        if(val && fire) Z.fire(o, 'change');
        i=null;
    } else if(t && (t=='checkbox' || t=='radio')) {
        var id=o.name;
        if(val!==false) {
            v=(typeof(val)=='string')?(val.split(/[,;]+/g)):(val);
            var vi={};
            i=v.length;
            while(i-- > 0) {
                vi[v[i]]=true;
            }
            L=o.form.querySelectorAll('input[name="'+id+'"]');
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
            vi=null;
        } else {
            L=o.form.querySelectorAll('input[name="'+id+'"]:checked');
            i=L.length;
            if(i) {
                v=[];
                while(i-- > 0) {
                    v.unshift(L[i].value);
                }
            } else {
                v = '';
            }
        }
        L=null;
        i=null;
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
    t=null;
    if(v && typeof(v) == 'object' && v.length<2) v=v.join('');
    return v;
};

Z.isVisible=function(o)
{
    return o.offsetWidth > 0 && o.offsetHeight > 0;
};

Z.formData=function(f, includeEmpty, returnObject)
{
    var d, n;
    if(arguments.length<3) returnObject=false;
    if(arguments.length<2) includeEmpty=true;

    if(('id' in f) && (f.id in _f)) {
        d=_f[f.id];
    } else {
        var v, i, skip={}, nn, nt;
        d={};
        for(i=0;i<f.elements.length;i++) {
            if('name' in f.elements[i] && (n=f.elements[i].name)) {
                if(n in skip) continue;
                nn=f.elements[i].nodeName.toLowerCase();
                nt=(nn=='input')?(f.elements[i].type):(f.elements[i].getAttribute('type'));
                if(nn=='input' && nt=='file') continue;

                v = Z.val(f.elements[i]);
                if(nt=='checkbox' || nt=='radio') skip[n]=true;
                if(v!==null && (v || includeEmpty)) {
                    if((n in d) && n.substr(-2)=='[]') {
                        if(typeof(d[n])=='string') d[n]=[d[n]];
                        d[n].push(v);
                    } else {
                        d[n] = v;
                    }
                }
            }
        }
    }
    if(returnObject) return d;
    var s='';
    if(d) {
        for(n in d) {
            if(n.substr(-2)=='[]') {
                var a = (typeof(d[n])=='string')?(d[n].split(',')):(d[n]),b=0;
                while(b<a.length) {
                    s += (s)?('&'):('');
                    s += (n+'='+encodeURIComponent(a[b]));
                    b++;
                }
                a=null;
                b=null;
            } else {
                s += (s)?('&'):('');
                s += (n+'='+encodeURIComponent(d[n]));
            }
        }
    }
    return s;
};

Z.deleteNode=function(o)
{
    return o.parentNode.removeChild(o);
};

Z.initCallback=function(o)
{
    if(!o || !Z.node(o)) o=this;
    var fn = o.getAttribute('data-callback'), 
        e=o.getAttribute('data-callback-event'),
        nn=o.nodeName.toLowerCase(),
        C,
        noe;
    if(!fn) return;
    if(!e) {
        noe=true;
        e='click';
    } else {
        o.removeAttribute('data-callback-event');
    }

    if(fn in Z) {
        C=Z[fn];
    } else if(fn in window) {
        C=window[fn];
    } else if(fn.indexOf('.')>-1) {
        var c=fn.substr(0,fn.indexOf('.'));
        C=(c in window)?(window[c]):(null);
        fn = fn.substr(fn.indexOf('.')+1);
        while(C && fn.indexOf('.')>-1) {
            c=fn.substr(0,fn.indexOf('.'));
            fn = fn.substr(fn.indexOf('.')+1);
            C=(c in C)?(C[c]):(null);
        }
        C=(fn in C)?(C[fn]):(null);
    }

    if(!C) return;
    o.removeAttribute('data-callback');
    var f;

    if(noe && ((nn=='input' && o.type!='radio' && o.type!='checkbox' && o.type!='button')||nn=='textarea'||nn=='select')) {
        e='change';
        f=Z.val(o);
    } else if(noe && nn=='form') {
        e='submit';
    } else {
        if(nn=='input' && o.checked) {
            f=true;
        }
    }
    Z.bind(o, e, C);
    if(f) {
        Z.fire(o, e);
    }

};

Z.removeChildren=function(o)
{
    var i=o.children.length;
    while(i--) {
        Z.deleteNode(o.children[i]);
    }
};

Z.selectOption=function(e)
{
    var o=this.getAttribute('data-original'), val=Z.val(this);
    if(o===null) {
        this.setAttribute('data-original',val);
        if(!e) return;
    } else if(o==val) {
        return;
    } else {
        this.setAttribute('data-original',val);
    }
    var F=this.form, i=this.options.length, j, n, v, t, q, p;
    p=this.getAttribute('name');
    if(p && p.indexOf('[')>-1) {
        p = p.replace(/\[[^\]]+\]$/, '');
    } else {
        p=null;
    }
    while(i--) {
        if(this.options[i].selected) {
            j=this.options[i].attributes.length;
            while(j--) {
                n=this.options[i].attributes[j].nodeName;
                if(n.substr(0,5)=='data-' && (v=this.options[i].getAttribute(n))) {
                    q = (p)?('input[name="'+p+'['+n.substr(5)+']"]'):('input[name="'+n.substr(5)+'"]');
                    if((t=F.querySelector(q))) {
                        var dtp=t.getAttribute('data-datalist-preserve');
                        if(dtp && (dtp=='0'||dtp=='false'||dtp=='off')) dtp=null;
                        if(!dtp || !Z.val(t)) {
                            Z.val(t,v,true);
                        }
                    }
                }
            }
        }

    }
};

// pt_BR
if(!('l' in Z)) Z.l={en:{},pt:{}};
Z.l.pt.add='Acrescentar';
Z.l.pt.del='Excluir';
Z.l.pt.Nothing='Nenhuma opção foi encontrada para esta consulta.';
Z.l.pt.Error='Houve um erro ao processar esta informação. Por favor tente novamente ou entre em contato com o suporte.';
Z.l.pt.moreRecord="É necessário selecionar mais de um registro para essa operação.";
Z.l.pt.noRecordSelected='Nenhum registro foi selecionado para essa operação.';
Z.l.pt.decimalSeparator = ',';
Z.l.pt.thousandSeparator = '.';
Z.l.pt.UploadSize='O arquivo é maior que o permitido.';
Z.l.pt.UploadInvalidFormat='O formato do arquivo não é suportado.';


Z.l.en.add='Insert';
Z.l.en.del='Remove';
Z.l.en.Nothing='No options was selected for this query.';
Z.l.en.Error='There was an error while processing this request. Please try again or contact support.';
Z.l.en.moreRecord="You need to select more than one record for this action.";
Z.l.en.noRecordSelected='No record was selected for this action.';
Z.l.en.decimalSeparator = '.';
Z.l.en.thousandSeparator = ',';
Z.l.en.UploadSize='File size is bigger than allowed.';
Z.l.en.UploadInvalidFormat='File format is not supported.';

// for timepickers
Z.l.en.previousMonth = 'Previous Month';
Z.l.en.nextMonth     = 'Next Month';
Z.l.en.months        = ['January','February','March','April','May','June','July','August','September','October','November','December'];
Z.l.en.weekdays      = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
Z.l.en.weekdaysShort = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
Z.l.en.midnight      = 'Midnight';
Z.l.en.noon          = 'Noon';
Z.l.en.dateFormat    ='YYYY-MM-DD';
Z.l.en.timeFormat    ='HH:mm';


Z.l.pt.previousMonth = 'Anterior';
Z.l.pt.nextMonth     = 'Próximo';
Z.l.pt.months        = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
Z.l.pt.weekdays      = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
Z.l.pt.weekdaysShort = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
Z.l.pt.midnight      = 'Meia-noite';
Z.l.pt.noon          = 'Meio-dia';
Z.l.pt.dateFormat    ='DD/MM/YYYY';
Z.l.pt.timeFormat    ='HH:mm';
Z.l.pt_BR = Z.l.pt;

Z.error=function()
{
    console.log('ERROR', arguments, arguments[0], arguments[1], this);
    //msg(Z.l[Z.language].Error, 'tdz-i-error');
    //Z.delay(msg, 5000, 'msg');
};



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
};
if('$' in window) {
    // jquery available, probably needs backwards compatible functions
    Z.trace=Z.log;

    if (!String.prototype.encodeHTML) {
      String.prototype.encodeHTML = function () {
        return this.replace(/&/g, '&amp;')
                   .replace(/</g, '&lt;')
                   .replace(/>/g, '&gt;')
                   .replace(/"/g, '&quot;')
                   .replace(/'/g, '&apos;');
      };
    }
    Z.xmlEscape = function(s) {return s.encodeHTML();};
    if (!String.prototype.decodeHTML) {
      String.prototype.decodeHTML = function () {
        return this.replace(/&apos;/g, "'")
                   .replace(/&quot;/g, '"')
                   .replace(/&gt;/g, '>')
                   .replace(/&lt;/g, '<')
                   .replace(/&amp;/g, '&');
      };
    }
    Z.xmlUnescape = function(s) {return s.decodeHTML();};
}

var _ResponseType={arraybuffer:true,blob:true,document:true,json:true,text:true};

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
    if(dataType in _ResponseType) {
        XMLHttpRequest.responseType = (_ResponseType[dataType]===true)?(dataType):(_ResponseType[dataType]);
    }
    //_ajax[url].r.onload = ajaxOnload;
    _ajax[url].r.open(m, url+qs, true);
    _ajax[url].r.setRequestHeader('x-requested-with', "XMLHttpRequest");
    var n;
    if('headers' in Z) {
        for(n in Z.headers) {
            if(Z.headers[n]) {
                _ajax[url].r.setRequestHeader(n, Z.headers[n]);
            }
        }
    }
    if(headers) {
        if(m=='post' && data && String(data)=='[object FormData]') {
            headers['content-type']=false;
        }
        for(n in headers) {
            if(headers[n]) {
                _ajax[url].r.setRequestHeader(n, headers[n]);
            }
        }
    }
    if(m=='post') {
        if(!headers || !('content-type' in headers)) {
            _ajax[url].r.setRequestHeader('content-type', 'application/x-www-form-urlencoded;charset=UTF-8');
        }
        //if(typeof(data)=='string' || 'length' in data) _ajax[url].r.setRequestHeader('Content-Length', data.length);
        //_ajax[url].r.setRequestHeader('Connection', 'close');
        _ajax[url].r.send(data);
    } else {
        _ajax[url].r.send();
    }
};

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
            var d, R=_ajax[u];
            delete(_ajax[u]);

            if(R.type=='xml' && R.r.responseXML) d=R.r.responseXML;
            else if(R.type=='json') {
                if(R.r.responseText) d=JSON.parse(R.r.responseText);
                else d=null;
            } else if('responseText' in R.r) d=R.r.responseText;
            else d=R.r.response;
            if(R.r.status==200) {
                R.success.apply(R.context, [ d, R.r.status, u, R.r ]);
            } else {
                R.error.apply(R.context, [ d, R.r.status, u, R.r ]);
            }
            d=null;
            if('r' in R) delete(R.r);
            R=null;
        }
    }
}

Z.t=function(s, lang)
{
    if(!lang) lang=Z.language;
    if((lang in Z.l) && (s in Z.l[lang])) {
        return Z.l[lang][s];
    } else if(lang.indexOf(/[-_]/)>0) {
        return Z.t(s, lang.replace(/[-_].*$/, ''));
    }
    return s;
};

 Z.formatNumber=function(n, d, ds, ts)
{
    if(!d) d=2;
    var x = (n.toFixed(d) + '').split('.');
    var x1 = x[0];
    if(!ds) ds=Z.l[Z.language].decimalSeparator;
    var x2 = x.length > 1 ? ds + x[1] : '';
    var rgx = /(\d+)(\d{3})/;
    while (rgx.test(x1)) {
        if(!ts) ts = Z.l[Z.language].thousandSeparator;
        x1 = x1.replace(rgx, '$1' + ts + '$2');
    }
    return x1 + x2;
};

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
    };
}

if(window.Element) {
    (function(ElementPrototype) {
        ElementPrototype.matchesSelector = ElementPrototype.matchesSelector ||
        ElementPrototype.mozMatchesSelector ||
        ElementPrototype.msMatchesSelector ||
        ElementPrototype.oMatchesSelector ||
        ElementPrototype.webkitMatchesSelector ||
        function (selector) {
            var node = this, nodes = (node.parentNode || node.document).querySelectorAll(selector), i = -1;
            while (nodes[++i] && nodes[i] != node);
            return !!nodes[i];
        };
    })(Element.prototype);
}

var matchesSelector = function(node, selector) {
    if(!('parentNode' in node) || !node.parentNode) return false;
    return Array.prototype.indexOf.call(node.parentNode.querySelectorAll(selector)) != -1;
};





initZ();

})(window.Z);
if (typeof exports !== 'undefined') {
  if (typeof module !== 'undefined' && module.exports) {
    exports = module.exports = Z;
  }
}
/*! end Z */
