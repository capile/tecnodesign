tdz.tinymce_mediaContent=function(data) {
  var f=$(this);
  f.animate({'opacity':1},'fast');
  if(f.length==0) f=$('.tdz');
  data=$(data).find('.files');
  f.html(data);
  var tb=f.find('.toolbar');
  if(tb.length>0 && f.find('.toolbar').length==0)f.prepend(tb);
  f.find('a').unbind('click').click(tdz.tinymce_mediaSelect);
}
tdz.tinymce_mediaClose=function(e){
  var o=$(this);
  if(typeof(e)=='object' && 'target' in e)o=$(e.target);
  if(o.hasClass('files')||o.hasClass('tinymedia')){
    //return false;
    window.close();
  }
  $(this).unbind('click');
  window.close();
  //return false;
}
tdz.tinymce_mediaSelect=function(e)
{
  //f=image
  var input=$(this),mw=false,url=tdz.ui+'/e/files?d=',ret=false;
  $(document).click(tdz.tinymce_mediaClose);
  if(input.hasClass('tinymedia'))
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
      //mw.hide('fast');
      $(window.opener.document.body).find('#src').val(input.attr('href'));
      window.close();
    }
  }
  mw.animate({'opacity':.5},'fast');
  $.ajax({'url': url, 'context': mw, 'success': tdz.tinymce_mediaContent});
  return ret;
}
