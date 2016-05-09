<?php
/**
 * Tecnodesign E-Studio
 *
 * List of existing entries
 * 
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id$
 */
$qs='';
if($format)$qs .= '&amp;f='.$format;
$permissions=array(
'preview-published'=>tdzEntries::hasStaticPermission('preview', 'Entry', 'published'),
'preview-unpublished'=>tdzEntries::hasStaticPermission('preview', 'Entry', 'unpublished'),
'edit-published'=>tdzEntries::hasStaticPermission('edit', 'Entry', 'published'),
'edit-unpublished'=>tdzEntries::hasStaticPermission('edit', 'Entry', 'unpublished'),
);
$aopt=(sfConfig::get('app_e-studio_assets_optimize'))?(sfConfig::get('app_e-studio_assets_prefix')):(false);
?>
<div class="tdz">
<?php if($toolbar): ?><div class="searchform toolbar"><div class="logo"><a href="/"><img src="<?php echo $img ?>" /></a></div><h1><span class="btn files"></span><?php echo __('Files at')." <em>{$current_folder}</em>" ?></h1><div class="tdzcms"><?php
$e=new tdzEntries();
echo $e->uiButtons('files',array('search'=>'q=link:'.urlencode($current_folder).'&t[]=page&t[]=feed&t[]=file', 'new'=>'link='.$current_folder.'&type=file'));
?></div></div><?php endif; ?>
<?php if($message): ?><div class="message"><?php echo $message ?></div><?php endif; ?>
<div class="files">
<?php if($current_folder!='/'): ?>
<div class="folder"><a href="?d=<?php echo urlencode(dirname($current_folder)).$qs ?>">..</a></div>
<?php endif; ?>
<?php if(count($folders)>0): ?><?php foreach($sf_data->getRaw('folders') as $f): ?>
<div class="folder"><a href="?d=<?php echo urlencode($f['folder']).$qs ?>"><?php echo basename($f['folder']) ?></a></div>
<?php endforeach; endif; ?>
<?php $fs=$sf_data->getRaw('files');if(isset($fs[0])): ?><?php foreach($fs as $f): ?>
<?php
$type = $f['type'];if($type=='')$type='entry';
$pub=($f['published'])?('published'):('unpublished');
if(!$permissions["preview-$pub"])continue;
$bg='';
if($type=='file' && (substr($f['format'],0,6)=='image/' || ($aopt && strpos($f['link'],$aopt)===0 && preg_match('/\.(jpg|jpeg|png|gif)$/i',$f['link'], $m))))
{
  $type .= ' image';
  $t='00'.strtotime($f['published']);
  $bg = ' style="background-image:url(\''.$ui_url.'/e/preview/'.$f['id'].'?optimize=thumb&amp;t=1'.$t.'\');background-position:0 0"';
}
if(!$f['published'])
  $type .= ' unpublished';

if($action!='')
  $faction = $action;
else
  $faction = ($permissions["edit-$pub"])?('edit'):('preview');
?>
<div class="file <?php echo $type ?>"><a id="e<?php echo $f['id'] ?>" class="<?php echo $faction ?>" href="<?php echo $f['link'] ?>"<?php echo $bg ?>><?php if($f['link']=='/') echo 'index'; else echo basename($f['link']) ?></a></div>
<?php endforeach; endif; ?>
<br style="clear:both" /></div>


<div id="file_upload">
    <form action="/e-studio/e/files?d=<?php echo urlencode($current_folder); ?>" method="POST" enctype="multipart/form-data">
        <input type="file" name="upload[]" multiple="multiple" />
        <button type="submit">Upload</button>
        <div class="file_upload_label">Upload files</div>
    </form>
    <table class="files">
        <tr class="file_upload_template" style="display:none;">
            <td class="file_upload_preview"></td>
            <td class="file_name"></td>
            <td class="file_size"></td>
            <td class="file_upload_progress"><div></div></td>
            <td class="file_upload_start"><button>Start</button></td>
            <td class="file_upload_cancel"><button>Cancel</button></td>
        </tr>
        <tr class="file_download_template" style="display:none;">
            <td class="file_download_preview"></td>
            <td class="file_name"><a></a></td>
            <td class="file_size"></td>
            <td class="file_download_delete" colspan="3"><button>Delete</button></td>
        </tr>
    </table>
    <div class="file_upload_overall_progress"><div style="display:none;"></div></div>
    <div class="file_upload_buttons">
        <button class="file_upload_start">Start All</button> 
        <button class="file_upload_cancel">Cancel All</button> 
        <button class="file_download_delete">Delete All</button>
    </div>
