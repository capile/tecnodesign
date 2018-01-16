tdz.exec.estudio_ui=function(){
$('input#tdze_tags').bind('keydown', function(e){if(e.keyCode===$.ui.keyCode.TAB && $(this).data('autocomplete').menu.active){e.preventDefault();};})
.autocomplete({
  source: function( request, response ) {
   $.ajax({url:window.location.href, dataType:'json', success: response,beforeSend: function(xhr){xhr.setRequestHeader('Tdz-Tags', request.term.replace(/\s+/, ' ').replace(/^\s+|\s+$/, ''))} });
	},
	search: function() {
    // custom minLength
		var term = this.value.replace(/\s+$/, '').split(/\s*,\s*/).pop();
		if ( term.length < 2 ) {
			return false;
		}
	},
	focus: function() {
	  // prevent value inserted on focus
    return false;
  },
  select: function( event, ui ) {
	  var terms = this.value.split(/\s*,\s*/);
		// remove the current input
		terms.pop();
		// add the selected item
		terms.push( ui.item.value );
		// add placeholder to get the comma-and-space at the end
		terms.push('');
		this.value = terms.join(', ');
		return false;
	}
});

};
tdz.exec.estudio_ui();
