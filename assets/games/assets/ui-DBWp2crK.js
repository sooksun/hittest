import{R as D,r as C}from"./react-vendor-BcInfMoi.js";function gi(t){var e,s,i="";if(typeof t=="string"||typeof t=="number")i+=t;else if(typeof t=="object")if(Array.isArray(t)){var n=t.length;for(e=0;e<n;e++)t[e]&&(s=gi(t[e]))&&(i&&(i+=" "),i+=s)}else for(s in t)t[s]&&(i&&(i+=" "),i+=s);return i}function mt(){for(var t,e,s=0,i="",n=arguments.length;s<n;s++)(t=arguments[s])&&(e=gi(t))&&(i&&(i+=" "),i+=e);return i}var Bt=t=>typeof t=="number"&&!isNaN(t),gt=t=>typeof t=="string",nt=t=>typeof t=="function",Zn=t=>gt(t)||Bt(t),be=t=>gt(t)||nt(t)?t:null,Qn=(t,e)=>t===!1||Bt(t)&&t>0?t:e,xe=t=>C.isValidElement(t)||gt(t)||nt(t)||Bt(t);function Jn(t,e,s=300){let{scrollHeight:i,style:n}=t;requestAnimationFrame(()=>{n.minHeight="initial",n.height=i+"px",n.transition=`all ${s}ms`,requestAnimationFrame(()=>{n.height="0",n.padding="0",n.margin="0",setTimeout(e,s)})})}function to({enter:t,exit:e,appendPosition:s=!1,collapse:i=!0,collapseDuration:n=300}){return function({children:r,position:o,preventExitTransition:a,done:l,nodeRef:u,isIn:c,playToast:f}){let d=s?`${t}--${o}`:t,h=s?`${e}--${o}`:e,g=C.useRef(0);return C.useLayoutEffect(()=>{let v=u.current,p=d.split(" "),T=y=>{y.target===u.current&&(f(),v.removeEventListener("animationend",T),v.removeEventListener("animationcancel",T),g.current===0&&y.type!=="animationcancel"&&v.classList.remove(...p))};v.classList.add(...p),v.addEventListener("animationend",T),v.addEventListener("animationcancel",T)},[]),C.useEffect(()=>{let v=u.current,p=()=>{v.removeEventListener("animationend",p),i?Jn(v,l,n):l()};c||(a?p():(g.current=1,v.className+=` ${h}`,v.addEventListener("animationend",p)))},[c]),D.createElement(D.Fragment,null,r)}}function ds(t,e){return{content:vi(t.content,t.props),containerId:t.props.containerId,id:t.props.toastId,theme:t.props.theme,type:t.props.type,data:t.props.data||{},isLoading:t.props.isLoading,icon:t.props.icon,reason:t.removalReason,status:e}}function vi(t,e,s=!1){return C.isValidElement(t)&&!gt(t.type)?C.cloneElement(t,{closeToast:e.closeToast,toastProps:e,data:e.data,isPaused:s}):nt(t)?t({closeToast:e.closeToast,toastProps:e,data:e.data,isPaused:s}):t}function eo({closeToast:t,theme:e,ariaLabel:s="close"}){return D.createElement("button",{className:`Toastify__close-button Toastify__close-button--${e}`,type:"button",onClick:i=>{i.stopPropagation(),t(!0)},"aria-label":s},D.createElement("svg",{"aria-hidden":"true",viewBox:"0 0 14 16"},D.createElement("path",{fillRule:"evenodd",d:"M7.71 8.23l3.75 3.75-1.48 1.48-3.75-3.75-3.75 3.75L1 11.98l3.75-3.75L1 4.48 2.48 3l3.75 3.75L9.98 3l1.48 1.48-3.75 3.75z"})))}function so({delay:t,isRunning:e,closeToast:s,type:i="default",hide:n,className:r,controlledProgress:o,progress:a,rtl:l,isIn:u,theme:c}){let f=n||o&&a===0,d={animationDuration:`${t}ms`,animationPlayState:e?"running":"paused"};o&&(d.transform=`scaleX(${a})`);let h=mt("Toastify__progress-bar",o?"Toastify__progress-bar--controlled":"Toastify__progress-bar--animated",`Toastify__progress-bar-theme--${c}`,`Toastify__progress-bar--${i}`,{"Toastify__progress-bar--rtl":l}),g=nt(r)?r({rtl:l,type:i,defaultClassName:h}):mt(h,r),v={[o&&a>=1?"onTransitionEnd":"onAnimationEnd"]:o&&a<1?null:()=>{u&&s()}};return D.createElement("div",{className:"Toastify__progress-bar--wrp","data-hidden":f},D.createElement("div",{className:`Toastify__progress-bar--bg Toastify__progress-bar-theme--${c} Toastify__progress-bar--${i}`}),D.createElement("div",{role:"progressbar","aria-hidden":f?"true":"false","aria-label":"notification timer","aria-valuenow":o?Math.round(a*100):void 0,"aria-valuemin":0,"aria-valuemax":100,className:g,style:d,...v}))}var io=1,Ti=()=>`${io++}`;function no(t,e,s){let i=1,n=0,r=[],o=[],a=e,l=new Map,u=new Set,c=y=>(u.add(y),()=>u.delete(y)),f=()=>{o=Array.from(l.values()),u.forEach(y=>y())},d=({containerId:y,toastId:m,updateId:b})=>{let A=y?y!==t:t!==1,S=l.has(m)&&b==null;return A||S},h=(y,m)=>{l.forEach(b=>{var A;(m==null||m===b.props.toastId)&&((A=b.toggle)==null||A.call(b,y))})},g=y=>{var m,b;y.isActive&&((b=(m=y.props)==null?void 0:m.onClose)==null||b.call(m,y.removalReason),y.isActive=!1,s(ds(y,"removed")))},v=y=>{if(y==null)l.forEach(g);else{let m=l.get(y);m&&g(m)}f()},p=()=>{n-=r.length,r=[]},T=y=>{var m,b;let{toastId:A,updateId:S}=y.props,w=S==null;y.staleId&&l.delete(y.staleId),y.isActive=!0,l.set(A,y),f(),s(ds(y,w?"added":"updated")),w&&((b=(m=y.props).onOpen)==null||b.call(m))};return{id:t,props:a,observe:c,toggle:h,removeToast:v,toasts:l,clearQueue:p,buildToast:(y,m)=>{if(d(m))return;let{toastId:b,updateId:A,data:S,staleId:w,delay:x}=m,M=A==null;M&&n++;let k={...a,style:a.toastStyle,key:i++,...Object.fromEntries(Object.entries(m).filter(([B,N])=>N!=null)),toastId:b,updateId:A,data:S,isIn:!1,className:be(m.className||a.toastClassName),progressClassName:be(m.progressClassName||a.progressClassName),autoClose:m.isLoading?!1:Qn(m.autoClose,a.autoClose),closeToast(B){let N=l.get(b);N&&(N.removalReason=B,v(b))},deleteToast(){if(l.get(b)!=null){if(l.delete(b),n--,n<0&&(n=0),r.length>0){T(r.shift());return}f()}}};k.closeButton=a.closeButton,m.closeButton===!1||xe(m.closeButton)?k.closeButton=m.closeButton:m.closeButton===!0&&(k.closeButton=xe(a.closeButton)?a.closeButton:!0);let V={content:y,props:k,staleId:w};a.limit&&a.limit>0&&n>a.limit&&M?r.push(V):Bt(x)?setTimeout(()=>{T(V)},x):T(V)},setProps(y){a=y},setToggle:(y,m)=>{let b=l.get(y);b&&(b.toggle=m)},isToastActive:y=>{var m;return(m=l.get(y))==null?void 0:m.isActive},getSnapshot:()=>o}}var $=new Map,Lt=[],_e=new Set,oo=t=>_e.forEach(e=>e(t)),bi=()=>$.size>0;function ro(){Lt.forEach(t=>_i(t.content,t.options)),Lt=[]}var ao=(t,{containerId:e})=>{var s;return(s=$.get(e||1))==null?void 0:s.toasts.get(t)};function xi(t,e){var s;if(e)return!!((s=$.get(e))!=null&&s.isToastActive(t));let i=!1;return $.forEach(n=>{n.isToastActive(t)&&(i=!0)}),i}function lo(t){if(!bi()){Lt=Lt.filter(e=>t!=null&&e.options.toastId!==t);return}if(t==null||Zn(t))$.forEach(e=>{e.removeToast(t)});else if(t&&("containerId"in t||"id"in t)){let e=$.get(t.containerId);e?e.removeToast(t.id):$.forEach(s=>{s.removeToast(t.id)})}}var co=(t={})=>{$.forEach(e=>{e.props.limit&&(!t.containerId||e.id===t.containerId)&&e.clearQueue()})};function _i(t,e){xe(t)&&(bi()||Lt.push({content:t,options:e}),$.forEach(s=>{s.buildToast(t,e)}))}function uo(t){var e;(e=$.get(t.containerId||1))==null||e.setToggle(t.id,t.fn)}function wi(t,e){$.forEach(s=>{(e==null||!(e!=null&&e.containerId)||(e==null?void 0:e.containerId)===s.id)&&s.toggle(t,e==null?void 0:e.id)})}function fo(t){let e=t.containerId||1;return{subscribe(s){let i=no(e,t,oo);$.set(e,i);let n=i.observe(s);return ro(),()=>{n(),$.delete(e)}},setProps(s){var i;(i=$.get(e))==null||i.setProps(s)},getSnapshot(){var s;return(s=$.get(e))==null?void 0:s.getSnapshot()}}}function ho(t){return _e.add(t),()=>{_e.delete(t)}}function po(t){return t&&(gt(t.toastId)||Bt(t.toastId))?t.toastId:Ti()}function Ot(t,e){return _i(t,e),e.toastId}function ie(t,e){return{...e,type:e&&e.type||t,toastId:po(e)}}function ne(t){return(e,s)=>Ot(e,ie(t,s))}function P(t,e){return Ot(t,ie("default",e))}P.loading=(t,e)=>Ot(t,ie("default",{isLoading:!0,autoClose:!1,closeOnClick:!1,closeButton:!1,draggable:!1,...e}));function mo(t,{pending:e,error:s,success:i},n){let r;e&&(r=gt(e)?P.loading(e,n):P.loading(e.render,{...n,...e}));let o={isLoading:null,autoClose:null,closeOnClick:null,closeButton:null,draggable:null},a=(u,c,f)=>{if(c==null){P.dismiss(r);return}let d={type:u,...o,...n,data:f},h=gt(c)?{render:c}:c;return r?P.update(r,{...d,...h}):P(h.render,{...d,...h}),f},l=nt(t)?t():t;return l.then(u=>a("success",i,u)).catch(u=>a("error",s,u)),l}P.promise=mo;P.success=ne("success");P.info=ne("info");P.error=ne("error");P.warning=ne("warning");P.warn=P.warning;P.dark=(t,e)=>Ot(t,ie("default",{theme:"dark",...e}));function yo(t){lo(t)}P.dismiss=yo;P.clearWaitingQueue=co;P.isActive=xi;P.update=(t,e={})=>{let s=ao(t,e);if(s){let{props:i,content:n}=s,r={delay:100,...i,...e,toastId:e.toastId||t,updateId:Ti()};r.toastId!==t&&(r.staleId=t);let o=r.render||n;delete r.render,Ot(o,r)}};P.done=t=>{P.update(t,{progress:1})};P.onChange=ho;P.play=t=>wi(!0,t);P.pause=t=>wi(!1,t);function go(t){var e;let{subscribe:s,getSnapshot:i,setProps:n}=C.useRef(fo(t)).current;n(t);let r=(e=C.useSyncExternalStore(s,i,i))==null?void 0:e.slice();function o(a){if(!r)return[];let l=new Map;return t.newestOnTop&&r.reverse(),r.forEach(u=>{let{position:c}=u.props;l.has(c)||l.set(c,[]),l.get(c).push(u)}),Array.from(l,u=>a(u[0],u[1]))}return{getToastToRender:o,isToastActive:xi,count:r==null?void 0:r.length}}function vo(t){let[e,s]=C.useState(!1),[i,n]=C.useState(!1),r=C.useRef(null),o=C.useRef({start:0,delta:0,removalDistance:0,canCloseOnClick:!0,canDrag:!1,didMove:!1}).current,{autoClose:a,pauseOnHover:l,closeToast:u,onClick:c,closeOnClick:f}=t;uo({id:t.toastId,containerId:t.containerId,fn:s}),C.useEffect(()=>{if(t.pauseOnFocusLoss)return d(),()=>{h()}},[t.pauseOnFocusLoss]);function d(){document.hasFocus()||T(),window.addEventListener("focus",p),window.addEventListener("blur",T)}function h(){window.removeEventListener("focus",p),window.removeEventListener("blur",T)}function g(w){if(t.draggable===!0||t.draggable===w.pointerType){y();let x=r.current;o.canCloseOnClick=!0,o.canDrag=!0,x.style.transition="none",t.draggableDirection==="x"?(o.start=w.clientX,o.removalDistance=x.offsetWidth*(t.draggablePercent/100)):(o.start=w.clientY,o.removalDistance=x.offsetHeight*(t.draggablePercent===80?t.draggablePercent*1.5:t.draggablePercent)/100)}}function v(w){let{top:x,bottom:M,left:k,right:V}=r.current.getBoundingClientRect();w.pointerType==="mouse"&&t.pauseOnHover&&w.clientX>=k&&w.clientX<=V&&w.clientY>=x&&w.clientY<=M?T():p()}function p(){s(!0)}function T(){s(!1)}function y(){o.didMove=!1,document.addEventListener("pointermove",b),document.addEventListener("pointerup",A)}function m(){document.removeEventListener("pointermove",b),document.removeEventListener("pointerup",A)}function b(w){let x=r.current;if(o.canDrag&&x){o.didMove=!0,e&&T(),t.draggableDirection==="x"?o.delta=w.clientX-o.start:o.delta=w.clientY-o.start,o.start!==w.clientX&&(o.canCloseOnClick=!1);let M=t.draggableDirection==="x"?`${o.delta}px, var(--y)`:`0, calc(${o.delta}px + var(--y))`;x.style.transform=`translate3d(${M},0)`,x.style.opacity=`${1-Math.abs(o.delta/o.removalDistance)}`}}function A(){m();let w=r.current;if(o.canDrag&&o.didMove&&w){if(o.canDrag=!1,Math.abs(o.delta)>o.removalDistance){n(!0),t.closeToast(!0),t.collapseAll();return}w.style.transition="transform 0.2s, opacity 0.2s",w.style.removeProperty("transform"),w.style.removeProperty("opacity")}}let S={onPointerDown:g,onPointerUp:v};return a&&l&&(S.onMouseEnter=T,t.stacked||(S.onMouseLeave=p)),f&&(S.onClick=w=>{c&&c(w),o.canCloseOnClick&&u(!0)}),{playToast:p,pauseToast:T,isRunning:e,preventExitTransition:i,toastRef:r,eventHandlers:S}}var Ai=typeof window<"u"?C.useLayoutEffect:C.useEffect,oe=({theme:t,type:e,isLoading:s,...i})=>D.createElement("svg",{viewBox:"0 0 24 24",width:"100%",height:"100%",fill:t==="colored"?"currentColor":`var(--toastify-icon-color-${e})`,...i});function To(t){return D.createElement(oe,{...t},D.createElement("path",{d:"M23.32 17.191L15.438 2.184C14.728.833 13.416 0 11.996 0c-1.42 0-2.733.833-3.443 2.184L.533 17.448a4.744 4.744 0 000 4.368C1.243 23.167 2.555 24 3.975 24h16.05C22.22 24 24 22.044 24 19.632c0-.904-.251-1.746-.68-2.44zm-9.622 1.46c0 1.033-.724 1.823-1.698 1.823s-1.698-.79-1.698-1.822v-.043c0-1.028.724-1.822 1.698-1.822s1.698.79 1.698 1.822v.043zm.039-12.285l-.84 8.06c-.057.581-.408.943-.897.943-.49 0-.84-.367-.896-.942l-.84-8.065c-.057-.624.25-1.095.779-1.095h1.91c.528.005.84.476.784 1.1z"}))}function bo(t){return D.createElement(oe,{...t},D.createElement("path",{d:"M12 0a12 12 0 1012 12A12.013 12.013 0 0012 0zm.25 5a1.5 1.5 0 11-1.5 1.5 1.5 1.5 0 011.5-1.5zm2.25 13.5h-4a1 1 0 010-2h.75a.25.25 0 00.25-.25v-4.5a.25.25 0 00-.25-.25h-.75a1 1 0 010-2h1a2 2 0 012 2v4.75a.25.25 0 00.25.25h.75a1 1 0 110 2z"}))}function xo(t){return D.createElement(oe,{...t},D.createElement("path",{d:"M12 0a12 12 0 1012 12A12.014 12.014 0 0012 0zm6.927 8.2l-6.845 9.289a1.011 1.011 0 01-1.43.188l-4.888-3.908a1 1 0 111.25-1.562l4.076 3.261 6.227-8.451a1 1 0 111.61 1.183z"}))}function _o(t){return D.createElement(oe,{...t},D.createElement("path",{d:"M11.983 0a12.206 12.206 0 00-8.51 3.653A11.8 11.8 0 000 12.207 11.779 11.779 0 0011.8 24h.214A12.111 12.111 0 0024 11.791 11.766 11.766 0 0011.983 0zM10.5 16.542a1.476 1.476 0 011.449-1.53h.027a1.527 1.527 0 011.523 1.47 1.475 1.475 0 01-1.449 1.53h-.027a1.529 1.529 0 01-1.523-1.47zM11 12.5v-6a1 1 0 012 0v6a1 1 0 11-2 0z"}))}function wo(){return D.createElement("div",{className:"Toastify__spinner"})}var we={info:bo,warning:To,success:xo,error:_o,spinner:wo},Ao=t=>t in we;function ko({theme:t,type:e,isLoading:s,icon:i}){let n=null,r={theme:t,type:e};return i===!1||(nt(i)?n=i({...r,isLoading:s}):C.isValidElement(i)?n=C.cloneElement(i,r):s?n=we.spinner():Ao(e)&&(n=we[e](r))),n}var So=t=>{let{isRunning:e,preventExitTransition:s,toastRef:i,eventHandlers:n,playToast:r}=vo(t),{closeButton:o,children:a,autoClose:l,onClick:u,type:c,hideProgressBar:f,closeToast:d,transition:h,position:g,className:v,style:p,progressClassName:T,updateId:y,role:m,progress:b,rtl:A,toastId:S,deleteToast:w,isIn:x,isLoading:M,closeOnClick:k,theme:V,ariaLabel:B}=t,N=mt("Toastify__toast",`Toastify__toast-theme--${V}`,`Toastify__toast--${c}`,{"Toastify__toast--rtl":A},{"Toastify__toast--close-on-click":k}),X=nt(v)?v({rtl:A,position:g,type:c,defaultClassName:N}):mt(N,v),H=ko(t),W=!!b||!l,jt={closeToast:d,type:c,theme:V},E=null;return o===!1||(nt(o)?E=o(jt):C.isValidElement(o)?E=C.cloneElement(o,jt):E=eo(jt)),D.createElement(h,{isIn:x,done:w,position:g,preventExitTransition:s,nodeRef:i,playToast:r},D.createElement("div",{id:S,tabIndex:0,onClick:u,"data-in":x,className:X,...n,style:p,ref:i,...x&&{role:m,"aria-label":B}},H!=null&&D.createElement("div",{className:mt("Toastify__toast-icon",{"Toastify--animate-icon Toastify__zoom-enter":!M})},H),vi(a,t,!e),E,!t.customProgressBar&&D.createElement(so,{...y&&!W?{key:`p-${y}`}:{},rtl:A,theme:V,delay:l,isRunning:e,isIn:x,closeToast:d,hide:f,type:c,className:T,controlledProgress:W,progress:b||0})))},Vo=(t,e=!1)=>({enter:`Toastify--animate Toastify__${t}-enter`,exit:`Toastify--animate Toastify__${t}-exit`,appendPosition:e}),Mo=to(Vo("bounce",!0)),Co={position:"top-right",transition:Mo,autoClose:5e3,closeButton:!0,pauseOnHover:!0,pauseOnFocusLoss:!0,draggable:"touch",draggablePercent:80,draggableDirection:"x",role:"alert",theme:"light","aria-label":"Notifications Alt+T",hotKeys:t=>t.altKey&&t.code==="KeyT"};function Po(t){let e={...Co,...t},s=t.stacked,[i,n]=C.useState(!0),r=C.useRef(null),{getToastToRender:o,isToastActive:a,count:l}=go(e),{className:u,style:c,rtl:f,containerId:d,hotKeys:h}=e;function g(p){let T=mt("Toastify__toast-container",`Toastify__toast-container--${p}`,{"Toastify__toast-container--rtl":f});return nt(u)?u({position:p,rtl:f,defaultClassName:T}):mt(T,be(u))}function v(){s&&(n(!0),P.play())}return Ai(()=>{var p;if(s){let T=r.current.querySelectorAll('[data-in="true"]'),y=12,m=(p=e.position)==null?void 0:p.includes("top"),b=0,A=0;Array.from(T).reverse().forEach((S,w)=>{let x=S;x.classList.add("Toastify__toast--stacked"),w>0&&(x.dataset.collapsed=`${i}`),x.dataset.pos||(x.dataset.pos=m?"top":"bot");let M=b*(i?.2:1)+(i?0:y*w),k=Math.max(.5,1-(i?A:0));x.style.setProperty("--y",`${m?M:M*-1}px`),x.style.setProperty("--g",`${y}`),x.style.setProperty("--s",`${k}`),b+=x.offsetHeight,A+=.025})}},[i,l,s]),C.useEffect(()=>{function p(T){var y;let m=r.current;h(T)&&((y=m==null?void 0:m.querySelector('[tabIndex="0"]'))==null||y.focus(),n(!1),P.pause()),T.key==="Escape"&&(document.activeElement===m||m!=null&&m.contains(document.activeElement))&&(n(!0),P.play())}return document.addEventListener("keydown",p),()=>{document.removeEventListener("keydown",p)}},[h]),D.createElement("section",{ref:r,className:"Toastify",id:d,onMouseEnter:()=>{s&&(n(!1),P.pause())},onMouseLeave:v,"aria-live":"polite","aria-atomic":"false","aria-relevant":"additions text","aria-label":e["aria-label"]},o((p,T)=>{let y=T.length?{...c}:{...c,pointerEvents:"none"};return D.createElement("div",{tabIndex:-1,className:g(p),"data-stacked":s,style:y,key:`c-${p}`},T.map(({content:m,props:b})=>D.createElement(So,{...b,stacked:s,collapseAll:v,isIn:a(b.toastId,b.containerId),key:`t-${b.key}`},m)))}))}var Do=`:root {
  --toastify-color-light: #fff;
  --toastify-color-dark: #121212;
  --toastify-color-info: #3498db;
  --toastify-color-success: #07bc0c;
  --toastify-color-warning: #f1c40f;
  --toastify-color-error: hsl(6, 78%, 57%);
  --toastify-color-transparent: rgba(255, 255, 255, 0.7);

  --toastify-icon-color-info: var(--toastify-color-info);
  --toastify-icon-color-success: var(--toastify-color-success);
  --toastify-icon-color-warning: var(--toastify-color-warning);
  --toastify-icon-color-error: var(--toastify-color-error);

  --toastify-container-width: fit-content;
  --toastify-toast-width: 320px;
  --toastify-toast-offset: 16px;
  --toastify-toast-top: max(var(--toastify-toast-offset), env(safe-area-inset-top));
  --toastify-toast-right: max(var(--toastify-toast-offset), env(safe-area-inset-right));
  --toastify-toast-left: max(var(--toastify-toast-offset), env(safe-area-inset-left));
  --toastify-toast-bottom: max(var(--toastify-toast-offset), env(safe-area-inset-bottom));
  --toastify-toast-background: #fff;
  --toastify-toast-padding: 14px;
  --toastify-toast-min-height: 64px;
  --toastify-toast-max-height: 800px;
  --toastify-toast-bd-radius: 6px;
  --toastify-toast-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
  --toastify-font-family: sans-serif;
  --toastify-z-index: 9999;
  --toastify-text-color-light: #757575;
  --toastify-text-color-dark: #fff;

  /* Used only for colored theme */
  --toastify-text-color-info: #fff;
  --toastify-text-color-success: #fff;
  --toastify-text-color-warning: #fff;
  --toastify-text-color-error: #fff;

  --toastify-spinner-color: #616161;
  --toastify-spinner-color-empty-area: #e0e0e0;
  --toastify-color-progress-light: linear-gradient(to right, #4cd964, #5ac8fa, #007aff, #34aadc, #5856d6, #ff2d55);
  --toastify-color-progress-dark: #bb86fc;
  --toastify-color-progress-info: var(--toastify-color-info);
  --toastify-color-progress-success: var(--toastify-color-success);
  --toastify-color-progress-warning: var(--toastify-color-warning);
  --toastify-color-progress-error: var(--toastify-color-error);
  /* used to control the opacity of the progress trail */
  --toastify-color-progress-bgo: 0.2;
}

.Toastify__toast-container {
  z-index: var(--toastify-z-index);
  -webkit-transform: translate3d(0, 0, var(--toastify-z-index));
  position: fixed;
  width: var(--toastify-container-width);
  box-sizing: border-box;
  color: #fff;
  display: flex;
  flex-direction: column;
}

.Toastify__toast-container--top-left {
  top: var(--toastify-toast-top);
  left: var(--toastify-toast-left);
}
.Toastify__toast-container--top-center {
  top: var(--toastify-toast-top);
  left: 50%;
  transform: translateX(-50%);
  align-items: center;
}
.Toastify__toast-container--top-right {
  top: var(--toastify-toast-top);
  right: var(--toastify-toast-right);
  align-items: end;
}
.Toastify__toast-container--bottom-left {
  bottom: var(--toastify-toast-bottom);
  left: var(--toastify-toast-left);
}
.Toastify__toast-container--bottom-center {
  bottom: var(--toastify-toast-bottom);
  left: 50%;
  transform: translateX(-50%);
  align-items: center;
}
.Toastify__toast-container--bottom-right {
  bottom: var(--toastify-toast-bottom);
  right: var(--toastify-toast-right);
  align-items: end;
}

.Toastify__toast {
  --y: 0px;
  position: relative;
  touch-action: none;
  width: var(--toastify-toast-width);
  min-height: var(--toastify-toast-min-height);
  box-sizing: border-box;
  margin-bottom: 1rem;
  padding: var(--toastify-toast-padding);
  border-radius: var(--toastify-toast-bd-radius);
  box-shadow: var(--toastify-toast-shadow);
  max-height: var(--toastify-toast-max-height);
  font-family: var(--toastify-font-family);
  /* webkit only issue #791 */
  z-index: 0;
  /* inner swag */
  display: flex;
  flex: 1 auto;
  align-items: center;
  word-break: break-word;
}

@media only screen and (max-width: 480px) {
  .Toastify__toast-container {
    width: 100vw;
    left: env(safe-area-inset-left);
    margin: 0;
  }
  .Toastify__toast-container--top-left,
  .Toastify__toast-container--top-center,
  .Toastify__toast-container--top-right {
    top: env(safe-area-inset-top);
    transform: translateX(0);
  }
  .Toastify__toast-container--bottom-left,
  .Toastify__toast-container--bottom-center,
  .Toastify__toast-container--bottom-right {
    bottom: env(safe-area-inset-bottom);
    transform: translateX(0);
  }
  .Toastify__toast-container--rtl {
    right: env(safe-area-inset-right);
    left: initial;
  }
  .Toastify__toast {
    --toastify-toast-width: 100%;
    margin-bottom: 0;
    border-radius: 0;
  }
}

.Toastify__toast-container[data-stacked='true'] {
  width: var(--toastify-toast-width);
}

@media only screen and (max-width: 480px) {
  .Toastify__toast-container[data-stacked='true'] {
    width: 100vw;
  }
}

.Toastify__toast--stacked {
  position: absolute;
  width: 100%;
  transform: translate3d(0, var(--y), 0) scale(var(--s));
  transition: transform 0.3s;
}

.Toastify__toast--stacked[data-collapsed] .Toastify__toast-body,
.Toastify__toast--stacked[data-collapsed] .Toastify__close-button {
  transition: opacity 0.1s;
}

.Toastify__toast--stacked[data-collapsed='false'] {
  overflow: visible;
}

.Toastify__toast--stacked[data-collapsed='true']:not(:last-child) > * {
  opacity: 0;
}

.Toastify__toast--stacked:after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  height: calc(var(--g) * 1px);
  bottom: 100%;
}

.Toastify__toast--stacked[data-pos='top'] {
  top: 0;
}

.Toastify__toast--stacked[data-pos='bot'] {
  bottom: 0;
}

.Toastify__toast--stacked[data-pos='bot'].Toastify__toast--stacked:before {
  transform-origin: top;
}

.Toastify__toast--stacked[data-pos='top'].Toastify__toast--stacked:before {
  transform-origin: bottom;
}

.Toastify__toast--stacked:before {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 100%;
  transform: scaleY(3);
  z-index: -1;
}

.Toastify__toast--rtl {
  direction: rtl;
}

.Toastify__toast--close-on-click {
  cursor: pointer;
}

.Toastify__toast-icon {
  margin-inline-end: 10px;
  width: 22px;
  flex-shrink: 0;
  display: flex;
}

.Toastify--animate {
  animation-fill-mode: both;
  animation-duration: 0.5s;
}

.Toastify--animate-icon {
  animation-fill-mode: both;
  animation-duration: 0.3s;
}

.Toastify__toast-theme--dark {
  background: var(--toastify-color-dark);
  color: var(--toastify-text-color-dark);
}

.Toastify__toast-theme--light {
  background: var(--toastify-color-light);
  color: var(--toastify-text-color-light);
}

.Toastify__toast-theme--colored.Toastify__toast--default {
  background: var(--toastify-color-light);
  color: var(--toastify-text-color-light);
}

.Toastify__toast-theme--colored.Toastify__toast--info {
  color: var(--toastify-text-color-info);
  background: var(--toastify-color-info);
}

.Toastify__toast-theme--colored.Toastify__toast--success {
  color: var(--toastify-text-color-success);
  background: var(--toastify-color-success);
}

.Toastify__toast-theme--colored.Toastify__toast--warning {
  color: var(--toastify-text-color-warning);
  background: var(--toastify-color-warning);
}

.Toastify__toast-theme--colored.Toastify__toast--error {
  color: var(--toastify-text-color-error);
  background: var(--toastify-color-error);
}

.Toastify__progress-bar-theme--light {
  background: var(--toastify-color-progress-light);
}

.Toastify__progress-bar-theme--dark {
  background: var(--toastify-color-progress-dark);
}

.Toastify__progress-bar--info {
  background: var(--toastify-color-progress-info);
}

.Toastify__progress-bar--success {
  background: var(--toastify-color-progress-success);
}

.Toastify__progress-bar--warning {
  background: var(--toastify-color-progress-warning);
}

.Toastify__progress-bar--error {
  background: var(--toastify-color-progress-error);
}

.Toastify__progress-bar-theme--colored.Toastify__progress-bar--info,
.Toastify__progress-bar-theme--colored.Toastify__progress-bar--success,
.Toastify__progress-bar-theme--colored.Toastify__progress-bar--warning,
.Toastify__progress-bar-theme--colored.Toastify__progress-bar--error {
  background: var(--toastify-color-transparent);
}

.Toastify__close-button {
  color: #fff;
  position: absolute;
  top: 6px;
  right: 6px;
  background: transparent;
  outline: none;
  border: none;
  padding: 0;
  cursor: pointer;
  opacity: 0.7;
  transition: 0.3s ease;
  z-index: 1;
}

.Toastify__toast--rtl .Toastify__close-button {
  left: 6px;
  right: unset;
}

.Toastify__close-button--light {
  color: #000;
  opacity: 0.3;
}

.Toastify__close-button > svg {
  fill: currentColor;
  height: 16px;
  width: 14px;
}

.Toastify__close-button:hover,
.Toastify__close-button:focus {
  opacity: 1;
}

@keyframes Toastify__trackProgress {
  0% {
    transform: scaleX(1);
  }
  100% {
    transform: scaleX(0);
  }
}

.Toastify__progress-bar {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 1;
  opacity: 0.7;
  transform-origin: left;
}

.Toastify__progress-bar--animated {
  animation: Toastify__trackProgress linear 1 forwards;
}

.Toastify__progress-bar--controlled {
  transition: transform 0.2s;
}

.Toastify__progress-bar--rtl {
  right: 0;
  left: initial;
  transform-origin: right;
  border-bottom-left-radius: initial;
}

.Toastify__progress-bar--wrp {
  position: absolute;
  overflow: hidden;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 5px;
  border-bottom-left-radius: var(--toastify-toast-bd-radius);
  border-bottom-right-radius: var(--toastify-toast-bd-radius);
}

.Toastify__progress-bar--wrp[data-hidden='true'] {
  opacity: 0;
}

.Toastify__progress-bar--bg {
  opacity: var(--toastify-color-progress-bgo);
  width: 100%;
  height: 100%;
}

.Toastify__spinner {
  width: 20px;
  height: 20px;
  box-sizing: border-box;
  border: 2px solid;
  border-radius: 100%;
  border-color: var(--toastify-spinner-color-empty-area);
  border-right-color: var(--toastify-spinner-color);
  animation: Toastify__spin 0.65s linear infinite;
}

@keyframes Toastify__bounceInRight {
  from,
  60%,
  75%,
  90%,
  to {
    animation-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
  }
  from {
    opacity: 0;
    transform: translate3d(3000px, 0, 0);
  }
  60% {
    opacity: 1;
    transform: translate3d(-25px, 0, 0);
  }
  75% {
    transform: translate3d(10px, 0, 0);
  }
  90% {
    transform: translate3d(-5px, 0, 0);
  }
  to {
    transform: none;
  }
}

@keyframes Toastify__bounceOutRight {
  20% {
    opacity: 1;
    transform: translate3d(-20px, var(--y), 0);
  }
  to {
    opacity: 0;
    transform: translate3d(2000px, var(--y), 0);
  }
}

@keyframes Toastify__bounceInLeft {
  from,
  60%,
  75%,
  90%,
  to {
    animation-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
  }
  0% {
    opacity: 0;
    transform: translate3d(-3000px, 0, 0);
  }
  60% {
    opacity: 1;
    transform: translate3d(25px, 0, 0);
  }
  75% {
    transform: translate3d(-10px, 0, 0);
  }
  90% {
    transform: translate3d(5px, 0, 0);
  }
  to {
    transform: none;
  }
}

@keyframes Toastify__bounceOutLeft {
  20% {
    opacity: 1;
    transform: translate3d(20px, var(--y), 0);
  }
  to {
    opacity: 0;
    transform: translate3d(-2000px, var(--y), 0);
  }
}

@keyframes Toastify__bounceInUp {
  from,
  60%,
  75%,
  90%,
  to {
    animation-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
  }
  from {
    opacity: 0;
    transform: translate3d(0, 3000px, 0);
  }
  60% {
    opacity: 1;
    transform: translate3d(0, -20px, 0);
  }
  75% {
    transform: translate3d(0, 10px, 0);
  }
  90% {
    transform: translate3d(0, -5px, 0);
  }
  to {
    transform: translate3d(0, 0, 0);
  }
}

@keyframes Toastify__bounceOutUp {
  20% {
    transform: translate3d(0, calc(var(--y) - 10px), 0);
  }
  40%,
  45% {
    opacity: 1;
    transform: translate3d(0, calc(var(--y) + 20px), 0);
  }
  to {
    opacity: 0;
    transform: translate3d(0, -2000px, 0);
  }
}

@keyframes Toastify__bounceInDown {
  from,
  60%,
  75%,
  90%,
  to {
    animation-timing-function: cubic-bezier(0.215, 0.61, 0.355, 1);
  }
  0% {
    opacity: 0;
    transform: translate3d(0, -3000px, 0);
  }
  60% {
    opacity: 1;
    transform: translate3d(0, 25px, 0);
  }
  75% {
    transform: translate3d(0, -10px, 0);
  }
  90% {
    transform: translate3d(0, 5px, 0);
  }
  to {
    transform: none;
  }
}

@keyframes Toastify__bounceOutDown {
  20% {
    transform: translate3d(0, calc(var(--y) - 10px), 0);
  }
  40%,
  45% {
    opacity: 1;
    transform: translate3d(0, calc(var(--y) + 20px), 0);
  }
  to {
    opacity: 0;
    transform: translate3d(0, 2000px, 0);
  }
}

.Toastify__bounce-enter--top-left,
.Toastify__bounce-enter--bottom-left {
  animation-name: Toastify__bounceInLeft;
}

.Toastify__bounce-enter--top-right,
.Toastify__bounce-enter--bottom-right {
  animation-name: Toastify__bounceInRight;
}

.Toastify__bounce-enter--top-center {
  animation-name: Toastify__bounceInDown;
}

.Toastify__bounce-enter--bottom-center {
  animation-name: Toastify__bounceInUp;
}

.Toastify__bounce-exit--top-left,
.Toastify__bounce-exit--bottom-left {
  animation-name: Toastify__bounceOutLeft;
}

.Toastify__bounce-exit--top-right,
.Toastify__bounce-exit--bottom-right {
  animation-name: Toastify__bounceOutRight;
}

.Toastify__bounce-exit--top-center {
  animation-name: Toastify__bounceOutUp;
}

.Toastify__bounce-exit--bottom-center {
  animation-name: Toastify__bounceOutDown;
}

@keyframes Toastify__zoomIn {
  from {
    opacity: 0;
    transform: scale3d(0.3, 0.3, 0.3);
  }
  50% {
    opacity: 1;
  }
}

@keyframes Toastify__zoomOut {
  from {
    opacity: 1;
  }
  50% {
    opacity: 0;
    transform: translate3d(0, var(--y), 0) scale3d(0.3, 0.3, 0.3);
  }
  to {
    opacity: 0;
  }
}

.Toastify__zoom-enter {
  animation-name: Toastify__zoomIn;
}

.Toastify__zoom-exit {
  animation-name: Toastify__zoomOut;
}

@keyframes Toastify__flipIn {
  from {
    transform: perspective(400px) rotate3d(1, 0, 0, 90deg);
    animation-timing-function: ease-in;
    opacity: 0;
  }
  40% {
    transform: perspective(400px) rotate3d(1, 0, 0, -20deg);
    animation-timing-function: ease-in;
  }
  60% {
    transform: perspective(400px) rotate3d(1, 0, 0, 10deg);
    opacity: 1;
  }
  80% {
    transform: perspective(400px) rotate3d(1, 0, 0, -5deg);
  }
  to {
    transform: perspective(400px);
  }
}

@keyframes Toastify__flipOut {
  from {
    transform: translate3d(0, var(--y), 0) perspective(400px);
  }
  30% {
    transform: translate3d(0, var(--y), 0) perspective(400px) rotate3d(1, 0, 0, -20deg);
    opacity: 1;
  }
  to {
    transform: translate3d(0, var(--y), 0) perspective(400px) rotate3d(1, 0, 0, 90deg);
    opacity: 0;
  }
}

.Toastify__flip-enter {
  animation-name: Toastify__flipIn;
}

.Toastify__flip-exit {
  animation-name: Toastify__flipOut;
}

@keyframes Toastify__slideInRight {
  from {
    transform: translate3d(110%, 0, 0);
    visibility: visible;
  }
  to {
    transform: translate3d(0, var(--y), 0);
  }
}

@keyframes Toastify__slideInLeft {
  from {
    transform: translate3d(-110%, 0, 0);
    visibility: visible;
  }
  to {
    transform: translate3d(0, var(--y), 0);
  }
}

@keyframes Toastify__slideInUp {
  from {
    transform: translate3d(0, 110%, 0);
    visibility: visible;
  }
  to {
    transform: translate3d(0, var(--y), 0);
  }
}

@keyframes Toastify__slideInDown {
  from {
    transform: translate3d(0, -110%, 0);
    visibility: visible;
  }
  to {
    transform: translate3d(0, var(--y), 0);
  }
}

@keyframes Toastify__slideOutRight {
  from {
    transform: translate3d(0, var(--y), 0);
  }
  to {
    visibility: hidden;
    transform: translate3d(110%, var(--y), 0);
  }
}

@keyframes Toastify__slideOutLeft {
  from {
    transform: translate3d(0, var(--y), 0);
  }
  to {
    visibility: hidden;
    transform: translate3d(-110%, var(--y), 0);
  }
}

@keyframes Toastify__slideOutDown {
  from {
    transform: translate3d(0, var(--y), 0);
  }
  to {
    visibility: hidden;
    transform: translate3d(0, 500px, 0);
  }
}

@keyframes Toastify__slideOutUp {
  from {
    transform: translate3d(0, var(--y), 0);
  }
  to {
    visibility: hidden;
    transform: translate3d(0, -500px, 0);
  }
}

.Toastify__slide-enter--top-left,
.Toastify__slide-enter--bottom-left {
  animation-name: Toastify__slideInLeft;
}

.Toastify__slide-enter--top-right,
.Toastify__slide-enter--bottom-right {
  animation-name: Toastify__slideInRight;
}

.Toastify__slide-enter--top-center {
  animation-name: Toastify__slideInDown;
}

.Toastify__slide-enter--bottom-center {
  animation-name: Toastify__slideInUp;
}

.Toastify__slide-exit--top-left,
.Toastify__slide-exit--bottom-left {
  animation-name: Toastify__slideOutLeft;
  animation-timing-function: ease-in;
  animation-duration: 0.3s;
}

.Toastify__slide-exit--top-right,
.Toastify__slide-exit--bottom-right {
  animation-name: Toastify__slideOutRight;
  animation-timing-function: ease-in;
  animation-duration: 0.3s;
}

.Toastify__slide-exit--top-center {
  animation-name: Toastify__slideOutUp;
  animation-timing-function: ease-in;
  animation-duration: 0.3s;
}

.Toastify__slide-exit--bottom-center {
  animation-name: Toastify__slideOutDown;
  animation-timing-function: ease-in;
  animation-duration: 0.3s;
}

@keyframes Toastify__spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
`,hs=new Map,Ro=(t,e)=>{Ai(()=>{if(typeof document>"u")return;let s=document,i=hs.get(s);if(i){e&&i.setAttribute("nonce",e);return}let n=s.createElement("style");n.textContent=t,e&&n.setAttribute("nonce",e),s.head.appendChild(n),hs.set(s,n)},[e])};function Fc(t){return Ro(Do,t.nonce),D.createElement(Po,{...t})}function Xe(t,e){t.indexOf(e)===-1&&t.push(e)}function Yt(t,e){const s=t.indexOf(e);s>-1&&t.splice(s,1)}const ot=(t,e,s)=>s>e?e:s<t?t:s;let He=()=>{};const lt={},ki=t=>/^-?(?:\d+(?:\.\d+)?|\.\d+)$/u.test(t),Si=t=>typeof t=="object"&&t!==null,Vi=t=>/^0[^.\s]+$/u.test(t);function Mi(t){let e;return()=>(e===void 0&&(e=t()),e)}const ct=t=>t,Ye=(...t)=>t.reduce((e,s)=>i=>s(e(i))),qe=(t,e,s)=>{const i=e-t;return i?(s-t)/i:1};class Ge{constructor(){this.subscriptions=[]}add(e){return Xe(this.subscriptions,e),()=>Yt(this.subscriptions,e)}notify(e,s,i){const n=this.subscriptions.length;if(n)if(n===1)this.subscriptions[0](e,s,i);else for(let r=0;r<n;r++){const o=this.subscriptions[r];o&&o(e,s,i)}}getSize(){return this.subscriptions.length}clear(){this.subscriptions.length=0}}const J=t=>t*1e3,Q=t=>t/1e3,Ci=(t,e)=>e?t*(1e3/e):0,Pi=(t,e,s)=>(((1-3*s+3*e)*t+(3*s-6*e))*t+3*e)*t,Eo=1e-7,Lo=12;function Io(t,e,s,i,n){let r,o,a=0;do o=e+(s-e)/2,r=Pi(o,i,n)-t,r>0?s=o:e=o;while(Math.abs(r)>Eo&&++a<Lo);return o}function Ft(t,e,s,i){if(t===e&&s===i)return ct;const n=r=>Io(r,0,1,t,s);return r=>r===0||r===1?r:Pi(n(r),e,i)}const Di=t=>e=>e<=.5?t(2*e)/2:(2-t(2*(1-e)))/2,Ri=t=>e=>1-t(1-e),Ei=Ft(.33,1.53,.69,.99),Ze=Ri(Ei),Li=Di(Ze),Ii=t=>t>=1?1:(t*=2)<1?.5*Ze(t):.5*(2-Math.pow(2,-10*(t-1))),Qe=t=>1-Math.sin(Math.acos(t)),Bi=Ri(Qe),Oi=Di(Qe),Bo=Ft(.42,0,1,1),Oo=Ft(0,0,.58,1),Fi=Ft(.42,0,.58,1),Fo=t=>Array.isArray(t)&&typeof t[0]!="number",Ni=t=>Array.isArray(t)&&typeof t[0]=="number",No={linear:ct,easeIn:Bo,easeInOut:Fi,easeOut:Oo,circIn:Qe,circInOut:Oi,circOut:Bi,backIn:Ze,backInOut:Li,backOut:Ei,anticipate:Ii},jo=t=>typeof t=="string",ps=t=>{if(Ni(t)){He(t.length===4);const[e,s,i,n]=t;return Ft(e,s,i,n)}else if(jo(t))return No[t];return t},$t=["setup","read","resolveKeyframes","preUpdate","update","preRender","render","postRender"];function $o(t,e){let s=new Set,i=new Set,n=!1,r=!1;const o=new WeakSet;let a={delta:0,timestamp:0,isProcessing:!1};function l(c){o.has(c)&&(u.schedule(c),t()),c(a)}const u={schedule:(c,f=!1,d=!1)=>{const g=d&&n?s:i;return f&&o.add(c),g.add(c),c},cancel:c=>{i.delete(c),o.delete(c)},process:c=>{if(a=c,n){r=!0;return}n=!0;const f=s;s=i,i=f,s.forEach(l),s.clear(),n=!1,r&&(r=!1,u.process(c))}};return u}const zo=40;function ji(t,e){let s=!1,i=!0;const n={delta:0,timestamp:0,isProcessing:!1},r=()=>s=!0,o=$t.reduce((m,b)=>(m[b]=$o(r),m),{}),{setup:a,read:l,resolveKeyframes:u,preUpdate:c,update:f,preRender:d,render:h,postRender:g}=o,v=()=>{const m=lt.useManualTiming,b=m?n.timestamp:performance.now();s=!1,m||(n.delta=i?1e3/60:Math.max(Math.min(b-n.timestamp,zo),1)),n.timestamp=b,n.isProcessing=!0,a.process(n),l.process(n),u.process(n),c.process(n),f.process(n),d.process(n),h.process(n),g.process(n),n.isProcessing=!1,s&&e&&(i=!1,t(v))},p=()=>{s=!0,i=!0,n.isProcessing||t(v)};return{schedule:$t.reduce((m,b)=>{const A=o[b];return m[b]=(S,w=!1,x=!1)=>(s||p(),A.schedule(S,w,x)),m},{}),cancel:m=>{for(let b=0;b<$t.length;b++)o[$t[b]].cancel(m)},state:n,steps:o}}const{schedule:F,cancel:vt,state:j,steps:ae}=ji(typeof requestAnimationFrame<"u"?requestAnimationFrame:ct,!0);let Ut;function Uo(){Ut=void 0}const z={now:()=>(Ut===void 0&&z.set(j.isProcessing||lt.useManualTiming?j.timestamp:performance.now()),Ut),set:t=>{Ut=t,queueMicrotask(Uo)}},$i=t=>e=>typeof e=="string"&&e.startsWith(t),zi=$i("--"),Ko=$i("var(--"),Je=t=>Ko(t)?Wo.test(t.split("/*")[0].trim()):!1,Wo=/var\(--(?:[\w-]+\s*|[\w-]+\s*,(?:\s*[^)(\s]|\s*\((?:[^)(]|\([^)(]*\))*\))+\s*)\)$/iu;function ms(t){return typeof t!="string"?!1:t.split("/*")[0].includes("var(--")}const St={test:t=>typeof t=="number",parse:parseFloat,transform:t=>t},It={...St,transform:t=>ot(0,1,t)},zt={...St,default:1},Dt=t=>Math.round(t*1e5)/1e5,ts=/-?(?:\d+(?:\.\d+)?|\.\d+)/gu;function Xo(t){return t==null}const Ho=/^(?:#[\da-f]{3,8}|(?:rgb|hsl)a?\((?:-?[\d.]+%?[,\s]+){2}-?[\d.]+%?\s*(?:[,/]\s*)?(?:\b\d+(?:\.\d+)?|\.\d+)?%?\))$/iu,es=(t,e)=>s=>!!(typeof s=="string"&&Ho.test(s)&&s.startsWith(t)||e&&!Xo(s)&&Object.prototype.hasOwnProperty.call(s,e)),Ui=(t,e,s)=>i=>{if(typeof i!="string")return i;const[n,r,o,a]=i.match(ts);return{[t]:parseFloat(n),[e]:parseFloat(r),[s]:parseFloat(o),alpha:a!==void 0?parseFloat(a):1}},Yo=t=>ot(0,255,t),le={...St,transform:t=>Math.round(Yo(t))},ht={test:es("rgb","red"),parse:Ui("red","green","blue"),transform:({red:t,green:e,blue:s,alpha:i=1})=>"rgba("+le.transform(t)+", "+le.transform(e)+", "+le.transform(s)+", "+Dt(It.transform(i))+")"};function qo(t){let e="",s="",i="",n="";return t.length>5?(e=t.substring(1,3),s=t.substring(3,5),i=t.substring(5,7),n=t.substring(7,9)):(e=t.substring(1,2),s=t.substring(2,3),i=t.substring(3,4),n=t.substring(4,5),e+=e,s+=s,i+=i,n+=n),{red:parseInt(e,16),green:parseInt(s,16),blue:parseInt(i,16),alpha:n?parseInt(n,16)/255:1}}const Ae={test:es("#"),parse:qo,transform:ht.transform},Nt=t=>({test:e=>typeof e=="string"&&e.endsWith(t)&&e.split(" ").length===1,parse:parseFloat,transform:e=>`${e}${t}`}),st=Nt("deg"),it=Nt("%"),_=Nt("px"),Go=Nt("vh"),Zo=Nt("vw"),ys={...it,parse:t=>it.parse(t)/100,transform:t=>it.transform(t*100)},bt={test:es("hsl","hue"),parse:Ui("hue","saturation","lightness"),transform:({hue:t,saturation:e,lightness:s,alpha:i=1})=>"hsla("+Math.round(t)+", "+it.transform(Dt(e))+", "+it.transform(Dt(s))+", "+Dt(It.transform(i))+")"},I={test:t=>ht.test(t)||Ae.test(t)||bt.test(t),parse:t=>ht.test(t)?ht.parse(t):bt.test(t)?bt.parse(t):Ae.parse(t),transform:t=>typeof t=="string"?t:t.hasOwnProperty("red")?ht.transform(t):bt.transform(t),getAnimatableNone:t=>{const e=I.parse(t);return e.alpha=0,I.transform(e)}},Qo=/(?:#[\da-f]{3,8}|(?:rgb|hsl)a?\((?:-?[\d.]+%?[,\s]+){2}-?[\d.]+%?\s*(?:[,/]\s*)?(?:\b\d+(?:\.\d+)?|\.\d+)?%?\))/giu;function Jo(t){var e,s;return isNaN(t)&&typeof t=="string"&&(((e=t.match(ts))==null?void 0:e.length)||0)+(((s=t.match(Qo))==null?void 0:s.length)||0)>0}const Ki="number",Wi="color",tr="var",er="var(",gs="${}",sr=/var\s*\(\s*--(?:[\w-]+\s*|[\w-]+\s*,(?:\s*[^)(\s]|\s*\((?:[^)(]|\([^)(]*\))*\))+\s*)\)|#[\da-f]{3,8}|(?:rgb|hsl)a?\((?:-?[\d.]+%?[,\s]+){2}-?[\d.]+%?\s*(?:[,/]\s*)?(?:\b\d+(?:\.\d+)?|\.\d+)?%?\)|-?(?:\d+(?:\.\d+)?|\.\d+)/giu;function At(t){const e=t.toString(),s=[],i={color:[],number:[],var:[]},n=[];let r=0;const a=e.replace(sr,l=>(I.test(l)?(i.color.push(r),n.push(Wi),s.push(I.parse(l))):l.startsWith(er)?(i.var.push(r),n.push(tr),s.push(l)):(i.number.push(r),n.push(Ki),s.push(parseFloat(l))),++r,gs)).split(gs);return{values:s,split:a,indexes:i,types:n}}function ir(t){return At(t).values}function Xi({split:t,types:e}){const s=t.length;return i=>{let n="";for(let r=0;r<s;r++)if(n+=t[r],i[r]!==void 0){const o=e[r];o===Ki?n+=Dt(i[r]):o===Wi?n+=I.transform(i[r]):n+=i[r]}return n}}function nr(t){return Xi(At(t))}const or=t=>typeof t=="number"?0:I.test(t)?I.getAnimatableNone(t):t,rr=(t,e)=>typeof t=="number"?e!=null&&e.trim().endsWith("/")?t:0:or(t);function ar(t){const e=At(t);return Xi(e)(e.values.map((i,n)=>rr(i,e.split[n])))}const tt={test:Jo,parse:ir,createTransformer:nr,getAnimatableNone:ar};function ce(t,e,s){return s<0&&(s+=1),s>1&&(s-=1),s<1/6?t+(e-t)*6*s:s<1/2?e:s<2/3?t+(e-t)*(2/3-s)*6:t}function lr({hue:t,saturation:e,lightness:s,alpha:i}){t/=360,e/=100,s/=100;let n=0,r=0,o=0;if(!e)n=r=o=s;else{const a=s<.5?s*(1+e):s+e-s*e,l=2*s-a;n=ce(l,a,t+1/3),r=ce(l,a,t),o=ce(l,a,t-1/3)}return{red:Math.round(n*255),green:Math.round(r*255),blue:Math.round(o*255),alpha:i}}function qt(t,e){return s=>s>0?e:t}const R=(t,e,s)=>t+(e-t)*s,ue=(t,e,s)=>{const i=t*t,n=s*(e*e-i)+i;return n<0?0:Math.sqrt(n)},cr=[Ae,ht,bt],ur=t=>cr.find(e=>e.test(t));function vs(t){const e=ur(t);if(!e)return!1;let s=e.parse(t);return e===bt&&(s=lr(s)),s}const Ts=(t,e)=>{const s=vs(t),i=vs(e);if(!s||!i)return qt(t,e);const n={...s};return r=>(n.red=ue(s.red,i.red,r),n.green=ue(s.green,i.green,r),n.blue=ue(s.blue,i.blue,r),n.alpha=R(s.alpha,i.alpha,r),ht.transform(n))},ke=new Set(["none","hidden"]);function fr(t,e){return ke.has(t)?s=>s<=0?t:e:s=>s>=1?e:t}function dr(t,e){return s=>R(t,e,s)}function ss(t){return typeof t=="number"?dr:typeof t=="string"?Je(t)?qt:I.test(t)?Ts:mr:Array.isArray(t)?Hi:typeof t=="object"?I.test(t)?Ts:hr:qt}function Hi(t,e){const s=[...t],i=s.length,n=t.map((r,o)=>ss(r)(r,e[o]));return r=>{for(let o=0;o<i;o++)s[o]=n[o](r);return s}}function hr(t,e){const s={...t,...e},i={};for(const n in s)t[n]!==void 0&&e[n]!==void 0&&(i[n]=ss(t[n])(t[n],e[n]));return n=>{for(const r in i)s[r]=i[r](n);return s}}function pr(t,e){const s=[],i={color:0,var:0,number:0};for(let n=0;n<e.values.length;n++){const r=e.types[n],o=t.indexes[r][i[r]],a=t.values[o]??0;s[n]=a,i[r]++}return s}const mr=(t,e)=>{const s=tt.createTransformer(e),i=At(t),n=At(e);return i.indexes.var.length===n.indexes.var.length&&i.indexes.color.length===n.indexes.color.length&&i.indexes.number.length>=n.indexes.number.length?ke.has(t)&&!n.values.length||ke.has(e)&&!i.values.length?fr(t,e):Ye(Hi(pr(i,n),n.values),s):qt(t,e)};function Yi(t,e,s){return typeof t=="number"&&typeof e=="number"&&typeof s=="number"?R(t,e,s):ss(t)(t,e)}const yr=t=>{const e=({timestamp:s})=>t(s);return{start:(s=!0)=>F.update(e,s),stop:()=>vt(e),now:()=>j.isProcessing?j.timestamp:z.now()}},qi=(t,e,s=10)=>{let i="";const n=Math.max(Math.round(e/s),2);for(let r=0;r<n;r++)i+=Math.round(t(r/(n-1))*1e4)/1e4+", ";return`linear(${i.substring(0,i.length-2)})`},Gt=2e4;function is(t){let e=0;const s=50;let i=t.next(e);for(;!i.done&&e<Gt;)e+=s,i=t.next(e);return e>=Gt?1/0:e}function gr(t,e=100,s){const i=s({...t,keyframes:[0,e]}),n=Math.min(is(i),Gt);return{type:"keyframes",ease:r=>i.next(n*r).value/e,duration:Q(n)}}const L={stiffness:100,damping:10,mass:1,velocity:0,duration:800,bounce:.3,visualDuration:.3,restSpeed:{granular:.01,default:2},restDelta:{granular:.005,default:.5},minDuration:.01,maxDuration:10,minDamping:.05,maxDamping:1};function Se(t,e){return t*Math.sqrt(1-e*e)}const vr=12;function Tr(t,e,s){let i=s;for(let n=1;n<vr;n++)i=i-t(i)/e(i);return i}const fe=.001;function br({duration:t=L.duration,bounce:e=L.bounce,velocity:s=L.velocity,mass:i=L.mass}){let n,r,o=1-e;o=ot(L.minDamping,L.maxDamping,o),t=ot(L.minDuration,L.maxDuration,Q(t)),o<1?(n=u=>{const c=u*o,f=c*t,d=c-s,h=Se(u,o),g=Math.exp(-f);return fe-d/h*g},r=u=>{const f=u*o*t,d=f*s+s,h=Math.pow(o,2)*Math.pow(u,2)*t,g=Math.exp(-f),v=Se(Math.pow(u,2),o);return(-n(u)+fe>0?-1:1)*((d-h)*g)/v}):(n=u=>{const c=Math.exp(-u*t),f=(u-s)*t+1;return-fe+c*f},r=u=>{const c=Math.exp(-u*t),f=(s-u)*(t*t);return c*f});const a=5/t,l=Tr(n,r,a);if(t=J(t),isNaN(l))return{stiffness:L.stiffness,damping:L.damping,duration:t};{const u=Math.pow(l,2)*i;return{stiffness:u,damping:o*2*Math.sqrt(i*u),duration:t}}}const xr=["duration","bounce"],_r=["stiffness","damping","mass"];function bs(t,e){return e.some(s=>t[s]!==void 0)}function wr(t){let e={velocity:L.velocity,stiffness:L.stiffness,damping:L.damping,mass:L.mass,isResolvedFromDuration:!1,...t};if(!bs(t,_r)&&bs(t,xr))if(e.velocity=0,t.visualDuration){const s=t.visualDuration,i=2*Math.PI/(s*1.2),n=i*i,r=2*ot(.05,1,1-(t.bounce||0))*Math.sqrt(n);e={...e,mass:L.mass,stiffness:n,damping:r}}else{const s=br({...t,velocity:0});e={...e,...s,mass:L.mass},e.isResolvedFromDuration=!0}return e}function Zt(t=L.visualDuration,e=L.bounce){const s=typeof t!="object"?{visualDuration:t,keyframes:[0,1],bounce:e}:t;let{restSpeed:i,restDelta:n}=s;const r=s.keyframes[0],o=s.keyframes[s.keyframes.length-1],a={done:!1,value:r},{stiffness:l,damping:u,mass:c,duration:f,velocity:d,isResolvedFromDuration:h}=wr({...s,velocity:-Q(s.velocity||0)}),g=d||0,v=u/(2*Math.sqrt(l*c)),p=o-r,T=Q(Math.sqrt(l/c)),y=Math.abs(p)<5;i||(i=y?L.restSpeed.granular:L.restSpeed.default),n||(n=y?L.restDelta.granular:L.restDelta.default);let m,b,A,S,w,x;if(v<1)A=Se(T,v),S=(g+v*T*p)/A,m=k=>{const V=Math.exp(-v*T*k);return o-V*(S*Math.sin(A*k)+p*Math.cos(A*k))},w=v*T*S+p*A,x=v*T*p-S*A,b=k=>Math.exp(-v*T*k)*(w*Math.sin(A*k)+x*Math.cos(A*k));else if(v===1){m=V=>o-Math.exp(-T*V)*(p+(g+T*p)*V);const k=g+T*p;b=V=>Math.exp(-T*V)*(T*k*V-g)}else{const k=T*Math.sqrt(v*v-1);m=X=>{const H=Math.exp(-v*T*X),W=Math.min(k*X,300);return o-H*((g+v*T*p)*Math.sinh(W)+k*p*Math.cosh(W))/k};const V=(g+v*T*p)/k,B=v*T*V-p*k,N=v*T*p-V*k;b=X=>{const H=Math.exp(-v*T*X),W=Math.min(k*X,300);return H*(B*Math.sinh(W)+N*Math.cosh(W))}}const M={calculatedDuration:h&&f||null,velocity:k=>J(b(k)),next:k=>{if(!h&&v<1){const B=Math.exp(-v*T*k),N=Math.sin(A*k),X=Math.cos(A*k),H=o-B*(S*N+p*X),W=J(B*(w*N+x*X));return a.done=Math.abs(W)<=i&&Math.abs(o-H)<=n,a.value=a.done?o:H,a}const V=m(k);if(h)a.done=k>=f;else{const B=J(b(k));a.done=Math.abs(B)<=i&&Math.abs(o-V)<=n}return a.value=a.done?o:V,a},toString:()=>{const k=Math.min(is(M),Gt),V=qi(B=>M.next(k*B).value,k,30);return k+"ms "+V},toTransition:()=>{}};return M}Zt.applyToOptions=t=>{const e=gr(t,100,Zt);return t.ease=e.ease,t.duration=J(e.duration),t.type="keyframes",t};const Ar=5;function Gi(t,e,s){const i=Math.max(e-Ar,0);return Ci(s-t(i),e-i)}function Ve({keyframes:t,velocity:e=0,power:s=.8,timeConstant:i=325,bounceDamping:n=10,bounceStiffness:r=500,modifyTarget:o,min:a,max:l,restDelta:u=.5,restSpeed:c}){const f=t[0],d={done:!1,value:f},h=x=>a!==void 0&&x<a||l!==void 0&&x>l,g=x=>a===void 0?l:l===void 0||Math.abs(a-x)<Math.abs(l-x)?a:l;let v=s*e;const p=f+v,T=o===void 0?p:o(p);T!==p&&(v=T-f);const y=x=>-v*Math.exp(-x/i),m=x=>T+y(x),b=x=>{const M=y(x),k=m(x);d.done=Math.abs(M)<=u,d.value=d.done?T:k};let A,S;const w=x=>{h(d.value)&&(A=x,S=Zt({keyframes:[d.value,g(d.value)],velocity:Gi(m,x,d.value),damping:n,stiffness:r,restDelta:u,restSpeed:c}))};return w(0),{calculatedDuration:null,next:x=>{let M=!1;return!S&&A===void 0&&(M=!0,b(x),w(x)),A!==void 0&&x>=A?S.next(x-A):(!M&&b(x),d)}}}function kr(t,e,s){const i=[],n=s||lt.mix||Yi,r=t.length-1;for(let o=0;o<r;o++){let a=n(t[o],t[o+1]);if(e){const l=Array.isArray(e)?e[o]||ct:e;a=Ye(l,a)}i.push(a)}return i}function Sr(t,e,{clamp:s=!0,ease:i,mixer:n}={}){const r=t.length;if(He(r===e.length),r===1)return()=>e[0];if(r===2&&e[0]===e[1])return()=>e[1];const o=t[0]===t[1];t[0]>t[r-1]&&(t=[...t].reverse(),e=[...e].reverse());const a=kr(e,i,n),l=a.length,u=c=>{if(o&&c<t[0])return e[0];let f=0;if(l>1)for(;f<t.length-2&&!(c<t[f+1]);f++);const d=qe(t[f],t[f+1],c);return a[f](d)};return s?c=>u(ot(t[0],t[r-1],c)):u}function Vr(t,e){const s=t[t.length-1];for(let i=1;i<=e;i++){const n=qe(0,e,i);t.push(R(s,1,n))}}function Mr(t){const e=[0];return Vr(e,t.length-1),e}function Cr(t,e){return t.map(s=>s*e)}function Pr(t,e){return t.map(()=>e||Fi).splice(0,t.length-1)}function Rt({duration:t=300,keyframes:e,times:s,ease:i="easeInOut"}){const n=Fo(i)?i.map(ps):ps(i),r={done:!1,value:e[0]},o=Cr(s&&s.length===e.length?s:Mr(e),t),a=Sr(o,e,{ease:Array.isArray(n)?n:Pr(e,n)});return{calculatedDuration:t,next:l=>(r.value=a(l),r.done=l>=t,r)}}const Dr=t=>t!==null;function re(t,{repeat:e,repeatType:s="loop"},i,n=1){const r=t.filter(Dr),a=n<0||e&&s!=="loop"&&e%2===1?0:r.length-1;return!a||i===void 0?r[a]:i}const Rr={decay:Ve,inertia:Ve,tween:Rt,keyframes:Rt,spring:Zt};function Zi(t){typeof t.type=="string"&&(t.type=Rr[t.type])}class ns{constructor(){this.updateFinished()}get finished(){return this._finished}updateFinished(){this._finished=new Promise(e=>{this.resolve=e})}notifyFinished(){this.resolve()}then(e,s){return this.finished.then(e,s)}}const Er=t=>t/100;class Qt extends ns{constructor(e){super(),this.state="idle",this.startTime=null,this.isStopped=!1,this.currentTime=0,this.holdTime=null,this.playbackSpeed=1,this.delayState={done:!1,value:void 0},this.stop=()=>{var i,n;const{motionValue:s}=this.options;s&&s.updatedAt!==z.now()&&this.tick(z.now()),this.isStopped=!0,this.state!=="idle"&&(this.teardown(),(n=(i=this.options).onStop)==null||n.call(i))},this.options=e,this.initAnimation(),this.play(),e.autoplay===!1&&this.pause()}initAnimation(){const{options:e}=this;Zi(e);const{type:s=Rt,repeat:i=0,repeatDelay:n=0,repeatType:r,velocity:o=0}=e;let{keyframes:a}=e;const l=s||Rt;l!==Rt&&typeof a[0]!="number"&&(this.mixKeyframes=Ye(Er,Yi(a[0],a[1])),a=[0,100]);const u=l({...e,keyframes:a});r==="mirror"&&(this.mirroredGenerator=l({...e,keyframes:[...a].reverse(),velocity:-o})),u.calculatedDuration===null&&(u.calculatedDuration=is(u));const{calculatedDuration:c}=u;this.calculatedDuration=c,this.resolvedDuration=c+n,this.totalDuration=this.resolvedDuration*(i+1)-n,this.generator=u}updateTime(e){const s=Math.round(e-this.startTime)*this.playbackSpeed;this.holdTime!==null?this.currentTime=this.holdTime:this.currentTime=s}tick(e,s=!1){const{generator:i,totalDuration:n,mixKeyframes:r,mirroredGenerator:o,resolvedDuration:a,calculatedDuration:l}=this;if(this.startTime===null)return i.next(0);const{delay:u=0,keyframes:c,repeat:f,repeatType:d,repeatDelay:h,type:g,onUpdate:v,finalKeyframe:p}=this.options;this.speed>0?this.startTime=Math.min(this.startTime,e):this.speed<0&&(this.startTime=Math.min(e-n/this.speed,this.startTime)),s?this.currentTime=e:this.updateTime(e);const T=this.currentTime-u*(this.playbackSpeed>=0?1:-1),y=this.playbackSpeed>=0?T<0:T>n;this.currentTime=Math.max(T,0),this.state==="finished"&&this.holdTime===null&&(this.currentTime=n);let m=this.currentTime,b=i;if(f){const x=Math.min(this.currentTime,n)/a;let M=Math.floor(x),k=x%1;!k&&x>=1&&(k=1),k===1&&M--,M=Math.min(M,f+1),!!(M%2)&&(d==="reverse"?(k=1-k,h&&(k-=h/a)):d==="mirror"&&(b=o)),m=ot(0,1,k)*a}let A;y?(this.delayState.value=c[0],A=this.delayState):A=b.next(m),r&&!y&&(A.value=r(A.value));let{done:S}=A;!y&&l!==null&&(S=this.playbackSpeed>=0?this.currentTime>=n:this.currentTime<=0);const w=this.holdTime===null&&(this.state==="finished"||this.state==="running"&&S);return w&&g!==Ve&&(A.value=re(c,this.options,p,this.speed)),v&&v(A.value),w&&this.finish(),A}then(e,s){return this.finished.then(e,s)}get duration(){return Q(this.calculatedDuration)}get iterationDuration(){const{delay:e=0}=this.options||{};return this.duration+Q(e)}get time(){return Q(this.currentTime)}set time(e){e=J(e),this.currentTime=e,this.startTime===null||this.holdTime!==null||this.playbackSpeed===0?this.holdTime=e:this.driver&&(this.startTime=this.driver.now()-e/this.playbackSpeed),this.driver?this.driver.start(!1):(this.startTime=0,this.state="paused",this.holdTime=e,this.tick(e))}getGeneratorVelocity(){const e=this.currentTime;if(e<=0)return this.options.velocity||0;if(this.generator.velocity)return this.generator.velocity(e);const s=this.generator.next(e).value;return Gi(i=>this.generator.next(i).value,e,s)}get speed(){return this.playbackSpeed}set speed(e){const s=this.playbackSpeed!==e;s&&this.driver&&this.updateTime(z.now()),this.playbackSpeed=e,s&&this.driver&&(this.time=Q(this.currentTime))}play(){var n,r;if(this.isStopped)return;const{driver:e=yr,startTime:s}=this.options;this.driver||(this.driver=e(o=>this.tick(o))),(r=(n=this.options).onPlay)==null||r.call(n);const i=this.driver.now();this.state==="finished"?(this.updateFinished(),this.startTime=i):this.holdTime!==null?this.startTime=i-this.holdTime:this.startTime||(this.startTime=s??i),this.state==="finished"&&this.speed<0&&(this.startTime+=this.calculatedDuration),this.holdTime=null,this.state="running",this.driver.start()}pause(){this.state="paused",this.updateTime(z.now()),this.holdTime=this.currentTime}complete(){this.state!=="running"&&this.play(),this.state="finished",this.holdTime=null}finish(){var e,s;this.notifyFinished(),this.teardown(),this.state="finished",(s=(e=this.options).onComplete)==null||s.call(e)}cancel(){var e,s;this.holdTime=null,this.startTime=0,this.tick(0),this.teardown(),(s=(e=this.options).onCancel)==null||s.call(e)}teardown(){this.state="idle",this.stopDriver(),this.startTime=this.holdTime=null}stopDriver(){this.driver&&(this.driver.stop(),this.driver=void 0)}sample(e){return this.startTime=0,this.tick(e,!0)}attachTimeline(e){var s;return this.options.allowFlatten&&(this.options.type="keyframes",this.options.ease="linear",this.initAnimation()),(s=this.driver)==null||s.stop(),e.observe(this)}}function Lr(t){for(let e=1;e<t.length;e++)t[e]??(t[e]=t[e-1])}const pt=t=>t*180/Math.PI,Me=t=>{const e=pt(Math.atan2(t[1],t[0]));return Ce(e)},Ir={x:4,y:5,translateX:4,translateY:5,scaleX:0,scaleY:3,scale:t=>(Math.abs(t[0])+Math.abs(t[3]))/2,rotate:Me,rotateZ:Me,skewX:t=>pt(Math.atan(t[1])),skewY:t=>pt(Math.atan(t[2])),skew:t=>(Math.abs(t[1])+Math.abs(t[2]))/2},Ce=t=>(t=t%360,t<0&&(t+=360),t),xs=Me,_s=t=>Math.sqrt(t[0]*t[0]+t[1]*t[1]),ws=t=>Math.sqrt(t[4]*t[4]+t[5]*t[5]),Br={x:12,y:13,z:14,translateX:12,translateY:13,translateZ:14,scaleX:_s,scaleY:ws,scale:t=>(_s(t)+ws(t))/2,rotateX:t=>Ce(pt(Math.atan2(t[6],t[5]))),rotateY:t=>Ce(pt(Math.atan2(-t[2],t[0]))),rotateZ:xs,rotate:xs,skewX:t=>pt(Math.atan(t[4])),skewY:t=>pt(Math.atan(t[1])),skew:t=>(Math.abs(t[1])+Math.abs(t[4]))/2};function Pe(t){return t.includes("scale")?1:0}function De(t,e){if(!t||t==="none")return Pe(e);const s=t.match(/^matrix3d\(([-\d.e\s,]+)\)$/u);let i,n;if(s)i=Br,n=s;else{const a=t.match(/^matrix\(([-\d.e\s,]+)\)$/u);i=Ir,n=a}if(!n)return Pe(e);const r=i[e],o=n[1].split(",").map(Fr);return typeof r=="function"?r(o):o[r]}const Or=(t,e)=>{const{transform:s="none"}=getComputedStyle(t);return De(s,e)};function Fr(t){return parseFloat(t.trim())}const Vt=["transformPerspective","x","y","z","translateX","translateY","translateZ","scale","scaleX","scaleY","rotate","rotateX","rotateY","rotateZ","skew","skewX","skewY"],Mt=new Set([...Vt,"pathRotation"]),As=t=>t===St||t===_,Nr=new Set(["x","y","z"]),jr=Vt.filter(t=>!Nr.has(t));function $r(t){const e=[];return jr.forEach(s=>{const i=t.getValue(s);i!==void 0&&(e.push([s,i.get()]),i.set(s.startsWith("scale")?1:0))}),e}const at={width:({x:t},{paddingLeft:e="0",paddingRight:s="0",boxSizing:i})=>{const n=t.max-t.min;return i==="border-box"?n:n-parseFloat(e)-parseFloat(s)},height:({y:t},{paddingTop:e="0",paddingBottom:s="0",boxSizing:i})=>{const n=t.max-t.min;return i==="border-box"?n:n-parseFloat(e)-parseFloat(s)},top:(t,{top:e})=>parseFloat(e),left:(t,{left:e})=>parseFloat(e),bottom:({y:t},{top:e})=>parseFloat(e)+(t.max-t.min),right:({x:t},{left:e})=>parseFloat(e)+(t.max-t.min),x:(t,{transform:e})=>De(e,"x"),y:(t,{transform:e})=>De(e,"y")};at.translateX=at.x;at.translateY=at.y;const yt=new Set;let Re=!1,Ee=!1,Le=!1;function Qi(){if(Ee){const t=Array.from(yt).filter(i=>i.needsMeasurement),e=new Set(t.map(i=>i.element)),s=new Map;e.forEach(i=>{const n=$r(i);n.length&&(s.set(i,n),i.render())}),t.forEach(i=>i.measureInitialState()),e.forEach(i=>{i.render();const n=s.get(i);n&&n.forEach(([r,o])=>{var a;(a=i.getValue(r))==null||a.set(o)})}),t.forEach(i=>i.measureEndState()),t.forEach(i=>{i.suspendedScrollY!==void 0&&window.scrollTo(0,i.suspendedScrollY)})}Ee=!1,Re=!1,yt.forEach(t=>t.complete(Le)),yt.clear()}function Ji(){yt.forEach(t=>{t.readKeyframes(),t.needsMeasurement&&(Ee=!0)})}function zr(){Le=!0,Ji(),Qi(),Le=!1}class os{constructor(e,s,i,n,r,o=!1){this.state="pending",this.isAsync=!1,this.needsMeasurement=!1,this.unresolvedKeyframes=[...e],this.onComplete=s,this.name=i,this.motionValue=n,this.element=r,this.isAsync=o}scheduleResolve(){this.state="scheduled",this.isAsync?(yt.add(this),Re||(Re=!0,F.read(Ji),F.resolveKeyframes(Qi))):(this.readKeyframes(),this.complete())}readKeyframes(){const{unresolvedKeyframes:e,name:s,element:i,motionValue:n}=this;if(e[0]===null){const r=n==null?void 0:n.get(),o=e[e.length-1];if(r!==void 0)e[0]=r;else if(i&&s){const a=i.readValue(s,o);a!=null&&(e[0]=a)}e[0]===void 0&&(e[0]=o),n&&r===void 0&&n.set(e[0])}Lr(e)}setFinalKeyframe(){}measureInitialState(){}renderEndStyles(){}measureEndState(){}complete(e=!1){this.state="complete",this.onComplete(this.unresolvedKeyframes,this.finalKeyframe,e),yt.delete(this)}cancel(){this.state==="scheduled"&&(yt.delete(this),this.state="pending")}resume(){this.state==="pending"&&this.scheduleResolve()}}const Ur=t=>t.startsWith("--");function tn(t,e,s){Ur(e)?t.style.setProperty(e,s):t.style[e]=s}const Kr={};function en(t,e){const s=Mi(t);return()=>Kr[e]??s()}const Wr=en(()=>window.ScrollTimeline!==void 0,"scrollTimeline"),sn=en(()=>{try{document.createElement("div").animate({opacity:0},{easing:"linear(0, 1)"})}catch{return!1}return!0},"linearEasing"),Pt=([t,e,s,i])=>`cubic-bezier(${t}, ${e}, ${s}, ${i})`,ks={linear:"linear",ease:"ease",easeIn:"ease-in",easeOut:"ease-out",easeInOut:"ease-in-out",circIn:Pt([0,.65,.55,1]),circOut:Pt([.55,0,1,.45]),backIn:Pt([.31,.01,.66,-.59]),backOut:Pt([.33,1.53,.69,.99])};function nn(t,e){if(t)return typeof t=="function"?sn()?qi(t,e):"ease-out":Ni(t)?Pt(t):Array.isArray(t)?t.map(s=>nn(s,e)||ks.easeOut):ks[t]}function Xr(t,e,s,{delay:i=0,duration:n=300,repeat:r=0,repeatType:o="loop",ease:a="easeOut",times:l}={},u=void 0){const c={[e]:s};l&&(c.offset=l);const f=nn(a,n);Array.isArray(f)&&(c.easing=f);const d={delay:i,duration:n,easing:Array.isArray(f)?"linear":f,fill:"both",iterations:r+1,direction:o==="reverse"?"alternate":"normal"};return u&&(d.pseudoElement=u),t.animate(c,d)}function on(t){return typeof t=="function"&&"applyToOptions"in t}function Hr({type:t,...e}){return on(t)&&sn()?t.applyToOptions(e):(e.duration??(e.duration=300),e.ease??(e.ease="easeOut"),e)}class rn extends ns{constructor(e){if(super(),this.finishedTime=null,this.isStopped=!1,this.manualStartTime=null,!e)return;const{element:s,name:i,keyframes:n,pseudoElement:r,allowFlatten:o=!1,finalKeyframe:a,onComplete:l}=e;this.isPseudoElement=!!r,this.allowFlatten=o,this.options=e,He(typeof e.type!="string");const u=Hr(e);this.animation=Xr(s,i,n,u,r),u.autoplay===!1&&this.animation.pause(),this.animation.onfinish=()=>{if(this.finishedTime=this.time,!r){const c=re(n,this.options,a,this.speed);this.updateMotionValue&&this.updateMotionValue(c),tn(s,i,c),this.animation.cancel()}l==null||l(),this.notifyFinished()}}play(){this.isStopped||(this.manualStartTime=null,this.animation.play(),this.state==="finished"&&this.updateFinished())}pause(){this.animation.pause()}complete(){var e,s;(s=(e=this.animation).finish)==null||s.call(e)}cancel(){try{this.animation.cancel()}catch{}}stop(){if(this.isStopped)return;this.isStopped=!0;const{state:e}=this;e==="idle"||e==="finished"||(this.updateMotionValue?this.updateMotionValue():this.commitStyles(),this.isPseudoElement||this.cancel())}commitStyles(){var s,i,n;const e=(s=this.options)==null?void 0:s.element;!this.isPseudoElement&&(e!=null&&e.isConnected)&&((n=(i=this.animation).commitStyles)==null||n.call(i))}get duration(){var s,i;const e=((i=(s=this.animation.effect)==null?void 0:s.getComputedTiming)==null?void 0:i.call(s).duration)||0;return Q(Number(e))}get iterationDuration(){const{delay:e=0}=this.options||{};return this.duration+Q(e)}get time(){return Q(Number(this.animation.currentTime)||0)}set time(e){const s=this.finishedTime!==null;this.manualStartTime=null,this.finishedTime=null,this.animation.currentTime=J(e),s&&this.animation.pause()}get speed(){return this.animation.playbackRate}set speed(e){e<0&&(this.finishedTime=null),this.animation.playbackRate=e}get state(){return this.finishedTime!==null?"finished":this.animation.playState}get startTime(){return this.manualStartTime??Number(this.animation.startTime)}set startTime(e){this.manualStartTime=this.animation.startTime=e}attachTimeline({timeline:e,rangeStart:s,rangeEnd:i,observe:n}){var r;return this.allowFlatten&&((r=this.animation.effect)==null||r.updateTiming({easing:"linear"})),this.animation.onfinish=null,e&&Wr()?(this.animation.timeline=e,s&&(this.animation.rangeStart=s),i&&(this.animation.rangeEnd=i),ct):n(this)}}const an={anticipate:Ii,backInOut:Li,circInOut:Oi};function Yr(t){return t in an}function qr(t){typeof t.ease=="string"&&Yr(t.ease)&&(t.ease=an[t.ease])}const de=10;class Gr extends rn{constructor(e){qr(e),Zi(e),super(e),e.startTime!==void 0&&e.autoplay!==!1&&(this.startTime=e.startTime),this.options=e}updateMotionValue(e){const{motionValue:s,onUpdate:i,onComplete:n,element:r,...o}=this.options;if(!s)return;if(e!==void 0){s.set(e);return}const a=new Qt({...o,autoplay:!1}),l=Math.max(de,z.now()-this.startTime),u=ot(0,de,l-de),c=a.sample(l).value,{name:f}=this.options;r&&f&&tn(r,f,c),s.setWithVelocity(a.sample(Math.max(0,l-u)).value,c,u),a.stop()}}const Ss=(t,e)=>e==="zIndex"?!1:!!(typeof t=="number"||Array.isArray(t)||typeof t=="string"&&(tt.test(t)||t==="0")&&!t.startsWith("url("));function Zr(t){const e=t[0];if(t.length===1)return!0;for(let s=0;s<t.length;s++)if(t[s]!==e)return!0}function Qr(t,e,s,i){const n=t[0];if(n===null)return!1;if(e==="display"||e==="visibility")return!0;const r=t[t.length-1],o=Ss(n,e),a=Ss(r,e);return!o||!a?!1:Zr(t)||(s==="spring"||on(s))&&i}function Ie(t){t.duration=0,t.type="keyframes"}const ln=new Set(["opacity","clipPath","filter","transform"]),Jr=/^(?:oklch|oklab|lab|lch|color|color-mix|light-dark)\(/;function ta(t){for(let e=0;e<t.length;e++)if(typeof t[e]=="string"&&Jr.test(t[e]))return!0;return!1}const ea=new Set(["color","backgroundColor","outlineColor","fill","stroke","borderColor","borderTopColor","borderRightColor","borderBottomColor","borderLeftColor"]),sa=Mi(()=>Object.hasOwnProperty.call(Element.prototype,"animate"));function ia(t){var f;const{motionValue:e,name:s,repeatDelay:i,repeatType:n,damping:r,type:o,keyframes:a}=t;if(!(((f=e==null?void 0:e.owner)==null?void 0:f.current)instanceof HTMLElement))return!1;const{onUpdate:u,transformTemplate:c}=e.owner.getProps();return sa()&&s&&(ln.has(s)||ea.has(s)&&ta(a))&&(s!=="transform"||!c)&&!u&&!i&&n!=="mirror"&&r!==0&&o!=="inertia"}const na=40;class oa extends ns{constructor({autoplay:e=!0,delay:s=0,type:i="keyframes",repeat:n=0,repeatDelay:r=0,repeatType:o="loop",keyframes:a,name:l,motionValue:u,element:c,...f}){var g;super(),this.stop=()=>{var v,p;this._animation&&(this._animation.stop(),(v=this.stopTimeline)==null||v.call(this)),(p=this.keyframeResolver)==null||p.cancel()},this.createdAt=z.now();const d={autoplay:e,delay:s,type:i,repeat:n,repeatDelay:r,repeatType:o,name:l,motionValue:u,element:c,...f},h=(c==null?void 0:c.KeyframeResolver)||os;this.keyframeResolver=new h(a,(v,p,T)=>this.onKeyframesResolved(v,p,d,!T),l,u,c),(g=this.keyframeResolver)==null||g.scheduleResolve()}onKeyframesResolved(e,s,i,n){var T,y;this.keyframeResolver=void 0;const{name:r,type:o,velocity:a,delay:l,isHandoff:u,onUpdate:c}=i;this.resolvedAt=z.now();let f=!0;Qr(e,r,o,a)||(f=!1,(lt.instantAnimations||!l)&&(c==null||c(re(e,i,s))),e[0]=e[e.length-1],Ie(i),i.repeat=0);const h={startTime:n?this.resolvedAt?this.resolvedAt-this.createdAt>na?this.resolvedAt:this.createdAt:this.createdAt:void 0,finalKeyframe:s,...i,keyframes:e},g=f&&!u&&ia(h),v=(y=(T=h.motionValue)==null?void 0:T.owner)==null?void 0:y.current;let p;if(g)try{p=new Gr({...h,element:v})}catch{p=new Qt(h)}else p=new Qt(h);p.finished.then(()=>{this.notifyFinished()}).catch(ct),this.pendingTimeline&&(this.stopTimeline=p.attachTimeline(this.pendingTimeline),this.pendingTimeline=void 0),this._animation=p}get finished(){return this._animation?this.animation.finished:this._finished}then(e,s){return this.finished.finally(e).then(()=>{})}get animation(){var e;return this._animation||((e=this.keyframeResolver)==null||e.resume(),zr()),this._animation}get duration(){return this.animation.duration}get iterationDuration(){return this.animation.iterationDuration}get time(){return this.animation.time}set time(e){this.animation.time=e}get speed(){return this.animation.speed}get state(){return this.animation.state}set speed(e){this.animation.speed=e}get startTime(){return this.animation.startTime}attachTimeline(e){return this._animation?this.stopTimeline=this.animation.attachTimeline(e):this.pendingTimeline=e,()=>this.stop()}play(){this.animation.play()}pause(){this.animation.pause()}complete(){this.animation.complete()}cancel(){var e;this._animation&&this.animation.cancel(),(e=this.keyframeResolver)==null||e.cancel()}}function cn(t,e,s,i=0,n=1){const r=Array.from(t).sort((u,c)=>u.sortNodePosition(c)).indexOf(e),o=t.size,a=(o-1)*i;return typeof s=="function"?s(r,o):n===1?r*i:a-r*i}const Vs=30,ra=t=>!isNaN(parseFloat(t));class aa{constructor(e,s={}){this.canTrackVelocity=null,this.events={},this.updateAndNotify=i=>{var r;const n=z.now();if(this.updatedAt!==n&&this.setPrevFrameValue(),this.prev=this.current,this.setCurrent(i),this.current!==this.prev&&((r=this.events.change)==null||r.notify(this.current),this.dependents))for(const o of this.dependents)o.dirty()},this.hasAnimated=!1,this.setCurrent(e),this.owner=s.owner}setCurrent(e){this.current=e,this.updatedAt=z.now(),this.canTrackVelocity===null&&e!==void 0&&(this.canTrackVelocity=ra(this.current))}setPrevFrameValue(e=this.current){this.prevFrameValue=e,this.prevUpdatedAt=this.updatedAt}onChange(e){return this.on("change",e)}on(e,s){this.events[e]||(this.events[e]=new Ge);const i=this.events[e].add(s);return e==="change"?()=>{i(),F.read(()=>{this.events.change.getSize()||this.stop()})}:i}clearListeners(){for(const e in this.events)this.events[e].clear()}attach(e,s){this.passiveEffect=e,this.stopPassiveEffect=s}set(e){this.passiveEffect?this.passiveEffect(e,this.updateAndNotify):this.updateAndNotify(e)}setWithVelocity(e,s,i){this.set(s),this.prev=void 0,this.prevFrameValue=e,this.prevUpdatedAt=this.updatedAt-i}jump(e,s=!0){this.updateAndNotify(e),this.prev=e,this.prevUpdatedAt=this.prevFrameValue=void 0,s&&this.stop(),this.stopPassiveEffect&&this.stopPassiveEffect()}dirty(){var e;(e=this.events.change)==null||e.notify(this.current)}addDependent(e){this.dependents||(this.dependents=new Set),this.dependents.add(e)}removeDependent(e){this.dependents&&this.dependents.delete(e)}get(){return this.current}getPrevious(){return this.prev}getVelocity(){const e=z.now();if(!this.canTrackVelocity||this.prevFrameValue===void 0||e-this.updatedAt>Vs)return 0;const s=Math.min(this.updatedAt-this.prevUpdatedAt,Vs);return Ci(parseFloat(this.current)-parseFloat(this.prevFrameValue),s)}start(e){return this.stop(),new Promise(s=>{this.hasAnimated=!0,this.animation=e(s),this.events.animationStart&&this.events.animationStart.notify()}).then(()=>{this.events.animationComplete&&this.events.animationComplete.notify(),this.clearAnimation()})}stop(){this.animation&&(this.animation.stop(),this.events.animationCancel&&this.events.animationCancel.notify()),this.clearAnimation()}isAnimating(){return!!this.animation}clearAnimation(){delete this.animation}destroy(){var e,s;(e=this.dependents)==null||e.clear(),(s=this.events.destroy)==null||s.notify(),this.clearListeners(),this.stop(),this.stopPassiveEffect&&this.stopPassiveEffect()}}function kt(t,e){return new aa(t,e)}function un(t,e){if(t!=null&&t.inherit&&e){const{inherit:s,...i}=t;return{...e,...i}}return t}function rs(t,e){const s=(t==null?void 0:t[e])??(t==null?void 0:t.default)??t;return s!==t?un(s,t):s}const la={type:"spring",stiffness:500,damping:25,restSpeed:10},ca=t=>({type:"spring",stiffness:550,damping:t===0?2*Math.sqrt(550):30,restSpeed:10}),ua={type:"keyframes",duration:.8},fa={type:"keyframes",ease:[.25,.1,.35,1],duration:.3},da=(t,{keyframes:e})=>e.length>2?ua:Mt.has(t)?t.startsWith("scale")?ca(e[1]):la:fa,ha=new Set(["when","delay","delayChildren","staggerChildren","staggerDirection","repeat","repeatType","repeatDelay","from","elapsed"]);function pa(t){for(const e in t)if(!ha.has(e))return!0;return!1}const fn=(t,e,s,i={},n,r)=>o=>{const a=rs(i,t)||{},l=a.delay||i.delay||0;let{elapsed:u=0}=i;u=u-J(l);const c={keyframes:Array.isArray(s)?s:[null,s],ease:"easeOut",velocity:e.getVelocity(),...a,delay:-u,onUpdate:d=>{e.set(d),a.onUpdate&&a.onUpdate(d)},onComplete:()=>{o(),a.onComplete&&a.onComplete()},name:t,motionValue:e,element:r?void 0:n};pa(a)||Object.assign(c,da(t,c)),c.duration&&(c.duration=J(c.duration)),c.repeatDelay&&(c.repeatDelay=J(c.repeatDelay)),c.from!==void 0&&(c.keyframes[0]=c.from);let f=!1;if((c.type===!1||c.duration===0&&!c.repeatDelay)&&(Ie(c),c.delay===0&&(f=!0)),(lt.instantAnimations||lt.skipAnimations||n!=null&&n.shouldSkipAnimations||a.skipAnimations)&&(f=!0,Ie(c),c.delay=0),c.allowFlatten=!a.type&&!a.ease,f&&!r&&e.get()!==void 0){const d=re(c.keyframes,a);if(d!==void 0){F.update(()=>{c.onUpdate(d),c.onComplete()});return}}return a.isSync?new Qt(c):new oa(c)},ma=/^var\(--(?:([\w-]+)|([\w-]+), ?([a-zA-Z\d ()%#.,-]+))\)/u;function ya(t){const e=ma.exec(t);if(!e)return[,];const[,s,i,n]=e;return[`--${s??i}`,n]}function dn(t,e,s=1){const[i,n]=ya(t);if(!i)return;const r=window.getComputedStyle(e).getPropertyValue(i);if(r){const o=r.trim();return ki(o)?parseFloat(o):o}return Je(n)?dn(n,e,s+1):n}function Ms(t){const e=[{},{}];return t==null||t.values.forEach((s,i)=>{e[0][i]=s.get(),e[1][i]=s.getVelocity()}),e}function hn(t,e,s,i){if(typeof e=="function"){const[n,r]=Ms(i);e=e(s!==void 0?s:t.custom,n,r)}if(typeof e=="string"&&(e=t.variants&&t.variants[e]),typeof e=="function"){const[n,r]=Ms(i);e=e(s!==void 0?s:t.custom,n,r)}return e}function wt(t,e,s){const i=t.getProps();return hn(i,e,s!==void 0?s:i.custom,t)}const pn=new Set(["width","height","top","left","right","bottom",...Vt]),Be=t=>Array.isArray(t);function ga(t,e,s){t.hasValue(e)?t.getValue(e).set(s):t.addValue(e,kt(s))}function va(t){return Be(t)?t[t.length-1]||0:t}function Ta(t,e){const s=wt(t,e);let{transitionEnd:i={},transition:n={},...r}=s||{};r={...r,...i};for(const o in r){const a=va(r[o]);ga(t,o,a)}}const K=t=>!!(t&&t.getVelocity);function ba(t){return!!(K(t)&&t.add)}function xa(t,e){const s=t.getValue("willChange");if(ba(s))return s.add(e);if(!s&&lt.WillChange){const i=new lt.WillChange("auto");t.addValue("willChange",i),i.add(e)}}function as(t){return t.replace(/([A-Z])/g,e=>`-${e.toLowerCase()}`)}const _a="framerAppearId",wa="data-"+as(_a);function mn(t){return t.props[wa]}function Aa({protectedKeys:t,needsAnimating:e},s){const i=t.hasOwnProperty(s)&&e[s]!==!0;return e[s]=!1,i}function yn(t,e,{delay:s=0,transitionOverride:i,type:n}={}){let{transition:r,transitionEnd:o,...a}=e;const l=t.getDefaultTransition();r=r?un(r,l):l;const u=r==null?void 0:r.reduceMotion,c=r==null?void 0:r.skipAnimations;i&&(r=i);const f=[],d=n&&t.animationState&&t.animationState.getState()[n],h=r==null?void 0:r.path;h&&h.animateVisualElement(t,a,r,s,f);for(const g in a){const v=t.getValue(g,t.latestValues[g]??null),p=a[g];if(p===void 0||d&&Aa(d,g))continue;const T={delay:s,...rs(r||{},g)};c&&(T.skipAnimations=!0);const y=v.get();if(y!==void 0&&!v.isAnimating()&&!Array.isArray(p)&&p===y&&!T.velocity){F.update(()=>v.set(p));continue}let m=!1;if(window.MotionHandoffAnimation){const S=mn(t);if(S){const w=window.MotionHandoffAnimation(S,g,F);w!==null&&(T.startTime=w,m=!0)}}xa(t,g);const b=u??t.shouldReduceMotion;v.start(fn(g,v,p,b&&pn.has(g)?{type:!1}:T,t,m));const A=v.animation;A&&f.push(A)}if(o){const g=()=>F.update(()=>{o&&Ta(t,o)});f.length?Promise.all(f).then(g):g()}return f}function Oe(t,e,s={}){var l;const i=wt(t,e,s.type==="exit"?(l=t.presenceContext)==null?void 0:l.custom:void 0);let{transition:n=t.getDefaultTransition()||{}}=i||{};s.transitionOverride&&(n=s.transitionOverride);const r=i?()=>Promise.all(yn(t,i,s)):()=>Promise.resolve(),o=t.variantChildren&&t.variantChildren.size?(u=0)=>{const{delayChildren:c=0,staggerChildren:f,staggerDirection:d}=n;return ka(t,e,u,c,f,d,s)}:()=>Promise.resolve(),{when:a}=n;if(a){const[u,c]=a==="beforeChildren"?[r,o]:[o,r];return u().then(()=>c())}else return Promise.all([r(),o(s.delay)])}function ka(t,e,s=0,i=0,n=0,r=1,o){const a=[];for(const l of t.variantChildren)l.notify("AnimationStart",e),a.push(Oe(l,e,{...o,delay:s+(typeof i=="function"?0:i)+cn(t.variantChildren,l,i,n,r)}).then(()=>l.notify("AnimationComplete",e)));return Promise.all(a)}function Sa(t,e,s={}){t.notify("AnimationStart",e);let i;if(Array.isArray(e)){const n=e.map(r=>Oe(t,r,s));i=Promise.all(n)}else if(typeof e=="string")i=Oe(t,e,s);else{const n=typeof e=="function"?wt(t,e,s.custom):e;i=Promise.all(yn(t,n,s))}return i.then(()=>{t.notify("AnimationComplete",e)})}const Va={test:t=>t==="auto",parse:t=>t},gn=t=>e=>e.test(t),vn=[St,_,it,st,Zo,Go,Va],Cs=t=>vn.find(gn(t));function Ma(t){return typeof t=="number"?t===0:t!==null?t==="none"||t==="0"||Vi(t):!0}const Ca=new Set(["brightness","contrast","saturate","opacity"]);function Pa(t){const[e,s]=t.slice(0,-1).split("(");if(e==="drop-shadow")return t;const[i]=s.match(ts)||[];if(!i)return t;const n=s.replace(i,"");let r=Ca.has(e)?1:0;return i!==s&&(r*=100),e+"("+r+n+")"}const Da=/\b([a-z-]*)\(.*?\)/gu,Fe={...tt,getAnimatableNone:t=>{const e=t.match(Da);return e?e.map(Pa).join(" "):t}},Ne={...tt,getAnimatableNone:t=>{const e=tt.parse(t);return tt.createTransformer(t)(e.map(i=>typeof i=="number"?0:typeof i=="object"?{...i,alpha:1}:i))}},Ps={...St,transform:Math.round},Ra={rotate:st,pathRotation:st,rotateX:st,rotateY:st,rotateZ:st,scale:zt,scaleX:zt,scaleY:zt,scaleZ:zt,skew:st,skewX:st,skewY:st,distance:_,translateX:_,translateY:_,translateZ:_,x:_,y:_,z:_,perspective:_,transformPerspective:_,opacity:It,originX:ys,originY:ys,originZ:_},Jt={borderWidth:_,borderTopWidth:_,borderRightWidth:_,borderBottomWidth:_,borderLeftWidth:_,borderRadius:_,borderTopLeftRadius:_,borderTopRightRadius:_,borderBottomRightRadius:_,borderBottomLeftRadius:_,width:_,maxWidth:_,height:_,maxHeight:_,top:_,right:_,bottom:_,left:_,inset:_,insetBlock:_,insetBlockStart:_,insetBlockEnd:_,insetInline:_,insetInlineStart:_,insetInlineEnd:_,padding:_,paddingTop:_,paddingRight:_,paddingBottom:_,paddingLeft:_,paddingBlock:_,paddingBlockStart:_,paddingBlockEnd:_,paddingInline:_,paddingInlineStart:_,paddingInlineEnd:_,margin:_,marginTop:_,marginRight:_,marginBottom:_,marginLeft:_,marginBlock:_,marginBlockStart:_,marginBlockEnd:_,marginInline:_,marginInlineStart:_,marginInlineEnd:_,fontSize:_,backgroundPositionX:_,backgroundPositionY:_,...Ra,zIndex:Ps,fillOpacity:It,strokeOpacity:It,numOctaves:Ps},Ea={...Jt,color:I,backgroundColor:I,outlineColor:I,fill:I,stroke:I,borderColor:I,borderTopColor:I,borderRightColor:I,borderBottomColor:I,borderLeftColor:I,filter:Fe,WebkitFilter:Fe,mask:Ne,WebkitMask:Ne},Tn=t=>Ea[t],La=new Set([Fe,Ne]);function bn(t,e){let s=Tn(t);return La.has(s)||(s=tt),s.getAnimatableNone?s.getAnimatableNone(e):void 0}const Ia=new Set(["auto","none","0"]);function Ba(t,e,s){let i=0,n;for(;i<t.length&&!n;){const r=t[i];typeof r=="string"&&!Ia.has(r)&&At(r).values.length&&(n=t[i]),i++}if(n&&s)for(const r of e)t[r]=bn(s,n)}class Oa extends os{constructor(e,s,i,n,r){super(e,s,i,n,r,!0)}readKeyframes(){const{unresolvedKeyframes:e,element:s,name:i}=this;if(!s||!s.current)return;super.readKeyframes();for(let c=0;c<e.length;c++){let f=e[c];if(typeof f=="string"&&(f=f.trim(),Je(f))){const d=dn(f,s.current);d!==void 0&&(e[c]=d),c===e.length-1&&(this.finalKeyframe=f)}}if(this.resolveNoneKeyframes(),!pn.has(i)||e.length!==2)return;const[n,r]=e,o=Cs(n),a=Cs(r),l=ms(n),u=ms(r);if(l!==u&&at[i]){this.needsMeasurement=!0;return}if(o!==a)if(As(o)&&As(a))for(let c=0;c<e.length;c++){const f=e[c];typeof f=="string"&&(e[c]=parseFloat(f))}else at[i]&&(this.needsMeasurement=!0)}resolveNoneKeyframes(){const{unresolvedKeyframes:e,name:s}=this,i=[];for(let n=0;n<e.length;n++)(e[n]===null||Ma(e[n]))&&i.push(n);i.length&&Ba(e,i,s)}measureInitialState(){const{element:e,unresolvedKeyframes:s,name:i}=this;if(!e||!e.current)return;i==="height"&&(this.suspendedScrollY=window.pageYOffset),this.measuredOrigin=at[i](e.measureViewportBox(),window.getComputedStyle(e.current)),s[0]=this.measuredOrigin;const n=s[s.length-1];n!==void 0&&e.getValue(i,n).jump(n,!1)}measureEndState(){var a;const{element:e,name:s,unresolvedKeyframes:i}=this;if(!e||!e.current)return;const n=e.getValue(s);n&&n.jump(this.measuredOrigin,!1);const r=i.length-1,o=i[r];i[r]=at[s](e.measureViewportBox(),window.getComputedStyle(e.current)),o!==null&&this.finalKeyframe===void 0&&(this.finalKeyframe=o),(a=this.removedTransforms)!=null&&a.length&&this.removedTransforms.forEach(([l,u])=>{e.getValue(l).set(u)}),this.resolveNoneKeyframes()}}function xn(t,e,s){if(t==null)return[];if(t instanceof EventTarget)return[t];if(typeof t=="string"){let i=document;const n=(s==null?void 0:s[t])??i.querySelectorAll(t);return n?Array.from(n):[]}return Array.from(t).filter(i=>i!=null)}const je=(t,e)=>e&&typeof t=="number"?e.transform(t):t;function Fa(t){return Si(t)&&"offsetHeight"in t&&!("ownerSVGElement"in t)}const{schedule:_n}=ji(queueMicrotask,!1),Z={x:!1,y:!1};function wn(){return Z.x||Z.y}function Nc(t){return t==="x"||t==="y"?Z[t]?null:(Z[t]=!0,()=>{Z[t]=!1}):Z.x||Z.y?null:(Z.x=Z.y=!0,()=>{Z.x=Z.y=!1})}function An(t,e){const s=xn(t),i=new AbortController,n={passive:!0,...e,signal:i.signal};return[s,n,()=>i.abort()]}function Na(t){return!(t.pointerType==="touch"||wn())}function jc(t,e,s={}){const[i,n,r]=An(t,s);return i.forEach(o=>{let a=!1,l=!1,u;const c=()=>{o.removeEventListener("pointerleave",g)},f=p=>{u&&(u(p),u=void 0),c()},d=p=>{a=!1,window.removeEventListener("pointerup",d),window.removeEventListener("pointercancel",d),l&&(l=!1,f(p))},h=()=>{a=!0,window.addEventListener("pointerup",d,n),window.addEventListener("pointercancel",d,n)},g=p=>{if(p.pointerType!=="touch"){if(a){l=!0;return}f(p)}},v=p=>{if(!Na(p))return;l=!1;const T=e(o,p);typeof T=="function"&&(u=T,o.addEventListener("pointerleave",g,n))};o.addEventListener("pointerenter",v,n),o.addEventListener("pointerdown",h,n)}),r}const kn=(t,e)=>e?t===e?!0:kn(t,e.parentElement):!1,ja=t=>t.pointerType==="mouse"?typeof t.button!="number"||t.button<=0:t.isPrimary!==!1,$a=new Set(["BUTTON","INPUT","SELECT","TEXTAREA","A"]);function za(t){return $a.has(t.tagName)||t.isContentEditable===!0}const Ua=new Set(["INPUT","SELECT","TEXTAREA"]);function $c(t){return Ua.has(t.tagName)||t.isContentEditable===!0}const Kt=new WeakSet;function Ds(t){return e=>{e.key==="Enter"&&t(e)}}function he(t,e){t.dispatchEvent(new PointerEvent("pointer"+e,{isPrimary:!0,bubbles:!0}))}const Ka=(t,e)=>{const s=t.currentTarget;if(!s)return;const i=Ds(()=>{if(Kt.has(s))return;he(s,"down");const n=Ds(()=>{he(s,"up")}),r=()=>he(s,"cancel");s.addEventListener("keyup",n,e),s.addEventListener("blur",r,e)});s.addEventListener("keydown",i,e),s.addEventListener("blur",()=>s.removeEventListener("keydown",i),e)};function Rs(t){return ja(t)&&!wn()}const Es=new WeakSet;function zc(t,e,s={}){const[i,n,r]=An(t,s),o=a=>{const l=a.currentTarget;if(!Rs(a)||Es.has(a))return;Kt.add(l),s.stopPropagation&&Es.add(a);const u=e(l,a),c=(h,g)=>{window.removeEventListener("pointerup",f),window.removeEventListener("pointercancel",d),Kt.has(l)&&Kt.delete(l),Rs(h)&&typeof u=="function"&&u(h,{success:g})},f=h=>{c(h,l===window||l===document||s.useGlobalTarget||kn(l,h.target))},d=h=>{c(h,!1)};window.addEventListener("pointerup",f,n),window.addEventListener("pointercancel",d,n)};return i.forEach(a=>{(s.useGlobalTarget?window:a).addEventListener("pointerdown",o,n),Fa(a)&&(a.addEventListener("focus",u=>Ka(u,n)),!za(a)&&!a.hasAttribute("tabindex")&&(a.tabIndex=0))}),r}function ls(t){return Si(t)&&"ownerSVGElement"in t}const Wt=new WeakMap;let rt;const Sn=(t,e,s)=>(i,n)=>n&&n[0]?n[0][t+"Size"]:ls(i)&&"getBBox"in i?i.getBBox()[e]:i[s],Wa=Sn("inline","width","offsetWidth"),Xa=Sn("block","height","offsetHeight");function Ha({target:t,borderBoxSize:e}){var s;(s=Wt.get(t))==null||s.forEach(i=>{i(t,{get width(){return Wa(t,e)},get height(){return Xa(t,e)}})})}function Ya(t){t.forEach(Ha)}function qa(){typeof ResizeObserver>"u"||(rt=new ResizeObserver(Ya))}function Ga(t,e){rt||qa();const s=xn(t);return s.forEach(i=>{let n=Wt.get(i);n||(n=new Set,Wt.set(i,n)),n.add(e),rt==null||rt.observe(i)}),()=>{s.forEach(i=>{const n=Wt.get(i);n==null||n.delete(e),n!=null&&n.size||rt==null||rt.unobserve(i)})}}const Xt=new Set;let xt;function Za(){xt=()=>{const t={get width(){return window.innerWidth},get height(){return window.innerHeight}};Xt.forEach(e=>e(t))},window.addEventListener("resize",xt)}function Qa(t){return Xt.add(t),xt||Za(),()=>{Xt.delete(t),!Xt.size&&typeof xt=="function"&&(window.removeEventListener("resize",xt),xt=void 0)}}function Uc(t,e){return typeof t=="function"?Qa(t):Ga(t,e)}function Ja(t){return ls(t)&&t.tagName==="svg"}const tl=[...vn,I,tt],el=t=>tl.find(gn(t)),Ls=()=>({translate:0,scale:1,origin:0,originPoint:0}),_t=()=>({x:Ls(),y:Ls()}),Is=()=>({min:0,max:0}),O=()=>({x:Is(),y:Is()}),sl=new WeakMap;function Vn(t){return t!==null&&typeof t=="object"&&typeof t.start=="function"}function cs(t){return typeof t=="string"||Array.isArray(t)}const us=["animate","whileInView","whileFocus","whileHover","whileTap","whileDrag","exit"],fs=["initial",...us];function Mn(t){return Vn(t.animate)||fs.some(e=>cs(t[e]))}function il(t){return!!(Mn(t)||t.variants)}function nl(t,e,s){for(const i in e){const n=e[i],r=s[i];if(K(n))t.addValue(i,n);else if(K(r))t.addValue(i,kt(n,{owner:t}));else if(r!==n)if(t.hasValue(i)){const o=t.getValue(i);o.liveStyle===!0?o.jump(n):o.hasAnimated||o.set(n)}else{const o=t.getStaticValue(i);t.addValue(i,kt(o!==void 0?o:n,{owner:t}))}}for(const i in s)e[i]===void 0&&t.removeValue(i);return e}const $e={current:null},Cn={current:!1},ol=typeof window<"u";function rl(){if(Cn.current=!0,!!ol)if(window.matchMedia){const t=window.matchMedia("(prefers-reduced-motion)"),e=()=>$e.current=t.matches;t.addEventListener("change",e),e()}else $e.current=!1}const Bs=["AnimationStart","AnimationComplete","Update","BeforeLayoutMeasure","LayoutMeasure","LayoutAnimationStart","LayoutAnimationComplete"];let te={};function Kc(t){te=t}function Wc(){return te}class al{scrapeMotionValuesFromProps(e,s,i){return{}}constructor({parent:e,props:s,presenceContext:i,reducedMotionConfig:n,skipAnimations:r,blockInitialAnimation:o,visualState:a},l={}){this.current=null,this.children=new Set,this.isVariantNode=!1,this.isControllingVariants=!1,this.shouldReduceMotion=null,this.shouldSkipAnimations=!1,this.values=new Map,this.KeyframeResolver=os,this.features={},this.valueSubscriptions=new Map,this.prevMotionValues={},this.hasBeenMounted=!1,this.events={},this.propEventSubscriptions={},this.notifyUpdate=()=>this.notify("Update",this.latestValues),this.render=()=>{this.current&&(this.triggerBuild(),this.renderInstance(this.current,this.renderState,this.props.style,this.projection))},this.renderScheduledAt=0,this.scheduleRender=()=>{const h=z.now();this.renderScheduledAt<h&&(this.renderScheduledAt=h,F.render(this.render,!1,!0))};const{latestValues:u,renderState:c}=a;this.latestValues=u,this.baseTarget={...u},this.initialValues=s.initial?{...u}:{},this.renderState=c,this.parent=e,this.props=s,this.presenceContext=i,this.depth=e?e.depth+1:0,this.reducedMotionConfig=n,this.skipAnimationsConfig=r,this.options=l,this.blockInitialAnimation=!!o,this.isControllingVariants=Mn(s),this.isVariantNode=il(s),this.isVariantNode&&(this.variantChildren=new Set),this.manuallyAnimateOnMount=!!(e&&e.current);const{willChange:f,...d}=this.scrapeMotionValuesFromProps(s,{},this);for(const h in d){const g=d[h];u[h]!==void 0&&K(g)&&g.set(u[h])}}mount(e){var s,i;if(this.hasBeenMounted)for(const n in this.initialValues)(s=this.values.get(n))==null||s.jump(this.initialValues[n]),this.latestValues[n]=this.initialValues[n];this.current=e,sl.set(e,this),this.projection&&!this.projection.instance&&this.projection.mount(e),this.parent&&this.isVariantNode&&!this.isControllingVariants&&(this.removeFromVariantTree=this.parent.addVariantChild(this)),this.values.forEach((n,r)=>this.bindToMotionValue(r,n)),this.reducedMotionConfig==="never"?this.shouldReduceMotion=!1:this.reducedMotionConfig==="always"?this.shouldReduceMotion=!0:(Cn.current||rl(),this.shouldReduceMotion=$e.current),this.shouldSkipAnimations=this.skipAnimationsConfig??!1,(i=this.parent)==null||i.addChild(this),this.update(this.props,this.presenceContext),this.hasBeenMounted=!0}unmount(){var e;this.projection&&this.projection.unmount(),vt(this.notifyUpdate),vt(this.render),this.valueSubscriptions.forEach(s=>s()),this.valueSubscriptions.clear(),this.removeFromVariantTree&&this.removeFromVariantTree(),(e=this.parent)==null||e.removeChild(this);for(const s in this.events)this.events[s].clear();for(const s in this.features){const i=this.features[s];i&&(i.unmount(),i.isMounted=!1)}this.current=null}addChild(e){this.children.add(e),this.enteringChildren??(this.enteringChildren=new Set),this.enteringChildren.add(e)}removeChild(e){this.children.delete(e),this.enteringChildren&&this.enteringChildren.delete(e)}bindToMotionValue(e,s){if(this.valueSubscriptions.has(e)&&this.valueSubscriptions.get(e)(),s.accelerate&&ln.has(e)&&this.current instanceof HTMLElement){const{factory:o,keyframes:a,times:l,ease:u,duration:c}=s.accelerate,f=new rn({element:this.current,name:e,keyframes:a,times:l,ease:u,duration:J(c)}),d=o(f);this.valueSubscriptions.set(e,()=>{d(),f.cancel()});return}const i=Mt.has(e);i&&this.onBindTransform&&this.onBindTransform();const n=s.on("change",o=>{this.latestValues[e]=o,this.props.onUpdate&&F.preRender(this.notifyUpdate),i&&this.projection&&(this.projection.isTransformDirty=!0),this.scheduleRender()});let r;typeof window<"u"&&window.MotionCheckAppearSync&&(r=window.MotionCheckAppearSync(this,e,s)),this.valueSubscriptions.set(e,()=>{n(),r&&r()})}sortNodePosition(e){return!this.current||!this.sortInstanceNodePosition||this.type!==e.type?0:this.sortInstanceNodePosition(this.current,e.current)}updateFeatures(){let e="animation";for(e in te){const s=te[e];if(!s)continue;const{isEnabled:i,Feature:n}=s;if(!this.features[e]&&n&&i(this.props)&&(this.features[e]=new n(this)),this.features[e]){const r=this.features[e];r.isMounted?r.update():(r.mount(),r.isMounted=!0)}}}triggerBuild(){this.build(this.renderState,this.latestValues,this.props)}measureViewportBox(){return this.current?this.measureInstanceViewportBox(this.current,this.props):O()}getStaticValue(e){return this.latestValues[e]}setStaticValue(e,s){this.latestValues[e]=s}update(e,s){(e.transformTemplate||this.props.transformTemplate)&&this.scheduleRender(),this.prevProps=this.props,this.props=e,this.prevPresenceContext=this.presenceContext,this.presenceContext=s;for(let i=0;i<Bs.length;i++){const n=Bs[i];this.propEventSubscriptions[n]&&(this.propEventSubscriptions[n](),delete this.propEventSubscriptions[n]);const r="on"+n,o=e[r];o&&(this.propEventSubscriptions[n]=this.on(n,o))}this.prevMotionValues=nl(this,this.scrapeMotionValuesFromProps(e,this.prevProps||{},this),this.prevMotionValues),this.handleChildMotionValue&&this.handleChildMotionValue()}getProps(){return this.props}getVariant(e){return this.props.variants?this.props.variants[e]:void 0}getDefaultTransition(){return this.props.transition}getTransformPagePoint(){return this.props.transformPagePoint}getClosestVariantNode(){return this.isVariantNode?this:this.parent?this.parent.getClosestVariantNode():void 0}addVariantChild(e){const s=this.getClosestVariantNode();if(s)return s.variantChildren&&s.variantChildren.add(e),()=>s.variantChildren.delete(e)}addValue(e,s){const i=this.values.get(e);s!==i&&(i&&this.removeValue(e),this.bindToMotionValue(e,s),this.values.set(e,s),this.latestValues[e]=s.get())}removeValue(e){this.values.delete(e);const s=this.valueSubscriptions.get(e);s&&(s(),this.valueSubscriptions.delete(e)),delete this.latestValues[e],this.removeValueFromRenderState(e,this.renderState)}hasValue(e){return this.values.has(e)}getValue(e,s){if(this.props.values&&this.props.values[e])return this.props.values[e];let i=this.values.get(e);return i===void 0&&s!==void 0&&(i=kt(s===null?void 0:s,{owner:this}),this.addValue(e,i)),i}readValue(e,s){let i=this.latestValues[e]!==void 0||!this.current?this.latestValues[e]:this.getBaseTargetFromProps(this.props,e)??this.readValueFromInstance(this.current,e,this.options);return i!=null&&(typeof i=="string"&&(ki(i)||Vi(i))?i=parseFloat(i):!el(i)&&tt.test(s)&&(i=bn(e,s)),this.setBaseTarget(e,K(i)?i.get():i)),K(i)?i.get():i}setBaseTarget(e,s){this.baseTarget[e]=s}getBaseTarget(e){var r;const{initial:s}=this.props;let i;if(typeof s=="string"||typeof s=="object"){const o=hn(this.props,s,(r=this.presenceContext)==null?void 0:r.custom);o&&(i=o[e])}if(s&&i!==void 0)return i;const n=this.getBaseTargetFromProps(this.props,e);return n!==void 0&&!K(n)?n:this.initialValues[e]!==void 0&&i===void 0?void 0:this.baseTarget[e]}on(e,s){return this.events[e]||(this.events[e]=new Ge),this.events[e].add(s)}notify(e,...s){this.events[e]&&this.events[e].notify(...s)}scheduleRenderMicrotask(){_n.render(this.render)}}class Pn extends al{constructor(){super(...arguments),this.KeyframeResolver=Oa}sortInstanceNodePosition(e,s){return e.compareDocumentPosition(s)&2?1:-1}getBaseTargetFromProps(e,s){const i=e.style;return i?i[s]:void 0}removeValueFromRenderState(e,{vars:s,style:i}){delete s[e],delete i[e]}handleChildMotionValue(){this.childSubscription&&(this.childSubscription(),delete this.childSubscription);const{children:e}=this.props;K(e)&&(this.childSubscription=e.on("change",s=>{this.current&&(this.current.textContent=`${s}`)}))}}class Xc{constructor(e){this.isMounted=!1,this.node=e}update(){}}function ll({top:t,left:e,right:s,bottom:i}){return{x:{min:e,max:s},y:{min:t,max:i}}}function Hc({x:t,y:e}){return{top:e.min,right:t.max,bottom:e.max,left:t.min}}function cl(t,e){if(!e)return t;const s=e({x:t.left,y:t.top}),i=e({x:t.right,y:t.bottom});return{top:s.y,left:s.x,bottom:i.y,right:i.x}}function pe(t){return t===void 0||t===1}function ze({scale:t,scaleX:e,scaleY:s}){return!pe(t)||!pe(e)||!pe(s)}function dt(t){return ze(t)||Dn(t)||t.z||t.rotate||t.rotateX||t.rotateY||t.skewX||t.skewY}function Dn(t){return Os(t.x)||Os(t.y)}function Os(t){return t&&t!=="0%"}function ee(t,e,s){const i=t-s,n=e*i;return s+n}function Fs(t,e,s,i,n){return n!==void 0&&(t=ee(t,n,i)),ee(t,s,i)+e}function Ue(t,e=0,s=1,i,n){t.min=Fs(t.min,e,s,i,n),t.max=Fs(t.max,e,s,i,n)}function Rn(t,{x:e,y:s}){Ue(t.x,e.translate,e.scale,e.originPoint),Ue(t.y,s.translate,s.scale,s.originPoint)}const Ns=.999999999999,js=1.0000000000001;function ul(t,e,s,i=!1){var a;const n=s.length;if(!n)return;e.x=e.y=1;let r,o;for(let l=0;l<n;l++){r=s[l],o=r.projectionDelta;const{visualElement:u}=r.options;u&&u.props.style&&u.props.style.display==="contents"||(i&&r.options.layoutScroll&&r.scroll&&r!==r.root&&(et(t.x,-r.scroll.offset.x),et(t.y,-r.scroll.offset.y)),o&&(e.x*=o.x.scale,e.y*=o.y.scale,Rn(t,o)),i&&dt(r.latestValues)&&Ht(t,r.latestValues,(a=r.layout)==null?void 0:a.layoutBox))}e.x<js&&e.x>Ns&&(e.x=1),e.y<js&&e.y>Ns&&(e.y=1)}function et(t,e){t.min+=e,t.max+=e}function $s(t,e,s,i,n=.5){const r=R(t.min,t.max,n);Ue(t,e,s,r,i)}function zs(t,e){return typeof t=="string"?parseFloat(t)/100*(e.max-e.min):t}function Ht(t,e,s){const i=s??t;$s(t.x,zs(e.x,i.x),e.scaleX,e.scale,e.originX),$s(t.y,zs(e.y,i.y),e.scaleY,e.scale,e.originY)}function En(t,e){return ll(cl(t.getBoundingClientRect(),e))}function Yc(t,e,s){const i=En(t,s),{scroll:n}=e;return n&&(et(i.x,n.offset.x),et(i.y,n.offset.y)),i}const fl={x:"translateX",y:"translateY",z:"translateZ",transformPerspective:"perspective"},dl=Vt.length;function hl(t,e,s){let i="",n=!0;for(let o=0;o<dl;o++){const a=Vt[o],l=t[a];if(l===void 0)continue;let u=!0;if(typeof l=="number")u=l===(a.startsWith("scale")?1:0);else{const c=parseFloat(l);u=a.startsWith("scale")?c===1:c===0}if(!u||s){const c=je(l,Jt[a]);if(!u){n=!1;const f=fl[a]||a;i+=`${f}(${c}) `}s&&(e[a]=c)}}const r=t.pathRotation;return r&&(n=!1,i+=`rotate(${je(r,Jt.pathRotation)}) `),i=i.trim(),s?i=s(e,n?"":i):n&&(i="none"),i}function Ln(t,e,s){const{style:i,vars:n,transformOrigin:r}=t;let o=!1,a=!1;for(const l in e){const u=e[l];if(Mt.has(l)){o=!0;continue}else if(zi(l)){n[l]=u;continue}else{const c=je(u,Jt[l]);l.startsWith("origin")?(a=!0,r[l]=c):i[l]=c}}if(e.transform||(o||s?i.transform=hl(e,t.transform,s):i.transform&&(i.transform="none")),a){const{originX:l="50%",originY:u="50%",originZ:c=0}=r;i.transformOrigin=`${l} ${u} ${c}`}}function In(t,{style:e,vars:s},i,n){const r=t.style;let o;for(o in e)r[o]=e[o];n==null||n.applyProjectionStyles(r,i);for(o in s)r.setProperty(o,s[o])}function Us(t,e){return e.max===e.min?0:t/(e.max-e.min)*100}const Ct={correct:(t,e)=>{if(!e.target)return t;if(typeof t=="string")if(_.test(t))t=parseFloat(t);else return t;const s=Us(t,e.target.x),i=Us(t,e.target.y);return`${s}% ${i}%`}},pl={correct:(t,{treeScale:e,projectionDelta:s})=>{const i=t,n=tt.parse(t);if(n.length>5)return i;const r=tt.createTransformer(t),o=typeof n[0]!="number"?1:0,a=s.x.scale*e.x,l=s.y.scale*e.y;n[0+o]/=a,n[1+o]/=l;const u=R(a,l,.5);return typeof n[2+o]=="number"&&(n[2+o]/=u),typeof n[3+o]=="number"&&(n[3+o]/=u),r(n)}},Ke={borderRadius:{...Ct,applyTo:["borderTopLeftRadius","borderTopRightRadius","borderBottomLeftRadius","borderBottomRightRadius"]},borderTopLeftRadius:Ct,borderTopRightRadius:Ct,borderBottomLeftRadius:Ct,borderBottomRightRadius:Ct,boxShadow:pl};function ml(t,{layout:e,layoutId:s}){return Mt.has(t)||t.startsWith("origin")||(e||s!==void 0)&&(!!Ke[t]||t==="opacity")}function Bn(t,e,s){var o;const i=t.style,n=e==null?void 0:e.style,r={};if(!i)return r;for(const a in i)(K(i[a])||n&&K(n[a])||ml(a,t)||((o=s==null?void 0:s.getValue(a))==null?void 0:o.liveStyle)!==void 0)&&(r[a]=i[a]);return r}function yl(t){return window.getComputedStyle(t)}class qc extends Pn{constructor(){super(...arguments),this.type="html",this.renderInstance=In}readValueFromInstance(e,s){var i;if(Mt.has(s))return(i=this.projection)!=null&&i.isProjecting?Pe(s):Or(e,s);{const n=yl(e),r=(zi(s)?n.getPropertyValue(s):n[s])||0;return typeof r=="string"?r.trim():r}}measureInstanceViewportBox(e,{transformPagePoint:s}){return En(e,s)}build(e,s,i){Ln(e,s,i.transformTemplate)}scrapeMotionValuesFromProps(e,s,i){return Bn(e,s,i)}}const gl={offset:"stroke-dashoffset",array:"stroke-dasharray"},vl={offset:"strokeDashoffset",array:"strokeDasharray"};function Tl(t,e,s=1,i=0,n=!0){t.pathLength=1;const r=n?gl:vl;t[r.offset]=`${-i}`,t[r.array]=`${e} ${s}`}const bl=["offsetDistance","offsetPath","offsetRotate","offsetAnchor"];function xl(t,{attrX:e,attrY:s,attrScale:i,pathLength:n,pathSpacing:r=1,pathOffset:o=0,...a},l,u,c){if(Ln(t,a,u),l){t.style.viewBox&&(t.attrs.viewBox=t.style.viewBox);return}t.attrs=t.style,t.style={};const{attrs:f,style:d}=t;f.transform&&(d.transform=f.transform,delete f.transform),(d.transform||f.transformOrigin)&&(d.transformOrigin=f.transformOrigin??"50% 50%",delete f.transformOrigin),d.transform&&(d.transformBox=(c==null?void 0:c.transformBox)??"fill-box",delete f.transformBox);for(const h of bl)f[h]!==void 0&&(d[h]=f[h],delete f[h]);e!==void 0&&(f.x=e),s!==void 0&&(f.y=s),i!==void 0&&(f.scale=i),n!==void 0&&Tl(f,n,r,o,!1)}const On=new Set(["baseFrequency","diffuseConstant","kernelMatrix","kernelUnitLength","keySplines","keyTimes","limitingConeAngle","markerHeight","markerWidth","numOctaves","targetX","targetY","surfaceScale","specularConstant","specularExponent","stdDeviation","tableValues","viewBox","gradientTransform","pathLength","startOffset","textLength","lengthAdjust"]),_l=t=>typeof t=="string"&&t.toLowerCase()==="svg";function wl(t,e,s,i){In(t,e,void 0,i);for(const n in e.attrs)t.setAttribute(On.has(n)?n:as(n),e.attrs[n])}function Al(t,e,s){const i=Bn(t,e,s);for(const n in t)if(K(t[n])||K(e[n])){const r=Vt.indexOf(n)!==-1?"attr"+n.charAt(0).toUpperCase()+n.substring(1):n;i[r]=t[n]}return i}class Gc extends Pn{constructor(){super(...arguments),this.type="svg",this.isSVGTag=!1,this.measureInstanceViewportBox=O}getBaseTargetFromProps(e,s){return e[s]}readValueFromInstance(e,s){if(Mt.has(s)){const i=Tn(s);return i&&i.default||0}return s=On.has(s)?s:as(s),e.getAttribute(s)}scrapeMotionValuesFromProps(e,s,i){return Al(e,s,i)}build(e,s,i){xl(e,s,this.isSVGTag,i.transformTemplate,i.style)}renderInstance(e,s,i,n){wl(e,s,i,n)}mount(e){this.isSVGTag=_l(e.tagName),super.mount(e)}}const kl=fs.length;function Fn(t){if(!t)return;if(!t.isControllingVariants){const s=t.parent?Fn(t.parent)||{}:{};return t.props.initial!==void 0&&(s.initial=t.props.initial),s}const e={};for(let s=0;s<kl;s++){const i=fs[s],n=t.props[i];(cs(n)||n===!1)&&(e[i]=n)}return e}function Nn(t,e){if(!Array.isArray(e))return!1;const s=e.length;if(s!==t.length)return!1;for(let i=0;i<s;i++)if(e[i]!==t[i])return!1;return!0}const Sl=[...us].reverse(),Vl=us.length;function Ml(t){return e=>Promise.all(e.map(({animation:s,options:i})=>Sa(t,s,i)))}function Zc(t){let e=Ml(t),s=Ks(),i=!0,n=!1;const r=u=>(c,f)=>{var h;const d=wt(t,f,u==="exit"?(h=t.presenceContext)==null?void 0:h.custom:void 0);if(d){const{transition:g,transitionEnd:v,...p}=d;c={...c,...p,...v}}return c};function o(u){e=u(t)}function a(u){const{props:c}=t,f=Fn(t.parent)||{},d=[],h=new Set;let g={},v=1/0;for(let T=0;T<Vl;T++){const y=Sl[T],m=s[y],b=c[y]!==void 0?c[y]:f[y],A=cs(b),S=y===u?m.isActive:null;S===!1&&(v=T);let w=b===f[y]&&b!==c[y]&&A;if(w&&(i||n)&&t.manuallyAnimateOnMount&&(w=!1),m.protectedKeys={...g},!m.isActive&&S===null||!b&&!m.prevProp||Vn(b)||typeof b=="boolean")continue;if(y==="exit"&&m.isActive&&S!==!0){m.prevResolvedValues&&(g={...g,...m.prevResolvedValues});continue}const x=Cl(m.prevProp,b);let M=x||y===u&&m.isActive&&!w&&A||T>v&&A,k=!1;const V=Array.isArray(b)?b:[b];let B=V.reduce(r(y),{});S===!1&&(B={});const{prevResolvedValues:N={}}=m,X={...N,...B},H=E=>{M=!0,h.has(E)&&(k=!0,h.delete(E)),m.needsAnimating[E]=!0;const Y=t.getValue(E);Y&&(Y.liveStyle=!1)};for(const E in X){const Y=B[E],ut=N[E];if(g.hasOwnProperty(E))continue;let Tt=!1;Be(Y)&&Be(ut)?Tt=!Nn(Y,ut)||x:Tt=Y!==ut,Tt?Y!=null?H(E):h.add(E):Y!==void 0&&h.has(E)?H(E):m.protectedKeys[E]=!0}m.prevProp=b,m.prevResolvedValues=B,m.isActive&&(g={...g,...B}),(i||n)&&t.blockInitialAnimation&&(M=!1);const W=w&&x;M&&(!W||k)&&d.push(...V.map(E=>{const Y={type:y};if(typeof E=="string"&&(i||n)&&!W&&t.manuallyAnimateOnMount&&t.parent){const{parent:ut}=t,Tt=wt(ut,E);if(ut.enteringChildren&&Tt){const{delayChildren:Gn}=Tt.transition||{};Y.delay=cn(ut.enteringChildren,t,Gn)}}return{animation:E,options:Y}}))}if(h.size){const T={};if(typeof c.initial!="boolean"){const y=wt(t,Array.isArray(c.initial)?c.initial[0]:c.initial);y&&y.transition&&(T.transition=y.transition)}h.forEach(y=>{const m=t.getBaseTarget(y),b=t.getValue(y);b&&(b.liveStyle=!0),T[y]=m??null}),d.push({animation:T})}let p=!!d.length;return i&&(c.initial===!1||c.initial===c.animate)&&!t.manuallyAnimateOnMount&&(p=!1),i=!1,n=!1,p?e(d):Promise.resolve()}function l(u,c){var d;if(s[u].isActive===c)return Promise.resolve();(d=t.variantChildren)==null||d.forEach(h=>{var g;return(g=h.animationState)==null?void 0:g.setActive(u,c)}),s[u].isActive=c;const f=a(u);for(const h in s)s[h].protectedKeys={};return f}return{animateChanges:a,setActive:l,setAnimateFunction:o,getState:()=>s,reset:()=>{s=Ks(),n=!0}}}function Cl(t,e){return typeof e=="string"?e!==t:Array.isArray(e)?!Nn(e,t):!1}function ft(t=!1){return{isActive:t,protectedKeys:{},needsAnimating:{},prevResolvedValues:{}}}function Ks(){return{animate:ft(!0),whileInView:ft(),whileHover:ft(),whileTap:ft(),whileDrag:ft(),whileFocus:ft(),exit:ft()}}function We(t,e){t.min=e.min,t.max=e.max}function G(t,e){We(t.x,e.x),We(t.y,e.y)}function Ws(t,e){t.translate=e.translate,t.scale=e.scale,t.originPoint=e.originPoint,t.origin=e.origin}const jn=1e-4,Pl=1-jn,Dl=1+jn,$n=.01,Rl=0-$n,El=0+$n;function q(t){return t.max-t.min}function Ll(t,e,s){return Math.abs(t-e)<=s}function Xs(t,e,s,i=.5){t.origin=i,t.originPoint=R(e.min,e.max,t.origin),t.scale=q(s)/q(e),t.translate=R(s.min,s.max,t.origin)-t.originPoint,(t.scale>=Pl&&t.scale<=Dl||isNaN(t.scale))&&(t.scale=1),(t.translate>=Rl&&t.translate<=El||isNaN(t.translate))&&(t.translate=0)}function Et(t,e,s,i){Xs(t.x,e.x,s.x,i?i.originX:void 0),Xs(t.y,e.y,s.y,i?i.originY:void 0)}function Hs(t,e,s,i=0){const n=i?R(s.min,s.max,i):s.min;t.min=n+e.min,t.max=t.min+q(e)}function Il(t,e,s,i){Hs(t.x,e.x,s.x,i==null?void 0:i.x),Hs(t.y,e.y,s.y,i==null?void 0:i.y)}function Ys(t,e,s,i=0){const n=i?R(s.min,s.max,i):s.min;t.min=e.min-n,t.max=t.min+q(e)}function se(t,e,s,i){Ys(t.x,e.x,s.x,i==null?void 0:i.x),Ys(t.y,e.y,s.y,i==null?void 0:i.y)}function qs(t,e,s,i,n){return t-=e,t=ee(t,1/s,i),n!==void 0&&(t=ee(t,1/n,i)),t}function Bl(t,e=0,s=1,i=.5,n,r=t,o=t){if(it.test(e)&&(e=parseFloat(e),e=R(o.min,o.max,e/100)-o.min),typeof e!="number")return;let a=R(r.min,r.max,i);t===r&&(a-=e),t.min=qs(t.min,e,s,a,n),t.max=qs(t.max,e,s,a,n)}function Gs(t,e,[s,i,n],r,o){Bl(t,e[s],e[i],e[n],e.scale,r,o)}const Ol=["x","scaleX","originX"],Fl=["y","scaleY","originY"];function Zs(t,e,s,i){Gs(t.x,e,Ol,s?s.x:void 0,i?i.x:void 0),Gs(t.y,e,Fl,s?s.y:void 0,i?i.y:void 0)}function Qs(t){return t.translate===0&&t.scale===1}function zn(t){return Qs(t.x)&&Qs(t.y)}function Js(t,e){return t.min===e.min&&t.max===e.max}function Nl(t,e){return Js(t.x,e.x)&&Js(t.y,e.y)}function ti(t,e){return Math.round(t.min)===Math.round(e.min)&&Math.round(t.max)===Math.round(e.max)}function Un(t,e){return ti(t.x,e.x)&&ti(t.y,e.y)}function ei(t){return q(t.x)/q(t.y)}function si(t,e){return t.translate===e.translate&&t.scale===e.scale&&t.originPoint===e.originPoint}function ii(t){return[t("x"),t("y")]}function jl(t,e,s){let i="";const n=t.x.translate/e.x,r=t.y.translate/e.y,o=(s==null?void 0:s.z)||0;if((n||r||o)&&(i=`translate3d(${n}px, ${r}px, ${o}px) `),(e.x!==1||e.y!==1)&&(i+=`scale(${1/e.x}, ${1/e.y}) `),s){const{transformPerspective:u,rotate:c,pathRotation:f,rotateX:d,rotateY:h,skewX:g,skewY:v}=s;u&&(i=`perspective(${u}px) ${i}`),c&&(i+=`rotate(${c}deg) `),f&&(i+=`rotate(${f}deg) `),d&&(i+=`rotateX(${d}deg) `),h&&(i+=`rotateY(${h}deg) `),g&&(i+=`skewX(${g}deg) `),v&&(i+=`skewY(${v}deg) `)}const a=t.x.scale*e.x,l=t.y.scale*e.y;return(a!==1||l!==1)&&(i+=`scale(${a}, ${l})`),i||"none"}const Kn=["borderTopLeftRadius","borderTopRightRadius","borderBottomLeftRadius","borderBottomRightRadius"],$l=Kn.length,ni=t=>typeof t=="string"?parseFloat(t):t,oi=t=>typeof t=="number"||_.test(t);function zl(t,e,s,i,n,r){n?(t.opacity=R(0,s.opacity??1,Ul(i)),t.opacityExit=R(e.opacity??1,0,Kl(i))):r&&(t.opacity=R(e.opacity??1,s.opacity??1,i));for(let o=0;o<$l;o++){const a=Kn[o];let l=ri(e,a),u=ri(s,a);if(l===void 0&&u===void 0)continue;l||(l=0),u||(u=0),l===0||u===0||oi(l)===oi(u)?(t[a]=Math.max(R(ni(l),ni(u),i),0),(it.test(u)||it.test(l))&&(t[a]+="%")):t[a]=u}(e.rotate||s.rotate)&&(t.rotate=R(e.rotate||0,s.rotate||0,i))}function ri(t,e){return t[e]!==void 0?t[e]:t.borderRadius}const Ul=Wn(0,.5,Bi),Kl=Wn(.5,.95,ct);function Wn(t,e,s){return i=>i<t?0:i>e?1:s(qe(t,e,i))}function Wl(t,e,s){const i=K(t)?t:kt(t);return i.start(fn("",i,e,s)),i.animation}function Xl(t,e,s,i={passive:!0}){return t.addEventListener(e,s,i),()=>t.removeEventListener(e,s)}const Hl=(t,e)=>t.depth-e.depth;class Yl{constructor(){this.children=[],this.isDirty=!1}add(e){Xe(this.children,e),this.isDirty=!0}remove(e){Yt(this.children,e),this.isDirty=!0}forEach(e){this.isDirty&&this.children.sort(Hl),this.isDirty=!1,this.children.forEach(e)}}function ql(t,e){const s=z.now(),i=({timestamp:n})=>{const r=n-s;r>=e&&(vt(i),t(r-e))};return F.setup(i,!0),()=>vt(i)}function me(t){return K(t)?t.get():t}class Gl{constructor(){this.members=[]}add(e){Xe(this.members,e);for(let s=this.members.length-1;s>=0;s--){const i=this.members[s];if(i===e||i===this.lead||i===this.prevLead)continue;const n=i.instance;(!n||n.isConnected===!1)&&!i.snapshot&&(Yt(this.members,i),i.unmount())}e.scheduleRender()}remove(e){if(Yt(this.members,e),e===this.prevLead&&(this.prevLead=void 0),e===this.lead){const s=this.members[this.members.length-1];s&&this.promote(s)}}relegate(e){var s;for(let i=this.members.indexOf(e)-1;i>=0;i--){const n=this.members[i];if(n.isPresent!==!1&&((s=n.instance)==null?void 0:s.isConnected)!==!1)return this.promote(n),!0}return!1}promote(e,s){var n;const i=this.lead;if(e!==i&&(this.prevLead=i,this.lead=e,e.show(),i)){i.updateSnapshot(),e.scheduleRender();const{layoutDependency:r}=i.options,{layoutDependency:o}=e.options;(r===void 0||r!==o)&&(e.resumeFrom=i,s&&(i.preserveOpacity=!0),i.snapshot&&(e.snapshot=i.snapshot,e.snapshot.latestValues=i.animationValues||i.latestValues),(n=e.root)!=null&&n.isUpdating&&(e.isLayoutDirty=!0)),e.options.crossfade===!1&&i.hide()}}exitAnimationComplete(){this.members.forEach(e=>{var s,i,n,r,o;(i=(s=e.options).onExitComplete)==null||i.call(s),(o=(n=e.resumingFrom)==null?void 0:(r=n.options).onExitComplete)==null||o.call(r)})}scheduleRender(){this.members.forEach(e=>e.instance&&e.scheduleRender(!1))}removeLeadSnapshot(){var e;(e=this.lead)!=null&&e.snapshot&&(this.lead.snapshot=void 0)}}const ye={hasAnimatedSinceResize:!0,hasEverUpdated:!1},ge=["","X","Y","Z"],Zl=1e3;let Ql=0;function ve(t,e,s,i){const{latestValues:n}=e;n[t]&&(s[t]=n[t],e.setStaticValue(t,0),i&&(i[t]=0))}function Xn(t){if(t.hasCheckedOptimisedAppear=!0,t.root===t)return;const{visualElement:e}=t.options;if(!e)return;const s=mn(e);if(window.MotionHasOptimisedAnimation(s,"transform")){const{layout:n,layoutId:r}=t.options;window.MotionCancelOptimisedAnimation(s,"transform",F,!(n||r))}const{parent:i}=t;i&&!i.hasCheckedOptimisedAppear&&Xn(i)}function Hn({attachResizeListener:t,defaultParent:e,measureScroll:s,checkIsScrollRoot:i,resetTransform:n}){return class{constructor(o={},a=e==null?void 0:e()){this.id=Ql++,this.animationId=0,this.animationCommitId=0,this.children=new Set,this.options={},this.isTreeAnimating=!1,this.isAnimationBlocked=!1,this.isLayoutDirty=!1,this.isProjectionDirty=!1,this.isSharedProjectionDirty=!1,this.isTransformDirty=!1,this.updateManuallyBlocked=!1,this.updateBlockedByResize=!1,this.isUpdating=!1,this.isSVG=!1,this.needsReset=!1,this.shouldResetTransform=!1,this.hasCheckedOptimisedAppear=!1,this.treeScale={x:1,y:1},this.eventHandlers=new Map,this.hasTreeAnimated=!1,this.layoutVersion=0,this.updateScheduled=!1,this.scheduleUpdate=()=>this.update(),this.projectionUpdateScheduled=!1,this.checkUpdateFailed=()=>{this.isUpdating&&(this.isUpdating=!1,this.clearAllSnapshots())},this.updateProjection=()=>{this.projectionUpdateScheduled=!1,this.nodes.forEach(ec),this.nodes.forEach(ac),this.nodes.forEach(lc),this.nodes.forEach(sc)},this.resolvedRelativeTargetAt=0,this.linkedParentVersion=0,this.hasProjected=!1,this.isVisible=!0,this.animationProgress=0,this.sharedNodes=new Map,this.latestValues=o,this.root=a?a.root||a:this,this.path=a?[...a.path,a]:[],this.parent=a,this.depth=a?a.depth+1:0;for(let l=0;l<this.path.length;l++)this.path[l].shouldResetTransform=!0;this.root===this&&(this.nodes=new Yl)}addEventListener(o,a){return this.eventHandlers.has(o)||this.eventHandlers.set(o,new Ge),this.eventHandlers.get(o).add(a)}notifyListeners(o,...a){const l=this.eventHandlers.get(o);l&&l.notify(...a)}hasListeners(o){return this.eventHandlers.has(o)}mount(o){if(this.instance)return;this.isSVG=ls(o)&&!Ja(o),this.instance=o;const{layoutId:a,layout:l,visualElement:u}=this.options;if(u&&!u.current&&u.mount(o),this.root.nodes.add(this),this.parent&&this.parent.children.add(this),this.root.hasTreeAnimated&&(l||a)&&(this.isLayoutDirty=!0),t){let c,f=0;const d=()=>this.root.updateBlockedByResize=!1;F.read(()=>{f=window.innerWidth}),t(o,()=>{const h=window.innerWidth;h!==f&&(f=h,this.root.updateBlockedByResize=!0,c&&c(),c=ql(d,250),ye.hasAnimatedSinceResize&&(ye.hasAnimatedSinceResize=!1,this.nodes.forEach(ci)))})}a&&this.root.registerSharedNode(a,this),this.options.animate!==!1&&u&&(a||l)&&this.addEventListener("didUpdate",({delta:c,hasLayoutChanged:f,hasRelativeLayoutChanged:d,layout:h})=>{if(this.isTreeAnimationBlocked()){this.target=void 0,this.relativeTarget=void 0;return}const g=this.options.transition||u.getDefaultTransition()||hc,{onLayoutAnimationStart:v,onLayoutAnimationComplete:p}=u.getProps(),T=!this.targetLayout||!Un(this.targetLayout,h),y=!f&&d;if(this.options.layoutRoot||this.resumeFrom||y||f&&(T||!this.currentAnimation)){this.resumeFrom&&(this.resumingFrom=this.resumeFrom,this.resumingFrom.resumingFrom=void 0);const m={...rs(g,"layout"),onPlay:v,onComplete:p};(u.shouldReduceMotion||this.options.layoutRoot)&&(m.delay=0,m.type=!1),this.startAnimation(m),this.setAnimationOrigin(c,y,m.path)}else f||ci(this),this.isLead()&&this.options.onExitComplete&&this.options.onExitComplete();this.targetLayout=h})}unmount(){this.options.layoutId&&this.willUpdate(),this.root.nodes.remove(this);const o=this.getStack();o&&o.remove(this),this.parent&&this.parent.children.delete(this),this.instance=void 0,this.eventHandlers.clear(),vt(this.updateProjection)}blockUpdate(){this.updateManuallyBlocked=!0}unblockUpdate(){this.updateManuallyBlocked=!1}isUpdateBlocked(){return this.updateManuallyBlocked||this.updateBlockedByResize}isTreeAnimationBlocked(){return this.isAnimationBlocked||this.parent&&this.parent.isTreeAnimationBlocked()||!1}startUpdate(){this.isUpdateBlocked()||(this.isUpdating=!0,this.nodes&&this.nodes.forEach(cc),this.animationId++)}getTransformTemplate(){const{visualElement:o}=this.options;return o&&o.getProps().transformTemplate}willUpdate(o=!0){if(this.root.hasTreeAnimated=!0,this.root.isUpdateBlocked()){this.options.onExitComplete&&this.options.onExitComplete();return}if(window.MotionCancelOptimisedAnimation&&!this.hasCheckedOptimisedAppear&&Xn(this),!this.root.isUpdating&&this.root.startUpdate(),this.isLayoutDirty)return;this.isLayoutDirty=!0;for(let c=0;c<this.path.length;c++){const f=this.path[c];f.shouldResetTransform=!0,(typeof f.latestValues.x=="string"||typeof f.latestValues.y=="string")&&(f.isLayoutDirty=!0),f.updateScroll("snapshot"),f.options.layoutRoot&&f.willUpdate(!1)}const{layoutId:a,layout:l}=this.options;if(a===void 0&&!l)return;const u=this.getTransformTemplate();this.prevTransformTemplateValue=u?u(this.latestValues,""):void 0,this.updateSnapshot(),o&&this.notifyListeners("willUpdate")}update(){if(this.updateScheduled=!1,this.isUpdateBlocked()){const l=this.updateBlockedByResize;this.unblockUpdate(),this.updateBlockedByResize=!1,this.clearAllSnapshots(),l&&this.nodes.forEach(nc),this.nodes.forEach(ai);return}if(this.animationId<=this.animationCommitId){this.nodes.forEach(li);return}this.animationCommitId=this.animationId,this.isUpdating?(this.isUpdating=!1,this.nodes.forEach(oc),this.nodes.forEach(rc),this.nodes.forEach(Jl),this.nodes.forEach(tc)):this.nodes.forEach(li),this.clearAllSnapshots();const a=z.now();j.delta=ot(0,1e3/60,a-j.timestamp),j.timestamp=a,j.isProcessing=!0,ae.update.process(j),ae.preRender.process(j),ae.render.process(j),j.isProcessing=!1}didUpdate(){this.updateScheduled||(this.updateScheduled=!0,_n.read(this.scheduleUpdate))}clearAllSnapshots(){this.nodes.forEach(ic),this.sharedNodes.forEach(uc)}scheduleUpdateProjection(){this.projectionUpdateScheduled||(this.projectionUpdateScheduled=!0,F.preRender(this.updateProjection,!1,!0))}scheduleCheckAfterUnmount(){F.postRender(()=>{this.isLayoutDirty?this.root.didUpdate():this.root.checkUpdateFailed()})}updateSnapshot(){this.snapshot||!this.instance||(this.snapshot=this.measure(),this.snapshot&&!q(this.snapshot.measuredBox.x)&&!q(this.snapshot.measuredBox.y)&&(this.snapshot=void 0))}updateLayout(){if(!this.instance||(this.updateScroll(),!(this.options.alwaysMeasureLayout&&this.isLead())&&!this.isLayoutDirty))return;if(this.resumeFrom&&!this.resumeFrom.instance)for(let l=0;l<this.path.length;l++)this.path[l].updateScroll();const o=this.layout;this.layout=this.measure(!1),this.layoutVersion++,this.layoutCorrected||(this.layoutCorrected=O()),this.isLayoutDirty=!1,this.projectionDelta=void 0,this.notifyListeners("measure",this.layout.layoutBox);const{visualElement:a}=this.options;a&&a.notify("LayoutMeasure",this.layout.layoutBox,o?o.layoutBox:void 0)}updateScroll(o="measure"){let a=!!(this.options.layoutScroll&&this.instance);if(this.scroll&&this.scroll.animationId===this.root.animationId&&this.scroll.phase===o&&(a=!1),a&&this.instance){const l=i(this.instance);this.scroll={animationId:this.root.animationId,phase:o,isRoot:l,offset:s(this.instance),wasRoot:this.scroll?this.scroll.isRoot:l}}}resetTransform(){if(!n)return;const o=this.isLayoutDirty||this.shouldResetTransform||this.options.alwaysMeasureLayout,a=this.projectionDelta&&!zn(this.projectionDelta),l=this.getTransformTemplate(),u=l?l(this.latestValues,""):void 0,c=u!==this.prevTransformTemplateValue;o&&this.instance&&(a||dt(this.latestValues)||c)&&(n(this.instance,u),this.shouldResetTransform=!1,this.scheduleRender())}measure(o=!0){const a=this.measurePageBox();let l=this.removeElementScroll(a);return o&&(l=this.removeTransform(l)),pc(l),{animationId:this.root.animationId,measuredBox:a,layoutBox:l,latestValues:{},source:this.id}}measurePageBox(){var u;const{visualElement:o}=this.options;if(!o)return O();const a=o.measureViewportBox();if(!(((u=this.scroll)==null?void 0:u.wasRoot)||this.path.some(mc))){const{scroll:c}=this.root;c&&(et(a.x,c.offset.x),et(a.y,c.offset.y))}return a}removeElementScroll(o){var l;const a=O();if(G(a,o),(l=this.scroll)!=null&&l.wasRoot)return a;for(let u=0;u<this.path.length;u++){const c=this.path[u],{scroll:f,options:d}=c;c!==this.root&&f&&d.layoutScroll&&(f.wasRoot&&G(a,o),et(a.x,f.offset.x),et(a.y,f.offset.y))}return a}applyTransform(o,a=!1,l){var c,f;const u=l||O();G(u,o);for(let d=0;d<this.path.length;d++){const h=this.path[d];!a&&h.options.layoutScroll&&h.scroll&&h!==h.root&&(et(u.x,-h.scroll.offset.x),et(u.y,-h.scroll.offset.y)),dt(h.latestValues)&&Ht(u,h.latestValues,(c=h.layout)==null?void 0:c.layoutBox)}return dt(this.latestValues)&&Ht(u,this.latestValues,(f=this.layout)==null?void 0:f.layoutBox),u}removeTransform(o){var l;const a=O();G(a,o);for(let u=0;u<this.path.length;u++){const c=this.path[u];if(!dt(c.latestValues))continue;let f;c.instance&&(ze(c.latestValues)&&c.updateSnapshot(),f=O(),G(f,c.measurePageBox())),Zs(a,c.latestValues,(l=c.snapshot)==null?void 0:l.layoutBox,f)}return dt(this.latestValues)&&Zs(a,this.latestValues),a}setTargetDelta(o){this.targetDelta=o,this.root.scheduleUpdateProjection(),this.isProjectionDirty=!0}setOptions(o){this.options={...this.options,...o,crossfade:o.crossfade!==void 0?o.crossfade:!0}}clearMeasurements(){this.scroll=void 0,this.layout=void 0,this.snapshot=void 0,this.prevTransformTemplateValue=void 0,this.targetDelta=void 0,this.target=void 0,this.isLayoutDirty=!1}forceRelativeParentToResolveTarget(){this.relativeParent&&this.relativeParent.resolvedRelativeTargetAt!==j.timestamp&&this.relativeParent.resolveTargetDelta(!0)}resolveTargetDelta(o=!1){var h;const a=this.getLead();this.isProjectionDirty||(this.isProjectionDirty=a.isProjectionDirty),this.isTransformDirty||(this.isTransformDirty=a.isTransformDirty),this.isSharedProjectionDirty||(this.isSharedProjectionDirty=a.isSharedProjectionDirty);const l=!!this.resumingFrom||this!==a;if(!(o||l&&this.isSharedProjectionDirty||this.isProjectionDirty||(h=this.parent)!=null&&h.isProjectionDirty||this.attemptToResolveRelativeTarget||this.root.updateBlockedByResize))return;const{layout:c,layoutId:f}=this.options;if(!this.layout||!(c||f))return;this.resolvedRelativeTargetAt=j.timestamp;const d=this.getClosestProjectingParent();d&&this.linkedParentVersion!==d.layoutVersion&&!d.options.layoutRoot&&this.removeRelativeTarget(),!this.targetDelta&&!this.relativeTarget&&(this.options.layoutAnchor!==!1&&d&&d.layout?this.createRelativeTarget(d,this.layout.layoutBox,d.layout.layoutBox):this.removeRelativeTarget()),!(!this.relativeTarget&&!this.targetDelta)&&(this.target||(this.target=O(),this.targetWithTransforms=O()),this.relativeTarget&&this.relativeTargetOrigin&&this.relativeParent&&this.relativeParent.target?(this.forceRelativeParentToResolveTarget(),Il(this.target,this.relativeTarget,this.relativeParent.target,this.options.layoutAnchor||void 0)):this.targetDelta?(this.resumingFrom?this.applyTransform(this.layout.layoutBox,!1,this.target):G(this.target,this.layout.layoutBox),Rn(this.target,this.targetDelta)):G(this.target,this.layout.layoutBox),this.attemptToResolveRelativeTarget&&(this.attemptToResolveRelativeTarget=!1,this.options.layoutAnchor!==!1&&d&&!!d.resumingFrom==!!this.resumingFrom&&!d.options.layoutScroll&&d.target&&this.animationProgress!==1?this.createRelativeTarget(d,this.target,d.target):this.relativeParent=this.relativeTarget=void 0))}getClosestProjectingParent(){if(!(!this.parent||ze(this.parent.latestValues)||Dn(this.parent.latestValues)))return this.parent.isProjecting()?this.parent:this.parent.getClosestProjectingParent()}isProjecting(){return!!((this.relativeTarget||this.targetDelta||this.options.layoutRoot)&&this.layout)}createRelativeTarget(o,a,l){this.relativeParent=o,this.linkedParentVersion=o.layoutVersion,this.forceRelativeParentToResolveTarget(),this.relativeTarget=O(),this.relativeTargetOrigin=O(),se(this.relativeTargetOrigin,a,l,this.options.layoutAnchor||void 0),G(this.relativeTarget,this.relativeTargetOrigin)}removeRelativeTarget(){this.relativeParent=this.relativeTarget=void 0}calcProjection(){var g;const o=this.getLead(),a=!!this.resumingFrom||this!==o;let l=!0;if((this.isProjectionDirty||(g=this.parent)!=null&&g.isProjectionDirty)&&(l=!1),a&&(this.isSharedProjectionDirty||this.isTransformDirty)&&(l=!1),this.resolvedRelativeTargetAt===j.timestamp&&(l=!1),l)return;const{layout:u,layoutId:c}=this.options;if(this.isTreeAnimating=!!(this.parent&&this.parent.isTreeAnimating||this.currentAnimation||this.pendingAnimation),this.isTreeAnimating||(this.targetDelta=this.relativeTarget=void 0),!this.layout||!(u||c))return;G(this.layoutCorrected,this.layout.layoutBox);const f=this.treeScale.x,d=this.treeScale.y;ul(this.layoutCorrected,this.treeScale,this.path,a),o.layout&&!o.target&&(this.treeScale.x!==1||this.treeScale.y!==1)&&(o.target=o.layout.layoutBox,o.targetWithTransforms=O());const{target:h}=o;if(!h){this.prevProjectionDelta&&(this.createProjectionDeltas(),this.scheduleRender());return}!this.projectionDelta||!this.prevProjectionDelta?this.createProjectionDeltas():(Ws(this.prevProjectionDelta.x,this.projectionDelta.x),Ws(this.prevProjectionDelta.y,this.projectionDelta.y)),Et(this.projectionDelta,this.layoutCorrected,h,this.latestValues),(this.treeScale.x!==f||this.treeScale.y!==d||!si(this.projectionDelta.x,this.prevProjectionDelta.x)||!si(this.projectionDelta.y,this.prevProjectionDelta.y))&&(this.hasProjected=!0,this.scheduleRender(),this.notifyListeners("projectionUpdate",h))}hide(){this.isVisible=!1}show(){this.isVisible=!0}scheduleRender(o=!0){var a;if((a=this.options.visualElement)==null||a.scheduleRender(),o){const l=this.getStack();l&&l.scheduleRender()}this.resumingFrom&&!this.resumingFrom.instance&&(this.resumingFrom=void 0)}createProjectionDeltas(){this.prevProjectionDelta=_t(),this.projectionDelta=_t(),this.projectionDeltaWithTransform=_t()}setAnimationOrigin(o,a=!1,l){const u=this.snapshot,c=u?u.latestValues:{},f={...this.latestValues},d=_t();(!this.relativeParent||!this.relativeParent.options.layoutRoot)&&(this.relativeTarget=this.relativeTargetOrigin=void 0),this.attemptToResolveRelativeTarget=!a;const h=O(),g=u?u.source:void 0,v=this.layout?this.layout.source:void 0,p=g!==v,T=this.getStack(),y=!T||T.members.length<=1,m=!!(p&&!y&&this.options.crossfade===!0&&!this.path.some(dc));this.animationProgress=0;let b;const A=l==null?void 0:l.interpolateProjection(o);this.mixTargetDelta=S=>{const w=S/1e3,x=A==null?void 0:A(w);x?(d.x.translate=x.x,d.x.scale=R(o.x.scale,1,w),d.x.origin=o.x.origin,d.x.originPoint=o.x.originPoint,d.y.translate=x.y,d.y.scale=R(o.y.scale,1,w),d.y.origin=o.y.origin,d.y.originPoint=o.y.originPoint):(ui(d.x,o.x,w),ui(d.y,o.y,w)),this.setTargetDelta(d),this.relativeTarget&&this.relativeTargetOrigin&&this.layout&&this.relativeParent&&this.relativeParent.layout&&(se(h,this.layout.layoutBox,this.relativeParent.layout.layoutBox,this.options.layoutAnchor||void 0),fc(this.relativeTarget,this.relativeTargetOrigin,h,w),b&&Nl(this.relativeTarget,b)&&(this.isProjectionDirty=!1),b||(b=O()),G(b,this.relativeTarget)),p&&(this.animationValues=f,zl(f,c,this.latestValues,w,m,y)),x&&x.rotate!==void 0&&(this.animationValues||(this.animationValues=f),this.animationValues.pathRotation=x.rotate),this.root.scheduleUpdateProjection(),this.scheduleRender(),this.animationProgress=w},this.mixTargetDelta(this.options.layoutRoot?1e3:0)}startAnimation(o){var a,l,u;this.notifyListeners("animationStart"),(a=this.currentAnimation)==null||a.stop(),(u=(l=this.resumingFrom)==null?void 0:l.currentAnimation)==null||u.stop(),this.pendingAnimation&&(vt(this.pendingAnimation),this.pendingAnimation=void 0),this.pendingAnimation=F.update(()=>{ye.hasAnimatedSinceResize=!0,this.motionValue||(this.motionValue=kt(0)),this.motionValue.jump(0,!1),this.currentAnimation=Wl(this.motionValue,[0,1e3],{...o,velocity:0,isSync:!0,onUpdate:c=>{this.mixTargetDelta(c),o.onUpdate&&o.onUpdate(c)},onStop:()=>{},onComplete:()=>{o.onComplete&&o.onComplete(),this.completeAnimation()}}),this.resumingFrom&&(this.resumingFrom.currentAnimation=this.currentAnimation),this.pendingAnimation=void 0})}completeAnimation(){this.resumingFrom&&(this.resumingFrom.currentAnimation=void 0,this.resumingFrom.preserveOpacity=void 0);const o=this.getStack();o&&o.exitAnimationComplete(),this.resumingFrom=this.currentAnimation=this.animationValues=void 0,this.notifyListeners("animationComplete")}finishAnimation(){this.currentAnimation&&(this.mixTargetDelta&&this.mixTargetDelta(Zl),this.currentAnimation.stop()),this.completeAnimation()}applyTransformsToTarget(){const o=this.getLead();let{targetWithTransforms:a,target:l,layout:u,latestValues:c}=o;if(!(!a||!l||!u)){if(this!==o&&this.layout&&u&&Yn(this.options.animationType,this.layout.layoutBox,u.layoutBox)){l=this.target||O();const f=q(this.layout.layoutBox.x);l.x.min=o.target.x.min,l.x.max=l.x.min+f;const d=q(this.layout.layoutBox.y);l.y.min=o.target.y.min,l.y.max=l.y.min+d}G(a,l),Ht(a,c),Et(this.projectionDeltaWithTransform,this.layoutCorrected,a,c)}}registerSharedNode(o,a){this.sharedNodes.has(o)||this.sharedNodes.set(o,new Gl),this.sharedNodes.get(o).add(a);const u=a.options.initialPromotionConfig;a.promote({transition:u?u.transition:void 0,preserveFollowOpacity:u&&u.shouldPreserveFollowOpacity?u.shouldPreserveFollowOpacity(a):void 0})}isLead(){const o=this.getStack();return o?o.lead===this:!0}getLead(){var a;const{layoutId:o}=this.options;return o?((a=this.getStack())==null?void 0:a.lead)||this:this}getPrevLead(){var a;const{layoutId:o}=this.options;return o?(a=this.getStack())==null?void 0:a.prevLead:void 0}getStack(){const{layoutId:o}=this.options;if(o)return this.root.sharedNodes.get(o)}promote({needsReset:o,transition:a,preserveFollowOpacity:l}={}){const u=this.getStack();u&&u.promote(this,l),o&&(this.projectionDelta=void 0,this.needsReset=!0),a&&this.setOptions({transition:a})}relegate(){const o=this.getStack();return o?o.relegate(this):!1}resetSkewAndRotation(){const{visualElement:o}=this.options;if(!o)return;let a=!1;const{latestValues:l}=o;if((l.z||l.rotate||l.rotateX||l.rotateY||l.rotateZ||l.skewX||l.skewY)&&(a=!0),!a)return;const u={};l.z&&ve("z",o,u,this.animationValues);for(let c=0;c<ge.length;c++)ve(`rotate${ge[c]}`,o,u,this.animationValues),ve(`skew${ge[c]}`,o,u,this.animationValues);o.render();for(const c in u)o.setStaticValue(c,u[c]),this.animationValues&&(this.animationValues[c]=u[c]);o.scheduleRender()}applyProjectionStyles(o,a){if(!this.instance||this.isSVG)return;if(!this.isVisible){o.visibility="hidden";return}const l=this.getTransformTemplate();if(this.needsReset){this.needsReset=!1,o.visibility="",o.opacity="",o.pointerEvents=me(a==null?void 0:a.pointerEvents)||"",o.transform=l?l(this.latestValues,""):"none";return}const u=this.getLead();if(!this.projectionDelta||!this.layout||!u.target){this.options.layoutId&&(o.opacity=this.latestValues.opacity!==void 0?this.latestValues.opacity:1,o.pointerEvents=me(a==null?void 0:a.pointerEvents)||""),this.hasProjected&&!dt(this.latestValues)&&(o.transform=l?l({},""):"none",this.hasProjected=!1);return}o.visibility="";const c=u.animationValues||u.latestValues;this.applyTransformsToTarget();let f=jl(this.projectionDeltaWithTransform,this.treeScale,c);l&&(f=l(c,f)),o.transform=f;const{x:d,y:h}=this.projectionDelta;o.transformOrigin=`${d.origin*100}% ${h.origin*100}% 0`,u.animationValues?o.opacity=u===this?c.opacity??this.latestValues.opacity??1:this.preserveOpacity?this.latestValues.opacity:c.opacityExit:o.opacity=u===this?c.opacity!==void 0?c.opacity:"":c.opacityExit!==void 0?c.opacityExit:0;for(const g in Ke){if(c[g]===void 0)continue;const{correct:v,applyTo:p,isCSSVariable:T}=Ke[g],y=f==="none"?c[g]:v(c[g],u);if(p){const m=p.length;for(let b=0;b<m;b++)o[p[b]]=y}else T?this.options.visualElement.renderState.vars[g]=y:o[g]=y}this.options.layoutId&&(o.pointerEvents=u===this?me(a==null?void 0:a.pointerEvents)||"":"none")}clearSnapshot(){this.resumeFrom=this.snapshot=void 0}resetTree(){this.root.nodes.forEach(o=>{var a;return(a=o.currentAnimation)==null?void 0:a.stop()}),this.root.nodes.forEach(ai),this.root.sharedNodes.clear()}}}function Jl(t){t.updateLayout()}function tc(t){var s;const e=((s=t.resumeFrom)==null?void 0:s.snapshot)||t.snapshot;if(t.isLead()&&t.layout&&e&&t.hasListeners("didUpdate")){const{layoutBox:i,measuredBox:n}=t.layout,{animationType:r}=t.options,o=e.source!==t.layout.source;if(r==="size")ii(f=>{const d=o?e.measuredBox[f]:e.layoutBox[f],h=q(d);d.min=i[f].min,d.max=d.min+h});else if(r==="x"||r==="y"){const f=r==="x"?"y":"x";We(o?e.measuredBox[f]:e.layoutBox[f],i[f])}else Yn(r,e.layoutBox,i)&&ii(f=>{const d=o?e.measuredBox[f]:e.layoutBox[f],h=q(i[f]);d.max=d.min+h,t.relativeTarget&&!t.currentAnimation&&(t.isProjectionDirty=!0,t.relativeTarget[f].max=t.relativeTarget[f].min+h)});const a=_t();Et(a,i,e.layoutBox);const l=_t();o?Et(l,t.applyTransform(n,!0),e.measuredBox):Et(l,i,e.layoutBox);const u=!zn(a);let c=!1;if(!t.resumeFrom){const f=t.getClosestProjectingParent();if(f&&!f.resumeFrom){const{snapshot:d,layout:h}=f;if(d&&h){const g=t.options.layoutAnchor||void 0,v=O();se(v,e.layoutBox,d.layoutBox,g);const p=O();se(p,i,h.layoutBox,g),Un(v,p)||(c=!0),f.options.layoutRoot&&(t.relativeTarget=p,t.relativeTargetOrigin=v,t.relativeParent=f)}}}t.notifyListeners("didUpdate",{layout:i,snapshot:e,delta:l,layoutDelta:a,hasLayoutChanged:u,hasRelativeLayoutChanged:c})}else if(t.isLead()){const{onExitComplete:i}=t.options;i&&i()}t.options.transition=void 0}function ec(t){t.parent&&(t.isProjecting()||(t.isProjectionDirty=t.parent.isProjectionDirty),t.isSharedProjectionDirty||(t.isSharedProjectionDirty=!!(t.isProjectionDirty||t.parent.isProjectionDirty||t.parent.isSharedProjectionDirty)),t.isTransformDirty||(t.isTransformDirty=t.parent.isTransformDirty))}function sc(t){t.isProjectionDirty=t.isSharedProjectionDirty=t.isTransformDirty=!1}function ic(t){t.clearSnapshot()}function ai(t){t.clearMeasurements()}function nc(t){t.isLayoutDirty=!0,t.updateLayout()}function li(t){t.isLayoutDirty=!1}function oc(t){t.isAnimationBlocked&&t.layout&&!t.isLayoutDirty&&(t.snapshot=t.layout,t.isLayoutDirty=!0)}function rc(t){const{visualElement:e}=t.options;e&&e.getProps().onBeforeLayoutMeasure&&e.notify("BeforeLayoutMeasure"),t.resetTransform()}function ci(t){t.finishAnimation(),t.targetDelta=t.relativeTarget=t.target=void 0,t.isProjectionDirty=!0}function ac(t){t.resolveTargetDelta()}function lc(t){t.calcProjection()}function cc(t){t.resetSkewAndRotation()}function uc(t){t.removeLeadSnapshot()}function ui(t,e,s){t.translate=R(e.translate,0,s),t.scale=R(e.scale,1,s),t.origin=e.origin,t.originPoint=e.originPoint}function fi(t,e,s,i){t.min=R(e.min,s.min,i),t.max=R(e.max,s.max,i)}function fc(t,e,s,i){fi(t.x,e.x,s.x,i),fi(t.y,e.y,s.y,i)}function dc(t){return t.animationValues&&t.animationValues.opacityExit!==void 0}const hc={duration:.45,ease:[.4,0,.1,1]},di=t=>typeof navigator<"u"&&navigator.userAgent&&navigator.userAgent.toLowerCase().includes(t),hi=di("applewebkit/")&&!di("chrome/")?Math.round:ct;function pi(t){t.min=hi(t.min),t.max=hi(t.max)}function pc(t){pi(t.x),pi(t.y)}function Yn(t,e,s){return t==="position"||t==="preserve-aspect"&&!Ll(ei(e),ei(s),.2)}function mc(t){var e;return t!==t.root&&((e=t.scroll)==null?void 0:e.wasRoot)}const yc=Hn({attachResizeListener:(t,e)=>Xl(t,"resize",e),measureScroll:()=>{var t,e;return{x:document.documentElement.scrollLeft||((t=document.body)==null?void 0:t.scrollLeft)||0,y:document.documentElement.scrollTop||((e=document.body)==null?void 0:e.scrollTop)||0}},checkIsScrollRoot:()=>!0}),Te={current:void 0},Jc=Hn({measureScroll:t=>({x:t.scrollLeft,y:t.scrollTop}),defaultParent:()=>{if(!Te.current){const t=new yc({});t.mount(window),t.setOptions({layoutScroll:!0}),Te.current=t}return Te.current},resetTransform:(t,e)=>{t.style.transform=e!==void 0?e:"none"},checkIsScrollRoot:t=>window.getComputedStyle(t).position==="fixed"}),mi=(t,e)=>Math.abs(t-e);function tu(t,e){const s=mi(t.x,e.x),i=mi(t.y,e.y);return Math.sqrt(s**2+i**2)}/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const gc=t=>t.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase(),vc=t=>t.replace(/^([A-Z])|[\s-_]+(\w)/g,(e,s,i)=>i?i.toUpperCase():s.toLowerCase()),yi=t=>{const e=vc(t);return e.charAt(0).toUpperCase()+e.slice(1)},qn=(...t)=>t.filter((e,s,i)=>!!e&&e.trim()!==""&&i.indexOf(e)===s).join(" ").trim(),Tc=t=>{for(const e in t)if(e.startsWith("aria-")||e==="role"||e==="title")return!0};/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var bc={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:2,strokeLinecap:"round",strokeLinejoin:"round"};/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const xc=C.forwardRef(({color:t="currentColor",size:e=24,strokeWidth:s=2,absoluteStrokeWidth:i,className:n="",children:r,iconNode:o,...a},l)=>C.createElement("svg",{ref:l,...bc,width:e,height:e,stroke:t,strokeWidth:i?Number(s)*24/Number(e):s,className:qn("lucide",n),...!r&&!Tc(a)&&{"aria-hidden":"true"},...a},[...o.map(([u,c])=>C.createElement(u,c)),...Array.isArray(r)?r:[r]]));/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const U=(t,e)=>{const s=C.forwardRef(({className:i,...n},r)=>C.createElement(xc,{ref:r,iconNode:e,className:qn(`lucide-${gc(yi(t))}`,`lucide-${t}`,i),...n}));return s.displayName=yi(t),s};/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const _c=[["path",{d:"m12 19-7-7 7-7",key:"1l729n"}],["path",{d:"M19 12H5",key:"x3x0zl"}]],eu=U("arrow-left",_c);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const wc=[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["line",{x1:"12",x2:"12",y1:"8",y2:"12",key:"1pkeuh"}],["line",{x1:"12",x2:"12.01",y1:"16",y2:"16",key:"4dfq90"}]],su=U("circle-alert",wc);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ac=[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m9 12 2 2 4-4",key:"dzmm74"}]],iu=U("circle-check",Ac);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const kc=[["path",{d:"M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5",key:"mvr1a0"}]],nu=U("heart",kc);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Sc=[["path",{d:"M21 12a9 9 0 1 1-6.219-8.56",key:"13zald"}]],ou=U("loader-circle",Sc);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Vc=[["path",{d:"M12 19v3",key:"npa21l"}],["path",{d:"M15 9.34V5a3 3 0 0 0-5.68-1.33",key:"1gzdoj"}],["path",{d:"M16.95 16.95A7 7 0 0 1 5 12v-2",key:"cqa7eg"}],["path",{d:"M18.89 13.23A7 7 0 0 0 19 12v-2",key:"16hl24"}],["path",{d:"m2 2 20 20",key:"1ooewy"}],["path",{d:"M9 9v3a3 3 0 0 0 5.12 2.12",key:"r2i35w"}]],ru=U("mic-off",Vc);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Mc=[["path",{d:"M12 19v3",key:"npa21l"}],["path",{d:"M19 10v2a7 7 0 0 1-14 0v-2",key:"1vc78b"}],["rect",{x:"9",y:"2",width:"6",height:"13",rx:"3",key:"s6n7sd"}]],au=U("mic",Mc);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Cc=[["path",{d:"M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z",key:"10ikf1"}]],lu=U("play",Cc);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Pc=[["path",{d:"M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8",key:"v9h5vc"}],["path",{d:"M21 3v5h-5",key:"1q7to0"}],["path",{d:"M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16",key:"3uifl3"}],["path",{d:"M8 16H3v5",key:"1cv678"}]],cu=U("refresh-cw",Pc);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Dc=[["path",{d:"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8",key:"1357e3"}],["path",{d:"M3 3v5h5",key:"1xhq8a"}]],uu=U("rotate-ccw",Dc);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Rc=[["path",{d:"m12.5 17-.5-1-.5 1h1z",key:"3me087"}],["path",{d:"M15 22a1 1 0 0 0 1-1v-1a2 2 0 0 0 1.56-3.25 8 8 0 1 0-11.12 0A2 2 0 0 0 8 20v1a1 1 0 0 0 1 1z",key:"1o5pge"}],["circle",{cx:"15",cy:"12",r:"1",key:"1tmaij"}],["circle",{cx:"9",cy:"12",r:"1",key:"1vctgf"}]],fu=U("skull",Rc);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ec=[["path",{d:"M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z",key:"1s2grr"}],["path",{d:"M20 2v4",key:"1rf3ol"}],["path",{d:"M22 4h-4",key:"gwowj6"}],["circle",{cx:"4",cy:"20",r:"2",key:"6kqj1y"}]],du=U("sparkles",Ec);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Lc=[["path",{d:"M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z",key:"r04s7s"}]],hu=U("star",Lc);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ic=[["path",{d:"M10 14.66v1.626a2 2 0 0 1-.976 1.696A5 5 0 0 0 7 21.978",key:"1n3hpd"}],["path",{d:"M14 14.66v1.626a2 2 0 0 0 .976 1.696A5 5 0 0 1 17 21.978",key:"rfe1zi"}],["path",{d:"M18 9h1.5a1 1 0 0 0 0-5H18",key:"7xy6bh"}],["path",{d:"M4 22h16",key:"57wxv0"}],["path",{d:"M6 9a6 6 0 0 0 12 0V3a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1z",key:"1mhfuq"}],["path",{d:"M6 9H4.5a1 1 0 0 1 0-5H6",key:"tex48p"}]],pu=U("trophy",Ic);/**
 * @license lucide-react v0.562.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Bc=[["path",{d:"M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z",key:"uqj9uw"}],["path",{d:"M16 9a5 5 0 0 1 0 6",key:"1q6k2b"}],["path",{d:"M19.364 18.364a9 9 0 0 0 0-12.728",key:"ijwkga"}]],mu=U("volume-2",Bc);export{ct as $,eu as A,ye as B,su as C,jc as D,Vn as E,Xc as F,Mn as G,Jc as H,$c as I,ml as J,Fa as K,ou as L,au as M,K as N,ja as O,lu as P,_l as Q,cu as R,Gc as S,pu as T,cs as U,mu as V,il as W,Yc as X,_n as Y,Q as Z,R as _,iu as a,wa as a0,it as a1,Ye as a2,zc as a3,qe as a4,Uc as a5,me as a6,wt as a7,hn as a8,Bn as a9,Al as aa,J as ab,Nc as ac,Kc as ad,Fc as ae,P as af,qc as b,nu as c,ru as d,uu as e,fu as f,du as g,hu as h,Xl as i,xa as j,fn as k,Ln as l,xl as m,q as n,vt as o,ot as p,mt as q,ll as r,Hc as s,Zc as t,O as u,tu as v,ii as w,F as x,j as y,Wc as z};
