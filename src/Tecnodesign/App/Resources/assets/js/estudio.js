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
    tdz.modules.Studio='div.tdzs.container';
    tdz.initStudioPlugins={
    };
    tdz.studio={};
    tdz.studio.url=window.location.pathname;
    tdz.dev=false;
    tdz.user=null;
    tdz.updateUserInfo=false;
    var _ui='/_e';
    var _editor=false;
    var _credentials=false;
    tdz.initStudio=function(s)
    {
        if(tdz.dev) console.log('tdz.initStudio', arguments);
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
            if(tdz.dev) console.log('  tdz.user', tdz.user);
            if(tdz.updateUserInfo!==false)tdz.updateUserInfo(false); 
            return false;
        }
        if(tdz.updateUserInfo!==false)tdz.updateUserInfo(tdz.user.user); 
        _editor=true;
        _credentials=tdz.user.actions;
        
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
        console.log(_credentials);
        var m='<div id="tdzm" class="tdze"><span class="tdzm tdzcms">', g={}, b={}, pid='';
        for(var c in _credentials) {
            var a=c.replace(/[A-Z].*$/, ''), lid=pid;
            if(!(a in g)) m+='<a href="'+_ui+'/'+_credentials[c]+lid+'" class="btn '+a+'"><span class="tdz-icon"></span><span class="tdz-label">'+tdz.t(tdz.ucfirst(a))+'</span></a>[['+a+']]';
            g[a]=true;
            if(!(a in b)) b[a]='';
            
            b[a]+='<a href="'+_ui+'/'+_credentials[c]+pid+'" class="btn '+c+'"><span class="tdz-icon"></span><span class="tdz-label">'+tdz.t(tdz.ucfirst(c.replace(/([A-Z])/g, ' $1')))+'</span></a>';
        }
        m+='</span></div>';
        for(var a in g) {
            var s=(a in b)?('<ul>'+b[a]+'</ul>'):('');
            console.log('[['+a+']]', s);
            m=m.replace('[['+a+']]', s);
        }
        console.log(m);
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