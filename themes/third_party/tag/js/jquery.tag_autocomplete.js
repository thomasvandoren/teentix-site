/*
 * THIS IS NOT THE DEFAULT AUTOCOMPLETE
 * this file has been changed to better work with ie7 and to respect arrow keys and mousecilcks
 * this file is not interchangable with jquery.ui.autocomplete or the last revised autocomplete 1.1 mentioned below
 * THIS HAS ALSO BEEN MODIFIED TO BE PREFIXED WITH 'tag_' and 'Tag_ in all public aspects
 * this has been recompressed with yui compliler because i dont like packer -gf	
 */


(function(b){var a={randomHash:"f1249759b77e529a6c49b9321dd58128"};b.fn.extend({tag_autocomplete:function(c,d){var e=typeof c=="string";d=b.extend({},b.Tag_Autocompleter.defaults,{url:e?c:null,data:e?null:c,delay:e?b.Tag_Autocompleter.defaults.delay:10,max:d&&!d.scroll?10:150},d);d.highlight=d.highlight||function(f){return f;};d.formatMatch=d.formatMatch||d.formatItem;return this.each(function(){new b.Tag_Autocompleter(this,d);});},tag_result:function(c){return this.bind("tag_result",c);},tag_search:function(c){return this.trigger("tag_search",[c]);},tag_flushCache:function(){return this.trigger("tag_flushCache");},tag_setOptions:function(c){return this.trigger("tag_setOptions",[c]);},tag_unautocomplete:function(){return this.trigger("tag_unautocomplete");}});b.Tag_Autocompleter=function(m,h){var d={LEFT:37,UP:38,RIGHT:39,DOWN:40,DEL:46,TAB:9,RETURN:13,ESC:27,COMMA:188,PAGEUP:33,PAGEDOWN:34,BACKSPACE:8,SHIFT:16,CONTROL:17,ALT:18};a.multipleSeparator=h.multipleSeparator;var c=b(m).attr("autocomplete","off").addClass(h.inputClass);var k;var s="";var n=b.Tag_Autocompleter.Cache(h);var f=0;var w;var z={mouseDownOnSelect:false};var r=0;var t=b.Tag_Autocompleter.Select(h,m,e,z);var y;b.browser.opera&&b(m.form).bind("submit.autocomplete",function(){if(y){y=false;return false;}});c.bind((b.browser.opera?"keypress":"keydown")+".autocomplete",function(A){f=1;w=A.keyCode;switch(A.keyCode){case d.UP:if(t.visible()){A.preventDefault();t.prev();}else{}break;case d.DOWN:if(t.visible()){A.preventDefault();t.next();}else{}break;case d.PAGEUP:A.preventDefault();if(t.visible()){t.pageUp();}else{v(0,true);}break;case d.PAGEDOWN:A.preventDefault();if(t.visible()){t.pageDown();}else{v(0,true);}break;case d.LEFT:case d.RIGHT:case d.BACKSPACE:case d.SHIFT:case d.CONTROL:case d.ALT:break;case h.multiple&&b.trim(h.multipleSeparator)==","&&d.COMMA:case d.TAB:case d.RETURN:if(e()){A.preventDefault();y=true;return false;}break;case d.ESC:t.hide();break;default:clearTimeout(k);k=setTimeout(v,h.delay);break;}}).focus(function(){f++;}).blur(function(){f=0;if(!z.mouseDownOnSelect){u();}}).click(function(){if(f++>1&&!t.visible()){}}).bind("tag_search",function(){var A=(arguments.length>1)?arguments[1]:null;function B(F,E){var C;if(E&&E.length){for(var D=0;D<E.length;D++){if(E[D].result.toLowerCase()==F.toLowerCase()){C=E[D];break;}}}if(typeof A=="function"){A(C);}else{c.trigger("tag_result",C&&[C.data,C.value]);}}b.each(i(c.val()),function(C,D){g(D,B,B);});}).bind("tag_flushCache",function(){n.flush();}).bind("tag_setOptions",function(){b.extend(h,arguments[1]);if("data" in arguments[1]){n.populate();}}).bind("tag_unautocomplete",function(){t.unbind();c.unbind();b(m.form).unbind(".autocomplete");});function e(){var D=t.selected();if(!D){return false;}var A=D.result;s=A;if(h.multiple){var G=i(b.trim(c.val()));if(G.length>1){var C=h.multipleSeparator.length;var F=r;var E=(G.length-1);var B=0;b.each(G,function(H,I){B+=I.length;if(F<=B){E=H;return false;}B+=C;});G[E]=A;A=G.join(h.multipleSeparator);}A+=h.multipleSeparator;}c.val(A);x();c.trigger("tag_result",[D.data,D.value]);return true;}function v(C,B){r=b(m).selection().start;if(w==d.DEL){t.hide();return;}var A=c.val();if(!B&&A==s){return;}s=A;A=j(A);if(A.length>=h.minChars){c.addClass(h.loadingClass);if(!h.matchCase){A=A.toLowerCase();}g(A,l,x);}else{o();t.hide();}}function i(A){if(!A){return[""];}if(!h.multiple){return[b.trim(A)];}return b.map(A.split(h.multipleSeparator),function(B){return b.trim(A).length?b.trim(B):null;});}function j(A){if(!h.multiple){return A;}var C=i(A);if(C.length==1){return C[0];}var B=b(m).selection().start;if(B==A.length){C=i(A);}else{C=i(A.replace(A.substring(B),""));}return C[C.length-1];}function q(A,B){if(h.autoFill&&(j(c.val()).toLowerCase()==A.toLowerCase())&&w!=d.BACKSPACE){c.val(c.val()+B.substring(j(s).length));b(m).selection(s.length,s.length+B.length);}}function u(){clearTimeout(k);k=setTimeout(x,200);}function x(){var A=t.visible();t.hide();clearTimeout(k);o();if(h.mustMatch){c.tag_search(function(B){if(!B){if(h.multiple){var C=i(c.val()).slice(0,-1);c.val(C.join(h.multipleSeparator)+(C.length?h.multipleSeparator:""));}else{c.val("");c.trigger("tag_result",null);}}});}}function l(B,A){if(A&&A.length&&f){o();t.display(A,B);q(B,A[0].value);t.show();}else{x();}}function g(B,D,A){if(!h.matchCase){B=B.toLowerCase();}var C=n.load(B);if(C&&C.length){D(B,C);}else{if((typeof h.url=="string")&&(h.url.length>0)){var E={timestamp:+new Date()};b.each(h.extraParams,function(F,G){E[F]=typeof G=="function"?G():G;});b.ajax({type:"POST",mode:"abort",port:"autocomplete"+m.name,dataType:h.dataType,url:h.url,data:b.extend({q:j(B),limit:h.max},E),success:function(G){var F=h.parse&&h.parse(G)||p(G);n.add(B,F);D(B,F);}});}else{t.emptyList();A(B);}}}function p(D){var A=[];var C=D.split("\n");for(var B=0;B<C.length;B++){var E=b.trim(C[B]);if(E){E=E.split("|");A[A.length]={data:E,value:E[0],result:h.formatResult&&h.formatResult(E,E[0])||E[0]};}}return A;}function o(){c.removeClass(h.loadingClass);}};b.Tag_Autocompleter.defaults={inputClass:"tag_ac_input",resultsClass:"tag_ac_results",loadingClass:"tag_ac_loading",minChars:1,delay:400,matchCase:false,matchSubset:true,matchContains:false,cacheLength:10,max:100,mustMatch:false,extraParams:{},selectFirst:true,formatItem:function(c){return c[0];},formatMatch:null,autoFill:false,width:0,multiple:false,multipleSeparator:", ",highlight:function(d,c){return d.replace(new RegExp("(?![^&;]+;)(?!<[^<>]*)("+c.replace(/([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi,"\\$1")+")(?![^<>]*>)(?![^&;]+;)","gi"),"<strong>$1</strong>");},scroll:true,scrollHeight:180};b.Tag_Autocompleter.Cache=function(d){var g={};var e=0;function i(l,k){if(!d.matchCase){l=l.toLowerCase();}var j=l.indexOf(k);if(d.matchContains=="word"){j=l.toLowerCase().search("\\b"+k.toLowerCase());}if(j==-1){return false;}return j==0||d.matchContains;}function h(k,j){if(e>d.cacheLength){c();}if(!g[k]){e++;}g[k]=j;}function f(){if(!d.data){return false;}var k={},j=0;if(!d.url){d.cacheLength=1;}k[""]=[];for(var m=0,l=d.data.length;m<l;m++){var p=d.data[m];p=(typeof p=="string")?[p]:p;var o=d.formatMatch(p,m+1,d.data.length);if(o===false){continue;}var n=o.charAt(0).toLowerCase();if(!k[n]){k[n]=[];}var q={value:o,data:p,result:d.formatResult&&d.formatResult(p)||o};k[n].push(q);if(j++<d.max){k[""].push(q);}}b.each(k,function(r,s){d.cacheLength++;h(r,s);});}setTimeout(f,25);function c(){g={};e=0;}return{flush:c,add:h,populate:f,load:function(n){if(!d.cacheLength||!e){return null;}if(!d.url&&d.matchContains){var m=[];for(var j in g){if(j.length>0){var o=g[j];b.each(o,function(p,k){if(i(k.value,n)){m.push(k);}});}}return m;}else{if(g[n]){return g[n];}else{if(d.matchSubset){for(var l=n.length-1;l>=d.minChars;l--){var o=g[n.substr(0,l)];if(o){var m=[];b.each(o,function(p,k){if(i(k.value,n)){m[m.length]=k;}});return m;}}}}}return null;}};};b.Tag_Autocompleter.Select=function(f,k,m,q){var j={ACTIVE:"tag_ac_over"};var l,g=-1,s,n="",t=true,d,p;function o(){if(!t){return;}d=b("<div/>").hide().addClass(f.resultsClass).css("position","absolute").appendTo(document.body);p=b("<ul/>").appendTo(d).mouseover(function(u){if(r(u).nodeName&&r(u).nodeName.toUpperCase()=="LI"){g=b("li",p).removeClass(j.ACTIVE).index(r(u));b(r(u)).addClass(j.ACTIVE);}}).click(function(u){b(r(u)).addClass(j.ACTIVE);m();k.focus();return false;}).mousedown(function(){q.mouseDownOnSelect=true;}).mouseup(function(){q.mouseDownOnSelect=false;});if(f.width>0){d.css("width",f.width);}t=false;}function r(v){var u=v.target;while(u&&u.tagName!="LI"){u=u.parentNode;}if(!u){return[];}return u;}function i(u){l.slice(g,g+1).removeClass(j.ACTIVE);h(u);var w=l.slice(g,g+1).addClass(j.ACTIVE);if(f.scroll){var v=0;l.slice(0,g).each(function(){v+=this.offsetHeight;});if((v+w[0].offsetHeight-p.scrollTop())>p[0].clientHeight){p.scrollTop(v+w[0].offsetHeight-p.innerHeight());}else{if(v<p.scrollTop()){p.scrollTop(v);}}}}function h(u){g+=u;if(g<0){g=l.size()-1;}else{if(g>=l.size()){g=0;}}}function c(u){return f.max&&f.max<u?f.max:u;}function e(){p.empty();var v=c(s.length);for(var w=0;w<v;w++){if(!s[w]){continue;}var x=f.formatItem(s[w].data,w+1,v,s[w].value,n);if(x===false){continue;}var u=b("<li/>").html(f.highlight(x,n)).addClass(w%2==0?"tag_ac_even":"tag_ac_odd").appendTo(p)[0];b.data(u,"tag_ac_data",s[w]);}l=p.find("li");if(f.selectFirst){l.slice(0,1).addClass(j.ACTIVE);g=0;}if(b.fn.bgiframe){p.bgiframe();}}return{display:function(v,u){o();s=v;n=u;e();},next:function(){i(1);},prev:function(){i(-1);},pageUp:function(){if(g!=0&&g-8<0){i(-g);}else{i(-8);}},pageDown:function(){if(g!=l.size()-1&&g+8>l.size()){i(l.size()-1-g);}else{i(8);}},hide:function(){d&&d.hide();l&&l.removeClass(j.ACTIVE);g=-1;},visible:function(){return d&&d.is(":visible");},current:function(){return this.visible()&&(l.filter("."+j.ACTIVE)[0]||f.selectFirst&&l[0]);},show:function(){var w=b(k).offset();d.css({width:typeof f.width=="string"||f.width>0?f.width:b(k).width(),top:w.top+k.offsetHeight,left:w.left}).show();if(f.scroll){p.scrollTop(0);p.css({maxHeight:f.scrollHeight,overflow:"auto"});if(b.browser.msie&&typeof document.body.style.maxHeight==="undefined"){var u=0;l.each(function(){u+=this.offsetHeight;});var v=u>f.scrollHeight;p.css("height",v?f.scrollHeight:u);if(!v){l.width(p.width()-parseInt(l.css("padding-left"))-parseInt(l.css("padding-right")));}}}},selected:function(){var u=l&&l.filter("."+j.ACTIVE).removeClass(j.ACTIVE);return u&&u.length&&b.data(u[0],"tag_ac_data");},emptyList:function(){p&&p.empty();},unbind:function(){d&&d.remove();}};};b.fn.selection=function(d,f){if(d!==undefined){return this.each(function(){if(this.createTextRange){var l=this.createTextRange();if(f===undefined||d==f){l.move("character",d);l.select();}else{l.collapse(true);l.moveStart("character",d);l.moveEnd("character",f);l.select();}}else{if(this.setSelectionRange){this.setSelectionRange(d,f);}else{if(this.selectionStart){this.selectionStart=d;this.selectionEnd=f;}}}});}var h=this[0];if(h.createTextRange){var g=document.selection.createRange(),i=h.value,e="<->",k=g.text.length;g.text=e;var j=h.value.indexOf(e);h.value=i;var c=i.substr(j);c=c.substring(0,c.indexOf(a.multiplesSeperator));k=c.length;this.selection(j,j+k);return{start:j,end:j+k};}else{if(h.selectionStart!==undefined){return{start:h.selectionStart,end:h.selectionEnd};}}};})(jQuery);