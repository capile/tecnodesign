/*!
 * Tecnodesign e-Studio
 *
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
*/
(function(tdz){
    tdz.modules.Estudio='.estudiox';
    tdz.initEstudioPlugins={
    };
    tdz.estudio={};
    tdz.estudio.url=window.location.pathname;
    tdz.dev=true;
    tdz.user=null;
    var _ui='/_e';
    var _editor=false;
    var _credentials=false;

    //tdz.modules.EstudioContent='.estudio-field-content select:not(.estudio-activated)';
    tdz.initFormPlugins['.estudio-field-content-type select:not(.estudio-activated)']='initEstudioContent';
    /*! Serialize Content */
    tdz.initEstudioContent=function(o)
    {
        o=$(o);
        if(o.length>1) return o.each(function(i,oo){tdz.initEstudioContent(oo);});
        if(!(o=tdz.isNode(o)) || o.hasClass('estudio-activated')) return;

        var f=o.parents('form');if(!f.data('tdz-estudio-content')) {
            f.bind('submit', function(e) {tdz.estudioSerializeContent(this);}).data('tdz-estudio-content', true);
        }

        tdz.estudioContent(o);
        o.addClass('estudio-activated');
        o.bind('change', tdz.estudioContent);
    }
    /*! Serialize Content */
    tdz.estudioContent=function(o){
        o=tdz.isNode(o,this);
        if(!o || o.length==0) return;

        if(o.hasClass('estudio-activated')) tdz.estudioSerializeContent(o.parents('.field').eq(0));
        var co=o.parents('.field').eq(0).nextAll('.estudio-field-contents');
        co.not('.estudio-field-disabled.tdz-novalidate').addClass('estudio-field-disabled tdz-novalidate');
        co.filter('.estudio-content-'+o.val()).removeClass('estudio-field-disabled tdz-novalidate');
        tdz.estudioUnserializeContent(o.parents('.field').eq(0));

    }
    /*! Serialize Content */
    tdz.estudioSerializeContent=function(f)
    {
        f=$(f);
        var s=(f.hasClass('estudio-field-content-type'))?(f):($('.estudio-field-content-type',f));
        s.each(function(i,o){
            o=$(o);
            var t=o.find('select').val(),c=o.next('input[type="hidden"]').eq(0),
                co=o.nextAll('.estudio-content-'+t), r={};
            co.each(function(fi,fo){
                fo=$('input,select,textarea', fo);
                var val=fo.val();
                if(fo.attr('data-format') && fo.attr('data-format')=='html') {
                    //tinymce
                    var t=fo.tinymce();
                    if(t) val=t.getContent();
                }
                r[fo.attr('id').replace(/^.*\-([^\-]+)/, '$1')]=val;
            });
            c.val(JSON.stringify(r));
        });
        return true;
    } 
    tdz.eFormDelete=function(f,m)
    {
      if(confirm(m)) {
        f=$(f).parents('form');
        f.attr('action', f.attr('action').replace(/\/e\//, '/d/'));
        f.submit();
      }
      return false;
    }


    /*! Serialize Content */
    tdz.estudioUnserializeContent=function(f)
    {
        f=$(f);
        var s=(f.hasClass('estudio-field-content-type'))?(f):($('.estudio-field-content-type',f));
        s.each(function(i,o){
            o=$(o);
            var t=o.find('select').val(),c=o.next('input[type="hidden"]').eq(0),
                co=o.nextAll('.estudio-content-'+t);
            var r=JSON.parse(c.val());
            var af=$('input,select,textarea', co);
            for(var n in r) {
                af.filter('*[id$="-'+n+'"]').val(r[n]);
            }
        });
        return true;
    } 




    tdz.initEstudio=function(s)
    {
        //if(tdz.dev) console.log('tdz.initEstudio', arguments);
        if(s.length==0)return;
        if(tdz.user===null) {
            tdz.user=false;
            // read user information and permissions -- can this user see unpublished content, create, update, or publish anything?
            $.ajax({
                'type':'GET',
                'url':_ui+'/u?'+escape(tdz.estudio.url),
                'context': s,
                'dataType':'json',
                'headers':{'Tdz-Action':'auth' },
                'success': function(u){tdz.user=u;tdz.initEstudio(this);}
            });
            return true;
        } else if(!tdz.user || !('actions' in tdz.user)) {
            //if(tdz.dev) console.log('  tdz.user', tdz.user);
            return false;
        }
        _editor=true;
        _credentials=tdz.user.actions;
        
        //console.log(tdz.user);
        tdz.estudioToolbar();
        // user must be authenticated and have at least one privilege
        for(var p in tdz.initEstudioPlugins){
            var el=$(p, s),m=tdz.initEstudioPlugins[p];
            if(el.length>0) {
                if(typeof(m)=='string'){
                    tdz[m](el);
                } else {
                    m(el);
                }
            }
        }
    };
    
    tdz.t=function(t)
    {
        if(t in tdz.l) return tdz.l[t];
        else return t;
    }
    
    tdz.estudioToolbar=function(){
        if($('#tdzm').length>0 || !_editor) return false;
        var m='<div id="tdzm" class="tdze"><ul class="tdzm">', g={}, b={}, pid='';
        for(var c in _credentials) {
            var a=c.replace(/[A-Z].*$/, ''), lid=pid;
            if(!(a in g)) m+='<li><a href="'+_ui+'/'+_credentials[c]+lid+'" class="btn '+a+'"><span class="tdz-icon"></span><span class="tdz-label">'+tdz.t(tdz.ucfirst(a))+'</span></a>[['+a+']]</li>';
            g[a]=true;
            if(!(a in b)) b[a]='';
            
            b[a]+='<li><a href="'+_ui+'/'+_credentials[c]+pid+'" class="btn '+c+'"><span class="tdz-icon"></span><span class="tdz-label">'+tdz.t(tdz.ucfirst(c.replace(/([A-Z])/g, ' $1')))+'</span></a></li>';
        }
        m+='</ul></div>';
        for(var a in g) {
            var s=(a in b)?('<ul>'+b[a]+'</ul>'):('');
            //console.log('[['+a+']]', s);
            m=m.replace('[['+a+']]', s);
        }
        $('body').append(m);

//<div class="new edit search files user publish unpublish" id="tdzm"><span class="tdzcms tdzm"><a title="[Novo]" href="/e-studio/e/new/1" class="btn new"></a><a title="[Atualizar]" href="/e-studio/e/edit/1" class="btn edit"></a><a title="[Procurar]" href="/e-studio/e" class="btn search"></a><a title="[Gerenciador de arquivo]" href="/e-studio/e/files" class="btn files"></a><a title="[Publicar]" href="/e-studio/e/publish/1" class="btn publish"></a><a title="[Ocultar]" href="/e-studio/e/unpublish/1" class="btn unpublish"></a></span></div>

        

        //o.unbind('click').bind('click',tdz.mtoggle);
    }
    
})(tdz);

(function(tdz){
    var l={
    'New':'Novo',
    'New Entry':'Nova página',
    'New Content':'Novo conteúdo',
    'Edit':'Atualizar',
    'Edit Entry':'Atualizar página',
    'Edit Content':'Atualizar conteúdo',
    'Search':'Buscar',
    'Search Entry':'Buscar página',
    'Search Content':'Buscar conteúdo',
    'Preview':'Visualizar',
    'Preview Entry Unpublished':'Visualizar página não-publicada',
    'Preview Content Unpublished':'Visualizar conteúdo não-publicado',
    'Publish':'Publicar',
    'Publish Entry':'Publicar página',
    'Publish Content':'Publicar conteúdo',
    'Delete':'Apagar',
    'Delete Entry':'Apagar página',
    'Delete Content':'Apagar conteúdo'};
    for(var o in l) {
        tdz.l[o]=l[o];
    }
})(tdz);