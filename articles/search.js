(function(){
var inp=document.getElementById('caf-search-input');
if(!inp)return;
var out=document.getElementById('caf-search-results');
var cnt=document.getElementById('caf-search-count');
var MAX=200;
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function run(){
  var q=inp.value.trim().toLowerCase();
  if(q.length<2||!window.CAF_ARTICLES){out.hidden=true;cnt.hidden=true;out.innerHTML='';return;}
  var terms=q.split(/\s+/);
  var hits=window.CAF_ARTICLES.filter(function(a){
    var h=(a.t+' '+a.c).toLowerCase();
    return terms.every(function(t){return h.indexOf(t)>-1;});
  });
  out.innerHTML=hits.slice(0,MAX).map(function(a){
    return '<li><a href="'+a.u+'">'+esc(a.t)+'</a> <span class="caf-count">&mdash; '+esc(a.c)+'</span></li>';
  }).join('');
  cnt.textContent=hits.length?hits.length+' result'+(hits.length===1?'':'s')+(hits.length>MAX?' (showing first '+MAX+')':''):'No matching articles';
  out.hidden=!hits.length;cnt.hidden=false;
}
inp.addEventListener('input',run);
})();