</div>



<script type="text/javascript">/*<![CDATA[*/
<?php if(isset($_GET['editor']) && $_GET['editor']=='tinymce'): ?>
$(function(){$('.tdz .files a').bind('click',tdz.file_action);});

// Uncomment and change this document.domain value if you are loading the script cross subdomains
// document.domain = 'moxiecode.com';

var tinymce=null,tinyMCEPopup,tinyMCE;tinyMCEPopup={init:function(){var b=this,a,c;a=b.getWin();tinymce=a.tinymce;tinyMCE=a.tinyMCE;b.editor=tinymce.EditorManager.activeEditor;b.params=b.editor.windowManager.params;b.features=b.editor.windowManager.features;b.dom=b.editor.windowManager.createInstance("tinymce.dom.DOMUtils",document);if(b.features.popup_css!==false){b.dom.loadCSS(b.features.popup_css||b.editor.settings.popup_css)}b.listeners=[];b.onInit={add:function(e,d){b.listeners.push({func:e,scope:d})}};b.isWindow=!b.getWindowArg("mce_inline");b.id=b.getWindowArg("mce_window_id");b.editor.windowManager.onOpen.dispatch(b.editor.windowManager,window)},getWin:function(){return(!window.frameElement&&window.dialogArguments)||opener||parent||top},getWindowArg:function(c,b){var a=this.params[c];return tinymce.is(a)?a:b},getParam:function(b,a){return this.editor.getParam(b,a)},getLang:function(b,a){return this.editor.getLang(b,a)},execCommand:function(d,c,e,b){b=b||{};b.skip_focus=1;this.restoreSelection();return this.editor.execCommand(d,c,e,b)},resizeToInnerSize:function(){var a=this;setTimeout(function(){var b=a.dom.getViewPort(window);a.editor.windowManager.resizeBy(a.getWindowArg("mce_width")-b.w,a.getWindowArg("mce_height")-b.h,a.id||window)},10)},executeOnLoad:function(s){this.onInit.add(function(){eval(s)})},storeSelection:function(){this.editor.windowManager.bookmark=tinyMCEPopup.editor.selection.getBookmark(1)},restoreSelection:function(){var a=tinyMCEPopup;if(!a.isWindow&&tinymce.isIE){a.editor.selection.moveToBookmark(a.editor.windowManager.bookmark)}},requireLangPack:function(){var b=this,a=b.getWindowArg("plugin_url")||b.getWindowArg("theme_url");if(a&&b.editor.settings.language&&b.features.translate_i18n!==false&&b.editor.settings.language_load!==false){a+="/langs/"+b.editor.settings.language+"_dlg.js";if(!tinymce.ScriptLoader.isDone(a)){document.write('<script type="text/javascript" src="'+tinymce._addVer(a)+'"><\/script>');tinymce.ScriptLoader.markDone(a)}}},pickColor:function(b,a){this.execCommand("mceColorPicker",true,{color:document.getElementById(a).value,func:function(e){document.getElementById(a).value=e;try{document.getElementById(a).onchange()}catch(d){}}})},openBrowser:function(a,c,b){tinyMCEPopup.restoreSelection();this.editor.execCallback("file_browser_callback",a,document.getElementById(a).value,c,window)},confirm:function(b,a,c){this.editor.windowManager.confirm(b,a,c,window)},alert:function(b,a,c){this.editor.windowManager.alert(b,a,c,window)},close:function(){var a=this;function b(){a.editor.windowManager.close(window);tinymce=tinyMCE=a.editor=a.params=a.dom=a.dom.doc=null}if(tinymce.isOpera){a.getWin().setTimeout(b,0)}else{b()}},_restoreSelection:function(){var a=window.event.srcElement;if(a.nodeName=="INPUT"&&(a.type=="submit"||a.type=="button")){tinyMCEPopup.restoreSelection()}},_onDOMLoaded:function(){var b=tinyMCEPopup,d=document.title,e,c,a;if(b.domLoaded){return}b.domLoaded=1;if(b.features.translate_i18n!==false){c=document.body.innerHTML;if(tinymce.isIE){c=c.replace(/ (value|title|alt)=([^"][^\s>]+)/gi,' $1="$2"')}document.dir=b.editor.getParam("directionality","");if((a=b.editor.translate(c))&&a!=c){document.body.innerHTML=a}if((a=b.editor.translate(d))&&a!=d){document.title=d=a}}if(!b.editor.getParam("browser_preferred_colors",false)||!b.isWindow){b.dom.addClass(document.body,"forceColors")}document.body.style.display="";if(tinymce.isIE){document.attachEvent("onmouseup",tinyMCEPopup._restoreSelection);b.dom.add(b.dom.select("head")[0],"base",{target:"_self"})}b.restoreSelection();b.resizeToInnerSize();if(!b.isWindow){b.editor.windowManager.setTitle(window,d)}else{window.focus()}if(!tinymce.isIE&&!b.isWindow){tinymce.dom.Event._add(document,"focus",function(){b.editor.windowManager.focus(b.id)})}tinymce.each(b.dom.select("select"),function(f){f.onkeydown=tinyMCEPopup._accessHandler});tinymce.each(b.listeners,function(f){f.func.call(f.scope,b.editor)});if(b.getWindowArg("mce_auto_focus",true)){window.focus();tinymce.each(document.forms,function(g){tinymce.each(g.elements,function(f){if(b.dom.hasClass(f,"mceFocus")&&!f.disabled){f.focus();return false}})})}document.onkeyup=tinyMCEPopup._closeWinKeyHandler},_accessHandler:function(a){a=a||window.event;if(a.keyCode==13||a.keyCode==32){a=a.target||a.srcElement;if(a.onchange){a.onchange()}return tinymce.dom.Event.cancel(a)}},_closeWinKeyHandler:function(a){a=a||window.event;if(a.keyCode==27){tinyMCEPopup.close()}},_wait:function(){if(document.attachEvent){document.attachEvent("onreadystatechange",function(){if(document.readyState==="complete"){document.detachEvent("onreadystatechange",arguments.callee);tinyMCEPopup._onDOMLoaded()}});if(document.documentElement.doScroll&&window==window.top){(function(){if(tinyMCEPopup.domLoaded){return}try{document.documentElement.doScroll("left")}catch(a){setTimeout(arguments.callee,0);return}tinyMCEPopup._onDOMLoaded()})()}document.attachEvent("onload",tinyMCEPopup._onDOMLoaded)}else{if(document.addEventListener){window.addEventListener("DOMContentLoaded",tinyMCEPopup._onDOMLoaded,false);window.addEventListener("load",tinyMCEPopup._onDOMLoaded,false)}}}};tinyMCEPopup.init();tinyMCEPopup._wait();
tdz.file_action=function(e)
{
  var a=$(this),t=(a.parent('.folder').length>0)?('folder'):('file'), link=(t=='folder')?(a.attr('href')+'&editor=tinymce'):(a.attr('href'));
  console.log(t, link);
  if(t=='folder') window.location.href=link;
  else {
    var win = tinyMCEPopup.getWindowArg("window");
    win.document.getElementById(tinyMCEPopup.getWindowArg("input")).value = link;
    // are we an image browser
    if (typeof(win.ImageDialog) != "undefined") {
        if (win.ImageDialog.getImageData) win.ImageDialog.getImageData();
        if (win.ImageDialog.showPreviewImage) win.ImageDialog.showPreviewImage(link);
    }
    // close popup window
    tinyMCEPopup.close();
  }
  return false;
}
<?php else: ?>
$(function(){$('.tdz .file a').bind('click',tdz.file_action);});
tdz.file_action=function(e)
{
  var a=$(this), action='preview',c=a.attr('class');
  if(c.search(/^(edit|preview|publish|unpublish|select)$/)>-1) action=c;
  if(action=='select')alert(a.html());
  else window.location.href=tdz.ui+'/e/'+action+'/'+a.attr('id').substr(1);
  return false;
}
<?php endif; ?>

<?php echo $sf_data->getRaw('js') ?>/*]]>*/</script>
</div>