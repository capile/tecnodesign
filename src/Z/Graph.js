/*! Tecnodesign Z.Graph v2.3 | (c) 2020 Capile Tecnodesign <ti@tecnodz.com> */
(function()
{

"use strict";

var _G={}, _gids=0, _gT;
function Graph(o)
{
    var n=Z.node(o, this), d, D, id;
    if(!n || n.className.search(/\bz-active\b/)>-1) return;
    n.className += ' z-active';
    if(!(id=n.id)) {
        id='_gid'+_gids++;
        n.id=id;
    }
    _G[id]=null;
    if(_gT) clearTimeout(_gT);
    _gT=setTimeout(buildGraph, 100);
}

function buildGraph(id)
{
    if(!id) {
        for(var s in _G) {
            if(_G[s]) _G[s]=null;
            buildGraph(s);
        }
        return;
    }

    var n=document.getElementById(id), d=(n) ?n.getAttribute('data-g') :null, D=(d) ?JSON.parse(atob(d)) :null;

    if(!D) return;
  	D.bindto = '#'+id;
    if('format' in D) {
    	if(!('axis' in D)) D.axis={};
    	if(!('y' in D.axis)) D.axis.y={};
    	if(!('tick' in D.axis.y)) D.axis.y.tick={};
    	D.axis.y.tick.format = d3.format(D.format);
    }
    _G[id]=c3.generate(D);
}

// default modules loaded into Z
window['Z.Graph.Graph'] = Graph;

})();
