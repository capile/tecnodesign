/*! Tecnodesign Z.Form v2.2 | (c) 2018 Capile Tecnodesign <ti@tecnodz.com> */
(function()
{

"use strict";

var _Tl,_L=[], _eids=0;
function checkLabel(e)
{
    /*jshint validthis: true */
    if(_Tl) clearTimeout(_Tl);
    if(arguments.length>0) {
        if(Z.node(this)) {
            var nn=this.nodeName.toLowerCase(),E;
            if(nn=='input') {
                _L.push(this);
            } else {
                E=this.querySelector('input[type="radio"],input[type="checkbox"]');
                if(E) {
                    _L.push(E);
                }
                if(nn=='label') E=null;
            }
            if(E) {
                if(E.checked) {
                    E.checked = false;
                    E.removeAttribute('checked');
                } else {
                    E.checked = true;
                    E.setAttribute('checked', 'checked');
                }
                _L.push(E);
            } else {
                _L.push(this);
            }
        }
        _Tl=setTimeout(checkLabel, 50);
        return;
    }
    var L=document.querySelectorAll(Z.modules.CheckLabel), i=L.length, P, cn;
    if(!i && _L.length>0) {
        L = _L;
        i=L.length;
        _L=[];
    }
    while(i--) {
        P=Z.parentNode(L[i], 'label');
        if(!P) P=L[i].parentNode;
        cn=P.className;

        if(L[i].checked) {
            if(!L[i].getAttribute('checked')) L[i].setAttribute('checked','checked');
            if(cn.search(/\bon\b/)<0) cn += ' on';
            if(cn.search(/\boff\b/)>-1) cn = cn.replace(/\s*\boff\b/g, '');
            L[i].setAttribute('data-switch', 'on');
        } else {
            if(L[i].getAttribute('checked')) L[i].removeAttribute('checked');
            if(cn.search(/\boff\b/)<0) cn += ' off';
            if(cn.search(/\bon\b/)>-1) cn = cn.replace(/\s*\bon\b/g, '');
            L[i].setAttribute('data-switch', 'off');
        }
        cn=cn.trim();
        if(P.className!=cn) P.className=cn;
        P=null;
    }
}

function initCheckLabel(e)
{
    /*jshint validthis: true */
    if(!this.getAttribute('data-check-label')) {
        this.setAttribute('data-check-label',1);
        var l=Z.parentNode(this, 'label');
        if(!l) l=this.parentNode;
        Z.bind(l, 'click', checkLabel);
        checkLabel(true);
    }
}

function initAutoSubmit(e)
{
    /*jshint validthis: true */
    if(!this.getAttribute('data-auto-submit')) {
        this.setAttribute('data-auto-submit',1);
        var L=this.querySelectorAll('input,select,textarea'), i=L.length,found=false,t, nn, a;
        while(i--) {
            t=L[i].getAttribute('type');
            nn=L[i].nodeName.toLowerCase();
            if(t && (t==='submit' || t==='button')) continue;
            Z.bind(L[i], (nn==='select' || t==='checkbox' || t==='radio') ?'input' :'change', autoSubmit);
            found=true;
        }
        if(!found) return;
        this.className+=' z-no-button';
    }
}

function autoSubmit(e)
{
    /*jshint validthis: true */
    Z.stopEvent(e);
    if(this.form) this.form.submit(e);
    else if(this.submit) this.submit(e);
    return false;
}

function initCleanup(o)
{
    /*jshint validthis: true */
    if(!o || !Z.node(o)) o=this;
    Z.bind(o, 'click', cleanup);
}

function cleanup(e)
{
    /*jshint validthis: true */
    if(this.className.search(/\bcleanup\b/)>-1) Z.clearForm(this.form);
}

function clearForm(f)
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

/**
 * Datalist options
 */
function initDatalist(o)
{
    /*jshint validthis: true */
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
    /*jshint validthis: true */
    e = e || window.event;

    var m=0,t=false;

    var o=Z.node(this);
    if(!o && e && ('target' in e)) o=Z.node(e.target);

    if (e.keyCode == '38' || e.keyCode == '37') {
        // up arrow or left arrow
        m=-1;
    } else if (e.keyCode == '40' || e.keyCode == '39') {
        // down arrow or right arrow
        m=1;
    } else if(e.keyCode == '13') {
        e.preventDefault();
        t = true;
    } else if(e.keyCode=='27') {
        // escape
        e.preventDefault();
        return datalistClear.apply(o);
    } else {
        return datalistQueryTimeout.call(o);
    }
    var c=o.parentNode.querySelector('ul.tdz-datalist'), s=(c)?(c.querySelector('.tdz-selected')):(null);
    if(!s) {
        if(!c || c.children.length==0) return;
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



var _dq=null;
function datalistQueryTimeout()
{
    /*jshint validthis: true */
    var o=this;
    if(_dq) clearTimeout(_dq);
    _dq = setTimeout(function(){ datalistQuery.apply(o); }, 500);
}

function datalistQuery(e)
{
    /*jshint validthis: true */
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
        var T=o.form.querySelector('#'+x);
        if(!focus && T && Z.val(T)!='') {
            Z.val(T, '');
            Z.fire(T, 'change');
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
        h = {'z-action':'choices', 'z-target': encodeURIComponent(x), 'z-term': encodeURIComponent(v)};
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
    if(arguments.length>1) {
        var dtp=o.getAttribute('data-datalist-preserve');
        if(dtp && (dtp=='0'||dtp=='false'||dtp=='off')) dtp=null;
        else if(dtp && Z.val(o)) return a;
        Z.val(o, v, fire);
    }
    return a;
}

var _db=null;
function datalistBlurTimeout()
{
    /*jshint validthis: true */
    var o=this;
    if(_db) clearTimeout(_db);
    _db = setTimeout(function(){ datalistBlur.apply(o); }, 200);
}

function datalistBlur(e)
{
    /*jshint validthis: true */
    if(document.activeElement && !Z.parentNode(document.activeElement, this.parentNode)) {
        datalistClear.apply(this);
    }
}

function datalistClear()
{
    /*jshint validthis: true */
    var o=this, v=datalistVal(o), t=new Date().getTime()+500, c=o.parentNode.querySelector('.tdz-datalist-container');
    o.setAttribute('data-datalist-q', v);
    o.setAttribute('data-datalist-t', t);
    if(c) c.parentNode.removeChild(c);
}

var _D={};
function datalistRender(d)
{
    /*jshint validthis: true */
    var r=this.getAttribute('data-datalist-renderer');
    if(r && (r in Z)) {
        _D = Z[r].call(this, d, datalistOption);
        return _D;
    }
    var o=this, c=o.parentNode.querySelector('ul.tdz-datalist'), n, p;
    if(!c) c=Z.element.call(o.parentNode,{e:'span',p:{className:'tdz-datalist-container'},c:[{e:'ul',p:{className:'tdz-datalist'},a:{'data-target':o.getAttribute('id')}}]}).children[0];
    else c.innerHTML=''; // remove child nodes
    var id=o.getAttribute('id'), prefix = o.getAttribute('data-prefix');
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
            if (prefix != undefined ) {
                p.a['data-prefix'] = prefix;
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
    /*jshint validthis: true */
    var id=this.parentNode.getAttribute('data-target'), o=this.parentNode.parentNode.parentNode.querySelector('#'+id);
    if(!o) return;
    o.setAttribute('data-datalist-t', new Date().getTime() + 1000);
    var v=this.getAttribute('data-value'),p=_D[id][v],b=this.getAttribute('data-prefix'), s=false,fo=o.form, e, n;
    var dts=o.getAttribute('data-datalist-target'), dt={};
    if(dts) {
        var L=dts.split(/[\s\,\;]+/g), i=L.length;
        while(i-- >0) {
            if(L[i]) dt[L[i]] = true;
        }
        L=null;
        i=null;
        dts=null;
    }
    if(!b)b='';
    if(p) {
        if(typeof(p)=='string') {
            o.setAttribute('data-datalist-q', p);
            datalistVal(o, p);
            if(id.search(/^q__/)>-1) {
                e=fo.querySelector('#'+id.replace(/^q__/, ''));
                if(e) {
                    datalistVal(e, v, true);
                }
            }
        } else {
            s=false;
            if(id.search(/^q__/)>-1) {
                e=fo.querySelector('#'+id.replace(/^q__/, ''));
                if(e) {
                    for(n in p) {
                        datalistVal(e, p[n], true);
                        delete(p[n]);
                        break;
                    }
                }
            }
            for(n in p) {
                if(p[n].hasOwnProperty) {
                    if(!s) {
                        s=true;
                        o.setAttribute('data-datalist-q', p[n]);
                        datalistVal(o, p[n]);
                    }
                    e=fo.querySelector('#'+b+n);
                    if(!e) e=fo.querySelector('*[name="'+b+n+'"]');
                    if(e && (n in dt)) {
                        datalistVal(e, p[n], (id!=n) && n.substr(0,3)!='q__');
                    }
                }
            }
        }
    }
    datalistClear.call(o);
}
//Z.datalistOption = datalistOption;

var _Picker={}, _Pickerc=0, _PickerT=0;
function initDatepicker()
{
    /*jshint validthis: true */
    if(!('datepicker' in Z) && ('Pikaday' in window)) Z.datepicker = 'Pikaday';
    if(!('datepicker' in Z) || this.getAttribute('data-datepicker')) return;

    var id='p'+(_Pickerc++);
    this.setAttribute('data-datepicker', id);

    if(Z.datepicker=='Pikaday') {
        var t=this.getAttribute('data-type'), cfg={ field: this, i18n: Z.l[Z.language], format:Z.l[Z.language].dateFormat, showTime: false };
        if(!t) t=this.getAttribute('type');
        if(t && t.search(/time/)>-1) {
            cfg.showTime = true;
            cfg.use24Hour = true;
            cfg.format+= ' '+Z.l[Z.language].timeFormat;
        }
        _Picker[id] = new Pikaday(cfg);
    }
    if(_PickerT) clearTimeout(_PickerT);
    _PickerT = setTimeout(cleanupDatepicker, 500);
}

Z.initDatepicker = initDatepicker;

function cleanupDatepicker()
{
    if(_PickerT) clearTimeout(_PickerT);
    _PickerT=0;
    for(var n in _Picker) {
        if(!document.querySelector('*[data-datepicker="'+n+'"]')) {
            _Picker[n].destroy();
            delete(_Picker[n]);
        }
        n=null;
    }
}

function initRequiredField(e)
{
    /*jshint validthis: true */
    var f=Z.parentNode(this, '.field');
    if(f) {
        f.className += ' required';
    }
}

function initUploader(o)
{
    /*jshint validthis: true */
    var f=Z.node(this, o);
    if(f.getAttribute('type')!='file' || f.getAttribute('data-status') || !('FileReader' in window)) return;
    f.setAttribute('data-status','ready');

    //Z.bind(f, 'input', preUpload);
    Z.bind(f, 'change', preUpload);
}

function preUpload(e)
{
    /*jshint validthis: true */
    Z.stopEvent(e);
    if(this.getAttribute('data-status')!='ready') return;
    this.setAttribute('data-status','uploading');
    var i=this.files.length, U={target:this,size:0,loaded:0,url:this.form.action,id:'upl'+((new Date()).getTime())};

    U.progress = this.parentNode.querySelector('.tdz-i-progress');
    if(!U.progress) U.progress = Z.element({e:'div',p:{className:'tdz-i-progress'},c:[{e:'div',p:{className:'tdz-i-progress-bar'}}]}, null, this);
    var s=this.getAttribute('data-size'),a=this.getAttribute('accept'),ff, err=[], valid;
    if(a) a=','+a+',';
    clearMsg(this.parentNode);
    while(i--) {
        // check file size and accepted formats
        if(s && s<this.files[i].size) {
            err.push(Z.t('UploadSize')+' ');
        }
        if(a) {
            valid = false;
            ff = this.files[i].type;
            if(ff) {
                if(a.indexOf(','+ff+',')>-1) {
                    valid=true;
                } else if(ff.indexOf('/')>-1 && a.indexOf(','+ff.replace(/\/.*/, '/*')+',')>-1) {
                    valid = true;
                }
            }
            if(!valid && (ff=this.files[i].name.replace(/.*(\.[^\.]+$)/, '$1')) && a.indexOf(','+ff+',')>-1) {
                valid = true;
            }
            if(!valid) {
                err.push(Z.t('UploadInvalidFormat')+' ');
            }
        }
    }
    if(err.length>0) {
        errorMsg(this.parentNode, err);
        this.setAttribute('data-status','ready');
        return false;
    }
    i=this.files.length;
    while(i--) {
        uploadFile(this.files[i], U);
    }
}

function errorMsg(o, m)
{
    return Z.element.call(o, {e:'div',p:{className:'tdz-i-msg tdz-i-error'},c:m});
}

function clearMsg(o)
{
    var L=o.querySelectorAll('.tdz-i-msg'), i=L.length;
    while(i--) {
        L[i].parentNode.removeChild(L[i]);
    }
}

function clearFileInput(el) {
  try {
    el.value = null;
    return el;
  } catch(ex) { }
  if (el.value) {
    return el.parentNode.replaceChild(el.cloneNode(true), el);
  }
}

function uploadFile(file, U)
{
    var loaded = 0;
    var step = 700000;//1024*1024;
    var total = file.size;
    var i=0;
    var ajax = [];
    var H = { 'Tdz-Action': 'Upload', 'Content-Type': 'application/json' };
    var workers = 2;
    var retries = 3;
    U.size += total;
    //var progress = document.getElementById(file.name).nextSibling.nextSibling;

    var reader = new FileReader();

    var uploadProgress = function(d)
    {
        if('size' in d) {
            U.loaded += d.size;
            if(U.loaded > U.size) {
                U.loaded = U.size;
            }
            workers++;
        }
        var w=(U.loaded*100/U.size);

        U.progress.querySelector('.tdz-i-progress-bar').setAttribute('style','width:'+w+'%');

        var el = this;
        if('id' in d) {
            el.previousSibling.value = d.value;
            var b=el.parentNode.querySelector('span.text');
            var t={e:'a',p:{className:'tdz-i-upload'},t:{click:removeUpload},c:d.file};
            if(!b) b=Z.element({e:'span',p:{className:'text'},c:t});
            else {
                Z.removeChildren(b, ':not(.tdz-i-upload)');
                Z.element.call(b, t);
            }
            b.className += ' tdz-f-file';
            el.setAttribute('data-status', 'ready');
            el = clearFileInput(el);
            //var v=d.value;
        }

        if(workers--) {
            if(ajax.length > 0) {
                Z.ajax.apply(el, ajax.shift());
            }
        }
    };

    var uploadError = function(d)
    {
        console.log('upload error!', this, d);
        if(retries--) {

        }
        //workers++;
    };

    reader.onload = function(e) {
        var d = { _upload: { id: U.target.id, uid: U.id, file: file.name, start: loaded, end: loaded+step, total: total, data: e.target.result, index: i++ }  };
        loaded += step;
        if(U.target.name.indexOf('[')) {
            var n=U.target.name, t=d, p=n.indexOf('['), m;
            while(p>-1) {
                m=n.substr(0,p+1).replace(/[\[\]]+/, '');
                n=n.substr(p+1);
                p=n.indexOf('[');
                if(p>-1) t[m]={};
                else t[m]=0;
                t=t[m];
            }
        }
        //progress.value = (loaded/total) * 100;
        if(loaded <= total) {
            blob = file.slice(loaded,loaded+step);
            reader.readAsDataURL(blob);
        } else {
            d._upload.end = loaded = total;
            d._upload.last = true;
        }
        var data = JSON.stringify(d), u = U.url;
        if(u.indexOf('?')>-1) u+='&_index='+d._upload.index;
        else u+='?_index='+d._upload.index;
        d = null;
        if(workers) {
            Z.ajax(u, data, uploadProgress, uploadError, 'json', U.target, H);
            workers--;
        } else {
            ajax.push([u, data, uploadProgress, uploadError, 'json', U.target, H]);
        }
    };
    var blob = file.slice(loaded,step);
    reader.readAsDataURL(blob);
}

function removeUpload(e)
{
    /*jshint validthis: true */
    var el = this.parentNode.parentNode.querySelector('input[type="hidden"]');
    el.value='';// remove only this upload
    el=null;
    this.parentNode.className = this.parentNode.className.replace(/\s*\btdz-f-file\b/g, '');
    this.parentNode.removeChild(this);
}


function initFilters()
{
    /*jshint validthis: true */
    var t=this;
    if(this.className.search(/\btdz-a-filters\b/)>-1) return;
    //Z.bind(this, 'input', formFilters);
    Z.bind(this, 'change', formFilters);
    formFilters.call(this);
}

var _FF={};

function enableField(on)
{
    /*jshint validthis: true */
    if(arguments.length==0) on=true;
    var cn = this.className,an='readonly', L, i;
    if(on) {
        if(cn.search(/\btdz-f-disable\b/)>-1) cn=cn.replace(/\s*\btdz-f-disable\b/g, '');
        if(cn.search(/\btdz-f-enable\b/)<0) cn+=' tdz-f-enable';
        L=this.querySelectorAll('input['+an+'],select['+an+'],textarea['+an+']');
        i=L.length;
        while(i--) L[i].removeAttribute(an);
    } else {
        if(cn.search(/\btdz-f-enable\b/)>-1) cn=cn.replace(/\s*\btdz-f-enable\b/g, '');
        if(cn.search(/\btdz-f-disable\b/)<0) cn+=' tdz-f-disable';
        L=this.querySelectorAll('input:not(['+an+']),select:not(['+an+']),textarea:not(['+an+'])');
        i=L.length;
        while(i--) L[i].setAttribute(an, an);
    }
    cn=cn.trim();
    if(cn!=this.className) this.className = cn;
}

function displayField(on)
{
    /*jshint validthis: true */
    if(arguments.length==0) on=true;
    var cn = this.className,an='readonly', L, i;
    if(on) {
        if(cn.search(/\bi-hidden\b/)>-1) cn=cn.replace(/\s*\bi-hidden\b/g, '');
        L=this.querySelectorAll('input['+an+'],select['+an+'],textarea['+an+']');
        i=L.length;
        while(i--) L[i].removeAttribute(an);
    } else {
        if(cn.search(/\bi-hidden\b/)<0) cn+=' i-hidden';
        L=this.querySelectorAll('input:not(['+an+']),select:not(['+an+']),textarea:not(['+an+'])');
        i=L.length;
        while(i--) L[i].setAttribute(an, an);
    }
    cn=cn.trim();
    if(cn!=this.className) this.className = cn;
}

/**
 * Form filters
 */
function formFilters(e)
{
    /*jshint validthis: true */
    var a=this.getAttribute('data-filters');
    if(!a) return;

    var reset=(this.className.search(/\btdz-a-filters\b/)<0);
    if(reset) this.className += ' tdz-a-filters';

    var t=(a.indexOf(',')>-1)?(a.split(',')):([a]), i=t.length, nn=this.getAttribute('name'), fa=this.getAttribute('data-filter-action'),
      tn, ltn, tp='', L, l, T, s, v=Z.val(this), tv, O,sel,A,fn,P, fid=(this.form.id)?(this.form.id + '.'):(''), fk, n;
    if(v && this.getAttribute('data-filter-value')) {
        var av=this.getAttribute('data-filter-value').split(/\s*\,\s*/g), avi=av.length,avf;
        while(avi--) {
            if(v==av[avi]) {
                avf=v;
                break;
            }
        }
        if(!avf) v=null;
    }
    if(nn.indexOf('[')>-1) {
        nn=nn.replace(/.*\[([^\[]+)\]$/, '$1');
        tp = this.id.substr(0, this.id.length - nn.length);
    }
    while(i--) {
        tn = tp+t[i];
        ltn = (tn.search(/\[[^\]]+/i)>-1)?(tn.replace(/^.*\[([^\]]+)\](\[\])?$/, '$1')+' '):(tn);
        fk = fid+tn;
        if(fa) {
            if((T=this.form.querySelector('#f__'+tn.replace(/[\[\]\-]+/g, '_')+',.if--'+ltn))) {
                if(fa=='enable' || fa=='disable') {
                    enableField.call(T, (fa=='enable')?(v):(!v));
                } if(fa=='show' || fa=='display' || fa=='hide') {
                    displayField.call(T, (fa!='hide')?(v):(!v));
                }
            }
        } else if((T=this.form.querySelector('select#'+tn))) {
            L = T.querySelectorAll('option');
            if(!(fk in _FF)) {
                _FF[fk]={o:[], v:{}, f:{}};
                for(l=0;l<L.length;l++) {
                    if(L[l].selected) _FF[fk].v[L[l].value]=true;
                    A=L[l].attributes;
                    n=A.length;
                    P={};
                    while(n--) {
                        if(A[n].name!='selected') {
                            P[A[n].name]=A[n].value;
                        }
                    }
                    P.label = L[l].label;//innerHTML;//Z.text(L[l]);
                    _FF[fk].o.push(P);
                }
            } else {
                _FF[fk].v = {};
                for(l=0;l<L.length;l++) {
                    if(L[l].selected) _FF[fk].v[L[l].value]=true;
                }
            }

            if(reset || !(nn in _FF[fk].f) || v!=_FF[fk].f[nn]) {
                _FF[fk].f[nn] = v;
                O = [];
                L=_FF[fk].o;
                for(l=0;l<L.length;l++) {
                    sel = (L[l].value in _FF[fk].v);
                    tv=true;
                    if(L[l].value) {
                        for(fn in _FF[fk].f) {
                            // make do for multiple source filters
                            if(!('data-'+fn in L[l]) || L[l]['data-'+fn]!=_FF[fk].f[fn]) {
                                tv=false;
                                break;
                            }
                        }
                    }
                    if(tv) O.push({e:'option',a:L[l],p:{'selected':sel},c:L[l].label});
                }
                Z.removeChildren(T);
                Z.element.call(T,O);
                if(T.getAttribute('data-filters')) {
                    Z.fire(T, 'change');
                }
            }
        } else if((T=this.form.querySelector('#f__'+tn.replace(/-/g, '_')))) {
            L = T.querySelectorAll('input[type="radio"],input[type="checkbox"]');
            if(!(fk in _FF)) {
                _FF[fk]={c:{}, v:{}, f:{}};
            }
            for(l=0;l<L.length;l++) {
                if(L[l].checked) _FF[fk].v[L[l].value]=true;
                _FF[fk].c[L[l].id]=L[l].value;
            }

            if(reset || !(nn in _FF[fk].f) || v!=_FF[fk].f[nn]) {
                _FF[fk].f[nn] = v;
                O = [];
                L=_FF[fk].c;
                var E, Pn;
                for(n in L) {
                    sel = (L[l] in _FF[fk].v);
                    tv=true;
                    if(L[l]) {
                        E = T.querySelector('input#'+n);
                        for(fn in _FF[fk].f) {
                            // make do for multiple source filters
                            if(!(a=E.getAttribute('data-'+fn)) || a!=_FF[fk].f[fn]) {
                                tv=false;
                                break;
                            }
                        }
                    }
                    if(!(Pn=Z.parentNode('label'))) {
                        Pn = E.parentNode;
                    }
                    if(tv) {
                        if(Pn.className.search(/\bi-hidden\b/)>-1) {
                            Pn.className = Pn.className.replace(/\s*\bi-hidden\b/g, '');
                        }
                        E.checked = sel;
                        E.setAttribute('checked', 'checked');
                    } else {
                        if(Pn.className.search(/\bi-hidden\b/)<0) {
                            Pn.className += ' i-hidden';
                        }
                        E.checked = false;
                        E.removeAttribute('checked');
                    }
                }
                /*
                if(E.getAttribute('data-filters')) {
                    Z.fire(E, 'change');
                }
                */
            }
        }
        //@TODO: search, checkbox and radio
    }
}

/**
 * Subform/multiple input inclusion
 */
function initSubform(o)
{
    /*jshint validthis: true */
    if(!o || !Z.node(o)) o=this;
    var btns=[{e:'a',a:{title:'-','class':'tdz-button-del'},t:{click:subformDel}},{e:'a',a:{title:'+','class':'tdz-button-add'},t:{click:subformAdd}}];

    // items
    var id=o.getAttribute('id');
    if(!id) {
        id='_zidf'+(_eids++);
        o.setAttribute('id', id);
    }
    var L=document.querySelectorAll('#'+id+'>.item'), i=L.length, fmin=o.getAttribute('data-min'), fmax=o.getAttribute('data-max'), cb;
    // add minimum fields
    if(fmin && i < fmin) {
        while(i++ < fmin) {
            subformAdd(o);
        }
        L=o.querySelectorAll('#'+id+'>.item');
        i=L.length;
    }

    // buttons: add, add(contextual), remove(contextual)
    if(!fmax || fmax!=i || fmax!=fmin) {
        var b=o.parentNode.parentNode.querySelector('div.tdz-subform-buttons');
        if(!b) b = Z.element({e:'div',p:{className:'tdz-subform-buttons tdz-buttons'},c:[btns[1]]}, o.parentNode);
        while(i-- > 0) {
            if(fmax && i > fmax) {
                Z.deleteNode(L[i]);
            } else if(!(cb=L[i].querySelector('.tdz-buttons')) || cb.parentNode!=L[i]) {
                if(cb) {
                    // might be sub-subforms, check if there's the button
                    var cL=L[i].querySelectorAll('.tdz-buttons'), ci=cL.length;
                    cb=null;
                    while(ci--) {
                        if(cL[ci].parentNode==L[i]) {
                            cb=cL[ci];
                            break;
                        }
                    }
                    if(cb) continue;
                }

                var xx=Z.element.call(L[i], {e:'div',p:{className:'tdz-buttons'},c:btns});
            }
        }
    }
    // minimun
    /*
    if(fmin && sf.length<=fmin && !bdel.hasClass('disabled')) bdel.addClass('disabled');
    else if(fmin && sf.length>fmin && bdel.hasClass('disabled')) bdel.removeClass('disabled');
    */
}

var _subformPos='§';
function subformAdd(e)
{
    /*jshint validthis: true */
    var el, o;

    if((o=Z.node(e))) {
        if(arguments.length>1) el=Z.node(arguments[1]);
    } else {
        Z.stopEvent(e);
        Z.tg = this;

        if(this.parentNode.parentNode.className.search(/\bitem\b/)>-1) {
            el=this.parentNode.parentNode;
            o=el.parentNode;
        } else {
            o = this.parentNode.nextSibling.childNodes[0];
        }
    }
    if(!o) return false;
    var tpl=o.getAttribute('data-template'),
        prefix=o.getAttribute('data-prefix'),
        _prefix=prefix.replace(/[\[\]\_]+/g, '_').replace(/_+$/, ''),
        sf=o.querySelectorAll('.item'),
        i=sf.length,
        fmax=o.getAttribute('data-max'),
        n,
        re;

    if(!(fmax && sf.length>=fmax)) {
        if(i>0){
            var ne=sf[i-1].querySelector('*[name]');
            if(ne) {
                n=ne.getAttribute('name');
                re=new RegExp(prefix.replace(/([^a-z0-9])/i, '\\\$1')+'\\\[([0-9]*)\\\].*');
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
        re=new RegExp((prefix+'\[§\]').replace(/([^a-z0-9])/gi, '\\\$1'), 'gi');
        var ri=new RegExp((_prefix+'_§_').replace(/([^a-z0-9])/gi, '\\\$1'), 'gi');
        var c=document.createElement('div');
        c.innerHTML = tpl.replace(re, prefix+'['+i+']').replace(ri, _prefix+'_'+i+'_');
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
    /*jshint validthis: true */
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


function Form(o)
{
    var q='Form.Form';
    if(q in Z.modules) {
        delete(Z.modules[q]);
        Z.load('z-form.css');
        Z.addPlugin('Datepicker', initDatepicker, 'input[data-type^=date],input[type^=date],.tdz-i-datepicker');
        Z.addPlugin('RequiredField', initRequiredField, '.field > .input > *[required]');
        Z.addPlugin('Datalist', initDatalist, '*[data-datalist-api],*[data-datalist]');
        Z.addPlugin('Uploader', initUploader, 'input[data-uploader]');
        Z.addPlugin('Filters', initFilters, 'input[data-filters],select[data-filters]');
        Z.addPlugin('Subform', initSubform, 'div.subform[data-template],div.items[data-template]');
        Z.addPlugin('Cleanup', initCleanup, 'button.cleanup');
        Z.clearForm=clearForm;

        var n=Z.node(o, this);
        if(n) Z.init(n);
    }
}


// new modules
//if(!('ZModules' in window))window.ZModules={};
//window.ZModules['*[data-datalist-api],*[data-datalist]'] = Datalist;

// default modules loaded into Z
window['Z.Form.Form'] = Form;
window['Z.Form.CheckLabel']=initCheckLabel;
window['Z.Form.AutoSubmit']=initAutoSubmit;

})();