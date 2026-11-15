(function(){
var rm=matchMedia('(prefers-reduced-motion: reduce)').matches;
// scroll reveals (0.5s, ease): add .in when 15% visible
var els=document.querySelectorAll('.rv,.needles');
if('IntersectionObserver' in window){
  var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}})},{threshold:.15});
  els.forEach(function(el){io.observe(el)});
}else{els.forEach(function(el){el.classList.add('in')})}
// hero crossfade (slow ambient, 1.2s fade every 6s), pausable, stops when tab hidden
function ld(){document.querySelectorAll('img[data-src]').forEach(function(m){m.src=m.getAttribute('data-src')})}
if(document.readyState==='complete')ld();else addEventListener('load',ld);
var slides=[].slice.call(document.querySelectorAll('.hero .slide')),i=0,t=null,paused=rm;
var btn=document.getElementById('pause');
function show(n){slides.forEach(function(s,k){s.classList.toggle('on',k===n)});i=n}
function start(){if(slides.length>1&&!paused&&!document.hidden&&!t)t=setInterval(function(){show((i+1)%slides.length)},6000)}
function stop(){clearInterval(t);t=null}
if(btn){
  btn.textContent=paused?'Play photos':'Pause photos';btn.setAttribute('aria-pressed',paused);
  btn.addEventListener('click',function(){paused=!paused;btn.textContent=paused?'Play photos':'Pause photos';btn.setAttribute('aria-pressed',paused);paused?stop():start()});
}
document.addEventListener('visibilitychange',function(){document.hidden?stop():start()});
start();
// parallax on hero photos (transform only, rAF-throttled)
if(!rm){var hero=document.querySelector('.hero'),tick=false;
  addEventListener('scroll',function(){if(tick)return;tick=true;requestAnimationFrame(function(){
    var y=Math.min(scrollY,hero.offsetHeight);slides.forEach(function(s){s.style.transform='translate3d(0,'+(y*.15)+'px,0) scale(1.08)'});tick=false})},{passive:true});}
// header shadow (opacity/transform only)
var h=document.querySelector('.hdr');
addEventListener('scroll',function(){h.classList.toggle('s',scrollY>40)},{passive:true});
})();
