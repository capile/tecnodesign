/*! Studio v2.3 | (c) 2019 Capile Tecnodesign <ti@tecnodz.com> */
(function() {

"use strict";

var _Z, _V, _L, _P, _Q, _Qt, _Qq, _ih={'z-action':'Interface'}, _Studio='/_studio';

// load authentication info
function startup()
{
    if(!('Z' in window) || !('Z.Interface.startup' in window)) {
        return setTimeout(startup, 500);
    }

    if(!('plugins' in Z)) Z.plugins={};
    if(!('studio' in Z.plugins)) {
        Z.plugins.studio={home:'/_studio', options:{}, load:[] };
    }

    if(!('modules' in Z)) Z.modules = {};
    if(('home' in Z.plugins.studio) && Z.plugins.studio.home!=_Studio) {
        _Studio = Z.plugins.studio.home;
    }
    _P = {c:[],s:[]};

    if(('options' in Z.plugins.studio) && ('interactive' in Z.plugins.studio.options) && Z.plugins.studio.options.interactive) {
        var l=document.querySelectorAll('[data-studio-s]'),L,j,i,id,d=[];
        for (i=0;i<l.length;i++) {
            Z.addEvent(l[i], 'dblclick', doubleClick);
            id=l[i].getAttribute('data-studio-s');
            _P.s.push(id);
            d.push({e:'div',a:{'class':'s-section s-hidden','data-id':'s/'+id}});
            L=l[i].querySelectorAll('[data-studio-c]');
            for(j=0;j<L.length;j++) {
                id=L[j].getAttribute('data-studio-c');
                Z.addEvent(L[j], 'dblclick', doubleClick);
                _P.c.push(id);
                d.push({e:'div',a:{'class':'s-content s-hidden','data-id':'c/'+id}});
            }
        }
    }

    if(('options' in Z.plugins.studio) && ('button' in Z.plugins.studio.options) && Z.plugins.studio.options.button) {
        var ui = [
          {e:'div',p:{id:'studio-viewport',className:'tdz-i-box'},a:{'base-url':_Studio}},
          {e:'div',p:{className:'studio-logo'}}/*,
          {e:'div',p:{className:'studio-menu'},c:[
            {e:'div',p:{className:'studio-box s-right z-i-actions'},c:[
              {e:'a',p:{className:'z-i--search'}},
              {e:'a',p:{className:'z-i--new'   }},
              {e:'a',p:{className:'z-i--update'}},
              {e:'a',p:{className:'z-i--delete'}}
            ]},
          ]}*/
        ];
        _Z = document.getElementById('studio');
        if(!_Z) {
            _Z = Z.element.call(document.body, {e:'div',p:{id:'studio',className:'studio-interface'},c:ui});
        } else if(_Z.className.search(/\bs-active\b/)<0) {
            var b=_Z.querySelector('.tdz-i-box');
            if(b) {
                ui.shift();
                b.id='studio-viewport';
                b=null;
            }
            Z.element.call(_Z, ui);
            trigger(null, true);
        }
        Z.bind(_Z, 'click', trigger);
    }
    /*
    while(i-->0) {
        if(id=l[i].getAttribute('data-studio-c')) {
            _P.c.push(id);
        } else if(id=l[i].getAttribute('data-studio-s')) {
            _P.s.push(id);
        }
        Z.addEvent(l[i], 'dblclick', doubleClick);
    }
    */
}

function doubleClick(e)
{
    e.preventDefault();
    e.stopPropagation();
    var id=this.getAttribute('data-studio-c');
    if(id) {
        getInterface(_Studio+'/c/v/'+id);
        return false;
    }
    id=this.getAttribute('data-studio-s');
    if(id) {
        getInterface(_Studio+'/c/n/'+id);
        return false;
    }
}

function getViewport()
{
    if(!_V) _V = document.getElementById('studio-viewport');
    var b=_V.querySelector('.tdz-i-header');
    if(!b) {
        Z.element.call(_V,{e:'div',a:{'class':'tdz-i-header'}});
        b=Z.element.call(_V,{e:'div',a:{'class':'tdz-i-body'}});
    }

    if(!('loadInterface' in Z)) {
        // startup Z.Interface.startup
        window['Z.Interface.startup']();
    }
    return _V;
}


/*!getinterface*/
function getInterface(u)
{
    if(!_V) getViewport();

    /*
    var t=_V.querySelector('.tdz-i[data-url="'+u+'"]');
    if(!t) {
        var b=_V.querySelector('.tdz-i-header');
        if(!b) {
            Z.element.call(_V,{e:'div',a:{'class':'tdz-i-header'}});
            b=Z.element.call(_V,{e:'div',a:{'class':'tdz-i-body'}});
        }
        //t=Z.element.call(b,{e:'div',a:{'class':'tdz-i','data-url':u } } );
        t=Z.element.call(b,{e:'div',a:{'class':'tdz-i','data-url':u } } );
        //Z.ajax(u, null, Z.loadInterface, Z.errorInterface, 'html', t, _ih);
    }
    */
    //Z.loadInterface.call(t, u);
    trigger(null, true);
    Z.loadInterface.call(_V, u);
}

function searchInterface(s)
{
    var d, u=_Studio+'/i/q';
    if(typeof(s)=='object') {
        d = JSON.stringify({q:s});
    } else {
        u+='?q='+escape(s);
    }

    loadInterface(u);
    //Z.ajax(u, d, listResults, null, 'json', _Z, {'z-action':'Studio','Content-Type':'application/json'});
}

function loadInterface(u)
{
    var p =(u.indexOf(/\?/)>-1) ?u.substr(0, u.indexOf(/\?/)) :u, 
        qs=(u.indexOf(/\?/)>-1) ?u.substr(u.indexOf(/\?/)+1) :'',
        P;
    if(!_Z.querySelector('.tdz-i-box .tdz-i[data-url="'+encodeURIComponent(p)+'"]')) {
        if(!(P=_Z.querySelector('.tdz-i-body'))) P=_Z.querySelector('.tdz-i-box');
        Z.element.call(P, {e:'div',a:{'class':'tdz-i', 'data-url':p, 'data-qs':qs, 'data-nav':'1'}});
    }
    Z.init(_Z);
}

function listResults(d)
{
    if(typeof(d)=='object' && ('status' in d) && d.status=='OK' && ('data' in d)) {
        if('interface' in d.data) {
            if(!_V) getViewport();
            Z.setInterface(d.data['interface']);
        }
    } 
    console.log('listResults!', d);
}

function errorInterface(d)
{
    console.log('errorInterface', d, this);
}

function keyPress()
{
    if(!_Q) _Q=this;
    if(_Qt) clearTimeout(_Qt);
    _Qt = setTimeout(search, 500);
}

function search()
{
    if(_Qt) {
        clearTimeout(_Qt);
        _Qt=0;
    }
    if(_Q.value==_Qq) return false;
    _Qq=_Q.value;
    console.log('search for '+_Qq);
}

function getProperties(id)
{
    if(arguments.length>0) {
        if(id in _P) {
            return _P[id];
        } else if(id.substr(0,1)=='c/') _P.c.push(id.substr(2));
        else if(id.substr(0,1)=='s/') _P.s.push(id.substr(2));
        else return;
    }

    // fetch new properties
    var d={c:_P.c,s:_P.s};
    Z.ajax(_Studio+'/p', JSON.stringify(d), setProperties, null, 'json', _Z, {'z-action':'Studio','Content-Type':'application/json'});
}

var _prop={};
function setProperties(d)
{
    if(!d) return;
    _prop = d;
    return;
    /*
    var n, t, id, E, c, p, P={'new':null,'update':null,'delete':null};
    if(!_L) _L=document.getElementById('studio-list');
    for(n in d) {
        if(!(n in _P) && d[n]) {
            E = _L.querySelector('.s-hidden[data-id="'+n+'"]');
            if(E) {
                if(E.className.search(/\bs-hidden\b/)>-1) E.className = E.className.replace(/\s*\bs-hidden\b/g, '');
                c=[];
                if('id' in d[n]) c.push({e:'span',p:{className:'s-title'},c:d[n].id});
                for(p in P) {
                    if((p in d[n]) && d[n][p]) c.push({e:'a',p:{className:'s-button studio-i-'+p},t:{click:trigger}});
                }
                Z.element.call(E, c);
            }
        }
    }
    */
}

function triggerFloatingMenu(e)
{
    console.log('trigger floating menu at', this);
}

function trigger(e, active)
{
    if(e && ('target' in e)) {
        if(e.target.className.search(/\b(studio-(logo|interface))\b/)<0) {
            return false;
        }
    }
    if(arguments.length>1) toggle.call(_Z, e, active);
    else toggle.call(_Z, e);
    var on=(_Z.className.search(/\bs-active\b/)>-1);
    if(!_Z.querySelector('.tdz-i')) {
        // no interface, preview current page, if found
        searchInterface({e:{link:window.location.pathname}});
    }

    /*
    if(!_L) {
        getProperties();
    }
    */
}

function toggle(e, active)
{
    var on,h=document.querySelector('html');
    if(arguments.length>1) {
        on=active;
    } else {
        on=(this.className.search(/\bs-active\b/)<0);        
    }
    if(on) {
        if(this.className.search(/\bs-active\b/)<0) {
            this.className=String(this.className+' s-active').trim();
        }
        if(h.className.search(/\bstudio-lock\b/)<0) {
            h.className=String(h.className+' studio-lock').trim();
        }
    } else {
        if(this.className.search(/\bs-active\b/)>-1) {
            this.className=this.className.replace(/\s*\bs-active\b/, '').trim();
        }
        if(h.className.search(/\bstudio-lock\b/)>-1) {
            h.className=h.className.replace(/\s*\bstudio-lock\b/, '').trim();
        }
    }
    h=null;
}


function setContent(c)
{
    this.innerHTML = c;
}

/*
Z.contentType = function()
{
    var F = Z.parentNode(this, 'form,.item'), L=F.querySelectorAll('*[data-content-type]'), i=L.length,v=Z.val(this);
    console.log('ContentType: '+v, L);
    while(i--) {
        var el=Z.parentNode(L[i], '.tdz-i-field');
        if(v && String(','+L[i].getAttribute('data-content-type')+',').indexOf(','+v+',')>-1) {
            el.className = el.className.replace(/\s*\bi-hidden\b/g, '');
        } else if(el.className.search(/\bi-hidden\b/)<0) {
            el.className+=' i-hidden';
        }
    }
};
*/

startup();

})();