/*! Tecnodesign Z base v2.1 | (c) 2015 Capile Tecnodesign <ti@tecnodz.com> */
if(!('Z' in window)) window.Z={uid:'/_me',timeout:0,headers:{}};
(function(Z) {
var _ajax={}, _isReady, _onReady=[], _got=0, _langs={}, 
  defaultModules={
    Subform:'div.subform[data-template]',
    Datalist:'*[data-datalist-api],*[data-datalist]',
    Button:'button.cleanup',
    Callback:'*[data-callback]',
    CheckLabel:'.i-check-label input[type=radio],.i-check-label input[type=checkbox]',
    Datepicker:'input[data-type^=date],input[type^=date],.tdz-i-datepicker'
  };

// load authentication info
var _reWeb=/^https?:\/\//;
function initZ(d)
{
    Z.language = Z.cookie('lang');
    if(!Z.language) {
        var m=document.querySelector('meta[name="language"]');
        if(m) Z.language = m.getAttribute('content');
        else Z.language = 'en';
    }
    var store=true;
    if(!('user' in Z)) {
        Z.user=null;
        d=Z.storage('Z-Auth');
        if(d && String(d)) {
            if(('token' in d) && d.token) {
                if(!('headers' in Z)) Z.headers = {};
                Z.headers['Z-Token']=d.token;
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
        }
    } else if(Z.uid) return;
    if(!('timeout' in Z)) Z.timeout = 0;
    if(store && Z.timeout) Z.storage('Z-Auth', d, Z.timeout);

    if(!('datepicker' in Z) && ('Pikaday' in window)) Z.datepicker = 'Pikaday';

    if(!('modules' in Z)) {
        Z.modules = defaultModules;
    }
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
}

Z.unique=function(array) {
    var a = array.concat();
    for(var i=0; i<a.length; ++i) {
        for(var j=i+1; j<a.length; ++j) {
            if(a[i] === a[j])
                a.splice(j--, 1);
        }
    }
    return a;
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
        if(o.t) {
            for(n in o.t) {
                if(n=='trigger' || n=='fastTrigger') Z[n](r,o.t[n]);
                else Z.addEvent(r,n,o.t[n]);
                delete(n);
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

Z.text=function(o, s)
{
    if(!o) return;
    var n=(arguments.length>0)?(o.querySelector(s)):(o);
    return n.textContent || n.innerText;
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

Z.toggleInput=function()
{
    var f;
    if(this.parentNode) {
        if(this.parentNode.nodeName.toLowerCase()=='th') {
            f=Z.parentNode(this,'table').querySelectorAll('td > input[type="checkbox"]');
        } else {
            f=Z.parentNode(this,'div').querySelectorAll('input[name][type="checkbox"]');
        }
    }
    if(!f) return;
    var i=f.length, chk=(Z.isNode(this))?(this.checked):(false);
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
}

Z.deleteNode=function(o)
{
    return o.parentNode.removeChild(o);
}

/*!checkLabel*/
Z.initCheckLabel=function()
{
    var s=false;
    if(!this.getAttribute('data-check-label')) {
        this.setAttribute('data-check-label',1);
        Z.bind(this, 'click', Z.initCheckLabel);
        s=true;
    }
    var cn = (this.checked)?('on'):('off'), l = Z.parentNode(this, 'label');
    if(l) {
        if(l.className!=cn) l.className = cn;
        if(!s && this.getAttribute('type')=='radio') {
            cn = (cn=='on')?('off'):('on');
            var L = l.parentNode.querySelectorAll('label'), i=L.length;
            while(i--) {
                if(l!=L[i]) {
                    if(L[i].className!=cn)L[i].className=cn;
                }
            }
        }
    }

}

/*!picker*/
var _Picker={}, _Pickerc=0;
Z.initDatepicker=function()
{
    if(!('datepicker' in Z) || this.getAttribute('data-datepicker')) return;
    this.setAttribute('data-datepicker', Z.datepicker);

    var id=this.getAttribute('id');
    if(!id) {
        id='p'+(_Pickerc++);
        this.id=id;
    }

    if(Z.datepicker=='Pikaday') {
        var t=this.getAttribute('data-type'), cfg={ field: this, i18n: Z.l[Z.language], format:Z.l[Z.language].dateFormat };
        if(!t) t=this.getAttribute('type');
        if(t && t.search(/time/)>-1) {
            cfg.use24Hour = true;
            cfg.format+= ' '+Z.l[Z.language].timeFormat;
        }
        _Picker[id] = new Pikaday(cfg);
    }
}


Z.initButton=function(o)
{
    if(!o || !Z.node(o)) o=this;
    Z.bind(o, 'click', button);
}

Z.initCallback=function(o)
{
    if(!o || !Z.node(o)) o=this;
    var fn = o.getAttribute('data-callback'), e=o.getAttribute('data-callback-event');
    if(!fn) return;
    if(!e) e='click';
    else o.removeAttribute('data-callback-event');

    if(fn in Z) Z.bind(o, e, Z[fn]);
    else if(fn in window) Z.bind(o, e, window[fn]);
    else return;

    o.removeAttribute('data-callback');

    if(o.nodeName.toLowerCase()=='input' && o.checked) {
        Z.fire(o, e);
    }
}

function button(e)
{
    if(this.className.search(/\bcleanup\b/)>-1) Z.clearForm(this.form);
}

Z.clearForm=function(f)
{
    for (i = 0; i < f.elements.length; i++)
    {
        t = f.elements[i].type.toLowerCase();
        switch (t)
        {
        case "radio":
        case "checkbox":
            if (f.elements[i].checked)
            {
                f.elements[i].checked = false;
            }
            break;
        case "select-one":
        case "select-multi":
            f.elements[i].selectedIndex = -1;
            break;
        default:
            f.elements[i].value = "";
            break;
        }
    }
}

Z.initSubform=function(o)
{
    if(!o || !Z.node(o)) o=this;
    var btns=[{e:'a',a:{title:'-','class':'tdz-button-del'},t:{click:subformDel}},{e:'a',a:{title:'+','class':'tdz-button-add'},t:{click:subformAdd}}];
    var b=o.parentNode.parentNode.querySelector('div.tdz-subform-buttons');
    if(!b) b = Z.element({e:'div',p:{className:'tdz-subform-buttons tdz-buttons'},c:[btns[1]]}, o.parentNode);

    // items
    var L=o.querySelectorAll('.item'), i=L.length, fmin=o.getAttribute('data-min'), fmax=o.getAttribute('data-max');
    // buttons: add, add(contextual), remove(contextual)
    while(i-- > 0) {
        if(fmax && i > fmax) {
            Z.deleteNode(L[i]);
        } else if(!L[i].querySelector('.tdz-buttons')) {
            var xx=Z.element.call(L[i], {e:'div',p:{className:'tdz-buttons'},c:btns});
        }
    };

    // minimun
    /*
    if(fmin && sf.length<=fmin && !bdel.hasClass('disabled')) bdel.addClass('disabled');
    else if(fmin && sf.length>fmin && bdel.hasClass('disabled')) bdel.removeClass('disabled');
    */
}
    
var _subformPos='§';
function subformAdd(e)
{
    Z.stopEvent(e);
    Z.tg = this;

    var el, o;
    if(this.parentNode.parentNode.className.search(/\bitem\b/)>-1) {
        el=this.parentNode.parentNode;
        o=el.parentNode;
    } else {
        o = this.parentNode.nextSibling.childNodes[0];
    }
    if(!o) return false;
    var tpl=o.getAttribute('data-template'), prefix=o.getAttribute('data-prefix'), sf=o.querySelectorAll('.item'),i=sf.length, fmax=o.getAttribute('data-max'), n;

    if(!(fmax && sf.length>=fmax)) {
        if(i>0){
            var ne=sf[i-1].querySelector('*[name]');
            if(ne) {
                n=ne.getAttribute('name');
                var re=new RegExp(prefix.replace(/([^a-z0-9])/i, '\\\$1')+'\\\[([0-9]*)\\\].*');
                n=n.replace(re, '$1');
                if(n) {
                    if(n.substr(0,3)=='q__') n=n.substr(3);
                    i=parseInt(n)+1;
                }
                while(o.querySelector('*[name^="'+prefix+'['+i+']"]') && i < 999) {
                    i++;
                }
            }
        }
        var re=new RegExp((prefix+'\[§\]').replace(/([^a-z0-9])/gi, '\\\$1'), 'gi');
        var ri=new RegExp((prefix+'_§_').replace(/([^a-z0-9])/gi, '\\\$1'), 'gi');
        var c=document.createElement('div');
        c.innerHTML = tpl.replace(re, prefix+'['+i+']').replace(ri, prefix+'_'+i+'_');
        c=c.children[0];
        if(el) {
            if(el.nextSibling) {
                el.parentNode.insertBefore(c, el.nextSibling);
                c=null;
            }
        } else if(sf.length>0) {
            el = sf[0];
            el.parentNode.insertBefore(c, el);
            c=null;
        }
        if(c) {
            o.appendChild(c);
        }
        Z.init(o.parentNode);
    }
    /*
    tdz.subform(o);
    tdz.initForm(o.parents('form').eq(0));
    */
    return false;
}

function subformDel(e)
{
    Z.stopEvent(e);
    var el, o;
    if(this.parentNode.parentNode.className.search(/\bitem\b/)>-1) {
        el=this.parentNode.parentNode;
        o=el.parentNode;
    } else {
        o = this.parentNode.nextSibling;
    }

    var sf=o.querySelectorAll('.item'),fmin=o.getAttribute('data-min');
   
    if(!(fmin && sf.length<=fmin)) {
        el.parentNode.removeChild(el);
    }
    //Z.subform(o);
    return false;
}

/*!datalist*/
Z.initDatalist=function(o)
{
    var t=Z.node(this, o);
    if(!t || !('nodeName' in t) || t.getAttribute('data-datalist-t')) return false;
    t.setAttribute('data-datalist-t', 1);
    t.setAttribute('data-datalist-q', Z.val(t));
    if(t.nodeName.toLowerCase()=='input') {
        //Z.bind(t, 'keypress', tdz.delayedChange);
        Z.bind(t, 'keydown', datalistKeypress);
        Z.bind(t, 'focus', datalistQuery);
        Z.bind(t, 'blur', datalistBlurTimeout);
    }
    Z.bind(t, 'change', datalistQueryTimeout);
    t.parentNode.className += ' tdz-input-search';
}
function datalistKeypress(e)
{
    e = e || window.event;

    var m=0,t=false;

    if (e.keyCode == '38' || e.keyCode == '37') {
        // up arrow or left arrow
        m=-1;
    } else if (e.keyCode == '40' || e.keyCode == '39') {
        // down arrow or right arrow
        m=1;
    } else if(e.keyCode == '13' || e.keyCode=='9') {
        e.preventDefault();
        t = true;
    } else if(e.keyCode=='27') {
        // escape
        e.preventDefault();
        return datalistClear.apply(this);
    } else {
        return datalistQueryTimeout.call(this);
    }
    var c=this.parentNode.querySelector('ul.tdz-datalist'), s=c.querySelector('.tdz-selected');
    if(!s) {
        if(c.children.length==0) return;
        s = c.children[0];
        m=0;
    } else {
        s.className = s.className.replace(/\s*\btdz-selected\b/, '');
    }
    if(m>0) {
        while(m-- > 0) {
            s=s.nextSibling;
        }
    } else if(m<0) {
        while(m++ < 0) {
            s=s.previousSibling;
        }
    }
    s.className += ' tdz-selected';
    if(t) {
        datalistOption.apply(s);
    }
}
/*!end keypress*/
var _dq=null;
function datalistQueryTimeout()
{
    var o=this;
    if(_dq) clearTimeout(_dq);
    _dq = setTimeout(function(){ datalistQuery.apply(o); }, 500);
}

function datalistQuery(e)
{
    var o=this, v=datalistVal(o), t=new Date().getTime(), focus=(e && ('type' in e) && e.type=='focus');
    if(_dq) clearTimeout(_dq);
    if(v==o.getAttribute('data-datalist-q') || o.getAttribute('data-datalist-t')>t) {
        if(!focus) {
            return;
        }
    }

    var x;
    if(o.getAttribute('id').search(/^q__/)>-1) {
        x=o.getAttribute('id').replace(/^q__/, '');
        var t=o.form.querySelector('#'+x);
        if(!focus && t && Z.val(t)!='') {
            Z.val(t, '');
            Z.fire(t, 'change');
        }
    } else {
        x = o.getAttribute('id');
    }
    if(!v) {
        datalistClear.apply(o);
    }

    var u=o.getAttribute('data-datalist-api'), api=(u!=''), h;
    if(u) {
        var m=u.match(/\$[a-z0-9\-\_]+/ig), i=(m)?(m.length):(0), n;
        if(u.substr(0,1)!='/' && u.substr(0,4)!='http') {
            u=window.location.pathname+'/'+u;
        }
        while(i-- > 0) {
            n=o.form.querySelector('#'+m[i].substr(1));
            if(n) u=u.replace(m[i], encodeURIComponent(Z.val(n)));
        }
        u += ((u.indexOf('?')>-1)?('&'):('?'))+'q='+encodeURIComponent(v);
    } else {
        //u = formUrl(o.parents('form'));
        //if(('form' in o) && (o.form.getAttribute('method')+'').toLowerCase()=='get') u=o.form.action;
        if('form' in o) u=o.form.action;
        else u=window.location.href; 
        h = {'Tdz-Action':'choices', 'Tdz-Target': x, 'Tdz-Term': v};
    }
    if(u===false || u===true) u=window.location.href;
    if(u.search(/\#/)>-1) u=u.replace(/\#.+$/, '');
    u=(u.search(/\?/)>-1)?(u.replace(/\&+(\bajax\b(=[^\&]*)?|$)/, '')+'&'):(u+'?');
    u+='ajax='+encodeURIComponent(x+'/'+v);

    o.setAttribute('data-datalist-q', v);
    Z.ajax(u, null, datalistRender, Z.error, 'json', o, h);
}

function datalistVal(o, v, fire)
{
    var s=o.getAttribute('data-datalist-multiple'), a=Z.val(o);
    if(s) {
        if(s=='1' || s=='true') s=';';
        var si=a.split(s);
        if(v) {
            si[si.length -1] = v;
        } else if(arguments.length>1) {
            si.pop();
        }
        v = si.join(s)+s;
        a = si.pop();
    }
    if(arguments.length>1) Z.val(o, v, fire);
    return a;
}

var _db=null;
function datalistBlurTimeout()
{
    var o=this;
    if(_db) clearTimeout(_db);
    _db = setTimeout(function(){ datalistBlur.apply(o); }, 200);
}

function datalistBlur(e)
{
    if(document.activeElement && !Z.parentNode(document.activeElement, this.parentNode)) {
        datalistClear.apply(this);
    }
}

function datalistClear()
{
    var o=this, v=datalistVal(o), t=new Date().getTime()+500;
    o.setAttribute('data-datalist-q', v);
    o.setAttribute('data-datalist-t', t);
    var o=this, c=o.parentNode.querySelector('.tdz-datalist-container');
    if(c) c.parentNode.removeChild(c);
}

var _D={};
function datalistRender(d)
{
    var r=this.getAttribute('data-datalist-renderer');
    if(r && (r in Z)) {
        _D = Z[r].call(this, d, datalistOption);
        return _D;
    }
    var o=this, c=o.parentNode.querySelector('ul.tdz-datalist'), n, p;
    if(!c) c=Z.element.call(o.parentNode,{e:'span',p:{className:'tdz-datalist-container'},c:[{e:'ul',p:{className:'tdz-datalist'},a:{'data-target':o.getAttribute('id')}}]}).children[0];
    else c.innerHTML=''; // remove child nodes
    var id=o.getAttribute('id');
    _D[id]={};
    for(n in d) {
        if(d.hasOwnProperty(n)) {
            p={e:'li',p:{className:'tdz-option'},a:{'data-value':n},t:{click:datalistOption}};
            if(typeof(d[n])=='string') {
                p.c=d[n];
            } else if('label' in d[n]) {
                if('value' in d[n]) p.a['data-value'] = d[n].value;
                if('group' in d[n]) {
                    p.c = [ {e:'strong', c: d[n].group }, ' '+d[n].label ];
                } else {
                    p.c=d[n].label;
                }
            } else {
                p.c=[];
                for (var s in d[n]) {
                    if(d[n].hasOwnProperty(s) && d[n][s]) {
                        p.c.push({e:'span', a:{'data-attr':s},p:{className:'tdz-attr-'+s}, c: d[n][s] });
                    }
                }
            }
            _D[id][p.a['data-value']]=d[n];

            Z.element.call(c,p);
        }
    }
    if(!p) {
        p={e:'li',p:{className:'tdz-msg tdz-alert'},c:Z.l[Z.language].Nothing};
        Z.element.call(c,p);
    }
    return _D;
}

function datalistOption()
{
    var id=this.parentNode.getAttribute('data-target'), o=this.parentNode.parentNode.parentNode.querySelector('#'+id);
    if(!o) return;
    o.setAttribute('data-datalist-t', new Date().getTime() + 1000);
    var v=this.getAttribute('data-value'),p=_D[id][v],b=this.getAttribute('data-prefix'), s=false,fo=o.form;
    if(!b)b='';
    if(p) {
        if(typeof(p)=='string') {
            o.setAttribute('data-datalist-q', p);
            datalistVal(o, p);
            if(id.search(/^q__/)>-1) {
                var e=fo.querySelector('#'+id.replace(/^q__/, ''));
                if(e) {
                    datalistVal(e, v, true);
                }
            }
        } else {
            s=false;
            if(id.search(/^q__/)>-1) {
                var e=fo.querySelector('#'+id.replace(/^q__/, '')), n;
                if(e) {
                    for(n in p) {
                        datalistVal(e, p[n], true);
                        delete(p[n]);
                        break;
                    }
                }
            }
            for(var n in p) {
                if(p[n].hasOwnProperty) {
                    if(!s) {
                        s=true;
                        o.setAttribute('data-datalist-q', p[n]);
                        datalistVal(o, p[n]);
                    }
                    var e=fo.querySelector('#'+b+n);
                    if(!e) e=fo.querySelector('*[name="'+b+n+'"]');
                    if(e) datalistVal(e, p[n], (id!=n));
                }
            }
        }
    }
    datalistClear.call(o);
}
Z.datalistOption = datalistOption;






// Tecnodesign_Translate
// pt_BR
if(!('l' in Z)) Z.l={en:{},pt:{}};
Z.l.pt.add='Acrescentar';
Z.l.pt.del='Excluir';
Z.l.pt.Nothing='Nenhuma opção foi encontrada para esta consulta.';
Z.l.pt.Error='Houve um erro ao processar esta informação. Por favor tente novamente ou entre em contato com o suporte.';
Z.l.en.add='Insert';
Z.l.en.del='Remove';
Z.l.en.Nothing='No options was selected for this query.';
Z.l.en.Error='There was an error while processing this request. Please try again or contact support.';

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

Z.error=function()
{
    console.log('ERROR', arguments, arguments[0], arguments[1], this);
    //msg(Z.l[Z.language].Error, 'tdz-i-error');
    //Z.delay(msg, 5000, 'msg');
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
    _ajax[url].r.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    var n;
    if('headers' in Z) {
        for(n in Z.headers) {
            if(Z.headers[n])
                _ajax[url].r.setRequestHeader(n, Z.headers[n]);
        }
    }
    if(headers) {
        if(m=='post' && data && String(data)=='[object FormData]') {
            headers['Content-Type']=false;
        }
        for(n in headers) {
            if(headers[n])
                _ajax[url].r.setRequestHeader(n, headers[n]);
        }
    }
    if(m=='post') {
        if(!headers || !('Content-Type' in headers)) {
            _ajax[url].r.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
        }
        //if(typeof(data)=='string' || 'length' in data) _ajax[url].r.setRequestHeader('Content-Length', data.length);
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
                _ajax[u].success.apply(_ajax[u].context, [ d, _ajax[u].r.status, u, _ajax[u].r ]);
            } else {
                _ajax[u].error.apply(_ajax[u].context, [ d, _ajax[u].r.status, u, _ajax[u].r ]);
            }
            delete(d);
            if(u in _ajax) {
                if('r' in _ajax[u]) delete(_ajax[u].r);
                delete(_ajax[u]);
            }
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