/**! e-studio-ui.js */
tdz.estudioAutocomplete=function( request, response ) {
   $.ajax({url:window.location.href, data:request, dataType:'json', success: response,beforeSend: function(xhr){xhr.setRequestHeader('Tdz-Tags', request.term.replace(/\s+/, ' ').replace(/^\s+|\s+$/, ''))} });
};
tdz.estudioAutocompleteTerms=function() {
    // custom minLength
    var term = this.value.replace(/\s+$/, '').split(/\s*,\s*/).pop();
    if ( term.length < 2 ) {
        return false;
    }
    return term;
};
tdz.estudioFalse=function() {
      // prevent value inserted on focus
    return false;
};
tdz.estudioAutocompleteSelect=function( event, ui ) {
    var terms = this.value.split(/\s*,\s*/);
    // remove the current input
    terms.pop();
    // add the selected item
    terms.push( ui.item.value );
    // add placeholder to get the comma-and-space at the end
    terms.push('');
    this.value = terms.join(', ');
    return false;
};
if(!('modules' in tdz)) tdz.modules={};
tdz.exec.estudio_ui=tdz.initEstudioAutocomplete=function(){
    tdz.modules.EstudioAutocomplete='input#tdze_tags';
    $('input#tdze_tags').bind('keydown', function(e){
        if(e.keyCode===$.ui.keyCode.TAB && $(this).data('autocomplete').menu.active){
            e.preventDefault();
        };
    }).autocomplete({ source: tdz.estudioAutocomplete, search: tdz.estudioAutocompleteTerms, select: tdz.estudioAutocompleteSelect, focus: tdz.estudioFalse });
};
tdz.exec.estudio_ui();
