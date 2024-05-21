import{r as h,D as te,o as m,c as A,w as _,a as p,b as e,d as S,g as v,j as u,k as f,i as x,T as F,t as l,x as se,y as oe,F as N,H as ae,n as E,e as H,v as re,z as le,h as q}from"./app.15fd251d.js";import{a as O,_ as ne}from"./AuthenticatedLayout.5b4e04d4.js";import"./vue-tailwind-datepicker.0047d8d9.js";import{a as w}from"./index.7eee7884.js";/* empty css                                                                 */import{q as z}from"./VueSearchSelect.d2fccdf2.js";/* empty css                                                            */import{_ as de}from"./ModalAddCarExpenses.308bc751.js";import{M as ie}from"./ModalDelCar.7238b510.js";import{n as ce}from"./new.f49fc1d7.js";import{s as ue}from"./show.ab3a6e87.js";import{t as me}from"./trash.7eee3ded.js";import{p as pe}from"./print.65fc4a06.js";import{W as he}from"./v3-infinite-loading.es.5f4fe2ad.js";import{d as ge}from"./debounce.83ff13d6.js";/* empty css                                                            *//* empty css                                                    */const ve={key:0,class:"modal-mask"},fe={class:"modal-wrapper max-h-[80vh]"},xe={class:"modal-container dark:bg-gray-900 overflow-auto max-h-[80vh]"},ye={class:"modal-header"},_e={class:"text-center dark:text-gray-200"},be={class:"modal-body"},ke={key:0,class:"grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2"},we={class:"mb-4 mx-1"},$e=e("label",{class:"dark:text-gray-200",for:"color_id"}," \u0635\u0627\u062D\u0628 \u0627\u0644\u0633\u064A\u0627\u0631\u0629 ",-1),Ce={class:"relative"},De={key:1,class:"grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2"},Ae={class:"mb-4 mx-1"},Me=e("label",{class:"dark:text-gray-200",for:"color_id"}," \u0627\u062E\u062A\u0631 \u0627\u0644\u0633\u064A\u0627\u0631\u0629 ",-1),Ve={class:"relative"},Be={key:2,class:"grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2 mt-5"},Ie=["disabled"],Ne={class:"modal-footer my-2"},Ee={class:"flex flex-row m-auto"},Se={__name:"ModalAddCarExpensesFav",props:{show:Boolean,formData:Object,client:Array,saveCar:Boolean},setup(n){function d(){const i=new Date,c=i.getFullYear(),r=String(i.getMonth()+1).padStart(2,"0"),b=String(i.getDate()).padStart(2,"0");return`${c}-${r}-${b}`}let a=h(0);h(!0);let g=h([]);return te(a,(i,c)=>{w.get("/api/getIndexCar",{params:{limit:1e3,user_id:i,car_have_expenses:0}}).then(r=>{g.value=r.data.data}).catch(r=>{console.error(r)})}),(i,c)=>(m(),A(F,{name:"modal"},{default:_(()=>[n.show?(m(),p("div",ve,[e("div",fe,[e("div",xe,[e("div",ye,[S(i.$slots,"header",{},()=>[e("h2",_e,l(i.$t("add_car")),1)])]),e("div",be,[n.formData.id?x("",!0):(m(),p("div",ke,[e("div",we,[$e,e("div",Ce,[v(u(z),{optionValue:"id",optionText:"name",modelValue:u(a),"onUpdate:modelValue":c[0]||(c[0]=r=>f(a)?a.value=r:a=r),list:n.client,placeholder:"\u062A\u062D\u062F\u064A\u062F \u0635\u0627\u062D\u0628 \u0627\u0644\u0633\u064A\u0627\u0631\u0629"},null,8,["modelValue","list"])])])])),u(g)[0]?(m(),p("div",De,[e("div",Ae,[Me,e("div",Ve,[v(u(z),{optionValue:"id",customText:r=>`${r.car_type} - ${r.car_color} -\u0643\u0627\u062A\u064A  ${r.car_number}-\u0634\u0627\u0646\u0635\u0649 ${r.vin}`,modelValue:n.formData.carId,"onUpdate:modelValue":c[1]||(c[1]=r=>n.formData.carId=r),list:u(g),placeholder:" \u0627\u062E\u062A\u0631 \u0627\u0644\u0633\u064A\u0627\u0631\u0629"},null,8,["customText","modelValue","list"])])])])):x("",!0),n.saveCar?x("",!0):(m(),p("div",Be,[e("button",{class:"modal-default-button py-3 bg-blue-500 rounded col-6",onClick:c[2]||(c[2]=r=>{n.formData.date=n.formData.date?n.formData.date:d(),i.$emit("a",n.formData),f(a)?a.value=0:a=0}),disabled:!u(a)&&!n.formData.carId}," \u0625\u0636\u0627\u0641\u0629 \u0648\u0645\u062A\u0627\u0628\u0639\u0629 ",8,Ie)]))]),e("div",Ne,[e("div",Ee,[e("button",{class:"modal-default-button py-3 bg-rose-500 rounded col-6",onClick:c[3]||(c[3]=r=>{i.$emit("close"),f(a)?a.value="":a=""})}," \u0625\u063A\u0644\u0627\u0642 ")])])])])])):x("",!0)]),_:3}))}},Fe={props:{show:Boolean,formData:Object}},Te={key:0,class:"modal-mask"},je={class:"modal-wrapper max-h-[80vh]"},He={class:"modal-container dark:bg-gray-900 overflow-auto max-h-[80vh]"},qe={class:"modal-header"},ze={class:"text-center py-5 dark:text-white"},Oe={class:"modal-footer my-2"},Ue={class:"flex flex-row"},We={class:"basis-1/2 px-4"},Re={class:"basis-1/2 px-4"};function Ze(n,d,a,g,i,c){return m(),A(F,{name:"modal"},{default:_(()=>[a.show?(m(),p("div",Te,[e("div",je,[e("div",He,[e("div",qe,[S(n.$slots,"header"),e("h2",ze,"\u0646\u0642\u0644 \u0627\u0644\u0649 \u0627\u0644\u0627\u0631\u0634\u064A\u0641 \u0644\u0644\u0633\u064A\u0627\u0631\u0629 "+l(a.formData.car_type+" \u0634\u0627\u0646\u0635"+a.formData.vin+" \u0631\u0642\u0645"+a.formData.car_number),1)]),e("div",Oe,[e("div",Ue,[e("div",We,[e("button",{class:"modal-default-button py-3 bg-gray-500 rounded",onClick:d[0]||(d[0]=r=>{n.$emit("close")})},"\u062A\u0631\u0627\u062C\u0639")]),e("div",Re,[e("button",{class:"modal-default-button py-3 bg-rose-500 rounded col-6",onClick:d[1]||(d[1]=r=>{n.$emit("a",a.formData)})},"\u0646\u0639\u0645")])])])])])])):x("",!0)]),_:3})}const Ge=O(Fe,[["render",Ze]]),Le={props:{show:Boolean,formData:Object}},Pe={key:0,class:"modal-mask"},Ye={class:"modal-wrapper max-h-[80vh]"},Je={class:"modal-container dark:bg-gray-900 overflow-auto max-h-[80vh]"},Ke={class:"modal-header"},Qe={class:"text-center py-5 dark:text-white"},Xe={class:"modal-footer my-2"},et={class:"flex flex-row"},tt={class:"basis-1/2 px-4"},st={class:"basis-1/2 px-4"};function ot(n,d,a,g,i,c){return m(),A(F,{name:"modal"},{default:_(()=>[a.show?(m(),p("div",Pe,[e("div",Ye,[e("div",Je,[e("div",Ke,[S(n.$slots,"header"),e("h2",Qe,"\u0646\u0642\u0644 \u0627\u0644\u0649 \u0642\u064A\u062F \u0627\u0644\u0639\u0645\u0644 \u0644\u0644\u0633\u064A\u0627\u0631\u0629 "+l(a.formData.car_type+" \u0634\u0627\u0646\u0635"+a.formData.vin+" \u0631\u0642\u0645"+a.formData.car_number),1)]),e("div",Xe,[e("div",et,[e("div",tt,[e("button",{class:"modal-default-button py-3 bg-gray-500 rounded",onClick:d[0]||(d[0]=r=>{n.$emit("close")})},"\u062A\u0631\u0627\u062C\u0639")]),e("div",st,[e("button",{class:"modal-default-button py-3 bg-rose-500 rounded col-6",onClick:d[1]||(d[1]=r=>{n.$emit("a",a.formData)})},"\u0646\u0639\u0645")])])])])])])):x("",!0)]),_:3})}const at=O(Le,[["render",ot]]),rt=e("h2",{class:"mb-5 dark:text-gray-400 text-center"}," \u0647\u0644 \u0645\u062A\u0623\u0643\u062F \u0645\u0646 \u062D\u0630\u0641 \u0627\u0644\u0633\u064A\u0627\u0631\u0629 \u061F ",-1),lt={key:0,class:"py-2"},nt={class:"sm:px-6 lg:px-8 text-sm font-medium text-center text-gray-500 rounded-lg flex dark:divide-gray-700 dark:text-gray-400"},dt={class:"w-full"},it={class:"w-full"},ct={class:"max-w-9xl mx-auto sm:px-6 lg:px-8"},ut={class:"bg-white overflow-hidden shadow-sm"},mt={class:"p-6 dark:bg-gray-900"},pt={class:"flex flex-col"},ht={class:"grid grid-cols-2 md:grid-cols-5 lg:grid-cols-5 gap-2 lg:gap-1"},gt={class:"flex items-center max-w-5xl"},vt=e("label",{class:"dark:text-gray-200",for:"simple-search"},null,-1),ft={class:"relative w-full"},xt=e("div",{class:"absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"},[e("svg",{"aria-hidden":"true",class:"w-5 h-5 text-gray-500 dark:text-gray-200 dark:text-gray-400",fill:"currentColor",viewBox:"0 0 20 20",xmlns:"http://www.w3.org/2000/svg"},[e("path",{"fill-rule":"evenodd",d:"M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z","clip-rule":"evenodd"})])],-1),yt={class:"text-center"},_t={value:"0",disabled:""},bt={value:""},kt=["value"],wt=e("div",null,null,-1),$t={class:"overflow-x-auto shadow-md sm:rounded-lg mt-4 mb-5"},Ct={class:"w-full text-sm text-right text-gray-500 dark:text-gray-200 dark:text-gray-400 text-center divide-y divide-gray-200 dark:divide-gray-800"},Dt={class:"text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 text-center"},At={class:"bg-emerald-600 text-gray-100"},Mt={scope:"col",class:"px-3 py-2 sm:px-4 sm:py-2"},Vt={scope:"col",class:"px-3 py-2 sm:px-4 sm:py-2"},Bt={scope:"col",class:"px-3 py-2 sm:px-4 sm:py-2"},It={scope:"col",class:"px-3 py-2 sm:px-4 sm:py-2"},Nt={scope:"col",class:"px-3 py-2 sm:px-4 sm:py-2"},Et={scope:"col",class:"px-3 py-2 sm:px-4 sm:py-2"},St={scope:"col",class:"px-3 py-2 sm:px-4 sm:py-2"},Ft=e("th",{scope:"col",class:"px-3 py-2 sm:px-4 sm:py-2"}," \u0645\u062F\u0641\u0648\u0639 \u062F\u0648\u0644\u0627\u0631 ",-1),Tt=e("th",{scope:"col",class:"px-3 py-2 sm:px-4 sm:py-2"}," \u0645\u062F\u0641\u0648\u0639 \u062F\u064A\u0646\u0627\u0631 ",-1),jt={scope:"col",class:"px-3 py-2 sm:px-4 sm:py-2",style:{width:"240px"}},Ht={className:"px-3 py-2 sm:px-4 sm:py-2 text-center"},qt={className:"px-3 py-2 sm:px-4 sm:py-2 text-center"},zt={className:"px-3 py-2 sm:px-4 sm:py-2 text-center"},Ot={className:"px-3 py-2 sm:px-4 sm:py-2 text-center"},Ut={className:"px-3 py-2 sm:px-4 sm:py-2 text-center"},Wt={className:"px-3 py-2 sm:px-4 sm:py-2 text-center"},Rt={className:"px-3 py-2 sm:px-4 sm:py-2 text-center"},Zt={className:"px-3 py-2 sm:px-4 sm:py-2 text-center"},Gt={className:"px-3 py-2 sm:px-4 sm:py-2 text-center"},Lt={className:"px-3 py-2 sm:px-4 sm:py-2 text-center"},Pt=["onClick"],Yt=["onClick"],Jt=e("svg",{class:"w-6 h-6 text-white","aria-hidden":"true",xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 20 16"},[e("path",{stroke:"currentColor","stroke-linejoin":"round","stroke-width":"2",d:"M8 8v1h4V8m4 7H4a1 1 0 0 1-1-1V5h14v9a1 1 0 0 1-1 1ZM2 1h16a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1Z"})],-1),Kt=[Jt],Qt=["onClick"],Xt=e("svg",{class:"w-6 h-6 text-white","aria-hidden":"true",xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 16 16"},[e("path",{stroke:"currentColor","stroke-linecap":"round","stroke-linejoin":"round","stroke-width":"2",d:"M4 8h11m0 0-4-4m4 4-4 4m-5 3H3a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h3"})],-1),es=[Xt],ts=["onClick"],ss=["href"],os={class:"spaner"},as=e("div",null,null,-1),ks={__name:"index",props:{client:Array},setup(n){se();const d=h({}),a=oe();h("");let g=h(!1),i=h(!1),c=h(!1),r=h(!1),b=h(!1),$=h([]);function U(o={}){g.value=!0}function W(o={}){d.value=o,i.value=!0}function R(o={}){d.value=o,c.value=!0}function Z(o={}){d.value=o,r.value=!0}function G(o={}){d.value=o,b.value=!0}const y=h(!0);let V=h(!1),C=0,B=1,D="";const k=()=>{B=0,$.value.length=0,V.value=!V.value},T=ge(k,500),L=async o=>{try{const s=(await w.get("/getIndexCar",{params:{limit:100,page:B,q:D,user_id:C,car_have_expenses:y.value?1:2}})).data;s.data.length<100?($.value.push(...s.data),o.complete()):($.value.push(...s.data),o.loaded()),B++}catch(t){console.log(t)}};function P(o){w.post("/api/confirmExpensesCar",o).then(t=>{i.value=!1,a.success("\u062A\u0645 \u0625\u0636\u0627\u0641\u0629 \u0627\u0644\u0633\u064A\u0627\u0631\u0629 \u0628\u0646\u062C\u0627\u062D ",{timeout:3e3,position:"bottom-right",rtl:!0}),k()}).catch(t=>{console.error(t)})}function Y(o){return o.reduce((t,s)=>t+(s.amount_dollar||0),0)}function J(o){return o.reduce((t,s)=>t+(s.amount_dinar||0),0)}function K(o){w.post("/api/addCarFavorite",o).then(t=>{i.value=!1,a.success("\u062A\u0645 \u0625\u0636\u0627\u0641\u0629 \u0627\u0644\u0633\u064A\u0627\u0631\u0629 \u0628\u0646\u062C\u0627\u062D ",{timeout:3e3,position:"bottom-right",rtl:!0}),k(),g.value=!1}).catch(t=>{console.error(t)})}function Q(o){w.post("/api/confirmArchiveCar",o).then(t=>{i.value=!1,a.success("\u062A\u0645 \u0646\u0642\u0644 \u0627\u0644\u0633\u064A\u0627\u0631\u0629 \u0628\u0646\u062C\u0627\u062D ",{timeout:3e3,position:"bottom-right",rtl:!0}),k(),c.value=!1}).catch(t=>{console.error(t)})}function X(o){w.post("/api/confirmArchiveCarBack",o).then(t=>{r.value=!1,a.success("\u062A\u0645 \u0646\u0642\u0644 \u0627\u0644\u0633\u064A\u0627\u0631\u0629 \u0628\u0646\u062C\u0627\u062D ",{timeout:3e3,position:"bottom-right",rtl:!0}),k(),c.value=!1}).catch(t=>{console.error(t)})}function j(o){y.value=o,k()}function ee(o){w.post("/api/confirmDelCarFav",o).then(t=>{b.value=!1,a.success("\u062D\u0630\u0641 \u0627\u0644\u0633\u064A\u0627\u0631\u0629 \u0628\u0646\u062C\u0627\u062D",{timeout:3e3,position:"bottom-right",rtl:!0}),k()}).catch(t=>{console.error(t)})}return(o,t)=>(m(),p(N,null,[v(u(ae),{title:"Dashboard"}),v(Ge,{formData:d.value,show:!!u(c),onA:t[0]||(t[0]=s=>Q(s)),onClose:t[1]||(t[1]=s=>f(c)?c.value=!1:c=!1)},{header:_(()=>[]),_:1},8,["formData","show"]),v(at,{formData:d.value,show:!!u(r),onA:t[2]||(t[2]=s=>X(s)),onClose:t[3]||(t[3]=s=>f(r)?r.value=!1:r=!1)},{header:_(()=>[]),_:1},8,["formData","show"]),v(Se,{formData:d.value,show:!!u(g),client:n.client,onA:t[4]||(t[4]=s=>K(s)),onClose:t[5]||(t[5]=s=>f(g)?g.value=!1:g=!1)},{header:_(()=>[]),_:1},8,["formData","show","client"]),v(de,{formData:d.value,show:!!u(i),currentWork:y.value,onA:t[6]||(t[6]=s=>P(s)),onClose:t[7]||(t[7]=s=>f(i)?i.value=!1:i=!1)},{header:_(()=>[]),_:1},8,["formData","show","currentWork"]),v(ie,{show:!!u(b),formData:d.value,onA:t[8]||(t[8]=s=>ee(s)),onClose:t[9]||(t[9]=s=>f(b)?b.value=!1:b=!1)},{header:_(()=>[rt]),_:1},8,["show","formData"]),v(ne,null,{default:_(()=>[o.$page.props.auth.user.type_id==1||o.$page.props.auth.user.type_id==7?(m(),p("div",lt,[e("ul",nt,[e("li",dt,[e("button",{onClick:t[10]||(t[10]=s=>j(!0)),class:E(["inline-block w-full p-4 border-r border-gray-200 dark:border-gray-700 hover:text-gray-700 hover:bg-gray-50 focus:outline-none dark:text-white",y.value?"bg-white  dark:bg-gray-900":"dark:bg-gray-800 dark:hover:bg-gray-700"])},"\u0642\u064A\u062F \u0627\u0644\u0639\u0645\u0644 ",2)]),e("li",it,[e("button",{onClick:t[11]||(t[11]=s=>j(!1)),class:E(["inline-block w-full p-4 border-r border-gray-200 dark:border-gray-700 hover:text-gray-700 hover:bg-gray-50 focus:outline-none dark:text-white",y.value?"dark:bg-gray-800 dark:hover:bg-gray-700":"bg-white  dark:bg-gray-900"])},"\u0627\u0644\u0633\u064A\u0627\u0631\u0629 \u0627\u0644\u0645\u0643\u062A\u0645\u0644\u0629",2)])]),e("div",ct,[e("div",ut,[e("div",mt,[e("div",pt,[e("div",ht,[e("div",null,[e("form",gt,[vt,e("div",ft,[xt,H(e("input",{"onUpdate:modelValue":t[12]||(t[12]=s=>f(D)?D.value=s:D=s),onInput:t[13]||(t[13]=(...s)=>u(T)&&u(T)(...s)),type:"text",id:"simple-search",class:"bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500",placeholder:"\u0628\u062D\u062B",required:""},null,544),[[re,u(D)]])])])]),e("div",yt,[e("button",{type:"button",onClick:t[14]||(t[14]=s=>U()),style:{"min-width":"150px"},className:"px-6 mb-12 mx-2 py-2 font-bold text-white bg-green-500 rounded"},l(o.$t("addCar")),1)]),e("div",null,[H(e("select",{onChange:t[15]||(t[15]=s=>k()),"onUpdate:modelValue":t[16]||(t[16]=s=>f(C)?C.value=s:C=s),id:"default",class:"pr-8 bg-gray-50 border border-gray-300 text-gray-900 mb-6 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-red-500 dark:focus:border-red-500"},[e("option",_t,l(o.$t("selectCustomer")),1),e("option",bt,l(o.$t("allOwners")),1),(m(!0),p(N,null,q(n.client,(s,M)=>(m(),p("option",{key:M,value:s.id},l(s.name),9,kt))),128))],544),[[le,u(C)]])])]),e("div",null,[wt,e("div",$t,[e("table",Ct,[e("thead",Dt,[e("tr",At,[e("th",Mt,l(o.$t("no")),1),e("th",Vt,l(o.$t("car_owner")),1),e("th",Bt,l(o.$t("car_type")),1),e("th",It,l(o.$t("year")),1),e("th",Nt,l(o.$t("color")),1),e("th",Et,l(o.$t("vin")),1),e("th",St,l(o.$t("car_number")),1),Ft,Tt,e("th",jt,l(o.$t("execute")),1)])]),e("tbody",null,[(m(!0),p(N,null,q(u($),s=>{var M;return m(),p("tr",{key:s.id,class:E([s.results==0?"":s.results==1?"bg-red-100 dark:bg-red-900":"bg-green-100 dark:bg-green-900","bg-white border-b dark:bg-gray-900 dark:border-gray-900 hover:bg-gray-50 dark:hover:bg-gray-600"])},[e("td",Ht,l(s.no),1),e("td",qt,l((M=s.client)==null?void 0:M.name),1),e("td",zt,l(s.car_type),1),e("td",Ot,l(s.year),1),e("td",Ut,l(s.car_color),1),e("td",Wt,l(s.vin),1),e("td",Rt,l(s.car_number),1),e("td",Zt,l(Y(s.carexpenses)),1),e("td",Gt,l(J(s.carexpenses)),1),e("td",Lt,[e("button",{tabIndex:"1",class:"px-2 py-1 text-white mx-1 bg-emerald-500 rounded",onClick:I=>W(s)},[y.value?(m(),A(ce,{key:0})):(m(),A(ue,{key:1}))],8,Pt),y.value?(m(),p("button",{key:0,tabIndex:"1",class:"px-2 py-1 text-white mx-1 bg-pink-600 rounded mt-3 sm:mt-0",onClick:I=>R(s)},Kt,8,Yt)):x("",!0),y.value?x("",!0):(m(),p("button",{key:1,tabIndex:"1",class:"px-2 py-1 text-white mx-1 bg-pink-600 rounded mt-3 sm:mt-0",onClick:I=>Z(s)},es,8,Qt)),y.value?(m(),p("button",{key:2,tabIndex:"1",class:"px-2 py-1 text-white mx-1 bg-orange-500 rounded mt-3 sm:mt-0",onClick:I=>G(s)},[v(me)],8,ts)):x("",!0),e("a",{target:"_blank",style:{display:"inline-flex"},href:`/api/getIndexExpensesPrint?car_id=${s.id}`,tabIndex:"1",class:"px-2 py-1 text-white m-1 bg-blue-500 rounded"},[v(pe)],8,ss)])],2)}),128))])])]),e("div",os,[v(u(he),{car:u($),onInfinite:L,identifier:u(V)},null,8,["car","identifier"])])])])])])])])):x("",!0),as]),_:1})],64))}};export{ks as default};
