window.WH=window.WH||{};
window.WH.Stu=function(){function J(a,b){var f=new XMLHttpRequest;f.open("GET",a,b);f.send()}function l(){var a=K;if(g){var b=+new Date-g;0<b&&(a+=b)}return a}function L(a){if("undefined"!==typeof a&&"undefined"!==typeof a.target&&"undefined"!==typeof a.target.getAttribute&&(a=a.target.getAttribute("id"),"undefined"!==typeof a&&0!==a.indexOf("whvid-player")))return;if(!(M||(M=!0,k&&6>=k))){a=v?location.href.match(/\bm\./i)?"vm":"vw":"pv";var b=h.pageName+" btraw "+l()/1E3,f=q({});delete f.dl;var N=
(w?"/x/devstu":"/Special:Stu")+"?v=6";N+="&"+x({d:a,m:b,b:"6"})+"&"+x(f);J(N,!1)}}function O(){g&&(K+=+new Date-g,g=!1)}function P(){g||(g=+new Date)}function Q(){var a=document.querySelectorAll("#intro, .section.steps, #quick_summary_section");if(a){var b=1E6,f=0;Array.prototype.forEach.call(a,function(a){var d=a;for(var c=0;d;)c+=d.offsetTop-d.clientTop,d=d.offsetParent;d=c;a=a.offsetHeight||a.clientHeight;b=Math.min(d,b);f=Math.max(d+a,f)});p=b;y=f}}function R(){return Math.max(document.body.clientHeight,
document.body.offsetHeight,document.body.scrollHeight)}function aa(){var a=!1;try{var b=Object.defineProperty({},"passive",{get:function(){a=!0}});window.addEventListener("testPassive",null,b);window.removeEventListener("testPassive",null,b)}catch(f){}window.addEventListener("scroll",function(){e++;if(-1!=r){var a=+new Date-t;a>=r+250&&(r=a,Q())}var b=l();a=b-S;S=b;if(!(0>=a)){var d=window.scrollY||window.pageYOffset;b=d+(window.innerHeight||document.documentElement.clientHeight);if(!(d>y||b<p)){var c=
y-p;if(!(0>=c))for(d=Math.floor(128*(1*d-p)/c),0>d&&(d=0),b=Math.ceil(128*(1*b-p)/c),127<b&&(b=127),c=d;c<=b;)"undefined"===typeof m[c]&&(m[c]=0),m[c]+=a,c++}}a=(window.scrollY||window.pageYOffset)+(window.innerHeight||document.documentElement.clientHeight);a>z&&(z=a)});window.addEventListener("resize",function(){e++});window.addEventListener("click",function(){e++});b=a?{passive:!0}:!1;window.addEventListener("touchstart",function(){e++},b);window.addEventListener("touchend",function(){e++});window.addEventListener("touchcancel",
function(){e++});window.addEventListener("touchmove",function(){e++},b);document.addEventListener("keydown",function(){e++});document.addEventListener("keyup",function(){e++});document.addEventListener("keypress",function(){e++});setInterval(function(){r=-1;Q();0<e&&(T++,e=0);var a=l();a>U&&(U=a,V<A&&V++)},3E3)}function B(a,b){a="/x/collect?t="+a+"&"+x(b);J(a,!0)}function W(){function a(){var a={ti:u[n].t};if(0===n){a:{try{var b=window.navigator,d=document,c=window.screen,h=document,e=h.documentElement,
g=h.body,l=g&&g.clientWidth&&g.clientHeight,k=[];e&&e.clientWidth&&e.clientHeight&&("CSS1Compat"===h.compatMode||!l)?k=[e.clientWidth,e.clientHeight]:l&&(k=[g.clientWidth,g.clientHeight]);e=0>=k[0]||0>=k[1]?"":k.join("x");a=C(a,{de:d&&(d.characterSet||d.charset),ul:(b&&(b.language||b.browserLanguage)||"").toLowerCase(),sd:c&&c.colorDepth+"-bit",sr:c&&c.width+"x"+c.height,vp:e,pr:"undefined"!=typeof window.devicePixelRatio?window.devicePixelRatio:0});var m=q(a);break a}catch(da){}m={}}B("first",m)}else B("later",
q(a));n++;n<u.length&&W()}var b=+new Date;n<u.length&&(b=t+1E3*u[n].t-b,0>=b?a():setTimeout(a,b))}function C(a,b){for(var f in b)b.hasOwnProperty(f)&&"undefined"==typeof a[f]&&(a[f]=b[f]);return a}function q(a){var b=ba();250>b&&(b=1500);var f={gg:v,to:Math.round((+new Date-t)/1E3),ac:Math.round(l()/1E3),pg:h.pageID,ns:h.pageNamespace,ra:X,cv:Y,cl:D,cm:ca,dl:location.href,b:"6"};f=C(a,f);if(0===h.pageNamespace){a=Math.round(l()/1E3*100/(b/200*60));0>a&&(a=0);100<a&&(a=100);E=b/1500*180;A=E/3;b=Math.round(100*
T/A);0>b&&(b=0);100<b&&(b=100);for(var e=0,d=0;128>d;d++){var c=0;"undefined"!==typeof m[d]&&(c=m[d]);c=(c/1E3-1)/9;0>c&&(c=0);1<c&&(c=1);e+=c}e=Math.round(100*e/128);c=l();var g=R();d=Math.round(z/g*100);0>d&&(d=0);100<d&&(d=100);c=Math.round(c/1E3/(g/15E3*180)*100);0>c&&(c=0);100<c&&(c=100);f=C({a1:a,a2:b,a3:e,a4:Math.round((d+c)/2),a5:F(40),a6:F(80),a7:F(160)},f)}return f}function x(a){var b="",f=!0,e;for(e in a)a.hasOwnProperty(e)&&(b+=(f?"":"&")+e+"="+encodeURIComponent(a[e]),f=!1);return b}
function ba(){var a=document.querySelectorAll("#intro, .section.steps, #quick_summary_section");if(!a)return 0;var b=0;Array.prototype.forEach.call(a,function(a){a=a.textContent.split(/\s/).filter(function(a){return""!==a}).length;b+=a});return b}function F(a){return"boolean"===typeof h.Stu.lastStepPingSent&&h.Stu.lastStepPingSent?l()/1E3>=1*R()/a?100:0:0}var h=window.WH,Y=h.stuCount,D=h.pageLang,ca=h.isMobile,w=!!location.href.match(/\.wikidogs\.com/),G=Y&&"en"==D||w,H=location.href.match(/\.wikihow\.[a-z]+\//)&&
"en"==D||w,t=!1,g=!1,K=0,v=0,M=!1,X,k=!1,n=0,E=180,A=E/3,V=0,T=0,e=0,U=0,m=[],r=0,p=0,y=0,S=0,z=0,Z=null,I=[],u=[{t:1},{t:10},{t:20},{t:30},{t:45},{t:60},{t:90},{t:120},{t:180},{t:240},{t:300},{t:360},{t:420},{t:480},{t:540},{t:600}];return{start:function(){var a=navigator.userAgent.match(/MSIE (\d+)/);a&&(k=a[1]);a="";for(var b=0;12>b;b++)a+="abcdefghijklmnopqrstuvwxyz0123456789".charAt(Math.floor(36*Math.random()));X=a;v=("string"===typeof document.referrer?document.referrer:"").match(/^[a-z]*:\/\/[^\/]*google/i)?
1:0;g=t="number"==typeof h.timeStart&&0<h.timeStart?h.timeStart:+new Date;"visibilityState"in document&&"hidden"==document.visibilityState&&(g=!1);H&&W();k&&7<=k&&9>=k?(document.onfocusin=P,document.onfocusout=O):(window.onfocus=P,window.onblur=O);G&&(window.onunload=L,window.onbeforeunload=L);(G||H)&&aa()},ping:function(a){(G||H)&&B("event",q(a))},registerDebug:function(a){if("function"!=typeof a)console.log("registerDebug: must be a function");else{Z=a;for(a=0;a<I.length;a++)Z(I[a]);I=[]}}}}();
window.WH.Stu.start();
