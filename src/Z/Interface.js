/*! Tecnodesign Z.Interface v2.2 | (c) 2018 Capile Tecnodesign <ti@tecnodz.com> */
(function()
{
    "use strict";
    var _is=false, _init, _cu='/', _i=0, _sel='.tdz-i[data-url]', _base, _load=0, _reload={}, _loading={}, _ids={}, _prop={}, _q=[], _last, _reStandalone=/\btdz-i-standalone\b/, _msgs=[];

    function startup(I)
    {
        /*jshint validthis: true */
        if(!('Z' in window)) {
            if('tdz' in window) window.Z=window.tdz;
            else {
                return setTimeout(startup, 100);
            }
        }
        if(!('loadInterface' in Z)) {
            Z.loadInterface = loadInterface;
            Z.setInterface = setInterface;
            // run once
            Z.bind(window, 'hashchange', hashChange);
        }
        _init = true;
        var i, l;
        if(arguments.length==0) {
            if(!(I=Z.node(this))) {
                return startup(document.querySelectorAll(_sel));
            }
        }
        if('length' in I) {
            if(I.length==0) return;
            if(I.length==1) I=I[0];
            else {
                for(i=0;i<I.length;i++) startup(I[i]);
                return;
            }
        }
        if(I.getAttribute('data-startup')) return;
        I.setAttribute('data-startup', '1');
        if(_init) Z.init(I);

        if(!_base) {
            var be=document.querySelector('.tdz-i-box[base-url]');
            if(be) _base = be.getAttribute('base-url');
            be=null;
        }
        var base=I.getAttribute('data-base-url');
        if(!base) {
            var b;
            if(!_base && (b=Z.parentNode(I, '.tdz-i-box'))) _base = b.getAttribute('base-url');
            base = _base;
        }

        // activate checkbox and radio buttons in lists
        var active=(_reStandalone.test(I.className)), ui=(I.className.search(/\btdz-i\b/)>-1)?(I.getAttribute('data-url')):(null), L=I.querySelector('.tdz-i-list');
        if(L) {
            active = true;
            l=L.querySelectorAll('input[type=checkbox][value],.tdz-i-list input[type=radio][value]');
            i=l.length;
            while(i-- > 0) if(!l[i].getAttribute('data-no-callback')) Z.bind(l[i], 'change', updateInterfaceDelayed);
            l=null;
        }

        // bind links to Interface actions
        l=I.querySelectorAll('a[href^="'+base+'"],.tdz-i-a');
        i=l.length;
        while(i-- > 0) if(!l[i].getAttribute('target') && !l[i].getAttribute('download')) Z.bind(l[i], 'click', loadInterface);
        l=null;

        // bind forms
        l=I.querySelectorAll('form[action^="'+base+'"],.tdz-i-preview form');
        i=l.length;
        while(i-- > 0) Z.bind(l[i], 'submit', loadInterface);
        l=null;

        L=null;

        // bind other actions
        var S=I.querySelectorAll('*[data-action-schema]');
        if(S.length==0 && I.getAttribute('data-action-schema')) S=[I];

        var iurl = I.getAttribute('data-action');
        iurl = (!iurl)?(''):('&next='+encodeURIComponent(iurl));
        i=S.length;
        while(i-- > 0) {
            var M = S[i].querySelectorAll('*[data-action-scope]'),
                j=M.length, 
                N, 
                k=S[i].getAttribute('data-action-schema'), 
                u=S[i].getAttribute('data-action-url'), 
                bt, 
                bu;
            while(j-- > 0) {
                bu=M[j].getAttribute('data-action-scope');
                if(M[j].querySelector('.tdz-i') || !bu || bu.substr(0, 1)=='_') continue;
                M[j].removeAttribute('data-action-scope');
                if(M[j].nodeName.toLowerCase()=='button') {
                    M[j].setAttribute('data-url', u+'?scope='+bu+iurl);
                    M[j].className = ((M[j].className)?(M[j].className+' '):(''))+'tdz-i--close';
                    Z.bind(M[j], 'click', loadAction);
                    bt = M[j].form.parentNode;
                } else {
                    bt= M[j];
                }
                Z.element.call(bt, {e:'a',a:{href:u+'?scope='+bu+iurl,'class':'tdz-i-button tdz-i--'+k},t:{click:loadAction}});
                bt=null;
            }
        }
        /*
        i=S.length;
        while(i-- > 0) {
            var M = S[i].querySelectorAll('*[data-action-item]'),j=M.length, N, k=S[i].getAttribute('data-action-schema'),u=S[i].getAttribute('data-action-url');
            while(j-- > 0) {
                N = document.createElement('a');
                N.setAttribute('href', u+'?item='+M[j].getAttribute('data-action-item'));
                N.className = 'tdz-i-button tdz-i--'+k;
                Z.bind(N, 'click', loadAction);
                Z.bind(M[j], 'dblclick', loadAction);
                M[j].appendChild(N);
            }
        }
        */

        // only full interfaces go beyond this point
        if(!ui) {
            return false;
        }

        if(active) {
            updateInterfaceDelayed();
        } else {
            updateInterface();
        }

        if(_noH) {
            if(_cu==I.getAttribute('data-url')) {
                _is = true;
            }
        }
        activeInterface(I);
        l=document.querySelectorAll('.tdz-i-header .tdz-i-title');
        i=l.length;
        while(i-- > 0) {
            if(!l[i].getAttribute('data-i')) {
                l[i].setAttribute('data-i', 1);
                Z.bind(l[i], 'click', activeInterface);
                Z.bind(l[i], 'dblclick', loadInterface);
            }
        }
        l=null;

        if(_noH) {
            _load--;
            if(_load==0) _noH = false;
            else return;

            setHashLink();
        }

        l=document.querySelectorAll('.tdz-i-header .tdz-i-title.tdz-i-off');
        i=l.length;
        while(i-- > 0) {
            l[i].parentNode.removeChild(l[i]);
        }

        parseHash(); // sets _H

        var h;
        if(!_last) {
            // first run, doesn't need to reload current page if in hash
            // reduce _H with currently loaded interface
            i=_H.length;
            while(i-- > 0) {
                h=_H[i];
                if(h.substr(0,1)=='?') h=_base+h;
                else if(h.substr(0,1)!='/') h = _base+'/'+h;
                if(document.querySelector('.tdz-i[data-url="'+h+'"]')) {
                    _H.splice(i,1);
                }
            }
        }


        if(!_is && _H.length>0) {
            var hu,hq;
            _noH = true;
            for(i=0;i<_H.length;i++) {
                h=_H[i];
                if(h.substr(0,1)=='?') h=_base+h;
                else if(h.substr(0,1)!='/') h = _base+'/'+h;
                loadInterface(h);
                _cu = h.replace(/\?.*/, '');
            }
        } else {
            while(_q.length>0) {
                var a=_q.shift();
                var f=a.shift();
                f.apply(I, a);
            }
            setHashLink();
            _is = true;
        }
        _last = new Date().getTime();
        reHash();

        if(I.getAttribute('data-ui') || (I.getAttribute('data-url') in _prop)) {
            metaInterface(I);
        }
    }

    var _Ht, _Hd=300;
    function hashChange(e)
    {
        if(arguments.length>0) {
            if(_Ht) clearTimeout(_Ht);
            _Ht = setTimeout(hashChange, _Ht);
            return;
        }

        if(!_reHash || !_checkHash) return;
        if(!_base) {
            setTimeout(hashChange, 500);
            return;
        }
        _checkHash = false;

        parseHash();
        // removes any interface that was unloaded by using backspace or messing with the hash
        var i=_H.length, L=document.querySelectorAll('.tdz-i-box .tdz-i-title[data-url]'), h, U={}, I, last;
        while(i--) {
            h=_H[i];
            if(h.substr(0,1)=='?') h=_base+h;
            else if(h.substr(0,1)!='/') h = _base+'/'+h;
            h=h.replace(/\?.*$/, '');
            if(!last) last = h;
            U[h]=i;
        }
        if(_H.length<=1 && L.length<=1) {
            if(_H.length==1 && L.length==1 && L[0].getAttribute('data-url')!=_H[0]) {
                // continue
            } else {
                _checkHash = true;
                return;
            }
        }
        i=L.length;
        var ni=i;
        while(i--) {
            h=L[i].getAttribute('data-url');
            if(h in U) {
                delete(U[h]);
            } else {
                I = L[i].parentNode.parentNode.querySelector('.tdz-i[data-url="'+h+'"]');
                if(I) {
                    ni--;
                    if(!ni && _H.length==0) break;

                    _reHash = false;
                    unloadInterface(I, false);
                    _reHash = true;
                }
            }
        }
        for(h in U) {
            loadInterface(h);
        }
        // checks if active interface is correct
        /*
        if(_H.length>1) {
            if(!document.querySelector('.tdz-i-box .tdz-i-title.tdz-i-title-active[data-url="'+last+'"]')) {
                _reHash = false;
                activeInterface(last);
                _reHash = true;
            }
        }
        */

        _checkHash = true;
    }
    var _H=[], _noH=false, _reHash=true, _checkHash=true;
    function parseHash()
    {
        var h = window.location + '',p=h.indexOf('#!');
        if(p<0 || h.length<p+2) {
            _H = [];
            return false;
        }
        h=h.substr(p+2);
        _H = h.split(/\,/g);
        return _H;
    }

    function setHash(h)
    {
        if(!_reHash) return;
        if(_noH) {
            if(_load==0) _noH = false;
            else return;
        }
        // remove h from _H
        if(h) {
            if(h.indexOf(',')>-1) h=h.replace(/,/g, '%2C');
            var i=_H.length, hu=h.replace(/\?.*/, '');
            while(i-- > 0) {
                var pu=_H[i].replace(/\?.*/, '');
                if(pu==hu) {
                    _H.splice(i,1);
                }
            }
        }
        if(h) _H.push(h);

        if(_H.length==1) {
            var I = document.querySelector('.tdz-i-active[data-url]'), ch, p;
            if(I) {
                p=I.getAttribute('data-url');
                if(I && p==window.location.pathname) {
                    /*
                    ch = I.getAttribute('data-qs');
                    if(ch) ch = p+'?'+ch;
                    else */
                    ch = p;
                    if(ch.substr(0,_base.length+1)==_base+'/') ch=ch.substr(_base.length+1);
                }
                if(ch==_H[0]) _H=[];
                I=null;
                ch=null;
            }
        }

        var s=(_H.length==0)?(''):('!'+_H.join(','));
        if(window.location.hash.replace(/^\#/, '')!=s) {
            window.location.hash=s;
        }
    }

    function reHash()
    {
        if(!_reHash) return;
        var l=document.querySelectorAll('.tdz-i-header .tdz-i-title[data-url]'), i=0,a,h,I;
        _H=[];
        for(i=0;i<l.length;i++) {
            h=l[i].getAttribute('data-url');
            if((I=document.querySelector('.tdz-i-body .tdz-i[data-url="'+h+'"][data-qs]'))) {
                h+='?'+I.getAttribute('data-qs');
            }
            if(h.substr(0,_base.length+1)==_base+'/') h=h.substr(_base.length+1);
            if(l[i].className.indexOf(/\btdz-i-title-active\b/)>-1)a=h;
            else _H.push(h);
        }
        if(a) _H.push(a);
        setHash(false);
    }

    function setHashLink()
    {
        var i=_H.length, o, hr;
        while(i-- > 0) {
            var pu=_H[i].replace(/\?.*/, '');
            if(pu.substr(0,1)!='/') pu=_base+'/'+pu;

            o=document.querySelector('a.tdz-i-title[data-url="'+pu+'"]');
            if(o) {
                hr = o.getAttribute('href');
                if(hr!=_H[i]) o.setAttribute('href', (_H[i].substr(0,1)!='/')?(_base+'/'+_H[i]):(_H[i]));
            }
        }
    }

    function unloadInterface(I, rehash, rI)
    {
        var u=I.getAttribute('data-url'),
            b=Z.parentNode(I, '.tdz-i-box');
        if(!b) b=document;
        var T=b.querySelector('.tdz-i-header .tdz-i-title[data-url="'+u+'"]');
        if(T) {
            T.parentNode.removeChild(T);
            T=null;
        }
        var B = I.previousSibling;
        if(arguments.length>2) {
            I.parentNode.replaceChild(I, rI);
            B = rI;
        } else {
            B = I.previousSibling;
            I.parentNode.removeChild(I);
        }
        I=null;
        if(!(I=b.querySelector('.tdz-i-active[data-url]'))) {
            if(!B) B=b.querySelector('.tdz-i[data-url]');
            activeInterface(B);
        }
        b=null;
        B=null;
        I=null;
        if(arguments.length<2 || arguments[1]) reHash();
    }

    function loadInterface(e)
    {
        /*jshint validthis: true */
        //Z.trace('loadInterface');
        _init = true;
        var I, m=false, t, q, urls=[], l, i,u,data,h={'Tdz-Action':'Interface'};
        if(typeof(e)=='string') {
            urls.push(e);
        } else {
            Z.stopEvent(e);
            if(typeof(this)=='undefined') return false;
            if((I=Z.parentNode(this, '.tdz-i'))) {
            } else if ((I=Z.parentNode(this, '.tdz-i-title[data-url]'))) {
                I = document.querySelector('.tdz-i[data-url="'+I.getAttribute('data-url')+'"]');
                if(!I) return true;
            } else return true;
            if(this.className.search(/\btdz-i--close\b/)>-1) {
                if((u=this.getAttribute('href'))) {
                    activeInterface(u);
                }
                unloadInterface(I);
                return false;
            }

            if(_noH) _noH = false;

            var valid=true;
            if(this.className.search(/\btdz-i-a-(many|one)\b/)>-1) {
                valid = false;
                if(this.className.search(/\btdz-i-a-many\b/)>-1) {
                    m=true;
                    if(I.matchesSelector('.tdz-i-list-many')) valid = true;
                }
                if(this.className.search(/\btdz-i-a-one\b/)>-1) {
                    if(I.matchesSelector('.tdz-i-list-one')) valid = true;
                }
                if(!valid) {
                    if (m) {
                        msg(Z.l[Z.language].moreRecord, 'tdz-i-error');
                    } else {
                        msg(Z.l[Z.language].noRecordSelected, 'tdz-i-error');
                    }
                    return false;
                }
            }
            if((t=this.getAttribute('data-url'))) {
                u=t;
                l=_ids[I.getAttribute('data-url')];
                if((q=this.getAttribute('data-qs'))) {
                    t=t.replace(/\?.*/, '');
                    q='?'+q;
                    u += q;
                } else q='';
                if(t.indexOf('{id}')>-1) {
                    i=(l.length && !m)?(1):(l.length);
                    while(i-- > 0) urls.push(t.replace('{id}', l[i])+q);
                } else {
                    if(l.length>0) {
                        q+=(q)?('&'):('?');
                        q+='_uid='+l.join(',');
                    }
                    urls.push(t+q);
                }
            } else if((t=this.getAttribute('action'))) {
                u=t;
                if(this.getAttribute('method').toLowerCase()=='post') {
                    var enc=this.getAttribute('enctype');
                    if(enc=='multipart/form-data') {
                        // usually file uploads
                        if('FormData' in window) {
                            data = new FormData(this);
                        }
                        h['Content-Type']=false;
                    } else {
                        h['Content-Type'] = enc;
                    }
                    if(!data) data = Z.formData(this);

                    // set index interface to be reloaded
                    var iu = u.replace(/\/[^/]+\/[^/]+(\?.*)$/, ''),
                        ib = Z.parentNode(this, '.tdz-i-box'),
                        ih = (ib)?(ib.querySelector('.tdz-i-header .tdz-i--list[data-url^="'+iu+'"]')):(null);
                    if(ih) {
                        _reload[ih.getAttribute('data-url')]=true;
                    }
                } else {
                    t = t.replace(/\?(.*)$/, '')+'?'+Z.formData(this, false);
                }
                urls.push(t);
            } else {
                urls.push(this.getAttribute('href'));
            }
        }
        i=urls.length;
        var o, H, B,SA=((typeof(I)=='object') && ('className' in I) && _reStandalone.test(I.className));
        while(i-- > 0) {
            var url = urls[i].replace(/(\/|\/?\?.+)$/, '');
            t=new Date().getTime();
            if((url in _loading) && t-_loading[url]<2000) continue;

            if (SA) {
                h['Tdz-Interface-Mode'] = 'standalone';
                o=I;
                B=I;
            } else {
                o=document.querySelector('.tdz-i[data-url="'+url+'"]');
                if(!o) {
                    o=Z.element.call(document.querySelector('.tdz-i-body'), {e:'div',a:{'class':'tdz-i tdz-i-off','data-url':url}});
                }
                if(!document.querySelector('.tdz-i-title[data-url="'+url+'"]')) {
                    if(!H) H = document.querySelector('.tdz-i-box .tdz-i-header');
                    if(H) {
                        Z.element.call(H, {e:'a',a:{'class':'tdz-i-title tdz-i-off','data-url':url,href:urls[i]}});
                    }
                }
                B = Z.parentNode(o, '.tdz-i-body');
            }
            _loading[url]=t;
            Z.blur(B);
            //Z.trace('loadInterface: ajax request');
            Z.ajax((urls[i].search(/\?/)>-1)?(urls[i].replace(/\&+$/, '')+'&ajax='+t):(urls[i]+'?ajax='+t), data, setInterface, interfaceError, 'html', o, h);

            _load++;
            o=null;
        }
        return false;
    }


    function loadAction(e)
    {
        /*jshint validthis: true */
        var u,t;
        if(typeof(e)=='object' && ('stopPropagation' in e)) {

            e.stopPropagation();
            e.preventDefault();

            if(this.nodeName.toLowerCase()=='button') {
                t=this.form.parentNode.parentNode;
                u=this.getAttribute('data-url');
            } else if(this.getAttribute('data-action-item')) {
                t=this;
                u=this.children[this.children.length -1].getAttribute('href');
            } else {
                t=Z.node(Z.parentNode(this.parentNode, '.tdz-i-scope-block'), this.parentNode);
                while(t && t.parentNode.className.search(/\btdz-i-scope-block\b/)>-1) t=t.parentNode;
                u=this.getAttribute('href');
            }
            var a=new Date().getTime();
            u=(u.search(/\?/)>-1)?(u.replace(/\&+$/, '')+'&ajax='+a):(u+'?ajax='+a);
            //Z.trace('loadAction: ajax request');
            Z.blur(t);
            Z.ajax(u, null, loadAction, interfaceError, 'html', t, {'Tdz-Action':'Interface'});
        } else {
            //Z.trace('loadAction: ajax response start');
            var f = document.createElement('div');
            f.innerHTML = e;
            var I = f.querySelector('.tdz-i[data-url] .tdz-i-preview');
            if(!I) I = f.querySelector('.tdz-i[data-url] .tdz-i-container');
            if(!I) I = f.querySelector('.tdz-i[data-url]');
            // get tdz-i only
            if(I.children.length==1) {
                t=I.children[0];
                if(t.className.search(/\btdz-i-scope-block\b/)<0) {
                    t.className=((this.className)?(this.className+' '):(''))+'tdz-i-scope-block';
                }
                this.parentNode.replaceChild(t, this);
            } else {
                t=this.parentNode.insertBefore(document.createElement('div'), this);
                t.className='tdz-i-scope-block';
                this.parentNode.removeChild(this);
                var i=0;
                while(i<I.children.length) {
                    t.appendChild(I.children[i]);
                    i++;
                }

            }
            startup(t);
            Z.focus(t);
            t=null;
        }

        return false;
    }

    function activeInterface(I)
    {
        /*jshint validthis: true */
        var u, qs, H;
        if(!I || typeof(I)=='string' || !Z.isNode(I)) {
            if(typeof(I)=='string') {
                u = I;
                if(u.indexOf('?')) {
                    qs = u.substr(u.indexOf('?')+1);
                    u=u.substr(0, u.indexOf('?'));
                }
                I = document.querySelector('.tdz-i[data-url="'+u+'"]');
            } else {
                if(I && ('stopPropagation' in I)) {
                    I.stopPropagation();
                    I.preventDefault();
                    // click events reload the interface
                    I=Z.node(this);
                    if(I && (u=I.getAttribute('data-url')) && (u in _reload)) {
                        delete(_reload[u]);
                        qs = I.getAttribute('data-qs');
                        if(!qs && I.getAttribute('href')) {
                            u=I.getAttribute('href');
                        }
                        I=null;
                    }
                } else {
                    I=Z.node(this);
                }
            }
        }
        if(I) {
            u=I.getAttribute('data-url');
            if(u) H = document.querySelector('.tdz-i-title[data-url="'+u+'"]');
            if(I==H) I = document.querySelector('.tdz-i[data-url="'+u+'"]');
        }
        if(!I && !u) {
            // get u from hash?
            return false;
        } else if(!I) {
            loadInterface((qs)?(u+'?'+qs):(u));
            return false;
        } else if(!_reStandalone.test(I.className)) {
            if(I.className.search(/\btdz-i-active\b/)<0) I.className += ' tdz-i-active';
            if(H && H.className.search(/\btdz-i-off\b/)>-1) H.className = H.className.replace(/\s*\btdz-i-off\b/, '');
            if(H && H.className.search(/\btdz-i-title-active\b/)<0) H.className += ' tdz-i-title-active';
            if(_is) {
                reHash();
            }
            var R = document.querySelectorAll('.tdz-i-title-active,.tdz-i-active'),i=R.length;
            while(i-- > 0) {
                if(R[i]==H || R[i]==I) continue;
                R[i].className = R[i].className.replace(/\btdz-i-(title-)?active\b\s*/g, '').trim();
            }
        }

        checkMessages(I);

        updateInterface(I);
        return false;
    }

    function checkMessages(I)
    {
        var S=(!I || !Z.isNode(I))?(document.querySelector('.tdz-i-active .tdz-i-summary')):(I.querySelector('.tdz-i-summary'));
        if(!S) return;
        var i=_msgs.length, now=(new Date()).getTime(), next=0, L=S.querySelectorAll('.tdz-msg[data-created],tdz-error'), timeout=5000, last=(L.length>0)?(L[L.length-1]):(null);
        while(i--) {
            if(_msgs[i].e < now) {
                _msgs[i].n.parentNode.removeChild(_msgs[i].n);
                _msgs[i].n=null;
                _msgs.splice(i, 1);
            } else if(!_msgs[i].n.parentNode || Z.parentNode(_msgs[i].n, '.tdz-i-summary')!=S) {
                if(last) {
                    last = Z.element({e:'div',p:{className:'tdz-msg'},c:[_msgs[i].n]}, null, last);
                } else {
                    last = Z.element.call(S, {e:'div',p:{className:'tdz-msg'},c:[_msgs[i].n]});
                }
                if(!next || next>_msgs[i].e) next=_msgs[i].e;
            }
        }
        last = null;
 
        i=L.length;
        while(i--) {
            var d=now + timeout;
            L[i].removeAttribute('data-created');
            if(L[i].childNodes.length>0) {
                _msgs.push({e: d, n: L[i]});
                if(!next) next=d;
            } else {
                L[i].parentNode.removeChild(L[i]);
            }
        }

        if(next) {
            setTimeout(checkMessages, next - now + 100);
        }
    }


    function setInterface(c)
    {
        /*jshint validthis: true */
        if(c) {
            var f = document.createElement('div'), O=Z.node(this),box=(O)?(Z.parentNode(O, '.tdz-i-box')):(null);
            if(!box) box=document.querySelector('.tdz-i-box');

            f.innerHTML = c;

            var r = f.querySelectorAll('a[data-action]'), i=r.length, ra;
            while(i-- > 0) {
                ra = r[i].getAttribute('data-action');
                if(ra && (ra in _A)) {
                    _A[ra].call(this, r[i]);
                }
                if(r[i].parentNode) r[i].parentNode.removeChild(r[i]);
            }
            r=null;

            var I = f.querySelector('.tdz-i');
            if(!I) {
                if(O) {
                    Z.focus(box.querySelector('.tdz-i-body'));
                } else {
                    Z.focus(document.querySelector('.tdz-i-body.tdz-blur'));
                }
                return false;
            }

            var H = box.querySelector('.tdz-i-header'),
                Hs = f.querySelectorAll('.tdz-i-header > .tdz-i-title'),
                h;
            var u = I.getAttribute('data-url'), cu=(O)?(O.getAttribute('data-url')):(null);
            if(u && u.substr(0, _base.length)!=_base) {
                var rbox=f.querySelector('.tdz-i-box[base-url]');
                if(rbox) I.setAttribute('data-base-url', rbox.getAttribute('base-url'));
                rbox=null;
            }

            // check if requested interface was not returned (but a different one)
            if(cu && (!u || u!=cu)) {
                // remove cu from body (no pun intended, really :) ) and hash
                O=box.querySelector('.tdz-i[data-url="'+u+'"]');
                if(!O) O=this;
                r=H.querySelectorAll('.tdz-i-title[data-url="'+cu+'"]');
                i=r.length;
                while(i--) {
                    r[i].parentNode.removeChild(r[i]);
                }
                r=box.querySelectorAll('.tdz-i[data-url="'+cu+'"]');
                i=r.length;
                while(i--) {
                    if(r[i]!=O) {
                        r[i].parentNode.removeChild(r[i]);
                    }
                }

                if(!(u in _loading)) {
                    _loading[u] = (new Date()).getTime();
                }
                if(_reHash) {
                    var ch=_checkHash;
                    _checkHash=false;
                    reHash();
                    _checkHash=ch;
                } else {
                    setTimeout(reHash, 500);
                }
            }


            /*
            if(u in _loading) {
                delete(_loading[u]);
            }
            */


            i = Hs.length;
            while(i-- > 0) {
                cu=Hs[i].getAttribute('data-url');
                h=H.querySelector('.tdz-i-title[data-url="'+cu+'"]');
                //Z.bind(Hs[i], 'click', activeInterface);
                if(!Hs[i].querySelector('*[data-action="close"]')) {
                    Z.element.call(Hs[i], {e:'span',a:{'class':'tdz-i-a tdz-i--close','data-action':'close'},t:{click:loadInterface}});
                }
                if(h) H.replaceChild(Hs[i], h);
                else if(cu==u) H.appendChild(Hs[i]);
                h=null;
            }

            if(!O || !O.parentNode) {
                O=box.querySelector('.tdz-i[data-url="'+u+'"]');
            }
            if(O) {
                O.parentNode.replaceChild(I, O);
            } else {
                box.querySelector('.tdz-i-body').appendChild(I);
            }
            startup(I);
            Z.focus(Z.parentNode(I, '.tdz-i-body'));

        }
        return false;
    }

    var _A = {
        unload:function(o) {
            var 
              ru = (typeof(o)=='string')?(o):(o.getAttribute('data-url')),
              rn = document.querySelector('.tdz-i-box .tdz-i-header .tdz-i-title[data-url="'+ru+'"]');
            if(rn) rn.parentNode.removeChild(rn);
            rn = document.querySelector('.tdz-i-box .tdz-i-body .tdz-i[data-url="'+ru+'"]');
            if(rn) rn.parentNode.removeChild(rn);
        },
        status:function(o) {
            var pid = o.getAttribute('data-status');
            if(!pid) return;
            _bkg[pid] = {u:o.getAttribute('data-url'),m:o.getAttribute('data-message')};
            msg(_bkg[pid].m, null, true);
            Z.delay(msg, 5000, 'msg');
            Z.delay(checkBkg, 2000, 'checkBkg');
        },
        message:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), 'tdz-i-message', true);
                Z.delay(msg, 10000, 'msg');
            }
        },
        success:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), 'tdz-i-success', true);
                Z.delay(msg, 5000, 'msg');
            }
        },
        error:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), 'tdz-i-error', true);
                Z.delay(msg, 5000, 'msg');
            }
        },
        download:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), null, true);
                Z.delay(msg, 5000, 'msg');
            }
            var u = o.getAttribute('data-url') || o.getAttribute('href');
            if(!u) return false;
            var d=o.getAttribute('data-download') || '';

            var link = document.createElement("a");
            link.setAttribute('download',d);
            link.target = "_blank";
            link.href = u;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            link=null;
        },
        redirect:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), null, true);
                Z.delay(msg, 5000, 'msg');
            }
            var u = o.getAttribute('data-url') || o.getAttribute('href');
            if(!u) return false;
            var t=o.getAttribute('data-target') || o.getAttribute('target');
            if(t) {
                window.open(u, t).focus();
            } else {
                window.location.href=u;
            }
        }
    };

    function msg(s, c, html)
    {
        var M=document.querySelector('.tdz-i.tdz-i-active .tdz-i-msg');
        if(!M) {
            var I = document.querySelector('.tdz-i-active .tdz-i-summary');
            if(!I) I = document.querySelector('.tdz-i-active .tdz-i-container');
            if(!I) I = document.querySelector('.tdz-i-active');
            if(!I) return;
            M=Z.element({e:'div',p:{className:'tdz-i-msg'}}, I.children[0]);
        }
        if(!c) c='';
        else c+=' ';
        c+='tdz-i-msg';
        if(s) {
            c+=' tdz-m-active';
        } else {
            s=null;
            c+=' tdz-m-inactive';
        }
        if(M.className!=c)M.className=c;
        if(arguments.length>2 && html) {
            M.innerHTML=s;
        } else {
            M.textContent=s;
        }
        Z.init(M);
        //Z.element.call(M, {c:s});
    }

    var _bkg={};
    function checkBkg()
    {
        var n;
        for(n in _bkg) {
            Z.ajax(_bkg[n].u, null, setInterface, interfaceError, 'html', document.querySelector('.tdz-i.tdz-i-active'), {'Tdz-Action':'Interface', 'Tdz-Param':n});
            delete(_bkg[n]);
        }

    }

    function interfaceError()
    {
        /*jshint validthis: true */
        Z.error.call(this, arguments);
        msg(Z.l[Z.language].Error, 'tdz-i-error');
        Z.delay(msg, 5000, 'msg');
    }

    function updateInterfaceDelayed(e)
    {
        /*jshint validthis: true */
        if(arguments.length>0) e.stopPropagation();
        if(Z.isNode(this) && 'checked' in this) Z.checkInput(this, null, false);
        Z.delay(updateInterface, 100);

    }

    function updateInterface(I)
    {
        var ref=(arguments.length>0 && Z.isNode(I)),
            isel='.tdz-i-list input[name="uid[]"][value]:checked', 
            L,
            i,
            tI,
            id,
            tr,
            cn;

        if(ref && (I.getAttribute('data-id')) && (id=I.getAttribute('data-url'))) {
            _ids[id] = [I.getAttribute('data-id')];
        } else {
            L = document.querySelectorAll('.tdz-i-active'+_sel+', .tdz-i-standalone');
            i=L.length;
            while(i--) {
                id=L[i].getAttribute('data-url');
                _ids[id] = [];
            }

            L = document.querySelectorAll('.tdz-i-active'+_sel+' '+isel+', .tdz-i-standalone '+isel);
            i=L.length;
            while(i--) {
                if(!(tI=Z.parentNode(L[i], '.tdz-i'))) continue;
                id=tI.getAttribute('data-url');
                if(!(id in _ids)) _ids[id] = [];
                _ids[id].push(L[i].value);
                if((tr=Z.parentNode(L[i], 'tr:not(.on)'))) {
                    tr.className += ' on';
                }
            }
        }

        for(id in _ids) {
            if((tI=document.querySelector(_sel+'[data-url="'+id+'"]'))) {
                cn=tI.className.replace(/\btdz-i-list-(none|one|many)\b\s*/g, '').trim();
                i=_ids[id].length;
                if(i==0 && tI.getAttribute('data-id')) i=1;
                if(i==0) cn += ' tdz-i-list-none';
                else if(i==1) cn += ' tdz-i-list-one';
                else if(i>1) cn+= ' tdz-i-list-many';
                if(tI.className!=cn)tI.className=cn;
            } else {
                delete(_ids[id]);
            }
        }
    }

    function metaInterface(I)
    {
        var u=I.getAttribute('data-url'), s, p;
        if(!u) return;

        /*
        if((s=I.getAttribute('data-ui'))) {
            I.removeAttribute('data-ui');
            if((p=JSON.parse(btoa(s)))) {
                _props[u]=p;
            }
        }
        if(!p) {
            if(u in _props) p=_props[u];
            else return removeDashboard();// clean up dashboard?
        }
        */
    }

    function removeDashboard()
    {

    }


    //startup();
    window['Z.Interface.startup']=startup;

})();