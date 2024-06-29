import{r as n,a as r,g as d,j as b,w,F as f,o as l,H as I,b as t,t as o,k as S,i as k,e as F,z as R,h as N,l as C}from"./app.4ddd96ac.js";import{_ as U}from"./AuthenticatedLayout.fa4baf59.js";import{M as B}from"./Modal.8b5669cd.js";import{t as P}from"./laravel-vue-pagination.es.1591e5c0.js";import{_ as u}from"./InputLabel.1475e8a3.js";import{_ as $}from"./TextInput.52f6b1fc.js";import{a as V}from"./index.1565c297.js";import{t as T}from"./trash.8221efe0.js";import{M as H}from"./ModalDelCar.b6fbb6ee.js";/* empty css                                              *//* empty css                                                    */const L={class:"mb-5 dark:text-white text-center"},j={key:0},z={id:"alert-2",class:"p-4 mb-4 bg-red-100 rounded-lg dark:bg-red-200 text-center",role:"alert"},E={class:"ml-3 text-sm font-medium text-red-700 dark:text-red-800"},q={class:"py-12"},G={class:"max-w-9xl mx-auto sm:px-6 lg:px-8"},J={class:"bg-white overflow-hidden shadow-sm sm:rounded-lg"},K={class:"p-6 dark:bg-gray-900"},O={class:"grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 lg:gap-1"},Q={class:"px-4"},W={value:"0",disabled:""},X=["value"],Y={className:"px-4"},Z={class:"px-4"},tt={className:"mb-4 mx-5"},et={class:"px-4"},at={className:"mb-4 mx-5"},st={className:"mb-4  mr-5 print:hidden"},ot=["disabled"],dt={key:0},rt={key:1},lt={className:"mb-4  mr-5 print:hidden"},nt=["disabled"],it={key:0},ut={key:1},mt={class:"relative overflow-x-auto shadow-md sm:rounded-lg mt-4"},pt={class:"w-full text-sm text-right text-gray-500 dark:text-gray-200 dark:text-gray-400 text-center"},ct={class:"text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 text-center"},_t={class:"bg-rose-500 text-gray-100 rounded-l-lg mb-2 sm:mb-0"},gt=t("th",{className:"px-1 py-2 text-base"},"#",-1),vt={className:"px-1 py-2 text-base"},yt={className:"px-1 py-2 text-base"},ht={className:"px-1 py-2 text-base"},bt={className:"px-1 py-2 text-base"},xt={className:"px-1 py-2 text-base"},ft={scope:"col",class:"px-1 py-2 text-base print:hidden",style:{width:"250px"}},kt={className:"px-4 py-2 border dark:border-gray-800 dark:text-gray-200"},$t={className:"px-4 py-2 border dark:border-gray-800 dark:text-gray-200"},wt={className:"px-4 py-2 border dark:border-gray-800 dark:text-gray-200"},Nt={className:"px-4 py-2 border dark:border-gray-800 dark:text-gray-200"},Ct={className:"px-4 py-2 border dark:border-gray-800 dark:text-gray-200"},Vt={className:"px-4 py-2 border dark:border-gray-800 dark:text-gray-200"},At={className:"px-4 py-2 border dark:border-gray-800 dark:text-gray-200"},Dt=["href"],Mt=["href"],It=["onClick"],St={class:"mt-3 text-center",style:{direction:"ltr"}},Gt={__name:"FormRegistrationCourt",props:{url:String,users:Array},setup(A){const i=n({}),m=n(0),g=n(0),p=n(0),c=n(0),v=n({}),y=n(0),h=async(s,a=1)=>{V.get(`/api/getIndexAccountsSelas?page=${a}&user_id=${m.value}&from=${p.value}&to=${c.value}`).then(e=>{i.value=e.data}).catch(e=>{console.error(e)})};function D(s={}){v.value=s,y.value=!0}function M(s){V.post("/api/deleteTransactions",s).then(a=>{y.value=!1,h()}).catch(a=>{console.error(a)})}let _=n(!1);return(s,a)=>(l(),r(f,null,[d(b(I),{title:"Dashboard"}),d(U,null,{default:w(()=>[d(H,{show:!!y.value,formData:v.value,onA:a[0]||(a[0]=e=>M(e)),onClose:a[1]||(a[1]=e=>y.value=!1)},{header:w(()=>[t("h2",L," \u0647\u0644 \u0645\u062A\u0623\u0643\u062F \u0645\u0646 \u062D\u0630\u0641 \u0627\u0644\u0639\u0645\u0644\u064A\u0629 \u0631\u0642\u0645 "+o(v.value.id)+" \u0628\u0642\u064A\u0645\u0629 "+o(v.value.amount)+" \u061F ",1)]),_:1},8,["show","formData"]),d(B,{show:!!b(_),data:b(_).toString(),onA:a[2]||(a[2]=e=>s.method1(e,s.arg1)),onClose:a[3]||(a[3]=e=>S(_)?_.value=!1:_=!1)},null,8,["show","data"]),s.$page.props.success?(l(),r("div",j,[t("div",z,[t("div",E,o(s.$page.props.success),1)])])):k("",!0),t("div",q,[t("div",G,[t("div",J,[t("div",K,[t("div",O,[t("div",Q,[d(u,{for:"to",class:"mb-1",value:"\u0643\u0634\u0641 \u062D\u0633\u0627\u0628"}),F(t("select",{onChange:a[4]||(a[4]=e=>h()),"onUpdate:modelValue":a[5]||(a[5]=e=>m.value=e),id:"default",class:"pr-8 bg-gray-50 border border-gray-300 text-gray-900 mb-6 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-red-500 dark:focus:border-red-500"},[t("option",W,o(s.$t("select_account")),1),(l(!0),r(f,null,N(A.users,(e,x)=>(l(),r("option",{key:x,value:e.id},o(e.name),9,X))),128))],544),[[R,m.value]])]),t("div",Y,[d(u,{for:"totalAmount",value:s.$t("Total_in_dollars")},null,8,["value"]),d($,{id:"totalAmount",type:"text",class:"mt-1 block w-full",modelValue:i.value.totalAmount,"onUpdate:modelValue":a[6]||(a[6]=e=>i.value.totalAmount=e),disabled:""},null,8,["modelValue"])]),t("div",Z,[t("div",tt,[d(u,{for:"from",value:s.$t("from_date")},null,8,["value"]),d($,{id:"from",type:"date",class:"mt-1 block w-full",modelValue:p.value,"onUpdate:modelValue":a[7]||(a[7]=e=>p.value=e)},null,8,["modelValue"])])]),t("div",et,[t("div",at,[d(u,{for:"to",value:s.$t("to_date")},null,8,["value"]),d($,{id:"to",type:"date",class:"mt-1 block w-full",modelValue:c.value,"onUpdate:modelValue":a[8]||(a[8]=e=>c.value=e)},null,8,["modelValue"])])]),t("div",st,[d(u,{for:"pay",value:"\u0641\u0644\u062A\u0631\u0629"}),t("button",{onClick:a[9]||(a[9]=C(e=>h(),["prevent"])),disabled:g.value||!parseInt(m.value),class:"px-6 mb-12 py-2 mt-1 font-bold text-white bg-gray-500 rounded",style:{width:"100%"}},[g.value?(l(),r("span",rt,"\u062C\u0627\u0631\u064A \u0627\u0644\u062D\u0641\u0638...")):(l(),r("span",dt,"\u0641\u0644\u062A\u0631\u0629"))],8,ot)]),t("div",lt,[d(u,{for:"pay",value:"\u0637\u0628\u0627\u0639\u0629"}),t("button",{onClick:a[10]||(a[10]=C(e=>s.confirmAddPaymentTotal(s.total,s.client_id),["prevent"])),disabled:g.value||!parseInt(m.value),class:"px-6 mb-12 py-2 mt-1 font-bold text-white bg-orange-500 rounded",style:{width:"100%"}},[g.value?(l(),r("span",ut,"\u062C\u0627\u0631\u064A \u0627\u0644\u062D\u0641\u0638...")):(l(),r("span",it,"\u0637\u0628\u0627\u0639\u0629"))],8,nt)])]),t("div",mt,[t("table",pt,[t("thead",ct,[t("tr",_t,[gt,t("th",vt,o(s.$t("no")),1),t("th",yt,o(s.$t("type")),1),t("th",ht,o(s.$t("date")),1),t("th",bt,o(s.$t("description")),1),t("th",xt,o(s.$t("amount")),1),t("th",ft,o(s.$t("execute")),1)])]),t("tbody",null,[(l(!0),r(f,null,N(i.value.transactions,(e,x)=>(l(),r("tr",{key:e.id,class:"text-center"},[t("td",kt,o(x),1),t("td",$t,o(e.id),1),t("td",wt,o(e.type),1),t("td",Nt,o(e.created),1),t("td",Ct,o(e.description),1),t("td",Vt,o(e.amount),1),t("td",At,[e.type=="out"&&e.amount<0?(l(),r("a",{key:0,target:"_blank",href:`/api/getIndexAccountsSelas?user_id=${i.value.client.id}&from=${p.value}&to=${c.value}&print=2&transactions_id=${e.id}`,tabIndex:"1",class:"px-4 py-1 text-white m-1 bg-green-500 rounded"}," \u0637\u0628\u0627\u0639\u0629 \u0648\u0635\u0644 \u0642\u0628\u0636 ",8,Dt)):k("",!0),e.type=="in"&&e.amount?(l(),r("a",{key:1,target:"_blank",href:`/api/getIndexAccountsSelas?user_id=${i.value.client.id}&from=${p.value}&to=${c.value}&print=3&transactions_id=${e.id}`,tabIndex:"1",class:"px-4 py-1 text-white m-1 bg-purple-500 rounded"}," \u0637\u0628\u0627\u0639\u0629 \u0648\u0635\u0644 \u062F\u0641\u0639 ",8,Mt)):k("",!0),t("button",{tabIndex:"1",class:"px-1 py-1 text-white mx-1 bg-orange-500 rounded",onClick:Ft=>D(e)},[d(T)],8,It)])]))),128))])])]),t("div",St,[d(b(P),{data:i.value,onPaginationChangePage:h,limit:10},null,8,["data"])])])])])])]),_:1})],64))}};export{Gt as default};
