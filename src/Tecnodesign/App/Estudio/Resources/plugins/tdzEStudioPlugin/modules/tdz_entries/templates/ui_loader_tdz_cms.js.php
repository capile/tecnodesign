<?php @header('Content-Type: application/javascript');
?>if(!('tdz' in window)) window.tdz={'started':false, 'exec':{} };
(function(tdz){
tdz.user=tdz.userName=<?php echo var_export($user, true); ?>;tdz.poll=<?php echo (int)$poll*1000 ?>;tdz.language=<?php var_export($language) ?>;tdz.lastUpdated=<?php echo time() ?>;<?php
    ?>tdz.jquery_ready=false;tdz.mobile=<?php var_export(tdz::isMobile()) ?>;tdz.dir='<?php echo $assets ?>';tdz.loaded={};tdz.link='<?php if($entry) echo $entry->getLink(); ?>';<?php
    ?>tdz.entry='<?php if($entry) echo $entry->getId(); ?>';tdz.tinymce_css='<?php echo sfConfig::get('app_e-studio_tinymce_css'); ?>';tdz.ui='<?php echo $ui_url ?>';<?php
    ?>if(!('l' in tdz)) tdz.l={};tdz.l.Blank='<?php echo __('Blank') ?>';tdz.l['Confirm_delete']='<?php echo __('This action will remove the current content from the website.\nClick on "Ok" to proceed, or "Cancel" to abort.') ?>';tdz.l.new='<?php echo __('New') ?>';tdz.l.edit='<?php echo __('Edit') ?>';tdz.l.search='<?php echo __('Search') ?>';tdz.l.file_manager='<?php echo __('File manager') ?>';tdz.l.publish='<?php echo __('Publish') ?>';tdz.l.unpublish='<?php echo __('Unpublish') ?>';<?php
    ?>tdz.run=[];tdz.a=<?php echo json_encode($sf_data->getRaw('a')) ?>;tdz.durl={'c':'<a class="btn [action]" href="'+tdz.ui+'/c/[slot]/[action]/[id]" title="[icon]"></a>', 'e':'<a class="btn [action]"  href="'+tdz.ui+'/e/[action]/[id]" title="[icon]"></a>'};
tdz.load=function(){var js='',css='';for(var i=0;i<arguments.length;i++){var f=arguments[i], iscss=false;var fname=f.replace(/^.*\/(.+)$/,'$1');if(typeof(tdz.loaded[fname])!='undefined'){if(typeof(tdz.exec[fname])=='function')tdz.exec[fname]();continue;};if(f.search(/\.css$/)>-1)iscss=true;if(f.search(/^((https?\:\/)?\/)/)<0) if(iscss)f=tdz.dir+'/css/'+f;else f=tdz.dir+'/js/'+f; ;if(iscss) css+='@import url(\''+f+'\');';else if(typeof(window.jQuery)!='undefined') $.ajax({type:"GET",url:f,dataType:"script"});else document.write('<scr'+'ipt src="'+f+'"></script>');tdz.loaded[fname]=f;};if(css!='' && typeof(window.jQuery)!='undefined') $('body').append('<style type="text/css">'+css+'</style>');else if(css!='') document.write('<style type="text/css">'+css+'</style>');};
})(tdz);
<?php
exit();
//if(tdz.mobile) tdz.load('jquery.js','jquery-mobile.js','e-studio.js','e-studio.css');else
//if(!tdz.started)tdz.load('jquery.js','e-studio.js','e-studio.css');
