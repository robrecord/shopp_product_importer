var Pricelines=new Pricelines(),
productOptions=new Array(),
optionMenus=new Array(),
detailsidx=1,
variationsidx=1,
optionsidx=1,
pricingidx=1,
pricelevelsidx=1,
fileUploader=false,
changes=false,
saving=false,
flashUploader=false,
template=true;

jQuery(document).ready(function(){
	var f=jqnc(),
	b=new SlugEditor(category,"category"),
	e=new ImageUploads(f("#image-category-id").val(),"category");
	
	postboxes.add_postbox_toggles("shopp_page_shopp-categories");
	
	f(".if-js-closed").removeClass("if-js-closed").addClass("closed");
	f(".postbox a.help").click(function(){
		f(this).colorbox({
			iframe:true,open:true,innerWidth:768,innerHeight:480,scrolling:false
		});
		return false
	});
	
	d();
	
	f("#category").submit(function(){
		this.action=this.action.substr(0,this.action.indexOf("?"))+"?"+f.param(request);
		return true
	});
	
	f("#templates, #details-template, #details-facetedmenu, #variations-template, #variations-pricing, #price-ranges, #facetedmenus-setting").hide();
	
	f("#spectemplates-setting").change(function(){
		if(this.checked){
			f("#templates, #details-template, #facetedmenus-setting").show()
		}else{
			f("#details-template, #facetedmenus-setting").hide()
		}
		if(!f("#spectemplates-setting").attr("checked")&&!f("#variations-setting").attr("checked")){
			f("#templates").hide()
		}
	}).change();
	
	f("#faceted-setting").change(function(){
		if(this.checked){
			f("#details-menu").removeClass("options").addClass("menu");
			f("#details-facetedmenu, #price-ranges").show()
		}else{
			f("#details-menu").removeClass("menu").addClass("options");
			f("#details-facetedmenu, #price-ranges").hide()
		}
	}).change();
	if(details){
		for(s in details){
			c(details[s])
		}
	}
	f("#addPriceLevel").click(function(){ a() });
	f("#addDetail").click(function(){ c() });
	f("#variations-setting").bind("toggleui",function(){
		if(this.checked){
			f("#templates, #variations-template, #variations-pricing").show()
		}else{
			f("#variations-template, #variations-pricing").hide()
		}
		if(!f("#spectemplates-setting").attr("checked")&&!f("#variations-setting").attr("checked")){
			f("#templates").hide()
		}
	}).click(function(){
		f(this).trigger("toggleui")
	}).trigger("toggleui");
	
	loadVariations((!options.v&&!options.a)?options:options.v,prices);
	
	f("#addVariationMenu").click(function(){
		addVariationOptionsMenu()
	});
	
	f("#pricerange-facetedmenu").change(function(){
		if(f(this).val()=="custom"){
			f("#pricerange-menu, #addPriceLevel").show()
		}else{
			f("#pricerange-menu, #addPriceLevel").hide()
		}
		}).change();
	if(priceranges){
		for(key in priceranges){
			a(priceranges[key])
		}
	}
	if(!category){ f("#title").focus() }
	
	function a(h){
	var g=f("#pricerange-menu");
	var j=pricelevelsidx++;
	var i=new NestedMenu(j,g,"priceranges","",h,false,{
	axis:"y",scroll:false
	});
	f(i.label).change(function(){
	this.value=asMoney(this.value)
	}).change()
	}function c(m){
	var k=f("#details-menu"),n=f("#details-list"),g=f("#addDetailOption"),h=detailsidx,j=new NestedMenu(h,k,"specs",NEW_DETAIL_DEFAULT,m,{
	target:n,type:"list"
	});
	j.items=new Array();
	j.addOption=function(q){
	var i=new NestedMenuOption(j.index,j.itemsElement,j.dataname,NEW_OPTION_DEFAULT,q,true);
	j.items.push(i)
	};
	var p=f('<li class="setting"></li>').appendTo(j.itemsElement);
	var o=f('<select name="specs['+j.index+'][facetedmenu]"></select>').appendTo(p);
	f('<option value="disabled">'+FACETED_DISABLED+"</option>").appendTo(o);
	f('<option value="auto">'+FACETED_AUTO+"</option>").appendTo(o);
	f('<option value="ranges">'+FACETED_RANGES+"</option>").appendTo(o);
	f('<option value="custom">'+FACETED_CUSTOM+"</option>").appendTo(o);
	if(m&&m.facetedmenu){
	o.val(m.facetedmenu)
	}o.change(function(){
	if(f(this).val()=="disabled"||f(this).val()=="auto"){
	f(g).hide();
	f(j.itemsElement).find("li.option").hide()
	}else{
	f(g).show();
	f(j.itemsElement).find("li.option").show()
	}
	}).change();
	if(m&&m.options){
	for(var l in m.options){
	j.addOption(m.options[l])
	}
	}f(j.itemsElement).sortable({
	axis:"y",items:"li.option",scroll:false
	});
	j.element.unbind("click",j.click);
	j.element.click(function(){
	j.selected();
	f(g).unbind("click").click(j.addOption);
	f(o).change()
	});
	detailsidx++
	}function d(){
		window.console.log(adminpage);
	f("#workflow").change(function(){
	setting=f(this).val();
	request.page=adminpage;
	request.id=category;
	if(!request.id){
	request.id="new"
	}if(setting=="new"){
	request.id="new";
	request.next=setting
	}if(setting=="close"){
	delete request.id
	}if(setting=="previous"){
	f.each(worklist,function(g,h){
	if(h.id!=category){
	return
	}if(worklist[g-1]){
	request.next=worklist[g-1].id
	}else{
	delete request.id
	}
	})
	}if(setting=="next"){
	f.each(worklist,function(g,h){
	if(h.id!=category){
	return
	}if(worklist[g+1]){
	request.next=worklist[g+1].id
	}else{
	delete request.id
	}
	})
	}
	}).change()
	}
});
