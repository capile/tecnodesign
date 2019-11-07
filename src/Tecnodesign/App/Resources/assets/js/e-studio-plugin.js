/*!
 * Tecnodesign e-Studio
 *
 * @package   capile/tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
(function(tdz){
    tdz.modules.Studio='.studiox';
    tdz.initStudioPlugins={
    };
    tdz.studio={};
    tdz.studio.url=window.location.pathname;
    tdz.dev=true;
    tdz.user=null;
    var _ui='/_e';
    var _editor=false;
    var _credentials=false;

    //tdz.modules.StudioContent='.studio-field-content select:not(.studio-activated)';
    tdz.initFormPlugins['.studio-field-content-type select:not(.studio-activated)']='initStudioContent';
    /*! Serialize Content */
    tdz.initStudioContent=function(o)
    {
        o=$(o);
        if(o.length>1) return o.each(function(i,oo){tdz.initStudioContent(oo);});
        if(!(o=tdz.isNode(o)) || o.hasClass('studio-activated')) return;

        var f=o.parents('form');if(!f.data('tdz-studio-content')) {
            f.bind('submit', function(e) {tdz.studioSerializeContent(this);}).data('tdz-studio-content', true);
        }

        tdz.studioContent(o);
        o.addClass('studio-activated');
        o.bind('change', tdz.studioContent);
    }
    /*! Serialize Content */
    tdz.studioContent=function(o){
        o=tdz.isNode(o,this);
        if(!o || o.length==0) return;

        if(o.hasClass('studio-activated')) tdz.studioSerializeContent(o.parents('.field').eq(0));
        var co=o.parents('.field').eq(0).nextAll('.studio-field-contents');
        co.not('.studio-field-disabled.tdz-novalidate').addClass('studio-field-disabled tdz-novalidate');
        co.filter('.studio-content-'+o.val()).removeClass('studio-field-disabled tdz-novalidate');
        tdz.studioUnserializeContent(o.parents('.field').eq(0));

    }
    /*! Serialize Content */
    tdz.studioSerializeContent=function(f)
    {
        f=$(f);
        var s=(f.hasClass('studio-field-content-type'))?(f):($('.studio-field-content-type',f));
        s.each(function(i,o){
            o=$(o);
            var t=o.find('select').val(),c=o.next('input[type="hidden"]').eq(0),
                co=o.nextAll('.studio-content-'+t), r={};
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
    tdz.studioUnserializeContent=function(f)
    {
        f=$(f);
        var s=(f.hasClass('studio-field-content-type'))?(f):($('.studio-field-content-type',f));
        s.each(function(i,o){
            o=$(o);
            var t=o.find('select').val(),c=o.next('input[type="hidden"]').eq(0),
                co=o.nextAll('.studio-content-'+t);
            var r=JSON.parse(c.val());
            var af=$('input,select,textarea', co);
            for(var n in r) {
                af.filter('*[id$="-'+n+'"]').val(r[n]);
            }
        });
        return true;
    } 




    tdz.initStudio=function(s)
    {
        //if(tdz.dev) console.log('tdz.initStudio', arguments);
        if(s.length==0)return;
        if(tdz.user===null) {
            tdz.user=false;
            // read user information and permissions -- can this user see unpublished content, create, update, or publish anything?
            $.ajax({
                'type':'GET',
                'url':_ui+'/u?'+escape(tdz.studio.url),
                'context': s,
                'dataType':'json',
                'headers':{'Tdz-Action':'auth' },
                'success': function(u){tdz.user=u;tdz.initStudio(this);}
            });
            return true;
        } else if(!tdz.user || !('actions' in tdz.user)) {
            //if(tdz.dev) console.log('  tdz.user', tdz.user);
            return false;
        }
        _editor=true;
        _credentials=tdz.user.actions;
        
        //console.log(tdz.user);
        tdz.studioToolbar();
        // user must be authenticated and have at least one privilege
        for(var p in tdz.initStudioPlugins){
            var el=$(p, s),m=tdz.initStudioPlugins[p];
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
    
    tdz.studioToolbar=function(){
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