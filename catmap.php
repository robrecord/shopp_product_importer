<? 
/**
	Copyright: Copyright © 2010 Catskin Studio
	Licence: see index.php for full licence details
 */
?>

<style type="text/css" media="screen">
/*<![CDATA[*/
	#message ul {
		margin-left:1em;
		list-style: circle inside;
	}
	#message ul li {
		font-weight: bold;
		font-style: italic;
	}
	#message ul ul li {
		font-style: normal;
	}
	#message ul ul ul li {
		font-weight: normal;
	}
	/*]]>*/
</style>
<div class="wrap shopp">
	<div class="icon32"></div>
	<h2><?php _e('EDGE Categories','Shopp'); ?> <a href="<?php echo esc_url(add_query_arg(array_merge(stripslashes_deep($_GET),array('page'=>$this->Admin->pagename('categories'),'id'=>'new')),admin_url('admin.php'))); ?>" class="button add-new"><?php _e('New Category','Shopp'); ?></a></h2>
	<?php if (!empty($this->Notice)): ?><div id="message" class="updated fade"><p><?php echo $this->Notice; ?></p></div><?php endif; ?>

	<form action="" id="categories" method="get">
	<div>
		<input type="hidden" name="page" value="<?php echo $this->Admin->pagename('categories'); ?>" />
	</div>

	<p id="post-search" class="search-box">
		<input type="text" id="categories-search-input" class="search-input" name="s" value="<?php echo esc_attr(stripslashes($s)); ?>" />
		<input type="submit" value="<?php _e('Search Categories','Shopp'); ?>" class="button" />
	</p>

	<div class="tablenav">
		<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
		<div class="alignleft actions">
			<button type="submit" id="delete-button" name="deleting" value="category" class="button-secondary"><?php _e('Delete','Shopp'); ?></button>
			<!-- &nbsp;
			<a href="<?php echo esc_url(add_query_arg(array_merge(stripslashes_deep($_GET),array('page'=>$this->Admin->pagename('importer-catmap'),'a'=>'arrange')),admin_url('admin.php'))); ?>" class="button add-new"><?php _e('Arrange','Shopp'); ?></a> -->
			&nbsp;
			<a href="<?php echo esc_url(add_query_arg(array_merge(stripslashes_deep($_GET),array('page'=>$this->Admin->pagename('importer-catmap'),'p'=>'process')),admin_url('admin.php'))); ?>" class="button add-new"><?php _e('Add products to mapped categories','Shopp'); ?></a>
		</div>
		<div class="clear"></div>
	</div>
	<div class="clear"></div>
	<table class="widefat" cellspacing="0">
		<thead>
		<tr><?php print_column_headers('shopp_page_shopp-importer-catmap'); ?></tr>
		</thead>
		<tfoot>
		<tr><?php print_column_headers('shopp_page_shopp-importer-catmap',false); ?></tr>
		</tfoot>
	<?php if (sizeof($EDGECategories) > 0): ?>
		<tbody id="categories-table" class="list categories">
		<?php
		$hidden = array();
		$hidden = get_hidden_columns('shopp_page_shopp-importer-catmap');

		$even = false;
		foreach ($EDGECategories as $EDGECategory):

		$editurl = esc_url(add_query_arg(array_merge($_GET,
			array('page'=>$this->Admin->pagename('importer-catmap'),
					'id'=>$EDGECategory->id)),
					admin_url('admin.php')));

		$deleteurl = esc_url(add_query_arg(array_merge($_GET,
			array('page'=>$this->Admin->pagename('importer-catmap'),
					'delete[]'=>$EDGECategory->id,
					'deleting'=>'category')),
					admin_url('admin.php')));

		$EDGECategoryName = empty($EDGECategory->name)?'('.__('no category name','Shopp').')':$EDGECategory->name;
		?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' name='delete[]' value='<?php echo $EDGECategory->id; ?>' /></th>
			<td><?= $EDGECategory->id ?></td>
			<td><a class='row-title' href='<?php echo $editurl; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($EDGECategoryName); ?>&quot;'><?php echo str_repeat("&#8212; ",$EDGECategory->depth); echo esc_html($EDGECategoryName); ?></a>
				<div class="row-actions">
					<span class='edit'><a href="<?php echo $editurl; ?>" title="<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($EDGECategoryName); ?>&quot;"><?php _e('Edit','Shopp'); ?></a> | </span>
					<span class='delete'><a class='submitdelete' title='<?php _e('Delete','Shopp'); ?> &quot;<?php echo esc_attr($EDGECategoryName); ?>&quot;' href="<?php echo $deleteurl; ?>" rel="<?php echo $EDGECategory->id; ?>"><?php _e('Delete','Shopp'); ?></a> <!-- | --> </span>
					<!-- <span class='view'><a href="<?php echo shoppurl(SHOPP_PRETTYURLS?"category/$EDGECategory->uri":array('shopp_category'=>$EDGECategory->id)); ?>" title="<?php _e('View','Shopp'); ?> &quot;<?php echo esc_attr($EDGECategoryName); ?>&quot;" rel="permalink" target="_blank"><?php _e('View','Shopp'); ?></a></span> -->
				</div>
			</td>
			<td width="5%" class="num links column-links<?php echo in_array('links',$hidden)?' hidden':''; ?>"><?php echo $EDGECategory->total; ?></td>
			<!-- <td width="5%" class="templates column-templates<?php echo ($EDGECategory->spectemplate == "on")?' spectemplates':''; echo in_array('templates',$hidden)?' hidden':''; ?>">&nbsp;</td>
			<td width="5%" class="menus column-menus<?php echo ($EDGECategory->facetedmenus == "on")?' facetedmenus':''; echo in_array('menus',$hidden)?' hidden':''; ?>">&nbsp;</td> -->
			<td class="category column-category<?php echo in_array('category',$hidden)?' hidden':''; ?>"><?php echo esc_html($EDGECategory->shopp_categories->string); ?></td>
			<!-- <td width="20%" class="menus column-cats">
							
							<div><?= add_cat_select_row($Categories,$EDGECategory->id,true) ?></div>
							<button class="add-button" onClick="return false;">Add</button>
						</td> -->
			
		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="6"><?php _e('No categories found.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>
	</form>
		
	<div class="tablenav">
		<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
		<div class="clear"></div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready( function() {

	var $ = jQuery.noConflict();

	$('#selectall').change( function() {
		$('#categories-table th input').each( function () {
			if (this.checked) this.checked = false;
			else this.checked = true;
		});
	});

	$('a.submitdelete').click(function () {
		if (confirm("<?php _e('You are about to delete this category!\n \'Cancel\' to stop, \'OK\' to delete.','Shopp'); ?>")) {
			$('<input type="hidden" name="delete[]" />').val($(this).attr('rel')).appendTo('#categories');
			$('<input type="hidden" name="deleting" />').val('category').appendTo('#categories');
			$('#categories').submit();
			return false;
		} else return false;
	});

	$('#delete-button').click(function() {
		if (confirm("<?php echo addslashes(__('Are you sure you want to delete the selected categories?','Shopp')); ?>")) {
			$('<input type="hidden" name="categories" value="list" />').appendTo($('#categories'));
			return true;
		} else return false;
	});
	
	$(".add-button").click(function(){
		$td = $(this).parent().children('div');
		$($td).children('span.template')
			.clone().appendTo($td)
			.removeClass('template');

	});
	

	pagenow = 'shopp_page_shopp-categories';
	columns.init(pagenow);

});
</script>
<?
function add_cat_row($cat_parent,$id,$level)
{
	ob_start();
	?>
	<tr class="<? if ($cat_parent[$id]): ?>first_td <? endif ?> tab_level_<?=$level?>">
		<td>
		<? if ($cat_parent[$id]): ?>
			<span class="cat_id"><?= $id ?></span> - <?= $cat_parent[$id] ?>
		<? else: ?>&nbsp;
		<? endif ?> 
		</td>
		<td>
			<div><?= add_cat_select_row(true); ?></div>
			<button onClick="return false;">Add</button>
		</td>
		<td>
			<div><?= add_tag_select_row(true); ?></div>
			<button onClick="return false;">Add</button></td>
	</tr>
	<? $return = ob_get_contents();
	ob_end_clean();
	return $return;
}
function add_cat_select_row(&$Categories,$id,$hide=false)
{
	ob_start();
	?>
	<span class="category_select<? if ($hide): ?> template<? endif ?>">
		<select name="name">
			<? foreach ($Categories as $id => $category): ?>
				<option value="<?= $category->slug ?>">
					<?= $category->name ?>
				</option>
				
			<? endforeach ?>
		</select>
	</span>
	
	
	<? $return = ob_get_contents();
	ob_end_clean();
	return $return;
} ?>