/*! Tecnodesign Z.Graph v2.2 | (c) 2018 Capile Tecnodesign <ti@tecnodz.com> */
(function()
{

"use strict";




function Graph(o)
{
    var q='Graph.Graph';
    if(q in Z.modules) {
        delete(Z.modules[q]);
        //Z.load('z-form.css');
        //Z.addPlugin('Datepicker', initDatepicker, 'input[data-type^=date],input[type^=date],.tdz-i-datepicker');

        var n=Z.node(o, this);
        if(n) Z.init(n);
    }
}


// default modules loaded into Z
window['Z.Graph.Graph'] = Graph;

})();