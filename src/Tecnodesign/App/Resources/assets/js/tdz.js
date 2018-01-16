/*!
 * Tecnodesign modules, divided per app
 *
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
*/
if(!('tdz' in window)) window.tdz={};
(function(tdz){
    var T0 = (new Date()).getTime();
    // Tecnodesign_App
    var d={
        dev: false,
        language:'pt-BR',
        dateFormat:'dd/mm/yy',
        timeFormat:'HH:mm:ss',
        decimalSeparator:',',
        thousandSeparator:'.',modules:{},
        iconClass:'icon',
        dir:'/_assets/tecnodesign',
        tinymce_css: '/_assets/css/esr.css'
    };
    for(var n in d) {
        if(!(n in tdz)) tdz[n]=d[n];
        delete(d[n]);
    }
    delete(d);
    tdz.trace=function() {
        var t=((new Date()).getTime()-T0)*0.001;
        for(var i=0;i<arguments.length;i++) 
            console.log(t.toFixed(3), arguments[i]);
        delete(t);
    }
    tdz.init=function(o){
        var c=tdz.isNode(o),n;
        if(c) n=true;
        else {
            c=$(document);
            n=false;
        }
        tdz.getLanguage();
        for(var i in tdz.modules){
            var ifn='init'+i;
            if(tdz.modules[i] && ifn in tdz) {
                if(typeof(tdz.modules[i])=='string') {
                    var t=(n && c.is(tdz.modules[i]))?(c):($(tdz.modules[i],c));
                    if(t.length>0) {
                        tdz[ifn](t);
                    }
                } else if(tdz.modules[i]) {
                    tdz[ifn](c);
                }
            }
        }
    }

    tdz.addPlugin=function(id, fn, q) {
        id = '_'+id;
        if(!(id in tdz.modules)) {
            tdz.modules[id]=q;
            tdz['init'+id]=fn;
        }
    }

    // find the base path of a script
    if(!('config' in tdz)) tdz.config = {};
    tdz.modules.Config='html';
    tdz.initConfig=function(){
        if(!('assetsUrl' in tdz.config)) {
            tdz.config.assetsUrl='/_assets';
            var s=document.getElementsByTagName('script');
            for (var i = s.length - 1; i >= 0; --i) {
                if(s[i].src.indexOf(window.location.host)==-1) continue;
                var src = s[i].src.substr(s[i].src.indexOf(window.location.host)+window.location.host.length);
                if(src.substr(0,1)=='/' && src.search(/\/[a-f0-9]+\.js\?[0-9]+$/)>-1) { // minimized javascript
                    tdz.config.assetsUrl=src.replace(/\/[a-f0-9]+\.js\?[0-9]+$/, '');
                    break;
                }
            }
        }
    }

    function loadComponent()
    {
        var i=arguments.length;
        while(i-- >0) {
            if(arguments[i].indexOf('.css')>-1) {
                tdz.element.call(document.getElementsByTagName('head')[0], {e:'link',a:{rel:'stylesheet',type:'text/css',href:arguments[i]}});
            } else if(arguments[i].indexOf('.js')>-1) {
                tdz.element.call(document.body, {e:'script',p:{async:true,src:arguments[i]}});
            } else {
                var p=arguments[i].indexOf('?'),
                    f=(p>0)?(arguments[i].substr(0,p)):(arguments[i]),
                    qs=(p>0)?(arguments[i].substr(p)):('');
                load(tdz.config.assetsUrl+'/css/'+f+'.css'+qs, tdz.config.assetsUrl+'/js/'+f+'.js'+qs);
                delete(p);
                delete(f);
                delete(qs);
            }
        }
        delete(i);
    }
    if(!('load' in tdz)) tdz.load=loadComponent;

    var _delayTimers = {};
    tdz.delay=function (fn, ms, uid) {
        if (!uid) {uid ='dunno';};
        if (uid in _delayTimers) {clearTimeout(_delayTimers[uid]);};
        _delayTimers[uid] = setTimeout(fn, ms);
    };

    tdz.delayedChange=function(e)
    {
        var t=e.target, id=t.getAttribute('id')+'-delayed-change';
        tdz.delay(function(){tdz.fire(t, 'change');}, 300, id);
    }
    

    tdz.selectors = {
        'checkinput': 'input[type=checkbox],input[type=radio]',
        'dateinput': 'input[type=date],input[data-type=date],input.date-picker',
        'datetimeinput': 'input[type=datetime],input[data-type=datetime],input[data-type=date],input.datetime-picker',
        'callbackinput': 'input[data-callback],select[data-callback]',
        'fileinput': '.app-file-preview input[type=file]:not(.ui-enabled)',
        'select': 'select'
    }



    tdz.formatNumber=function(n, d, ds, ts)
    {
        if(!d) d=2;
        var x = (n.toFixed(d) + '').split('.');
        var x1 = x[0];
        if(!ds) ds=tdz.decimalSeparator;
        var x2 = x.length > 1 ? ds + x[1] : '';
        var rgx = /(\d+)(\d{3})/;
        while (rgx.test(x1)) {
            if(!ts) ts = tdz.thousandSeparator;
            x1 = x1.replace(rgx, '$1' + ts + '$2');
        }
        return x1 + x2;
    };

    tdz.text=function(o)
    {
        if('textContent' in o) {
            if(arguments.length>1) {
                o.textContent = arguments[1];
            }
            return o.textContent;
        } else {
            if(arguments.length>1) {
                o.innerText = arguments[1];
            }
            return o.innerText;
        }
    }

    tdz.debug=function()
    {
        var s='';
        for(var i=0;i<arguments.length;i++){
            var a=arguments[i];
            if(i>0)s+='\n';
            if(Object.prototype.toString.call(a) === '[object Array]'){
                s+='[ ';
                var s2=[];
                for(var p=0;p<a.length;p++){
                    s2.push(((a[p]!==false)?(tdz.debug(a[p],false)):('false')));
                }
                s+=s2.join(', ')+' ]';
            } else if(typeof(a)=='object'){
                s+='{ ';
                var s2=[];
                for(var p in a){
                    s2.push('"'+p+'":'+((a[p]!==false)?(tdz.debug(a[p],false)):('false')));
                }
                s+=s2.join(', ')+' }';
            } else if(typeof(a)=='string'){
                s+='"'+a.replace(/\"/g, '\\\"').replace(/\s+/g, ' ')+'"';
            } else if(typeof(a)=='function'){
                s+='function(){}';
            } else if(a===false){
                return s;
            } else if(a===true){
                s+='true';
            } else {
                s+=''+a;
            }
        }
        s+='\n';
        alert(s);
        return false;
    };
    
    tdz.log=function(s)
    {
        var o=$('#log');
        if(o.length==0){
            $('body').append('<div id="#log"></div>');
            o=$('#log');
        }
        o.append('<pre>'+tdz.debug(s, false)+'</pre>');
    }

    tdz.ucfirst = function(s) {
        return s.charAt(0).toUpperCase() + s.slice(1);
    }
    
    tdz.slug=function(str)
    {
      str = str.replace(/^\s+|\s+$/g, ''); // trim
      str = str.toLowerCase();

      // remove accents, swap ñ for n, etc
      var from = "ãàáäâẽèéëêìíïîõòóöôùúüûñç·/_,:;";
      var to   = "aaaaaeeeeeiiiiooooouuuunc------";
      for (var i=0, l=from.length ; i<l ; i++) {
        str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
      }

      str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
        .replace(/\s+/g, '-') // collapse whitespace and replace by -
        .replace(/-+/g, '-') // collapse dashes
        .replace(/^-|-$/g, '');

      return str;
    };
    

    /*!isnode*/
    tdz.isNode=function()
    {
        for(var i=0;i<arguments.length;i++) {
            o=arguments[i];
            if((typeof(o)=='string' && o) || (typeof(o)=='object' && ('jquery' in o || 'nodeName' in o))) {
                return $(o);
            }
        }
        return false;
    }
    
     
    tdz.selectState=function(e)
    {
        var s=$(this);
        if(s.val()=='') {
            s.removeClass('options').addClass('placeholder');
        } else {
            s.removeClass('placeholder').addClass('options');
        }
    }

    tdz.bind=function(o, tg, fn) {
        if (o.addEventListener) {
            o.addEventListener(tg, fn, false);
        } else if (o.attachEvent) {
            o.attachEvent('on'+tg, fn);
        } else {
            o['on'+tg] = fn;
        }
    }
    tdz.unbind=function(o, tg, fn) {
        if (o.addEventListener) {
            o.removeEventListener(tg, fn, false);
        }
        if (o.detachEvent) {
            o.detachEvent('on'+tg, fn);
        }
        if('on'+tg in o) {
            o['on'+tg] = null;
            o.removeAttribute('on'+tg);
        }
    }
    tdz.fastTrigger=function(o,fn){
        if(o.addEventListener) {
            o.addEventListener('touchstart', fn, false);
            o.addEventListener('mousedown', fn, false);
        } else if(o.attachEvent) {
            o.attachEvent('onclick', fn);
        }
    }
    tdz.trigger=function(o,fn){
        if(o.addEventListener) {
            o.addEventListener('tap', fn, false);
            o.addEventListener('click', fn, false);
        } else if(o.attachEvent) {
            o.attachEvent('onclick', fn);
        }
    }
    tdz.stopEvent=function(e){
        e.preventDefault();
        e.stopPropagation();
        return false;
    }
    tdz.node=function()
    {
        for(var i=0;i<arguments.length;i++) {
            o=arguments[i];
            if(typeof(o)=='string' && o && (document.querySelector(o))) return o;
            else if('nodeName' in o) return o;
            else if('jquery' in o) return o.get(0);
        }
        return false;
    }
    tdz.parentNode=function(p, q)
    {
        if(!p || !(p=tdz.node(p))) return false;
        else if((typeof(q)=='string' && p.matchesSelector(q))||p==q) return p;
        else if(p.nodeName.toLowerCase()!='html') return tdz.parentNode(p.parentNode, q);
        else return;
    }
    tdz.blur=function(o)
    {
        if(o && o.className.search(/\btdz-blur\b/)<0) {
            o.className += ' tdz-blur';
        }
    }

    tdz.focus=function(o)
    {
        if(o && o.className.search(/\btdz-blur\b/)>0) {
            o.className = o.className.replace(/\s*\btdz-blur\b/, '');
        }
    }

    // Tecnodesign_Form
    tdz.modules.Form='form';
    tdz.initFormPlugins={};
    tdz.initForm=function(F)
    {
        if('length' in F && !(('nodeName' in F) && F.nodeName.toLowerCase()=='form')) {
            if(F.length==0) return;
            else if(F.length==1)F=F[0];
            else {
                var i=0;
                while(i<F.length) {
                    tdz.initForm(F[i++]);
                }
                return;
            }
        }
        // jquery is required...
        var f=$(F);
        // disable date support on browsers that already have embedded calendars
        if (!tdz.isDateInputSupported()) {
            $(tdz.selectors.dateinput+' '+tdz.selectors.datetimeinput, f).not('.tdz-dt').each(adjustDate);
            $(tdz.selectors.dateinput, f).not('.tdz-dt').addClass('tdz-dt').datepicker();
            $(tdz.selectors.datetimeinput, f).not('.tdz-dt').addClass('tdz-dt').datetimepicker();
        } else {
            $(tdz.selectors.dateinput, f).not('.tdz-dt').addClass('tdz-dt').click(function(e){
                 e.preventDefault();
            }).datepicker({
                onSelect: function(dateText){
                    if(dateText.indexOf('/') && this.getAttribute('type')!='text') {
                        fd = $(this).datepicker( "option", "dateFormat" );
                        dt = $.datepicker.parseDate(fd, dateText);
                        dateText = $.datepicker.formatDate('yy-mm-dd',dt);
                    }
                    $(this).val(dateText).trigger('change');
                }
            });
        }

        // file preview
        var fi=$(tdz.selectors.fileinput, f);
        if(fi.length>0) {
            var i=fi.length, fie;
            while(i-- > 0) {
                fie=fi.eq(i);
                fie.addClass('ui-enabled');
                if(fie.prev('input[type="hidden"]').val()) fie.hide().before('<a class="icon update" onclick="$(this).next(\'input\').toggle(500);return false;" href="#preview"></a>');
            }
        }

        if('mask' in $) $('input[data-mask]', f).mask();
        $('div.subform[data-template]',f).each(function(i,o){tdz.subform($(o))});
        //$(tdz.selectors.checkinput,f).attr('onClick','tdz.checkField(this)').each(tdz.checkField);
        //Verifica se já tem uma função onClick e se está preenchida, caso sim não adiciona nada       
        $(tdz.selectors.checkinput,f).each(function(i,o) {                        
            if ($(o).attr('onClick') == undefined || $(o).attr('onClick') == '') {
                $(o).attr('onClick','tdz.checkField(this)');
                tdz.checkField($(o));
            }            
        });
        $(tdz.selectors.select,f).each(function(i,fo){
            fo=$(fo);
            if(!fo.hasClass('placeholder') && !fo.hasClass('options')) {
                fo.bind('change', tdz.selectState);
                tdz.selectState.call(fo);
            }
        });
        
        $(tdz.selectors.callbackinput,f).each(function(i,fo) {
            var fn=fo.getAttribute('data-callback').replace(/[^a-z0-9\.\_\,]+/gi, ''),m='change';
            if(!fn) return;
            fo.removeAttribute('data-callback');
            var fs = fn.split(/\,/g),i=0, F;
            for(i=0;i<fs.length;i++) {
                fn = fs[i];
                if(fn in tdz) F=tdz[fn];
                else F=tdz.obj(fn, null, 'function');
                if(F) {
                    tdz.bind(fo, m, F);
                }
                delete(F);
            }
            /*
            if(fn in tdz) {
                tdz[fn](fo);
                fn='tdz.'+fn;
            } else {
                eval(fn+'(fo);');
            }
            if(fo.attr('on'+m)) fo.attr('on'+m, fn+'(this);'+fo.attr('on'+m));
            else fo.attr('on'+m, fn+'(this)');
            if(fo.get(0).nodeName.toLowerCase()=='input') {
                fo.keypress(tdz.delayedChange);
            }
            */
            
            if(fo.nodeName.toLowerCase()=='input') {
                tdz.bind(fo, 'keypress', tdz.delayedChange);
            }
            tdz.fire(fo, m);
        });
        for(var s in tdz.initFormPlugins){
            var el=$(s, f),m=tdz.initFormPlugins[s];
            if(el.length>0) {
                if(typeof(m)=='string'){
                    tdz[m](el);
                } else {
                    m(el);
                }
            }
        }
        if(!F.getAttribute('novalidate')) {
            tdz.bind(F, 'submit', tdz.validateForm);
            F.setAttribute('novalidate', 'novalidate');
        }

    }

    tdz.checkField=function(e) 
    {
        var o=tdz.isNode(e,this), c=(o.attr('checked'))?(true):(false), ty=o.attr('type'), p=o.parent();
        
        if(c) {
            if(ty=='radio') o.parents('.input').eq(0).find('span.radio.on').not(p).removeClass('on');
            p.not('.on').addClass('on');
        } else if(!c) {
            p.filter('.on').removeClass('on');
        }
        if(o.attr('onchange')) {
            o.get(0).onchange();
        }
    }

    tdz.click=function(c)
    {
        return tdz.fire(c, 'click');
    }

    tdz.fire=function(c, ev)
    {
        if('createEvent' in document) {
            var e=document.createEvent('HTMLEvents');
            e.initEvent(ev, true, true);
            return c.dispatchEvent(e);
        } else {
            return c.fireEvent('on'+ev);
        }
    }

    tdz.element=function(o,before,after) {
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
                    if(n=='trigger' || n=='fastTrigger') tdz[n](r,o.t[n]);
                    else tdz.addEvent(r,n,o.t[n]);
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
                    else tdz.element.call(r,o.c[i]);
                    i++;
                }
                delete(i);
                delete(t);
            }
        }
        delete(o);

        if(before) return before.parentNode.insertBefore(r,before);
        else if(after) return after.parentNode.insertBefore(r,after.nextSibling);
        else if(this.appendChild) return this.appendChild(r);
        else return r;
    }

    tdz.checkInput=function(e, c, r)
    {
        if(arguments.length==1 || c===null) c=e.checked;
        else if(e.checked==c) return;
        if(e.checked!=c) {
            e.checked = c;
            tdz.fire(e, 'change');
        }
        if(arguments.length<3 || r) tdz.click(e);
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

    tdz.toggleInput=function(q, c)
    {
        var f=document.querySelectorAll(q), i=f.length, chk=(tdz.isNode(c))?(c.checked):(false);
        while(i-- > 0) {
            if(f[i]==c) continue;
            tdz.checkInput(f[i], chk, false);
        }
    }

    var _v=false, _f={};

    function adjustDate(i,o)
    {
        if(o.getAttribute('type')!='text' && !tdz.isDateInputSupported() && tdz.dateFormat && o.value) {
            var d=new Date(o.value);
            var f=tdz.dateFormat;
            if(d) {
                var s=$.datepicker.formatDate(f,d);
                if(o.getAttribute('data-type')=='datetime') s+=' '+$.datepicker.formatTime(tdz.timeFormat,{hour:d.getHours(),minute:d.getMinutes(),second:d.getSeconds(),timezone:d.getTimezoneOffset()/60});
                o.value = s;
            }
        }
    }

    function serializeDate(i,o)
    {
        if(o.value=='') return;
        var m=o.value.match(/^([0-9]{1,2})[-\/]([0-9]{1,2})[-\/]([0-9]{4})\s*(.*)$/);
        if(m) {
            o.setAttribute('data-value', m[0]);
            //check language
            if(tdz.language.substr(0,2)=='pt' || parseInt(m[1])>12) { // dd/mm/yyyy
                o.value = m[3]+'-'+String('0'+m[2]).slice(-2)+'-'+String('0'+m[1]).slice(-2)
                    +((m[4])?(' '+m[4].replace(/\s+/g, '').replace(/-/g, ':')):(''));
            } else {
                o.value = m[3]+'-'+String('0'+m[1]).slice(-2)+'-'+String('0'+m[2]).slice(-2)
                    +((m[4])?(' '+m[4].replace(/\s+/g, '').replace(/-/g, ':')):(''));

            }
        }
    }

    function recoverValue(i,o)
    {
        var v=o.getAttribute('data-value');
        if(v) o.value=v;
    }

    tdz.validateForm=function(e)
    {
        // validation
        _v=true;
        var f = tdz.node(e, this);
        if(!f) f = document.querySelector('form');
        if(!f) return false;
        if(!f.id) return true;
        _f[f.id]={};

        var L=f.querySelectorAll('input,select,textarea'), i=0;
        while(i < L.length) {

            if(L[i].id.substr(0,3)!='q__' && tdz.validateField(L[i])===false) {
                _v = false;
            }
            i++;
            if(!(i in L)) break;
        }

        if(_v) {
            _v = formUrl(f);
            if(_v!==false && _v!==true) {
                if(tdz.parentNode(f,'.tdz-i')) {
                    if(f.getAttribute('action')!=_v) f.setAttribute('action', _v);
                    tdz.loadInterface.call(f, e);
                } else {
                    window.location.href = _v;
                }
                tdz.stopEvent(e);
                return false;
            }
        }
        if(!_v) tdz.stopEvent(e);
        return _v;
    }

    tdz.validateField=function(o){
        var v=true;
        tdz.fire(o, 'validate');
        if(!('name' in o) || !('form' in o) || tdz.parentNode(o, '.field.tdz-novalidate'))return;
        var id=o.name, val=tdz.val(o);
        //if((o.form.id in _f) && (id in _f[o.form.id])) return _f[o.form.id][id];
        _f[o.form.id][id]=val;
        if(!val && o.getAttribute('required')){
            val=false;
            tdz.setError(o);
        } else if(o.className.search(/\berror\b/)>-1) {
            tdz.removeError(o);
        }
        return val;
    }

    tdz.val=function(o, val, fire)
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
            if(val && fire) tdz.fire(o, 'change');
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
                            if(fire) tdz.fire(L[i], 'change');
                        }
                    } else {
                        if(L[i].checked) {
                            L[i].removeAttribute('checked');
                            L[i].checked = false;
                            if(fire) tdz.fire(L[i], 'change');
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
            tdz.fire(o, 'validate');
            v=o.value;
        } else if('value' in o) {
            if(val!==false) {
                o.value=val;
                o.setAttribute('value', val);
                if(fire) tdz.fire(o, 'change');
            }
            v = o.value;
        } else {
            if(val!==false) {
                o.setAttribute('value', val);
                if(fire) tdz.fire(o, 'change');
            }
            v=o.getAttribute('value');
        }
        delete(t);
        if(v && typeof(v) == 'object' && v.length<2) v=v.join('');
        return v;
    }

    tdz.formData=function(f)
    {
        var d;
        if(('id' in f) && (f.id in _f)) {
            d=_f[f.id];
        } else {
            var v, i;
            d={};
            for(i=0;i<f.elements.length;i++) {
                if('name' in f.elements[i] && f.elements[i].name) {
                    v = tdz.val(f.elements[i]);
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

    function formUrl(f)
    {
        f=$(f);
        if(f.hasClass('tdz-auto')) {
            if(f.attr('method').toLowerCase()=='post') {
                return f.attr('action');
            }
            var d=$(tdz.selectors.dateinput+','+tdz.selectors.datetimeinput,f), b=f.hasClass('tdz-no-empty');
            d.each(serializeDate);
            var s=(b)?($(":input[value!='']:not([id^='q__'])",f).serialize()):($(":input:not([id^='q__'])",f).serialize());
            if(b) {
                s=s.replace(/(\?|\&)[A-Za-z0-9\-\_\[\]]+=(&|$)/, '$1');
            }
            if(f.hasClass('tdz-simple-serialize')) {
                var a=s.split('&'),i=0,b=[],m={},v;
                while(i<a.length) {
                    if(a[i].indexOf('%5B%5D=')>-1) {
                        v=a[i].split('%5B%5D=',2);
                        if(v[0] in m) {
                            b[m[v[0]]] += ','+v[1];
                        } else {
                            m[v[0]]=b.length;
                            b.push(v.join('='));
                        }
                        delete(v);
                    } else {
                        b.push(a[i]);
                    }
                    i++;
                }
                s=b.join('&');
                delete(a);
                delete(i);
                delete(b);
                delete(m);
            }
            d.each(recoverValue);
            if(f.attr('method').toLowerCase()=='get') {
                if(f.hasClass('inurl')) {
                    return f.attr('action').replace(/\/?\?.*/, '')+((s)?(s.replace(/(^|\&)[^\=]+\=/g, '/')):(''));
                } else {
                    return f.attr('action').replace(/\?.*/, '')+((s)?('?'+s):(''));
                }
                //return false;
            }
        }
        return true;
    }

    var _ajax={};
    tdz.ajax=function(url, data, success, error, dataType, context, headers)
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
                if(headers[n]) {
                    _ajax[url].r.setRequestHeader(n, headers[n]);
                }
            }
        }
        if(m=='post') {
            var addct=(!headers || !('Content-Type' in headers));
            if(typeof(data)=='object' && ('nodeName' in data) && data.nodeName.toLowerCase()=='form') {
                var enctype=data.getAttribute('enctype');
                if(!('FormData' in window) || (enctype && enctype.substr(0,33)=='application/x-www-form-urlencoded')) {
                    data = tdz.formData(data);
                } else {
                    data = new FormData(data);
                    addct=false;
                }
            } else if(typeof(data)=='string' || 'length' in data) _ajax[url].r.setRequestHeader('Content-Length', data.length);

            if(addct) {
                _ajax[url].r.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
                _ajax[url].r.setRequestHeader('Connection', 'close');
            }
            console.log('added data: ', data, addct, headers);
            _ajax[url].r.send(data);
        } else {
            _ajax[url].r.send();
        }
    }

    function tdzError() 
    {
        console.log('[ERROR]'+JSON.stringify(arguments));
    }

    function ajaxProbe()
    {
        var u;
        for(u in _ajax) {
            if(_ajax[u].r && _ajax[u].r.readyState==4) {
                var a=_ajax[u],d;
                delete(_ajax[u]);
                if(!a.r.responseText) d='';
                else if(a.type=='xml' && a.r.responseXML) d=a.r.responseXML;
                else if(a.type=='json') d=JSON.parse(a.r.responseText);
                else d=a.r.responseText;
                if(a.r.status==200) {
                    a.success.apply(a.context, [ d, a.r.status, u ]);
                } else {
                    a.error.apply(a.context, [ d, a.r.status, u ]);
                }
                delete(a.r);
                delete(a);
                delete(d);
            }
        }
    }

    tdz.addEvent=function(o, tg, fn) {
        if(!o) return;
        if (o.addEventListener) {
            o.addEventListener(tg, fn, false);
        } else if (o.attachEvent) {
            o.attachEvent('on'+tg, fn);
        } else {
            o['on'+tg] = fn;
        }
    }

    tdz.modules.FormAutocomplete='form.enable-autocomplete';
    tdz.initFormAutocomplete=function(o)
    {
        if(!('length' in o)) o=[o];
        var i=o.length;
        while(i-- > 0) {
            o[i].className = o[i].className.replace(/\s*\benable-autocomplete\b/, '');
            o[i].setAttribute('autocomplete','on');
        }

    }
    tdz.initFormPlugins[':input[data-filters]:not([id^="q__"])']='initFormFilters';
    tdz.initFormFilters=function(o)
    {
        if(!('length' in o)) o=[o];
        var i=o.length;
        while(i-- > 0) {
            if(o[i].className.search(/\btdz-filters\b/)<0) {
                tdz.addEvent(o[i], 'change', tdzFilters);
                o[i].className += ' tdz-filters';
                if(o[i].getAttribute('checked')) tdzFilters.apply(o[i]);
            }
        }
    }

    tdz.initFormPlugins['button.cleanup']='initButtonCleanup';
    tdz.initButtonCleanup=function(o)
    {
        if(!('length' in o)) o=[o];
        var i=o.length;
        while(i-- > 0) {
            tdz.bind(o[i], 'click', tdz.resetForm);
        }
    }

    tdz.resetForm=function()
    {
        var f=tdz.parentNode(this, 'form'),e=f.querySelectorAll('input,textarea,select'),i=e.length;
        while(i-- > 0) {
            var t=e[i].type;
            if(t.substr(0,6)=='select') {
                var j=e[i].options.length;
                while(j-- > 0) {
                    if(e[i].options[j].selected) e[i].options[j].selected=false;
                }
            } else if(t=='checkbox' || t=='radio') {
                if(e[i].checked) e[i].checked=false;
            } else if(e[i].value && t!='submit' && t!='button') {
                e[i].value='';
            }
        }
    }

    function tdzFilters(e)
    {
        var fa=this.getAttribute('data-filters');
        if(fa.search(/^#[^\,\-\s]+$/)>-1) { // #id, just trigger it on/off according to value
            if($(this).val()>0) $(fa).fadeIn();
            else $(fa).fadeOut();
            return;
        }
        var d0=$(this.form).serializeObject(),d={},fs=fa.split(','),fn;
        var ff=this.form.querySelectorAll('*[data-filters]'), i=ff.length, fd={};
        while(i-- > 0) {
            fn=ff[i].getAttribute('name') || ff[i].getAttribute('id');
            if(fn.substr(0,3)!='q__' && fn in d0) {
                fd[fn]=d0[fn];
            }
        }
        if(this.name in d0) {
            d[this.name]=d0[this.name]; // make this work with []
        }

        while(fs.length>0) {
            fn=fs.shift();
            if(fn in d) continue;
            if(fn in d0) d[fn]=d0[fn];
            else d[fn]=null;
            if(this.form.elements[fn] && (fa=this.form.elements[fn].getAttribute('data-filters'))) {
                fs=fs.concat(fa.split(','));
            }
        }

        var p={d:fd,f:[]};
        for(fn in d) {
            p.f.push(fn);
        }
        var h={'Tdz-Action': 'refreshFields','Tdz-Params': JSON.stringify(p)};
        if(this.form.getAttribute('id')) {
            h['Tdz-Target']=this.form.getAttribute('id');
        }
        tdz.ajax(this.form.action, true, reloadFields, null, 'json', this.form, h);
    }

    $.fn.serializeObject = function()
    {
        var o = {};
        var a = this.serializeArray();
        $.each(a, function() {
            if (o[this.name] !== undefined) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(this.value || '');
            } else {
                o[this.name] = this.value || '';
            }
        });
        return o;
    };

    tdz.refreshForm=function(f)
    {
        var h={'Tdz-Action': 'refreshForm'};
        tdz.ajax(f.action, JSON.stringify(s), reloadForm, null, 'html', f, h);
    }

    function reloadForm()
    {
        console.log('reloadForm', arguments);
    }

    function reloadFields(fs)
    {
        for(var fn in fs) {
            this.elements[fn].parentNode.innerHTML=fs[fn];
        }

        tdz.initForm($(this));
    }


    function datalist(o)
    {
        if(o && !('nodeName' in o) && o.length>0) {
            var i=o.length;
            while(i-- > 0) {
                datalist.call(o.get(i));
            }
            return;
        }
        var t=tdz.node(this, o);
        if(!t || !('nodeName' in t) || t.getAttribute('data-datalist-t')) return false;
        t.setAttribute('data-datalist-t', 1);
        t.setAttribute('data-datalist-q', tdz.val(t));
        if(t.nodeName.toLowerCase()=='input') {
            //tdz.bind(t, 'keypress', tdz.delayedChange);
            tdz.bind(t, 'keydown', datalistKeypress);
            tdz.bind(t, 'focus', datalistQuery);
            tdz.bind(t, 'blur', datalistBlurTimeout);
        }
        tdz.bind(t, 'change', datalistQueryTimeout);
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
            if(!focus && t && tdz.val(t)!='') {
                tdz.val(t, '');
                tdz.fire(t, 'change');
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
                if(n) u=u.replace(m[i], encodeURIComponent(tdz.val(n)));
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
        tdz.ajax(u, null, datalistRender, tdz.error, 'json', o, h);
    }

    function datalistVal(o, v, fire)
    {
        var s=o.getAttribute('data-datalist-multiple'), a=tdz.val(o);
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
        if(arguments.length>1) tdz.val(o, v, fire);
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
        if(document.activeElement && !tdz.parentNode(document.activeElement, this.parentNode)) {
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
        var o=this, c=o.parentNode.querySelector('ul.tdz-datalist'), n, p;
        if(!c) c=tdz.element.call(o.parentNode,{e:'span',p:{className:'tdz-datalist-container'},c:[{e:'ul',p:{className:'tdz-datalist'},a:{'data-target':o.getAttribute('id')}}]}).children[0];
        else c.innerHTML=''; // remove child nodes
        _D[o.getAttribute('id')]=d;
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
                tdz.element.call(c,p);
            }
        }
        if(!p) {
            p={e:'li',p:{className:'tdz-msg tdz-alert'},c:tdz.l.Nothing};
            tdz.element.call(c,p);
        }
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


    tdz.initFormPlugins['*[data-datalist-api],*[data-datalist]']=datalist;
    
    tdz._autocompleteMin=3;

    function renderAutocomplete( ul, item ) {
        var t='';
        if(typeof(item.label)=='object') {
            t='<strong>'+item.label.group+'</strong> '+item.label.label;
        } else {
            t=item.label;
        }
        return $( "<li></li>" )
            .data( "item.autocomplete", item )
            .append( "<a>" + t + "</a>" )
            .appendTo( ul );
    };

    tdz.selectAutocomplete=function(o){
        o = tdz.isNode(o, this);
        if(o.attr('data-autocomplete')) {
            return false;
        }
        o.attr('data-autocomplete', o.attr('id').replace(/^q__/, '')).attr('autocomplete', 'off');
        o.autocomplete({'source':tdz.autocomplete, 'minLength':0, 'select':tdz.autocomplete, 'appendTo': o.parent()}).data( "autocomplete" )._renderItem = renderAutocomplete;
        if(o.hasClass('multiple')) tdz.autocompleteSelected(o);
    }
    
    tdz.autocompleteSelected=function(o)
    {
        o=$(o);
        var s=o.prevAll('span.selected-option'), val=[], uval={};
        s.each(function(i,sp){
            sp=$(sp), value=sp.attr('data-value');
            if(!value || value in uval) {
                sp.fadeOut('fast', function(){$(this).remove();});
                return;
            }
            uval[value]=true;
            val.push(value);
            if(sp.find('a').length==0) {
                sp.append(' <a onclick="tdz.autocompleteSelected($(this).parent().attr(\'data-value\', \'\').nextAll(\'input\').eq(0));return false;" href="#'+tdz.l.del+'"><span class="'+tdz.iconClass+' del"></span><span class="text">'+tdz.l.del+'</span></a>');
                if(sp.hasClass('new')) sp.fadeIn('fast');
            }
        });
        $('input#'+o.attr('data-autocomplete')).val(val.join(','));
    }
    
    /**
     * Autocomplete search and replace callback.
     * Autocomplete is automatically enabled whenever a select has more than Tecnodesign_Field::$maxOptions
     */
    tdz.autocomplete = function(o, fn) {
        if(arguments.length==2 && typeof(o)=='object' && typeof(fn)=='function') {
            if(o.term!='' && o.term.length<tdz._autocompleteMin) return;
            var op=o;
            o=this.element;
            if(o.hasClass('error')) tdz.removeError(o);
            o.data('autocompleteCallback', fn);

            var t = o.get(0), e=t.form.querySelector('input#'+o.attr('data-autocomplete'));
            if(e.value!='') {
                e.value='';
                tdz.fire(e, 'change');
            }
            if(op.term=='') {
                return ;
            }
            var u=o.attr('data-autocomplete-api'),h;
            if(u) {
                u += ((u.indexOf('?')>-1)?('&'):('?'))+'q='+encodeURIComponent(op.term);
            } else {
                u = formUrl(o.parents('form'));
                h = {'Tdz-Action':'choices', 'Tdz-Target': o.attr('data-autocomplete'), 'Tdz-Term': op.term};
            }

            if(u===false || u===true) u=window.location.href;
            if(u.search(/\#/)>-1) u=u.replace(/\#.+$/, '');
            u=(u.search(/\?/)>-1)?(u.replace(/\&+(\bajax\b(=[^\&]*)?|$)/, '')+'&'):(u+'?');
            u+='ajax='+encodeURIComponent(o.attr('data-autocomplete')+'/'+op.term);

            tdz.ajax(u, null, tdz.autocomplete, tdz.error, 'json', o, h);
            /*
            $.ajax({
                'type':'GET',
                'url':u,
                'context': o,
                'dataType':'json',
                'headers':h,
                'success': tdz.autocomplete,
                'error':function(){return false;}
            });
            */
        } else if(arguments.length==2 && typeof(o)=='object'){
            o=(!tdz.isNode(o))?($(this)):($(o));
            var t='';
            if(typeof(fn.item.label)=='object') {
                if('label' in fn.item.label) {
                    t='<strong>'+fn.item.label.group+'</strong> '+fn.item.label.label;
                } else {
                    var n, f=o.get(0).form;
                    for(n in fn.item.label) {
                        if(n in f.elements) {
                            tdz.val(f.elements[n], fn.item.label[n]);
                        }
                    }
                    return;
                }
            } else {
                t=fn.item.label;
            }
            var val;
            if(o.hasClass('multiple')) {
                o.before('<span class="ui-button selected-option new" data-value="'+fn.item.value+'">'+t+'</span>');
                val=$('input#'+o.attr('data-autocomplete')).val();
                if(val) val+=',';
                val+=fn.item.value;
                t='';
            } else {
                val = fn.item.value;
            }
            var e=o.get(0).form.querySelector('input#'+o.attr('data-autocomplete'));
            e.value=val;
            tdz.fire(e, 'change');
            o.val(t);
            fn.item.value=t;
        } else {
            var ro=[], op=o;
            o=$(this);
            var m=o.hasClass('multiple');
            for(var i in op) {
                if(m && o.prevAll('.selected-option[data-value="'+i+'"]').length>0) continue;
                ro.push({'label':op[i],'value':i});
            }
            if(ro.length==0) {
                $('input#'+o.attr('data-autocomplete')).val('').trigger('change');
                tdz.setError(this);
            } else {
                if(o.hasClass('error')) tdz.removeError(o);
                var fn=o.data('autocompleteCallback');
                if(fn) fn(ro);
            }
        }
        if(o.hasClass('multiple')) tdz.autocompleteSelected(o);
    }


    tdz.modules.TouchHover='.touchhover';
    tdz.initTouchHover=function(o)
    {
        o=tdz.isNode(o, this);
        o.unbind('touchstart', tdz.touchHover).bind('touchstart', tdz.touchHover);
    }

    tdz.touchHover=function(e)
    {
        e.preventDefault();
    }

    tdz.modules.NextField='*[data-next]:not([id^="q__"]):not(.tdz-n)';
    tdz.initNextField=function(o)
    {
        o.addClass('tdz-n').bind('change',nextField).each(function(i,o){nextField.apply(o);});
        o.parents('.field').addClass('tdz-n');
    }

    function nextField()
    {
        var v=(this.className.search(/\btdz-next\b/)>-1)?(false):(tdz.val(this)),n=this.getAttribute('data-next').split(/\,/g),i=n.length,c,p,e,r,l=1;
        if(v==='0') v=0;
        while(i-- > 0) {
            p=n[i].indexOf(':');
            if(p>-1) {
                if(n[i].substr(0,p)==v) c=true;
                else c=false;
                n[i] = n[i].substr(p+1);
            } else if(v) c=true;
            else c=false;
            if(n[i].substr(0,1)=='!') {
                c=!c;
                n[i] = n[i].substr(1);
            }
            e=document.getElementById(n[i]);
            var L=(e)?([e]):(document.querySelectorAll('*[name="'+n[i]+'[]"]')), j=L.length;
            while(j-- > 0) {
                l=2;
                e=L[j];
                while(e && l) {
                    r=e.className.replace(/\s*\btdz-next(on)?\b/g, '');
                    if(c) r+=' tdz-nexton';
                    else  r+=' tdz-next';
                    r=r.trim();
                    if(r!=e.className) e.className = r;
                    if(e.getAttribute('data-next')) nextField.apply(e);
                    e=tdz.parentNode(e, '.field');
                    l--;
                }
            }
        }
    }
    
    tdz.modules.LoadInline='a[data-target]';
    tdz.initLoadInline=function(o)
    {
        o=tdz.isNode(o, this);
        if(o.attr('data-target')) {
            o.data('target', o.attr('data-target'));
            o.attr('data-target', null);
            o.bind('click', tdz.initLoadInline);
        } else if(o.data('target')) {
            
            $.ajax({
                'type':'GET',
                'url':o.attr('href'),
                'dataType':'html',
                'headers':{'Tdz-Target':o.data('target')},
                'success':tdz.setContent,
                'context':$('#'+o.data('target'))
            });
        }
        return false;
    }
    
    
    tdz.setContent=function(d)
    {
        var id='#'+$(this).attr('id');
        $(id).replaceWith(d);
        tdz.init(id);
    };
    
    tdz.offset=function(o) {
        if('jquery' in o) {
            o=o.get(0);
        }
        var l = t = 0;
        if (o.offsetParent) {
            do {
                l += o.offsetLeft || 0;
                t += o.offsetTop || 0;
            } while (o = o.offsetParent);
        }
        return {'left': l, 'top':t};
    }
    
    tdz.camelize=function camelize(s)
    {
        return s.replace(/\-+(.)?/g, function() {return (arguments[1] || '').toUpperCase()});
    }

    tdz.filter=function(fo)
    {
        fo=$(fo);
        var f=fo.parents('.field').eq(0), ft=f.data('filtered'), t=new Date().getTime();
        if(ft && ft>=t) return;
        f.data('filtered', t+500);
        // figure-out what to filter
        var o=fo.attr('data-filters');
        if(!o) return;
        o=o.split(/\s*[\,\;]+\s*/g);
        for(var i=o.length -1;i>=0;i--) {
            var op=o[i].split(/\s+/g), m='filterChoices';
            if(op.length>1) {
                m = tdz.camelize('filter-'+op[1]);
            }
            var t=fo.parents('form').find('#'+op[0]);
            if(t.length==0) {
                t=fo.parents('form').find('#f__'+op[0]+'.field .input');
            }
            if(t.length>0 && m in tdz) {
                tdz[m](fo, t);
            }
        }
    }

    tdz.filterShow=function(fo, t)
    {
        var pt=$(t).parents('.field'), val=fo.serialize();
        if(pt.length>0) t=pt;
        if(val) t.show('slow');
        else t.hide('slow');
    }

    tdz.filterHide=function(fo, t)
    {
        var pt=$(t).parents('.field'), val=fo.serialize();
        if(pt.length>0) t=pt;
        if(!val) t.show('slow');
        else t.hide('slow');
    }
    
    tdz.obj=function(s, ctx)
    {
        var o=(ctx)?(ctx):(window);
        var p = s.split(/\./g), i=0;
        for(i=0;i<p.length;i++) {
            if(p[i] in o) {
                o = o[p[i]];
            } else {
                return;
            }
        }
        return o;
    }
    
    tdz.filterChoices=function(fo, t)
    {
        fo=$(fo);
        if(arguments.length==1 || !t){
            t = fo.parents('form').find('select[data-filter-param]');
            if(t.length==0) return;
        }
        var id={}, label={};
        if(fo.get(0).nodeName.toLowerCase()=='select') {
            fo.find('option:selected').each(function(foi, foo) {
                id[foo.value]=true;
                label[$(foo).text()]=true;
            });
        } else if(fo.get(0).nodeName.toLowerCase()=='input') {
            fo.parents('.field').eq(0).find('input:checked').each(function(foi, foo) {
                id[foo.value]=true;
                label[$(foo).parents('label').eq(0).text()]=true;
            });
        }
        t.each(function(i,o) {
            o=$(o);
            var c=o.data('choices'), sid=(o.attr('data-filter-param'))?(o.attr('data-filter-param')):('value'),r='';
            if(!c) {
                c=o.find('>*');
                o.data('choices',c);
            }
            c.each(function(ci, co) {
                co=$(co);
                var clabel=(co.attr('label'))?(co.attr('label')):(co.text()), cid=co.attr(sid);
                if((!cid && ci==0 && co.get(0).nodeName.toLowerCase()=='option') || clabel in label || (cid && cid in id)) {
                    if(!r)r=co;
                    else r=r.add(co);
                }
            });
            o.html(r);
            if(o.get(0).nodeName.toLowerCase()=='select') {
                o.trigger('change');
            }
        });
        
    }
    
    
    
    tdz.sortable=function(fo)
    {
        fo=(tdz.isNode(fo))?($(fo)):($(this));
        if(arguments.length>1) {
            fo.find('>*').each(function(i,o){
                o=$(o);
                if(i%2 && o.hasClass('even')) o.removeClass('even').addClass('odd');
                else if(i%2 == 0 && o.hasClass('odd')) o.removeClass('odd').addClass('even');
                o.find('input.sortable-input').val(i+1);
            });
            return true;
        }
        var val=fo.val(), oval=val, c, i;
        if(fo.parents('.item').parent('.items').length>0) {
            i=fo.parents('.item').eq(0);
            c=i.parent('.items').eq(0);
            val=i.prevAll('.item').length +1;
        } else if(fo.parents('.field').parent('form').length>0) {
            i=fo.parents('.field').eq(0);
            c=i.parent('form').eq(0);
            val=i.prevAll('.field').length +1;
        } else {
            return false;
        }
        if(!fo.hasClass('sortable-input')) {
            fo.addClass('sortable-input');
        }
        if(!c.hasClass('ui-sortable')) {
            c.sortable({'containment':c.parent().parent(), 'update':tdz.sortable, 'cancel':'.no-sort, input, textarea, select'});
            c.eq(0).append('<div style="clear:both;height:0;"></div>');
        }
        if(val!=oval) {
            fo.val(val);
        }
    }

    tdz.clearForm=function(f)
    {
        if(!tdz.isNode(f)) return false;
        //if($(f)[0].tagName.toLowerCase()=='form') $(f)[0].reset();
        $('input,select,textarea', f).each(function() {
            var tag = this.tagName.toLowerCase(),en='change';
            if(tag=='select') {
                this.selectedIndex=-1;
                $('options[selected]',this).attr('selected','');
            } else if(tag=='input' && (this.type == 'checkbox' || this.type == 'radio')) {
                this.checked=false;
                en='click';
            } else {
                this.value='';
            }
            $(this).trigger(en);
        });
        return true;
    }
    
    tdz.setError=function(o, msg)
    {
        if(!tdz.node(o) || o.className.search(/\berror\b/)>-1) return false;
        o.className = (o.className)?(' error'):('error');
        o.setAttribute('error-value', tdz.val(o));
        tdz.bind(o, 'change', tdz.removeError);
        var f=tdz.parentNode(o,'.field');
        if(f) {
            f.className += ' error';
            var l=f.querySelector('.label:not(:empty),.input');
            if(l) {
                tdz.element.call(l,{e:'span',p:{className:'icon error'},c:msg});
            }
        }
    }

    tdz.removeError=function(o)
    {
        var t=tdz.node(o,this);
        if(!t || t.getAttribute('error-value')===tdz.val(t)) return;
        if(t.className.search(/\berror\b/)>-1) t.className = t.className.replace(/\s*\berror\b/, '');
        tdz.unbind(t,'change',tdz.removeError);
        var f=tdz.parentNode(t,'.field');
        if(f) {
            if(f.className.search(/\berror\b/)>-1) f.className = f.className.replace(/\s*\berror\b/, '');
            var l=f.querySelector('.label:not(:empty) .icon.error,.input .icon.error');
            if(l) {
                l.parentNode.removeChild(l);
            }
            delete(l);
        }
    }

    tdz.subform=function(o)
    {
        var b=o.parents('.field').eq(0).find('>.ui-buttons');
        if(b.length==0) {
            var d=o.parent('.input');
            d.before('<span class="ui-buttons"></span>');
            b=o.parents('.field').eq(0).find('>.ui-buttons');
        }
        // items
        var sf=$('.item', o), fmin=o.attr('data-min'), fmax=o.attr('data-max');
        
        
        // buttons: add, add(contextual), remove(contextual)
        var badd=$('>.add', b);
        if(badd.length==0){
            b.prepend('<a class="ui-button add" onclick="return tdz.subformAdd($(this).parent().next().find(\'&gt;.subform\'),0);" href="#'+tdz.l.add+'"><span class="'+tdz.iconClass+' add"></span><span class="text">'+tdz.l.add+'</span></a>');
            badd=$('>.add', b);
        }
        var bdel=$('.ui-button.del', o);
        sf.each(function(i,f){
            f=$(f);
            if($('.ui-button.del', f).length==0) {
                var btn='<span class="ui-buttons">'
                    + '<a class="ui-button del" onclick="return tdz.subformDel($(this).parents(\'.item\'));" href="#'+tdz.l.del+'"><span class="'+tdz.iconClass+' del"></span><span class="text">'+tdz.l.del+'</span></a>'
                    + '<a class="ui-button add" onclick="return tdz.subformAdd($(this).parents(\'.subform\').eq(0),$(this).parent().prevAll().length+1);" href="#'+tdz.l.add+'"><span class="'+tdz.iconClass+' add"></span><span class="text">'+tdz.l.add+'</span></a>'
                    + '</span>';
                
                f.append(btn);
                bdel.add(f.find('>.del').eq(0));
            }
        });

        // maximun
        if(fmax && sf.length>=fmax && !badd.hasClass('disabled')) badd.addClass('disabled');
        else if(fmax && sf.length<fmax && badd.hasClass('disabled')) badd.removeClass('disabled');
        
        // minimun
        if(fmin && sf.length<=fmin && !bdel.hasClass('disabled')) bdel.addClass('disabled');
        else if(fmin && sf.length>fmin && bdel.hasClass('disabled')) bdel.removeClass('disabled');
        
    }
    
    var _subformPos='§';
    tdz.subformAdd=function(o)
    {
        var tpl=o.attr('data-template'), prefix=o.attr('data-prefix'), sf=$('>.item', o),i=0, fmax=o.attr('data-max');

        if(!(fmax && sf.length>=fmax)) {
            if(sf.length>0){
                i=sf.length;
                var n=sf.last().find('.input>*[name]:first');
                if(n) {
                    n=n.attr('name');
                    var re=new RegExp(prefix.replace(/([^a-z0-9])/i, '\\\$1')+'\\\[([0-9]*)\\\].*');
                    n=n.replace(re, '$1');
                    if(n) {
                        if(n.substr(0,3)=='q__') n=n.substr(3);
                        i=parseInt(n)+1;
                    }
                    while(sf.find('*[name^="'+prefix+'['+i+']"]').length>0) {
                        i++;
                    }
                }
            }
            var re=new RegExp((prefix+'\[§\]').replace(/([^a-z0-9])/gi, '\\\$1'), 'gi');
            var ri=new RegExp((prefix+'_§_').replace(/([^a-z0-9])/gi, '\\\$1'), 'gi');
            var no=$(tpl.replace(re, prefix+'['+i+']').replace(ri, prefix+'_'+i+'_'));
            no.hide();
            if(arguments.length>1) {
                var t=o.find('>*').eq(arguments[1]);
                if(t.length>0)t.before(no);
                else o.append(no);
            } else {
                o.append(no);
            }
            no.show('fast');
            tdz.init(no);
        }
        tdz.subform(o);
        tdz.initForm(o.parents('form').eq(0));
        return false;
    }

    tdz.subformDel=function(f)
    {
        var o=f.parent('.subform'), sf=$('>.item', o),fmin=o.attr('data-min');
        
        if(!(fmin && sf.length<=fmin)){
            f.hide('fast', function(){$(this).remove();});
        }
        tdz.subform(o);
        return false;
    }    
    // Tecnodesign_Translate
    // pt_BR
    if(!('l' in tdz)) tdz.l={};
    tdz.l.add='Acrescentar';
    tdz.l.del='Excluir';
    tdz.l.Nothing='Nenhuma opção foi encontrada para esta consulta.';
    tdz.l.Error='Houve um erro ao processar esta informação. Por favor tente novamente ou entre em contato com o suporte.';



    var xml_special_to_escaped_one_map = {
    '&': '&amp;',
    '"': '&quot;',
    '<': '&lt;',
    '>': '&gt;'
    };
    
    var escaped_one_to_xml_special_map = {
    '&amp;': '&',
    '&quot;': '"',
    '&lt;': '<',
    '&gt;': '>'
    };

    tdz.xmlEscape=function (s) {
        return s.replace(/([\&"<>])/g, function(str, item) {
            return xml_special_to_escaped_one_map[item];
        });
    };
    
    tdz.xmlUnescape=function (s) {
        return s.replace(/(&quot;|&lt;|&gt;|&amp;)/g, function(str, item) {
            return escaped_one_to_xml_special_map[item];
        });
    }

    tdz.initFormPlugins['input[type="file"]']='initFormUpload';
    tdz.initFormUpload=function(o)
    {
        if(('length' in o) && !('nodeName' in o)) {
            for(var i=0;i<o.length;i++) {
                var el=('get' in o)?(o.get(i)):(o[i]);
                if(el.form.getAttribute('enctype')!='multipart/form-data') {
                    el.form.setAttribute('enctype', 'multipart/form-data');
                    return;
                }
            }
        } else {
            if(o.form.getAttribute('enctype')!='multipart/form-data') {
                o.form.setAttribute('enctype', 'multipart/form-data');
                return;
            }
        }

    }
    
    //tdz.modules.Tinymce='textarea[data-format="html"]';
    tdz.initFormPlugins['textarea[data-format="html"]']='initEditor';
    var _E, _Et;
    tdz.initEditor=function(o)
    {
        var f=false;
        if(!_E) {
            _E = {};
            f=true;
            if(!('Squire' in window)) {
                loadComponent(tdz.config.assetsUrl+'/tecnodesign/js/squire.min.js');
            }
            /*
            // load quill?
            if(!('Quill' in window)) {
                var u=tdz.config.assetsUrl+'/tecnodesign/js/quill.min.js';
                loadComponent(u);
            }
            */
        }

        if(('length' in o) && !('nodeName' in o)) {
            for(var i=0;i<o.length;i++) {
                var el=('get' in o)?(o.get(i)):(o[i]);
                if(el.getAttribute('data-editor')) continue;
                el.setAttribute('data-editor', 'squire');
                _E[el.id] = null;
                delete(el);
            }
        } else {
            if(o.getAttribute('data-editor')) return;
            o.setAttribute('data-editor', 'squire');
            _E[o.id] = null;
        }
        if(_Et) clearTimeout(_Et);
        _Et=setTimeout(buildEditors, 300);
    }

    function buildEditors()
    {
        for(var id in _E) {
            if(_E[id]) continue;
            var e=id+'-editor', t=document.getElementById(id), p=t.getAttribute('data-editor');
            if(p=='squire') {
                if(!('Squire' in window)) {
                    if(_Et) clearTimeout(_Et);
                    _Et=setTimeout(buildEditors, 500);
                    return;
                }
                var I=tdz.element({e:'iframe',a:{src:'javascript:false',id:e,className:'tdz-html-editor'}}, t);
                var win = I.contentWindow || I, doc=I.contentDocument || I.contentWindow.document;
                win._l = loadSquire;
                var css=t.getAttribute('data-editor-css') || tdz.tinymce_css, a=t.getAttribute('data-editor-body-class');
                if(a) a=' class="'+a+'"';
                else a='';
                _E[id] = doc;
                _E[id].write('<!DOCTYPE html><html data-squireinit="true"><head><meta charset="UTF-8" /><meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" /><title></title><style type="text/css">html {height: 100%;} body {-moz-box-sizing: border-box;-webkit-box-sizing:border-box;box-sizing: border-box;height: 100%;padding:0.5em;background:#fff;color: #2b2b2b;cursor: text;} a {text-decoration: underline; }</style><link rel="stylesheet" type="text/css" href="'+css+'" /></head><body onload="_l(\''+id+'\');"'+a+'>'+t.value+'</body></html>');
                _E[id].close();
                //tdz.bind(win, 'load', loadSquire);
            /*
            } else if(p=='quill') {
                if(!('Quill' in window)) {
                    if(_Qt) clearTimeout(_Qt);
                    _Qt=setTimeout(buildEditors, 500);
                    return;
                }
                tdz.element({e:'div',a:{id:e,className:'tdz-html-editor'}}, t).innerHTML=t.value;
                _E[id]=new Quill('#'+e);
                tdz.trace('content?'+t.value);
                //_Q[id].setHTML(t.value);
                _E[id].on('text-change', syncTextarea);
                tdz.bind(t, 'change', syncEditor);
            */
            }

        }
    }

    function loadSquire(id)
    {
        //tdz.trace('loadSquire', _E[id]);
        var t=document.getElementById(id);
        t.parentNode.className+=' tdz-html-editor-active';
        _E[id]=new Squire(_E[id], {blockTag:'p'});
        _E[id]._target = id;
        _E[id].setHTML(t.value);
        _E[id].addEventListener('input', syncEditor);
    }

    var _Q, _Qt;
    tdz.initQuill=function(o)
    {
        if(!_Q) {
            _Q = {};
            if(!('Quill' in window)) {
                var u=tdz.config.assetsUrl+'/tecnodesign/js/quill.min.js';
                loadComponent(u);
            }
        }
        if(('length' in o) && !('nodeName' in o)) {
            for(var i=0;i<o.length;i++) {
                var el=('get' in o)?(o.get(i)):(o[i]);
                if(el.getAttribute('data-editor')) continue;
                el.setAttribute('data-editor', 'quill');
                _Q[el.id] = null;
                delete(el);
            }
        } else {
            if(o.getAttribute('data-editor')) return;
            o.setAttribute('data-editor', 'quill');
            _Q[o.id] = null;
        }
        if(_Qt) clearTimeout(_Qt);
        _Qt=setTimeout(buildEditors, 300);
    }

    function syncTextarea(delta, source)
    {
        tdz.trace('syncTextarea', delta, source, this.getHTML());
    }

    function syncEditor(type)
    {
        document.getElementById(this._target).value=this.getHTML();
    }

    var _tinyload;
    
    tdz.initTinymce=function(o)
    {
        if(!_tinyload && tdz.htmlPlugin.options.script_url.substr(0,1)!='/') {
            tdz.htmlPlugin.options.script_url=tdz.config.assetsUrl+'/'+tdz.htmlPlugin.options.script_url;
            tdz.htmlPlugin.options.content_css=tdz.config.assetsUrl+'/'+tdz.htmlPlugin.options.content_css;
            _tinyload=true;
        }
        if(('length' in o) && !('nodeName' in o)) {
            for(var i=0;i<o.length;i++) {
                tdz.initTinymce(o[i]);
            }
            return;
        }
        if(o.getAttribute('data-editor')) return;
        o.setAttribute('data-editor', 'tinymce');
        tdz.tinymceNew(o);
    }

    tdz.htmlPlugin={
        'fn':'tinymce',
        'options': {
            'mode':'textareas',
            'suffix': '',
            'query': '',
            'script_url' : 'tecnodesign/tiny_mce/tiny_mce.js',
            'theme' : 'advanced',
            'plugins' : 'pdw,advimage,table,inlinepopups,preview,media,searchreplace,contextmenu,paste,fullscreen,noneditable,nonbreaking,xhtmlxtras',
            'language': 'pt',
            'entity_encoding': 'named',
            'entities': '160,#160,150,#150,151,#151',
            'theme_advanced_buttons1' : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,styleselect,formatselect,fontselect,fontsizeselect,pdw_toggle",
            'theme_advanced_buttons2' : "bullist,numlist,|,outdent,indent,blockquote,|,link,unlink,image,media,|,cite,abbr,acronym,|,tablecontrols",
            'theme_advanced_buttons3' : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,undo,redo,|,forecolor,backcolor,|,sub,sup,|,hr,nonbreaking,removeformat,visualaid,|,preview,fullscreen,cleanup,code",
            'theme_advanced_toolbar_location' : "top",
            'theme_advanced_toolbar_align' : "left",
            'theme_advanced_statusbar_location' : "bottom",
            'theme_advanced_resizing' : true,
            'theme_advanced_source_editor_height': 460,
            'content_css' : 'css/site.css',
            'convert_urls' : false,
            'pdw_toggle_on' : 1,
            'pdw_toggle_toolbars' : '2,3',
            'dialog_type' : 'modal',
            'file_browser_callback': false
        }
    };
    var _tinymceLoaded=false;
    tdz.tinymce_ta=false;

    tdz.fullScreen=function(element) {
        if(element.requestFullscreen) {
            element.requestFullscreen();
        } else if(element.mozRequestFullScreen) {
            element.mozRequestFullScreen();
        } else if(element.webkitRequestFullscreen) {
            element.webkitRequestFullscreen();
        } else if(element.msRequestFullscreen) {
            element.msRequestFullscreen();
        }
    }
    tdz.exitFullScreen=function() {
        if(document.exitFullscreen) {
            document.exitFullscreen();
        } else if(document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if(document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        }
    }

    tdz.tinymceNew=function(t)
    {
        var f=(arguments.length>0)?(t):($('form'));
        if(!('tinyMCE' in window)) {
            tdz.htmlPlugin.options.language=(tdz.language.search(/^[a-z][a-z]_[A-Z][A-Z]$/)>-1)?(tdz.language.substring(0,2)):('en');
            //tdz.htmlPlugin.options.content_css=tdz.tinymce_css;
            if('ui' in tdz && 'user' in tdz && tdz.user) {
                tdz.htmlPlugin.options.file_browser_callback='tdz.eFileBrowser';
            }
            var o=tdz.htmlPlugin.options;
            o.base=o.script_url.replace(/\/[^\/]+$/, '');
            window.tinyMCEPreInit = o;
            if(!tdz.tinymce_ta) {
                tdz.tinymce_ta=f;
                var ar={
                   'url': o.script_url,
                   'data': 'js=true&core=true&suffix=&themes='+o.themes+'&plugins='+o.plugins+'&language='+o.language,
                   'dataType':'script',
                   'success':function(){/*tinyMCE.init(tdz.htmlPlugin.options);*/tdz.tinymceNew(tdz.tinymce_ta);},
                   'async':false
                };
                $.ajax(ar);
            }
            return false;
        } else if(!_tinymceLoaded) {
            _tinymceLoaded=true;
            tdz.htmlPlugin.options.mode='none';
            tinyMCE.init(tdz.htmlPlugin.options);
        }
        var ta=false, s='textarea.html,textarea[data-format="html"]';
        if(f.filter(s).length>0) {
            ta=f;
            f=f.parents('form');
            f.data('tdz-html', true);
        } else {
            f.each(function(i,o){
                o=$(o);
                if(!o.data('tdz-html')) {
                    if(!ta) ta=o.find(s);
                    else ta=ta.add(o.find(s));
                    o.data('tdz-html', true);
                }
            });
        }
        if(!ta || ta.length==0) return;
        ta.each(function(i,o){
            o=$(o);
            tinyMCE.execCommand('mceAddControl', false, o.attr('id'));
            o.bind('validate', function(){this.value=tinyMCE.get(this.id).getContent();});
            var f=o.parents('form');if(!f.data('tdz-html')) {
                f.bind('form-pre-serialize', function(e) {tinyMCE.triggerSave();}).data('tdz-html', true);
            }
        });
    };
    /*
    tdz.tinymce=function(t)
    {
        var f=(arguments.length>0)?(t):($('form'));
        var ta=false, s='textarea.html,textarea[data-format="html"]';
        f.each(function(i,o){
            o=$(o);
            if(!o.data('tinymce')) {
                if(!ta) ta=o.find(s);
                else ta=ta.add(o.find(s));
                o.data('tinymce', true);
            }
        });
        if(!ta || ta.length==0) return;
        if(!_tinymceLoaded) {
            tdz.htmlPlugin.options.language=(tdz.language.search(/^[a-z][a-z]_[A-Z][A-Z]$/)>-1)?(tdz.language.substring(0,2)):('en');
            tdz.htmlPlugin.options.content_css=tdz.tinymce_css;
            if('ui' in tdz && 'user' in tdz && tdz.user) {
                tdz.htmlPlugin.options.file_browser_callback='tdz.eFileBrowser';
            }
        }
        ta.each(function(i,o){
            if(!_tinymceLoaded)
                $(o).tinymce(tdz.htmlPlugin.options);
            else
                $(o).tinymce();
        });
    };
    */

    tdz.eFileBrowser=function(field_name, url, type, win) {
    
        // alert("Field_Name: " + field_name + "nURL: " + url + "nType: " + type + "nWin: " + win); // debug/testing
    
        /* If you work with sessions in PHP and your client doesn't accept cookies you might need to carry
           the session name and session ID in the request string (can look like this: "?PHPSESSID=88p0n70s9dsknra96qhuk6etm5").
           These lines of code extract the necessary parameters and add them back to the filebrowser URL again. */
    
        var cmsURL = tdz.ui+'/e/files?editor=tinymce&t='+type+'&d='+url;    // script URL - use an absolute path!
        tinyMCE.activeEditor.windowManager.open({
            file : cmsURL,
            title : tdz.l.file_manager,
            width : 450,  // Your dimensions may differ - toy around with them!
            height : 360,
            resizable : "yes",
            inline : "yes",  // This parameter only has an effect if you use the inlinepopups plugin!
            close_previous : "no"
        }, {
            window : win,
            input : field_name
        });
        return false;
    }
    
    tdz.isDateInputSupported = function(){
        var elem = document.createElement('input');
        elem.setAttribute('type','date');
        elem.value = 'foo';
        return (elem.type == 'date' && elem.value != 'foo');
    }

    tdz.getLanguage=function()
    {
        /*!
         * Brazilian initialisation for the jQuery UI date picker plugin.
         * Written by Leonildo Costa Silva (leocsilva@gmail.com).
         */
        if(tdz.language.substr(0,2)=='pt') {
            tdz.dateFormat = 'dd/mm/yy';
            $.datepicker.regional['pt-BR'] = {
                    closeText: 'Fechar',
                    prevText: '&#x2c2;',
                    nextText: '&#x2c3;',
                    currentText: 'Hoje',
                    monthNames: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho', 'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'],
                    monthNamesShort: ['Jan','Fev','Mar','Abr','Mai','Jun', 'Jul','Ago','Set','Out','Nov','Dez'],
                    dayNames: ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'],
                    dayNamesShort: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
                    dayNamesMin: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
                    weekHeader: 'Sm',
                    dateFormat: 'dd/mm/yy',
                    firstDay: 0,
                    isRTL: false,
                    showMonthAfterYear: false,
                    yearSuffix: '',
                    gotoCurrent:true};
                $.datepicker.setDefaults($.datepicker.regional['pt-BR']);
            
                $.timepicker.regional['pt-BR'] = {
                        timeOnlyTitle: 'Escolha o horário',
                        timeText: 'Horário',
                        hourText: 'Hora',
                        minuteText: 'Minutos',
                        secondText: 'Segundos',
                        millisecText: 'Milisegundos',
                        timezoneText: 'Fuso horário',
                        currentText: 'Agora',
                        closeText: 'Fechar',
                        timeFormat: 'HH:mm',
                        amNames: ['AM', 'A'],
                        pmNames: ['PM', 'P'],
                        isRTL: false};
                $.timepicker.setDefaults($.timepicker.regional['pt-BR']);
        }
    }



    tdz.initUi=function() {
        $('#ui-nav ul li span.ui-nav-header').click(function() {
            var open = false;
            
            //Verifica se já está aberto para não abrí-lo novamente
            if ($(this).hasClass('over')) {
                open = true;
            }
            
            //Fecha o menu aberto           
            $('#ui-nav ul li span.over').siblings('ul').slideUp('fast');           
            $('#ui-nav ul li span.over').removeClass('over');
            //Abre o menu atual, caso já não estava aberto
            if (!open) {
                $(this).addClass('over').siblings('ul').slideDown();
            }
        });
        
        $('#toggle-menu').click(function(){
            smn = $(this);
            $(this).siblings('ul').toggle('fast',function(){                
                if ($(this).is(':visible')) {
                    $(smn).removeClass('closed').text('«');
                    $('#ui-nav').removeClass('closed');
                    $('#ui-app').removeClass('expanded');
                } else {                    
                    $(smn).addClass('closed').text('»');
                    $('#ui-nav').addClass('closed');
                    $('#ui-app').addClass('expanded');
                }
            });
        });
    }
    tdz.modules.Ui='#ui-nav';

var matchesSelector = function(node, selector) {
    if(!('parentNode' in node) || !node.parentNode) return false;
    return Array.prototype.indexOf.call(node.parentNode.querySelectorAll(selector)) != -1
};

})(tdz);

/*! polyfills */
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

$(tdz.init);

