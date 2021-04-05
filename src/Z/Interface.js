/*! Tecnodesign Z.Interface v2.3 | (c) 2020 Capile Tecnodesign <ti@tecnodz.com> */
(function()
{
    "use strict";
    var _is=false, _init, _cu='/', _i=0, _sel='.tdz-i[data-url]', _base, _load=0, _toLoadTimeout, _toLoad=[], _reload={}, _loading={}, _ids={}, _prop={}, _q=[], _last, _reStandalone=/\btdz-i-standalone\b/, _msgs=[];

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
            Z.resizeCallback(headerOverflow);
        }
        _init = true;
        var i, l;
        if(arguments.length==0) {
            if(!(I=Z.node(this))) {
                return startup(document.querySelectorAll(_sel));
            }
        }
        if(!Z.node(I) && ('length' in I)) {
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

        getBase();
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

        if(_reStandalone.test(I.className)) return true;

        var B=Z.parentNode(I, '.tdz-i-body');

        if(B && (b=B.querySelector(':scope > .z-i-nav')) && !b.getAttribute('data-startup')) {
            b.setAttribute('data-startup', '1');
            l=b.querySelectorAll('a[href]');
            i=l.length;
            while(i-- > 0) if(!l[i].getAttribute('target') && !l[i].getAttribute('download')) Z.bind(l[i], 'click', loadInterface);
            l=null;
        }

        // bind links to Interface actions
        l=I.querySelectorAll('a[href^="'+base+'"],.z-i-a,.z-i-link');
        i=l.length;
        while(i-- > 0) if(!l[i].getAttribute('target') && !l[i].getAttribute('download')) Z.bind(l[i], 'click', loadInterface);
        l=null;

        // bind forms
        l=I.querySelectorAll('form[action^="'+base+'"],.tdz-i-preview form');
        i=l.length;
        while(i-- > 0) Z.bind(l[i], 'submit', (l[i].parentNode.getAttribute('data-action-schema')) ?loadAction :loadInterface);
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
                    M[j].className = ((M[j].className)?(M[j].className+' '):(''))+'z-i--close';
                    Z.bind(M[j], 'click', loadAction);
                    bt = M[j].form.parentNode;
                } else {
                    bt= M[j];
                }
                Z.element.call(bt, {e:'a',a:{href:u+'?scope='+bu+iurl,'class':'tdz-i-button z-i--'+k},t:{click:loadAction}});
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
        if(!_toLoadTimeout) activeInterface(I);
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
                loadInterface(h, true);
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
        if(I.className.search(/\nbtdz-i-active\b/)>-1) reHash();

        if(I.getAttribute('data-ui') || (I.getAttribute('data-url') in _prop)) {
            metaInterface(I);
        }
    }

    function getBase()
    {
        if(!_base) {
            var be=document.querySelector('.tdz-i-box[base-url]');
            if(be) _base = be.getAttribute('base-url');
            be=null;
        }
        return _base;
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
        if(!getBase()) {
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

        if(document.querySelector('.tdz-i-box .tdz-i-header[data-overflow]')) setTimeout(function() { headerOverflow(true); }, 200);       
    }

    function loadInterface(e, delayed)
    {
        /*jshint validthis: true */
        //Z.trace('loadInterface');
        _init = true;
        var I, m=false, t, q, urls=[], l, i,u,data,h={'z-action':'Interface'}, ft, method='get',nav=false;
        if(typeof(e)=='string') {
            urls.push(e);
        } else {
            Z.stopEvent(e);
            if(typeof(this)=='undefined') return false;
            if((I=Z.parentNode(this, '.tdz-i'))) {
            } else if ((I=Z.parentNode(this, '.tdz-i-title[data-url]'))) {
                I = document.querySelector('.tdz-i[data-url="'+I.getAttribute('data-url')+'"]');
                if(!I) return true;
            } else if(!Z.parentNode(this, '.z-i-nav')) return true;
            if(this.className.search(/\bz-i--close\b/)>-1) {
                if((u=this.getAttribute('href'))) {
                    activeInterface(u);
                }
                unloadInterface(I);
                return false;
            }

            if(_noH) _noH = false;

            var valid=true;
            if(this.className.search(/\bz-i-a-(many|one)\b/)>-1) {
                valid = false;
                if(this.className.search(/\bz-i-a-many\b/)>-1) {
                    m=true;
                    if(I.matchesSelector('.z-i-list-many')) valid = true;
                }
                if(this.className.search(/\bz-i-a-one\b/)>-1) {
                    if(I.matchesSelector('.z-i-list-one')) valid = true;
                }
                if(!valid) {
                    if (m) {
                        msg(Z.l[Z.language].moreRecord, 'z-i-error');
                    } else {
                        msg(Z.l[Z.language].noRecordSelected, 'z-i-error');
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
                if(this.id) ft=this.id;
                if(this.getAttribute('method').toLowerCase()=='post') {
                    method = 'post';
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
                        ih = (ib)?(ib.querySelector('.tdz-i-header .z-i--list[data-url^="'+iu+'"]')):(null);
                    if(ih) {
                        _reload[ih.getAttribute('data-url')]=true;
                    }
                } else {
                    t = t.replace(/\?(.*)$/, '')+'?'+Z.formData(this, false);
                }
                urls.push(t);
            } else if((t=this.getAttribute('href'))) {
                urls.push(t);
            }
        }
        i=urls.length;
        var o, H, B,SA=((typeof(I)=='object') && ('className' in I) && _reStandalone.test(I.className));
        while(i--) {
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

            if(delayed) {
                _toLoad.push(urls[i]);
                continue;
            }

            _loading[url]=t;
            Z.blur(B);
            //Z.trace('loadInterface: ajax request');
            if(I) {
                h['z-referer'] = I.getAttribute('data-url');
                if(I.getAttribute('data-qs')) h['z-referer'] += '?'+ I.getAttribute('data-qs');
            }

            if(ft && method==='post') {
                o.setAttribute('data-target-id', ft);
                ft=null;
            }

            var hn=h;
            if(o.getAttribute('data-nav')) hn['z-navigation'] = o.getAttribute('data-nav');
            else if('z-navigation' in hn) delete(hn['z-navigation']);

            Z.ajax((urls[i].search(/\?/)>-1)?(urls[i].replace(/\&+$/, '')+'&ajax='+t):(urls[i]+'?ajax='+t), data, setInterface, interfaceError, 'html', o, h);

            _load++;
            o=null;
        }

        if(delayed && _toLoad.length>0) {
            if(_toLoadTimeout) clearTimeout(_toLoadTimeout);
            _toLoadTimeout=setTimeout(loadToLoad, 500);
        }
        return false;
    }

    function loadToLoad()
    {
        if(_toLoadTimeout) clearTimeout(_toLoadTimeout);
        while(_toLoad.length>0) {
            loadInterface(_toLoad.shift());
        }
        _toLoadTimeout = null;
    }


    function loadAction(e)
    {
        /*jshint validthis: true */
        var u,t;
        if(typeof(e)=='object' && ('stopPropagation' in e)) {

            var nn=this.nodeName.toLowerCase(), data=null, method='get', h={'z-action': 'Interface'};
            e.stopPropagation();
            e.preventDefault();

            if(nn==='form') {
                t=Z.node(Z.parentNode(this, '.tdz-i-scope-block'), this.parentNode);
                if(this.id) t.setAttribute('data-action-expects', 'form#'+Z.slug(this.id));
                u=this.getAttribute('action');

                if(this.getAttribute('method').toLowerCase()=='post') {
                    method = 'post';
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
                } else {
                    u = u.replace(/\?(.*)$/, '')+'?'+Z.formData(this, false);
                }


            } else if(nn=='button') {
                t=Z.node(Z.parentNode(this.form, '.tdz-i-scope-block'), this.form.parentNode);
                u=this.getAttribute('data-url');
            } else if(this.getAttribute('data-action-item')) {
                t=this;
                u=this.children[this.children.length -1].getAttribute('href');
            } else {
                t=Z.node(Z.parentNode(this.parentNode, '.tdz-i-scope-block'), this.parentNode);
                var ss, sn;
                if(this.href && (ss=this.href.match(/[\?\&](scope=[^\&]+)/)) && ss.length>0) {
                    sn = new RegExp('\b'+ss[1].replace('=', '-')+'\b');
                }
                if(!sn || t.className.search(sn)===false) {
                    while(t && t.parentNode.className.search(/\btdz-i-scope-block\b/)>-1) {
                        t=t.parentNode;
                        if(sn && t.className.search(sn)!==false) break;
                    }
                }
                u=this.getAttribute('href');
            }
            var a=new Date().getTime(), I=Z.parentNode('.tdz-i[data-url].tdz-i-active');
            if(I) {
                h['z-referer'] = I.getAttribute('data-url');
                if(I.getAttribute('data-qs')) h['z-referer'] += '?'+ I.getAttribute('data-qs');
            }

            u=(u.search(/\?/)>-1)?(u.replace(/\&+$/, '')+'&ajax='+a):(u+'?ajax='+a);
            //Z.trace('loadAction: ajax request');
            Z.blur(t);
            Z.ajax(u, data, loadAction, interfaceError, 'html', t, h);
        } else {
            //Z.trace('loadAction: ajax response start');
            var f = document.createElement('div'), pI=Z.parentNode(this, '.tdz-i'), S, i, expects=this.getAttribute('data-action-expects');
            f.innerHTML = e;

            if(expects && !f.querySelector(expects)) {
                return setInterface.apply(pI, arguments);
            }

            runActions(f);



            var I = f.querySelector('.tdz-i[data-url] .tdz-i-preview'), del;
            if(!I) I = f.querySelector('.tdz-i[data-url] .tdz-i-container');
            if(!I) I = f.querySelector('.tdz-i[data-url]');

            if(I) {

                if(pI) {
                    S=pI.querySelectorAll('.z-i-summary .z-i-msg,.z-i-summary .tdz-i-msg,.tdz-i-msg[data-message],.z-i-msg[data-message]');
                    i=S.length;
                    while(i--) {
                        del = S[i];
                        if(del.parentNode.className.search(/\b(td)?z-msg\b/)>-1) del = del.parentNode;
                        Z.deleteNode(del);
                    }
                }

                // get tdz-i only
                if(I.children.length==1) {
                    t=I.children[0];
                    //while(t.children.length==1 && t.children[0].className.search(/\btdz-i-scope-block\b/)>-1) {
                    //    t=t.children[0];
                    //}
                    t.className=this.className;
                    if(t.className.search(/\btdz-i-scope-block\b/)<0) {
                        t.className+=' tdz-i-scope-block';
                    }
                    this.parentNode.replaceChild(t, this);
                } else {
                    t=this.parentNode.insertBefore(document.createElement('div'), this);
                    t.className='tdz-i-scope-block';
                    this.parentNode.removeChild(this);
                    i=0;
                    while(i<I.children.length) {
                        t.appendChild(I.children[i]);
                        i++;
                    }
                }
            }

            S=f.querySelectorAll('.z-i-summary .z-i-msg,.z-i-summary .tdz-i-msg');
            i=S.length;
            var rt=t;
            while(i--) {
                S[i].setAttribute('data-message', 1);
                rt.parentNode.insertBefore(S[i], rt);
                rt=S[i];
                //Z.deleteNode(S[i]);
            }

            if(!t) t = Z.node(this);

            if(t) {
                startup(t);
                Z.focus(t);
                t=null;
            }
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
            if(!Z.isNode(Z.parentNode(I, '.tdz-i-body'))) {
                loadInterface((qs)?(u+'?'+qs):(u));
                return false;
            }
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
            var txt = Z.text(H);
            if(!txt) {
                for(var i=1;i<3;i++) {
                    if(txt=Z.text(I.querySelector('h'+i))) break;
                }
            }
            if(txt && txt.trim()) document.title = txt;
        }

        var N = Z.parentNode(I, '.tdz-i-body').querySelector(':scope > .z-i-nav'), nb;
        if(N && (nb = N.getAttribute('data-base-url'))) {
            R=N.querySelectorAll('a.z-current[href]');
            i=R.length;
            while(i--) R[i].className = R[i].className.replace(/\s*\bz-current\b/g, '');
            if(u.substr(0, nb.length+1)==nb+'/') {
                if((i=u.indexOf('/', nb.length+1))) u = u.substr(0, i);
                if((N=N.querySelector('a[href="'+u+'"]'))) N.className = String(N.className+' z-current').trim();
            }
        }


        checkMessages(I);

        updateInterface(I);

        //if(document.querySelector('.tdz-i-box .tdz-i-header[data-overflow]')) headerOverflow(true);
        if(I.style) I.removeAttribute('style');
        Z.resizeCallback();

        return false;
    }

    function headerOverflow(timeout)
    {
        // flow & reflow tabs
        var He = document.querySelector('.tdz-i-box .tdz-i-header[data-overflow]');
        if(!He) return;

        var box=Z.parentNode(He, '.tdz-i-box'),
            Hs = box.querySelectorAll('.tdz-i-header > .tdz-i-title'),
            H =  box.querySelector('.tdz-i-header > .tdz-i-title.tdz-i-title-active'),
            ew, fw=0, ws={}, i, wmax, hw, el;

        i=Hs.length;
        if(H && i) {
            // remove all styles
            hw = He.clientWidth;
            if(el=He.querySelector(':scope > .z-spacer')) hw -= el.clientWidth;

            wmax = hw * 0.5;

            while(i--) {
                if(Hs[i].getAttribute('style')) Hs[i].setAttribute('style', '');
                el = Hs[i].querySelector('.z-text');
                if(!el) el = Hs[i];
                ew = el.clientWidth;
                Hs[i].setAttribute('style', 'max-width: '+ew+'px');
                //if(ew > wmax) ew = wmax;
                fw += ew;
                ws[i] = ew;
            }

            i=Hs.length;
            // check length
            if(i>1 && fw > hw) {
                if(He.className.search(/\bz-overflow\b/)<0) He.className += ' z-overflow';
                // flex:1 -- only the selected tab should be resized
                el = H.querySelector('.z-text');
                if(!el) el = H;
                ew = el.clientWidth;
                if(ew > wmax) el= wmax;
                H.setAttribute('style', 'flex: 2; width: '+ew+'px; max-width: '+ew+'px');
            } else {
                if(He.className.search(/\bz-overflow\b/)>-1) He.className = He.className.replace(/\s*\bz-overflow\b/g, '');
            }
        }


    }

    function checkMessages(I)
    {
        var S=(!I || !Z.isNode(I))?(document.querySelector('.tdz-i-active .tdz-i-summary')):(I.querySelector('.tdz-i-summary'));
        if(!S) return;
        var i=_msgs.length, now=(new Date()).getTime(), next=0, L=S.querySelectorAll(':scope > .tdz-msg[data-created],:scope > .tdz-i-msg'), timeout=5000, last=(L.length>0)?(L[L.length-1]):(null), el;
        while(i--) {
            if(_msgs[i].e < now || !_msgs[i].n.parentNode) {
                if(_msgs[i].n) {
                    el = _msgs[i].n;
                    if(el.parentNode && el.parentNode.className=='tdz-msg') el=el.parentNode;
                    Z.deleteNode(el);
                }
                _msgs[i].n=null;
                _msgs.splice(i, 1);
            } else if(Z.parentNode(_msgs[i].n, '.tdz-i-summary')!=S) {
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

    function parseResponse(d, req)
    {
        var h=req.getAllResponseHeaders(), c=h.match(/content-type: [^\;]+;\s*charset=([^\s\n]+)/i);
        if(c && c.length>1 && c[1].search(/^utf-?(8|16)$/i)<0) {
            //console.log('decode from '+c[1], d, escape(d));
            d =  decodeURIComponent(escape(d));
        }
        return d;
    }

    function setInterface(c)
    {
        /*jshint validthis: true */
        if(!_base) {
            getBase();
        }
        if(c) {
            if(arguments.length>=4 && arguments[1]==200) {
                c=parseResponse(c, arguments[3]);
            }
            var f = document.createElement('div'), O=Z.node(this),box=(O)?(Z.parentNode(O, '.tdz-i-box')):(null), ft, I;
            if(!box) box=document.querySelector('.tdz-i-box');

            f.innerHTML = c;

            if(ft=O.getAttribute('data-target-id')) {
                O.removeAttribute('data-target-id');
                var from=document.getElementById(ft), to=f.querySelector('#'+ft), fromI;
                if(from && to && (I=Z.parentNode(from, '.tdz-i'))) {
                    from.parentNode.replaceChild(to, from);
                    I.removeAttribute('data-startup');
                    if(O.parentNode) Z.deleteNode(O);
                    O=I;
                }
            }

            var r = f.querySelectorAll('a[data-action]'), i=r.length, ra;
            while(i-- > 0) {
                ra = r[i].getAttribute('data-action');
                if(ra && (ra in _A)) {
                    _A[ra].call(this, r[i]);
                }
                if(r[i].parentNode) r[i].parentNode.removeChild(r[i]);
            }
            r=null;
            if(!I) I = f.querySelector('.tdz-i');
            if(I && box && !box.querySelector('.tdz-i-body') && f.querySelector('.tdz-i-body')) {
                // replace entire box and startup
                Z.removeChildren(box);
                var mv = f.querySelector('.tdz-i-header');
                if(mv) box.appendChild(mv);
                mv = f.querySelector('.tdz-i-body');

                box.appendChild(mv);

                parseHash();
                startup(I);
                Z.init(box);
                Z.focus(mv);
                return;
            }

            if(!I) {
                if(O) {
                    Z.focus(box.querySelector('.tdz-i-body'));
                } else {
                    Z.focus(document.querySelector('.tdz-i-body.tdz-blur'));
                }
                return false;
            }

            var u = I.getAttribute('data-url'), cu=(O)?(O.getAttribute('data-url')):(null), S;

            if(S=O.querySelector('.tdz-i-summary')) {
                r=S.querySelectorAll(':scope .tdz-i-msg');
                i=r.length;
                while(i--) {
                    Z.deleteNode(r[i]);
                }
            }


            if(I!==O) {
                var H = box.querySelector('.tdz-i-header'),
                    Hs = f.querySelectorAll('.tdz-i-header > .tdz-i-title'),
                    h;

                if(u && u.substr(0, _base.length)!=_base) {
                    var rbox=f.querySelector('.tdz-i-box[base-url]');
                    if(rbox) I.setAttribute('data-base-url', rbox.getAttribute('base-url'));
                    rbox=null;
                }

                // check if requested interface was not returned (but a different one)
                if(cu && (!u || u!=cu)) {
                    // remove cu from body and hash
                    O=box.querySelector('.tdz-i[data-url="'+u+'"]');
                    if(!O) O=this;
                    if(H) {
                        r=H.querySelectorAll('.tdz-i-title[data-url="'+cu+'"]');
                        i=r.length;
                        while(i--) {
                            r[i].parentNode.removeChild(r[i]);
                        }
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
                i = I.attributes.length;
                while(i--) {
                    if(I.attributes[i].name.search(/^data-/)>-1) {
                        O.setAttribute(I.attributes[i].name, I.attributes[i].value);
                    }
                }
                i = Hs.length;
                while(i-- > 0) {
                    cu=Hs[i].getAttribute('data-url');
                    h=H.querySelector('.tdz-i-title[data-url="'+cu+'"]');
                    //Z.bind(Hs[i], 'click', activeInterface);
                    if(!Hs[i].querySelector('*[data-action="close"]')) {
                        Z.element.call(Hs[i], {e:'span',a:{'class':'z-i-a z-i--close','data-action':'close'},t:{click:loadInterface}});
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
            } else {
                // copy elements from summary
                if(S && (r=f.querySelectorAll('.tdz-i .tdz-i-summary .tdz-i-msg'))) {
                    i=r.length;
                    while(i--) {
                        S.appendChild(r[i]);
                    }
                }
                if(u in _loading) {
                    delete(_loading[u]);
                }
                if(!I.parentNode && box) {
                    box.querySelector('.tdz-i-body').appendChild(I);
                }
            }

            startup(I);
            Z.focus(Z.parentNode(I, '.tdz-i-body'));

        }
        return false;
    }

    function runActions(el)
    {
        var r = el.querySelectorAll('a[data-action]'), i=r.length, ra;
        while(i-- > 0) {
            ra = r[i].getAttribute('data-action');
            if(ra && (ra in _A)) {
                _A[ra].call(this, r[i]);
            }
            if(r[i].parentNode) r[i].parentNode.removeChild(r[i]);
        }
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
        load:function(o) {
            if(o.getAttribute('data-message')) {
                msg(o.getAttribute('data-message'), null, true);
                Z.delay(msg, 5000, 'msg');
            }
            var u = o.getAttribute('data-url') || o.getAttribute('href');
            if(!u) return false;
            Z.setInterface(u);
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
            if(I.children.length>0) M=Z.element({e:'div',p:{className:'tdz-i-msg'}}, I.children[0]);
            else M=Z.element.call(I, {e:'div',p:{className:'tdz-i-msg'}});
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
            Z.ajax(_bkg[n].u, null, setInterface, interfaceError, 'html', document.querySelector('.tdz-i.tdz-i-active'), {'z-action':'Interface', 'z-param':n});
            delete(_bkg[n]);
        }

    }

    function interfaceError(d, status, url, x)
    {
        /*jshint validthis: true */
        var mid = 'Error';
        if(status) mid += String(status);
        var m=(mid in Z.l[Z.language]) ?Z.l[Z.language][mid] :Z.l[Z.language].Error;
        Z.error.call(this, m);
        msg(m, 'tdz-i-error');
        Z.delay(msg, 5000, 'msg');
        Z.focus(document.querySelector('.tdz-i-body.tdz-blur'));
        if(this.className.search(/\btdz-i-off\b/)>-1) Z.deleteNode(this);
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
            L = document.querySelectorAll('.tdz-i-active'+_sel+', .z-i-standalone');
            i=L.length;
            while(i--) {
                id=L[i].getAttribute('data-url');
                _ids[id] = [];
            }

            L = document.querySelectorAll('.tdz-i-active'+_sel+' '+isel+', .z-i-standalone '+isel);
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
                cn=tI.className.replace(/\bz-i-list-(none|one|many)\b\s*/g, '').trim();
                i=_ids[id].length;
                if(i==0 && tI.getAttribute('data-id')) i=1;
                if(i==0) cn += ' z-i-list-none';
                else if(i==1) cn += ' z-i-list-one';
                else if(i>1) cn+= ' z-i-list-many';
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


    function initAutoRemove()
    {
        if(!this.querySelector('.z-i--close')) {
            var el=Z.element.call(this, {e:'i',p:{className:'z-i--close z-i-a z-round'},t:{click:autoRemove}});
            if(el.previousSibling.nodeName.toLowerCase()=='a' && !el.previousSibling.getAttribute('href')) Z.bind(el.previousSibling, 'click', autoRemove);
            var P=Z.parentNode(this,'.field,.z-i-field,.tdz-i-field');
            if(P) P.className+=' has-auto-remove';
        }
    }

    function autoRemove(e)
    {
        if(e) Z.stopEvent(e);
        var P=Z.parentNode(this, '.has-auto-remove');
        destroyParents.call(this);
        if(P) P.className = P.className.replace(/\s*\bhas-auto-remove\b/g, '');
    }

    function destroyParents(e)
    {
        if(e) Z.stopEvent(e);
        var P=this.parentNode.parentNode, nP;
        this.parentNode.parentNode.removeChild(this.parentNode);
        while(P && P.children.length==0) {
            nP = P.parentNode;
            nP.removeChild(P);
            P=nP;
        }
        return false;
    }


    //startup();
    window['Z.Interface.startup']=startup;
    window['Z.Interface.AutoRemove']=initAutoRemove;

})();