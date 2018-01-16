if(!('tdz' in window)) window.tdz={'started':true, 'exec':{} };
(function(tdz){
tdz.$=jQuery;
tdz.updated={};
tdz.cms_publish=function(f){f=$(f);f.attr('action',f.attr('action').replace(/\/edit\//,'/publish/'));tdz.cms_submit(f);};
tdz.cms_unpublish=function(f){f=$(f);f.attr('action',f.attr('action').replace(/\/edit\//,'/unpublish/'));tdz.cms_submit(f);};
tdz.toggleConnect=function(){var ci=$('#connect-info');if(ci.css('display')!='block')ci.fadeIn('fast'); else ci.fadeOut('fast');return false;};
tdz.connect=function(e){e.stopPropagation();var o=$(this),ci=$('#connect-info'),isform=(o.attr('action'));if(!isform){var iframe=ci.find('iframe'),code='<iframe src="'+o.attr('href')+'"></iframe>';if(iframe.length==0)ci.append(code);else iframe.replaceWith(code);ci.find('iframe').fadeIn('normal');};return false;};
if(!('load' in tdz)) {
  tdz.load=function(){var js='',css='';for(var i=0;i<arguments.length;i++){var f=arguments[i], iscss=false;var fname=f.replace(/^.*\/(.+)$/,'$1');if(typeof(tdz.loaded[fname])!='undefined'){if(typeof(tdz.exec[fname])=='function')tdz.exec[fname]();continue;};if(f.search(/\.css$/)>-1)iscss=true;if(f.search(/^((https?\:\/)?\/)/)<0) if(iscss)f=tdz.dir+'/css/'+f;else f=tdz.dir+'/js/'+f; ;if(iscss) css+='@import url(\''+f+'\');';else if(typeof(window.jQuery)!='undefined') $.ajax({type:"GET",url:f,dataType:"script"});else document.write('<scr'+'ipt src="'+f+'"></script>');tdz.loaded[fname]=f;};if(css!='' && typeof(window.jQuery)!='undefined') $('body').append('<style type="text/css">'+css+'</style>');else if(css!='') document.write('<style type="text/css">'+css+'</style>');};
  tdz.loaded={};
  tdz.exec={};
}

tdz.connectSignOut=function()
{
  var url='';
  if(arguments.length>0)url=arguments[0];
  var provider=url.replace(/^.*\/([^\/]+)$/, '$1'), m=provider+'ConnectSignOut';
  if(m in tdz) return tdz[m](url);
  url+='?from='+encodeURIComponent(window.location);
  window.location=url;
  return false;
};
tdz.fbConnectSignOut=function()
{
  var url='';
  if(arguments.length>0)url=arguments[0];
  url=url.replace(/(^.*)\/[^\/]+$/, '$1');
  FB.logout(function(response){
    // user is now logged out
    window.location.reload();
    //tdz.connectSignOut();

  });
};
tdz.fbConnectResponse=function(response)
{
  // if we dont have a session, just hide the user info
  /*
  if(response.session) {
    tdz.toggleConnect();
    return;
  }
  */
 //tdz.debug(response);
 //tdz.debug(response.session);
 if('status' in response && response.status=='connected' && !tdz.user)
   window.location.reload();
/*
  // if we have a session, query for the user's profile picture and name
  FB.api(
    {
      method: 'fql.query',
      query: 'SELECT name, pic FROM profile WHERE id=' + FB.getSession().uid
    },
    function(response) {
      var user = response[0];
      $('#user-info').html('<img src="' + user.pic + '">' + user.name).show('fast');
    }
  );
  */
};
tdz.cms_submit=function(f){
 f=$(f);
 var o=$('#tdzo');

 // trigger validation

 if(o.length>0)
 {
   f.fadeOut('fast');
   $.ajax({
     type: 'POST',
     url: f.attr('action'),
     context: o.find('.tdzcontainer'),
     data: f.serialize(),
     success: function(data){
       $(this).html(data);
     }
   });
   return false;
 }
 if(f.attr('method').search(/^get$/i)>-1)
 {
   tdz.cms_reload();return false;
 }
 f.get(0).submit();
 //f.submit();
 return false;

};
tdz.cms_reload=function()
{
  var op={};if(arguments.length>0)op=arguments[0];
  var f=$('form.tdzf');
  if(f.length>0 && typeof(window.tinyMCE)!='undefined')tinyMCE.triggerSave();
  f.each(function(i,o){
    o=$(o);o.fadeOut('fast');
    var url=o.attr('action');/*+'?'+o.serialize();*/
    if('qs' in op) url+='?'+op.qs;
    //window.location.href=url;exit();
    $.ajax({
      url: url,
      type: o.attr('method'),
      data: o.serialize(),
      context: o.parents('.tdzcontainer'),
      beforeSend: function(xhr){xhr.setRequestHeader('Tdz-Not-For-Update',1)},
      success: function(data) {
        var f=$(this);
        if(f.length==0) f=$('.tdz');
        var tb=f.find('.toolbar');
        f.html(data);
        if(tb.length>0 && f.find('.toolbar').length==0)f.prepend(tb);
        tdz.init(f);
      }
    });
  });
};

tdz.getActions=function(){
  tdz.a={
    "e":{"new":true,"edit":true,"search":"<a class=\"btn search\" href=\"\/e-studio\/e\" title=\"[icon]\"><\/a><a class=\"btn files\" href=\"\/e-studio\/e\/files\" title=\"[icon]\"><\/a>","publish":true,"unpublish":true},
    "c":{"new":"<a class=\"btn [action]\"  href=\"\/e-studio\/c\/[slot]\/new\/1?before=[id]\" title=\"[icon]\"><\/a>","edit":true,"publish":true,"unpublish":true}
  };
  tdz.durl={'c':'<a class="btn [action]" href="'+tdz.ui+'/c/[slot]/[action]/[id]" title="[icon]"></a>', 'e':'<a class="btn [action]"  href="'+tdz.ui+'/e/[action]/[id]" title="[icon]"></a>'};
}
tdz.enable_updates=false;
tdz.update_slots=function(update)
{
  if(arguments.length>0) tdz.enable_updates=update;
  else tdz.enable_updates=(tdz.poll>0);
  var s='',a=arguments,now=Math.round(new Date().getTime() / 1000);
  if(!('lastUpdated' in tdz) || !tdz.lastUpdated) {
    tdz.lastUpdated=Math.round(Date.parse(document.lastModified)*.001);
    s='userinfo=0';
  }
  if(a.length==1 && typeof(a[0])!='string')a=a[0];
  else if(a.length==0){
   a=[];
   $('.tdzs').each(function(i,o){a.push($(o).parent().attr('id'));});
  }
  if('page' in tdz)
   s+='page='+tdz.lastUpdated;
  for(var i=0;i<a.length;i++)
  {
    var slot=a[i];
    if($('#'+slot+' .tdzc').length==0)
    {
      if(s!='')s+='&';
      s += slot+'[]='+tdz.lastUpdated;
    }
    else
    {
      var c=$('#'+slot+' .tdzc').each(function(i,o){
        if(s!='')s+='&';
        var id=$(o).attr('id');
        s += slot+'['+id+']=';
        if(id in tdz.updated)
          s += ''+tdz.updated[id];
        else
          s += ''+tdz.lastUpdated;
      });
    }
  }
  var url=window.location.href.split('#')[0];
  if(url.indexOf('?')>-1)url=url.replace(/&$/,'')+'&'+now;
  else url+='?'+now;
  $.ajax({
    type:'POST',
    url: url,
    beforeSend: function(xhr){xhr.setRequestHeader('Tdz-Slots', s)},
    success: tdz.update_success
  });
};
tdz.update_success=function(data){
  if(typeof(data)!='object') return false;
  if('tdz' in data) {
    for(var k in data.tdz) {
      tdz[k]=data.tdz[k];
    }
    tdz.getActions();
  }
	if('slots' in data)
	{
	  // slot should contain only the assigned slots
	  for(var slot in data.slots)
	  {
	    var cc={},slotc=$('#'+slot+'>.tdzs'),n=$('#'+slot+'>.tdzs>.tdzcms');
	    $('#'+slot+' .tdzs .tdzc').each(function(si,so){
	      cc[$(so).attr('id')]=true;
	    });
	    if('contents' in data.slots[slot]){
	      // first check if the content order is the same
	      var same=true;
	      if(slotc.find('.tdzc').length!=data.slots[slot].contents.length) same=false;
	      else
	      slotc.find('.tdzc').each(function(ci,co){
	        if($(co).attr('id')!=data.slots[slot].contents[co]){same=false;return false;}
	      });
	      if(!same && tdz.enable_updates)
	      for(var si=0;si<data.slots[slot].contents.length;si++)
	      {
	        var cid=data.slots[slot].contents[si];
	        if(cid in cc){
	          if(n){n.remove();n=false;}
	          slotc.find('#'+cid).appendTo(slotc);
	          cc[cid]=false;
	        }
	        else{
	          slotc.append('<div class="tdzc" id="'+cid+'"><br /></div>');
	          $('#'+cid).hide().show('normal');
	        }
	      };
	    }
	    if('prop' in data.slots[slot]) slotc.attr('class','tdzs container '+data.slots[slot].prop);
	    if(tdz.enable_updates) for(cid in cc){if(cc[cid])$('#'+cid).hide('normal',function(){$(this).remove();});}
	  }
	}
	if('contents' in data)
	{
	  for(var i in data.contents)
	  {
	    var original=$('#'+i);
	    if('html' in data.contents[i]){
	      original.replaceWith(data.contents[i].html);original=$('#'+i);
	    }
	    if('prop' in data.contents[i]) original.attr('class','tdzc '+data.contents[i].prop);
	    tdz.updated[i]=data.contents[i].updated;
	  }
	}
	mtoggle=false;
	if('page' in data)
	{
	  tdz.page=data.page;
	  tdz.lastUpdated=tdz.page.updated;
	  if('prop' in data.page)
	  {
	    var m=$('#tdzm');
	    if(m.length==0){$('body').append('<div id="tdzm" class="tdze"></div>');m=$('#tdzm');};
	    mtoggle=m.hasClass('active');
	    m.attr('class',data.page.prop);
	  }
	}
	tdz.init_cms();
	if(mtoggle){tdz.mtoggle(true);}
};
tdz.init_cms=function(){
  var icon='[icon]';
  if(tdz.entry=='')tdz.entry=0;
  $('.tdzs').each(function(i,o){
    o=$(o);var e=o.hasClass('edit'),n=(tdz.entry>0 && o.hasClass('new')),p=o.hasClass('publish');
    if(n && o.find('>.tdzcms').length==0){
      var qs=(o.find('.tdzc:last').length==1)?('?after='+o.find('.tdzc:last').attr('id').replace(/[^0-9]/g,'')):('');
      o.append('<span class="tdzcms"><a class="btn new" href="'+tdz.ui+'/c/'+o.parent().attr('id')+'/new/'+tdz.entry+qs+'" title="['+tdz.l['new']+']"></a></span>');
    }
  });
  $('.tdzs .tdzc').each(function(i,o){
    o=$(o);var s='';
		o.dblclick(tdz.dblclick);
    if('a' in tdz && 'c' in tdz.a && o.find('.tdzcms').length==0){
      var slot=o.parents('.tdzs').parent().attr('id'),id=o.attr('id').replace(/[^0-9]/g,'');
      for(var i in tdz.a.c){
        if(!o.hasClass(i)) continue;
        var link=(typeof(tdz.a.c[i])!='string')?(tdz.durl.c):(tdz.a.c[i]);
        s+=link.replace(/\[slot\]/g,slot).replace(/\[id\]/g,id).replace(/\[action\]/g,i).replace(/\[icon\]/g,icon.replace(/\[icon\]/g,'['+tdz.l[i]+']'));
      }
      if(s!='') o.prepend('<span class="tdzcms">'+s+'</span>');
    }
  });
  var a=$('.tdzcms a');
  $('#tdzm').each(function(i,o){
    o=$(o);var id=o.attr('id'),s='';
    if(id=='tdzm')id=tdz.entry;else if(id.search(/^e[0-9]+$/)<0) return true;
    if('a' in tdz && 'e' in tdz.a && o.find('.tdzcms').length==0){
      for(var i in tdz.a.e){
        if(!o.hasClass(i)) continue;
        
        var link=(typeof(tdz.a.e[i])!='string')?(tdz.durl.e):(tdz.a.e[i]);
       
        if (i == 'search'){
          var lk = link.split('</a>');
          
          for (var x in lk){
            if (lk[x].search(/search/g) != -1){
              s+=lk[x].replace(/\[id\]/g,id).replace(/\[action\]/g,i).replace(/\[icon\]/g,icon.replace(/\[icon\]/g,'['+tdz.l[i]+']'))+'</a>';
            }else if(lk[x].search(/files/g) != -1){
              s+=lk[x].replace(/\[id\]/g,id).replace(/\[action\]/g,i).replace(/\[icon\]/g,icon.replace(/\[icon\]/g,'['+tdz.l['file_manager']+']'))+'</a>';
            }
          }
        } else {
          s+=link.replace(/\[id\]/g,id).replace(/\[action\]/g,i).replace(/\[icon\]/g,icon.replace(/\[icon\]/g,'['+tdz.l[i]+']'));
        }
      }
      if(s!='') o.prepend('<span class="tdzcms tdzm">'+s+'</span>');
      o.unbind('click').bind('click',tdz.mtoggle);
    }
    var na=o.find('.tdzcms a');
    if(na.length>0)a=a.not(na);
  });
  a.unbind('click').click(tdz.cms_load).css({opacity:.2}).unbind('hover').hover(tdz.cms_h,tdz.cms_hreset);
};
tdz.cms_delete=function()
{
  var f;
  if(arguments.length>0)f=$(arguments[0]);
  else f=$('form.tdzf').get(0);

  if(window.confirm(tdz.l.Confirm_delete))
  {
    var action=f.attr('action').replace(/\/edit\//, '/delete/');
    f.attr('action',action);
    tdz.cms_submit(f);
  }
}
tdz.update_entries=function(){tdz.update_slots(true);};
tdz.dblclick=function(){var a=$(this).find('.tdzcms a.edit');if(a.length>0)a.click();};
tdz.debug=function(obj){var text = '';var pat=(arguments.length > 1)?(arguments[1]):('');for (var i in obj){if(pat!='' && i.search(pat) < 0){continue;};text += (text != '')?(', '):('');text += i+'('+typeof(obj[i])+')';text += (typeof(obj[i]) == 'boolean' || typeof(obj[i]) == 'string' || typeof(obj[i]) == 'number')?('['+obj[i]+']'):('');};alert(text);}

tdz.mtoggle=function(){
 var active=false,m=$('#tdzm'),ca=m.hasClass('active');
 if(m.length==0)return false;
 if(arguments.length>0 && arguments[0]=='active' || arguments[0]===true) active=true;
 else active=!ca;
 if(active) $('body').addClass('tdze-active'); else $('body').removeClass('tdze-active');
 if((active && ca)||(!active && !ca))return false;
 if(!active){m.removeClass('active').animate({right:0},200,'swing');$('.tdzs .tdzcms').hide('fast');}
 else {m.addClass('active').animate({right:((m.find('a').length*60))+'px'},200,'swing');$('.tdzs .tdzcms').show('fast',function(){tdz.tdzcms_fix(0,this);});}
 //$('.tdzcms').each(tdz.tdzcms_fix);
}
tdz.cms_load=function()
{
  var url=$(this).attr('href');
  if(tdz.url==url)return false;
  tdz.url=url;
  var o=$('#tdzo');
  if(o.length==0){$('body').append('<div id="tdzo"><a class="tdzo_close" href="#close" onclick="tdz.close_tdzo();return false;"></a><div class="tdzcontainer"></div></div>');o=$('#tdzo');}
  o.find('.tdzcontainer').html('<img class="tdzloading" src="'+tdz.dir+'/images/ajax-loader.gif" alt="Please wait..." title="Please wait..." border="0" />');
  o.fadeIn('fast');
  $.ajax({
    url: url,
    context: o.find('.tdzcontainer'),
    success: function(data) {
      $(this).html(data);
    }
  });
  return false;
}
tdz.tdzcms_fix=function(i,o)
{
  var s=$(o),c=s.parent(),n=s.nextAll(),co={top:0,left:0},po={top:0,left:0};
  if(c.attr('id')=='tdzm' || c.length==0) return false;
  if(n.length>0)co=n.offset();
  else co=c.offset();
  if(c.parent().length>0)po=c.parent().offset();
  else po=co;
  if(c.hasClass('tdzs'))
  {
    var p=s.prevAll(),bt=(p.length>0)?(-20):(0);
    s.css({'height':25,'width':(p.length>0)?(p.width()):(c.width()),'bottom':bt,'left':0});
  }
  else
  {
		var coo=c.offset(),h=25;
		if(coo.top==co.top) h=c.height();
		else if(n.height()>20) h=n.height()+20;
		
    s.css({'height':h,'width':n.width(),'top':co.top-po.top,'left':co.left-po.left});
  }
}
tdz.close_tdzo=function(){var o=$('#tdzo');tdz.url=false;o.fadeOut('fast');};
tdz.cms_h=function(){
  var a=$(this),s=a.parent();
  a.css({opacity:1});
  s.each(tdz.tdzcms_fix);
  var alpha=(s.parent().hasClass('tdzs'))?('.9'):('.5');
  s.css({'background-color':'rgb(220,240,255)','background':'rgba(220,240,255,'+alpha+')'});
};
tdz.cms_hreset=function(){var a=$(this);a.css({opacity:.2});
  a.parent().css({'background':'none'});
};

tdz.initEstudioUpload=function()
{
  if(!('EstudioUpload' in tdz.modules)) tdz.modules.EstudioUpload='.tdz .upload-input';
  $('.tdz .upload-input').hide();
  $('.tdz .upload-name').bind('click',function(e){
    $(this).find('.upload-input').show('fast');
  });
}

tdz.init_form=function()
{
  var f=$('form.tdzf');
  //tdz.form_required();
  tdz.form_toggle(f);
  tdz.modules.EstudioUpload='.tdz .upload-input';
  tdz.initEstudioUpload();
  $('.tdz .typelist input').bind('click',tdz.cms_reload);

  var pos=-1,embed=$('.tdzf .embedded');

  embed.each(function(i,o){
    o=$(o);
    var id=o.attr('id');
    if(id)pos=o.attr('id').replace(/^[^0-9]+/,'');
    var qs=((id)?("'new["+pos+"]=before'"):("'new["+pos+"]=after'")),html='<div class="tdzcms new"><a class="icon" href="#New" onclick="tdz.cms_reload({qs:'+qs+'});return false;"></a></div>';
    o.prepend(html);
    if(embed.length==i+1 && pos>=0)
      o.after(html.replace(qs,"'new["+pos+"]=after'"));
  });

  // sitemap
  $('.tdzf .Contents').sortable({'axis':'y','containment':'parent',cursor: 'crosshair',distance: 30,'items':'>.sortable','scroll':true})
  $('.tdzf .sitemap a').before('<a class="before" href="#before"></a>').after('<a class="after" href="#after"></a>');
  $('.tdzf .sitemap a, .tdzf .outside-sitemap a').bind('click',tdz.cms_update_sitemap);
  /*
  $('.tdzf .sitemap li').each(function(i,o){
    o=$(o);
    if(o.find('li.selected').length>0||o.find('ul').length==0)return true;
    o.find('ul').hide();
    o.hover(tdz.show_ul,tdz.hide_ul);
    o.find('li').hover(tdz.show_ul,tdz.hide_ul);
  });
  */
  if('formTabs' in tdz) tdz.formTabs(f);
  else tdz.tinymce(f);
  
  f.find('input.media').bind('focus',tdz.mediaSelect);

}
tdz.mediaContent=function(data) {
  var f=$(this);
  f.animate({'opacity':1},'fast');
  if(f.length==0) f=$('.tdz');
  data=$(data).find('.files');
  f.html(data);
  var tb=f.find('.toolbar');
  if(tb.length>0 && f.find('.toolbar').length==0)f.prepend(tb);
  f.find('a').unbind('click').click(tdz.mediaSelect);
}
tdz.mediaClose=function(e){
  var o=$(this);
  if(typeof(e)=='object' && 'target' in e)o=$(e.target);
  if(o.hasClass('files')||o.hasClass('media'))return false;
  $(this).unbind('click');
  $('.media-window').hide('fast');
  return false;
}
tdz.mediaSelect=function(e)
{
  //f=image
  var input=$(this),mw=false,url=tdz.ui+'/e/files?d=',ret=false;
  $(document).click(tdz.mediaClose);
  if(input.hasClass('media'))
  {
    mw=input.next('.media-window');
    if(mw.length>0) return mw.show('fast');
    else{input.after('<div class="media-window"></div>');mw=input.next('.media-window');}
    url+=encodeURIComponent(input.val().replace(/\/[^\/]+$/,''));
  }
  else
  {
    mw=input.parents('.media-window');
    if(input.parent().hasClass('folder'))
    {
      mw.prev('input').val(decodeURIComponent(input.attr('href').replace(/^\?d\=/,'')));
      url=tdz.ui+'/e/files'+input.attr('href');
    }
    else
    {
      mw.prev('input').val(input.attr('href'));
      mw.hide('fast');
      return ret;
    }
  }
  mw.animate({'opacity':.5},'fast');
  $.ajax({'url': url, 'context': mw, 'success': tdz.mediaContent});
  return ret;
}

tdz.show_ul=function(){
  $(this).find('>ul').show('fast');
}
tdz.hide_ul=function(){
  $(this).find('>ul').hide('slow');
}
tdz.cms_update_sitemap=function(e)
{
  var a=$(this),li=a.parent('li'),sel=$('.tdzf .sitemap .selected, .tdzf .outside-sitemap .selected');
  var parent='',position=0, title=$('.tdzf #tdze_title').val();
  if(title=='')title='New entry';
  if(li.hasClass('selected') || li.parents('li.selected').length>0) return false;
  if(sel.length>0){sel=sel.eq(0);sel.find('>a[id]').html(title);}
  else sel='<li class="selected"><a id="new_sitemap" href="#current" onclick="return tdz.cms_update_sitemap()">'+title+'</a></li>';

  if(a.hasClass('before'))
  {
    li.before(sel);
    var pli=li.parent('ul').parent('li');
    if(pli.length>0)
      parent=pli.find('>a[id]').attr('id').replace(/^e/,'');
    position=li.prevAll('li').length;
  }
  else if(a.hasClass('after'))
  {
    li.after(sel);
    var pli=li.parent('ul').parent('li');
    if(pli.length>0)
      parent=pli.find('>a[id]').attr('id').replace(/^e/,'');
    position=li.prevAll('li').length +2;
  }
  else
  {
    var ul=li.find('ul');
    if(ul.length>0)ul=ul.eq(0);
    else
    {
      var na=(a.next('a').length>0)?(a.next('a')):(a);
      na.after('<ul></ul>');
      ul=li.find('ul');
    }
    ul.prepend(sel);
    parent=a.attr('id').replace(/^e/,'');
    position=1;
  }
  $('.tdzf #tdze_parents_parent').val(parent);
  $('.tdzf #tdze_parents_position').val(position);
  return false;
}

tdz.form_toggle=function()
{
  var f=(arguments.length>0)?($(arguments[0])):($('form.tdzf'));
  f.find('input.preview,select.preview,textarea.preview').each(function(i,o){
    o=$(o);
    var val=o.val();
    if(o.find('option').length>0){
      val='';
      o.find('option:selected').each(function(i1,o1){
        val += (val=='')?($(o1).text()):(' '+$(o1).text());
      });
    }
    if(val.search(/[^\s]+/)<0) val='<em>'+tdz.l.Blank+'</em>';
    o.before('<span class="tdz_ftoggle">'+val+' <a href="#preview">&#187;</a></span>').wrap('<div class="tdz_ftoggle"></div>');
  });
  f.find('div.tdz_ftoggle').hide();
  f.find('span.tdz_ftoggle a').bind('click',tdz.ftoggle);
}
tdz.ftoggle=function ()
{
  var t=$(this).parent().next('div.tdz_ftoggle');
  if($(this).text()=='»'){$(this).text('«');t.slideDown();}
  else{$(this).text('»');t.slideUp();};
  return false;
}
tdz.tinymce=function(){
    var f=(arguments.length>0)?($(arguments[0])):($('form.tdzf'));
    /*
     * Necessita tirar qualquer variável de localização, como por exemplo _BR, 
     * porque os pacotes de linguagem do tiny_mce são sem localização.
     * Importante: Foi verificado que o pacote pt.js do site é o português Brasil.
     */
    var lang=(tdz.language.search(/^([a-z][a-z]_[A-Z][A-Z]|[a-z][a-z]\-[A-Z][A-Z])$/)>-1)?(tdz.language.substring(0,2)):('en');    
  
    f.find('textarea.html').not('.tinymce').addClass('tinymce').tinymce({
        // Location of TinyMCE script
        script_url : tdz.dir+'/tiny_mce/tiny_mce.js',
        entity_encoding: 'raw',
        // General options
        theme : "advanced",
        plugins : "pdw,safari,style,layer,table,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras",
        language: lang,

        // Theme options
        theme_advanced_buttons1 : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,styleselect,formatselect,fontselect,fontsizeselect,pdw_toggle",
        theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,help,code,|,insertdate,inserttime,preview,|,forecolor,backcolor",
        theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,iespell,media,advhr,|,print,|,ltr,rtl,|,fullscreen",
        theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking",
        theme_advanced_toolbar_location : "top",
        theme_advanced_toolbar_align : "left",
        theme_advanced_statusbar_location : "bottom",
        theme_advanced_resizing : true,
        theme_advanced_source_editor_height: 460,

        // Example content CSS (should be your site CSS)
        content_css : tdz.tinymce_css,
        convert_urls : false,
        // Drop lists for link/image/media/template dialogs
        template_external_list_url : "lists/template_list.js",
        external_link_list_url : "lists/link_list.js",
        external_image_list_url : "lists/image_list.js",
        media_external_list_url : "lists/media_list.js",

        pdw_toggle_on : 1,
        pdw_toggle_toolbars : "2,3,4",

        // Replace values for the template plugin
        template_replace_values : {
            username : "Some User",
            staffid : "991234"
        },

        //Customização
        file_browser_callback: 'tdz.tiny_fileManager'
    });
}

tdz.formTabs=function(f){
  f=$(f);var li='',fs=f.find('fieldset');
  if(fs.length<=1) return tdz.tinymce(f);
  fs.each(function(i,o){
    o=$(o);
    var legend=o.find('legend');
    li+='<li><a href="#'+encodeURIComponent(legend.text())+'">'+legend.text()+'</a></li>';
    legend.hide();
    var w=o.wrap('<div class="tab-contents" id="tab'+i+'"></div>');
  });
  if(li!='')
  {
    f.prepend('<ul class="tabs">'+li+'</li>');
    if(!('current_tab' in tdz))tdz.current_tab=0;
    f.find('.tabs a').click(tdz.setTab).eq(tdz.current_tab).click();
  }
  else tdz.tinymce(f);
}
tdz.setTab=function()
{
  var a=$(this),i=a.parent().prevAll('li').length, t=$('.tab-contents');
  t.not(t.eq(i)).fadeOut('fast');
  tdz.tinymce(t.eq(i).fadeIn('fast'));
  a.parent().parent().find('li').removeClass('current');a.parent().addClass('current');
  tdz.current_tab=i;
  return false;
};
tdz.ready=function()
{
    for(var i=0;i<arguments.length;i++)tdz.run.push(arguments[i]);
    if(typeof(window.jQuery)!='undefined' && tdz.jquery_ready){
        for(var i=0;i<tdz.run.length;i++){
            var fn=tdz.run[i];
            if(typeof(fn)=='function') fn(jQuery);
            else if(typeof(window[fn])!='undefined'){
                fn=window[fn];fn();
            }
        }
    }
};


tdz.tiny_fileManager=function(fname, url, type, win){wfather = win.open('/_assets/e-studio/tiny_mce/plugins/tdzfilemanager/files.php','filebrowser','width=650,height=500,resizable=0');};
tdz.start=function(){
if(tdz.feed!='' && $('.tdzs').length > 0 && $('#tdzcms').length==0){tdz.update_slots();if(tdz.poll){tdz.interval=setInterval('tdz.update_slots();',tdz.poll);};};
if($('#connect-info').length>0){
  $('#connect a').click(tdz.toggleConnect);
  var ci=$('#connect-info');
  ci.find('.buttons a').click(tdz.connect);
  ci.find('form').bind('submit',tdz.connect);
  if(ci.find('#button-fb').length>0){
    var lang=(tdz.language.search(/^[a-z][a-z]_[A-Z][A-Z]$/)>-1)?(tdz.language):('en_US');
    var initurl=ci.find('#button-fb').attr('href');
    if(!initurl)initurl=ci.find('#button-fb').attr('data-signout');
    if(initurl)initurl+= (initurl.indexOf('?')>-1)?('&type=js'):('?type=js');
    ci.append('<div id="fb-root"></div>');
    $.ajax({async:true, type: 'GET', dataType: 'script', url: '//connect.facebook.net/'+lang+'/all.js',success:function(){
      if(initurl)$.ajax({async:true, type: 'GET', dataType: 'script', url: initurl });
    }});
  };
};
};
})(tdz);

$(document).ready(function($){tdz.start();tdz.ready();});
