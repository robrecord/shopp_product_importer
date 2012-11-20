<? 
/**
	Copyright: Copyright © 2010 Catskin Studio
	Licence: see index.php for full licence details
 */
?>

<style type="text/css" media="screen">
/*<![CDATA[*/
	.shopp h2 {
		margin:.5em 0 1em;
	}
	.shopp table {
		border: 1px solid #ccc;
		width: 100%;
		border-collapse: collapse;
		border-spacing: 0px;
		background-color: #f7f7f7;
	}
	table.category_map tr {
		font-size: 0.8em;
	}
	.shopp thead,
	.shopp tbody {
		line-height:20px;
		
	}
	.shopp th {
		background-color: #d0d0d0;
		border-top: 1px solid #999;
	}
	.shopp th,
	.shopp td {
		padding: 3px 6px;
		line-height:20px;
		margin:0;
		vertical-align: top;
	}
	.shopp tr {
		background-color: #f7f7f7;
	}
	.shopp td {
		background-color: white;
	}
	.cat_id {
		font-weight: bold;
	}
/*	.tab_level_1 {
		text-indent: 20px;
	}
	.tab_level_2 {
		text-indent: 40px;
	}
*/
	.tab_level_1 th {
/*		padding-left: 20px;*/
	}
	.tab_level_2 th {
		padding-left: 20px;
/*		display:block;*/
	}
	.tab_level_1 th,
	.tab_level_2 th {
		background-color: #e7e7e7;
		border-top: 1px solid #bbb;
	}
	.tab_level_1 {
/*		border-left: 1px solid #c0c0c0;*/
/*		text-indent: 20px;*/
	}
	.tab_level_2 {
		background-color: #f7f7f7;
/*		padding-left: 40px;*/
/*		border-left: 1px solid #c0c0c0;*/
/*		display:block;*/
/*		width:100%;*/
	}
	.tab_level_1 td:first-child {
		text-indent: 20px;
	}
	.tab_level_2 td:first-child {
/*		background-color:red;*/
		text-indent: 40px;
	}
	.first_td td {
		border-top: 1px solid #ddd;
	}
	.tri_bullet {
		margin-left:20px;
		margin-right:10px;
		color:#c0c0c0;
	}
	.category_select {
		display: block;
	}
	.template {
		display: none;
	}
	thead.category {
		text-align:left;
		font-weight:normal;
	}
	/*]]>*/
</style><?
	$cat_group = 'RING / Engagement Ring';
	$cat_id = '100';
	$cat_name = 'Diamond Engagement Rings';
	$cat_parent = 0;
	global $cat_parents;
	$cat_parents = $this->cat_parents;
	?>
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
				&nbsp;
				<a href="<?php echo esc_url(add_query_arg(array_merge(stripslashes_deep($_GET),array('page'=>$this->Admin->pagename('categories'),'a'=>'arrange')),admin_url('admin.php'))); ?>" class="button add-new"><?php _e('Arrange','Shopp'); ?></a>
			</div>
			<div class="clear"></div>
		</div>
		<div class="clear"></div>

		<table class="widefat" cellspacing="0">
			<thead>
			<tr><?php print_column_headers('shopp_page_shopp-categories'); ?></tr>
			</thead>
			<tfoot>
			<tr><?php print_column_headers('shopp_page_shopp-categories',false); ?></tr>
			</tfoot>
		<?php if (sizeof($Categories) > 0): ?>
			<tbody id="categories-table" class="list categories">
			<?php
			$hidden = array();
			$hidden = get_hidden_columns('shopp_page_shopp-categories');

			$even = false;
			foreach ($Categories as $Category):

			$editurl = esc_url(add_query_arg(array_merge($_GET,
				array('page'=>$this->Admin->pagename('categories'),
						'id'=>$Category->id)),
						admin_url('admin.php')));

			$deleteurl = esc_url(add_query_arg(array_merge($_GET,
				array('page'=>$this->Admin->pagename('categories'),
						'delete[]'=>$Category->id,
						'deleting'=>'category')),
						admin_url('admin.php')));

			$CategoryName = empty($Category->name)?'('.__('no category name','Shopp').')':$Category->name;
			?>
			<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
				<th scope='row' class='check-column'><input type='checkbox' name='delete[]' value='<?php echo $Category->id; ?>' /></th>
				<td><a class='row-title' href='<?php echo $editurl; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($CategoryName); ?>&quot;'><?php echo str_repeat("&#8212; ",$Category->depth); echo esc_html($CategoryName); ?></a>
					<div class="row-actions">
						<span class='edit'><a href="<?php echo $editurl; ?>" title="<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($CategoryName); ?>&quot;"><?php _e('Edit','Shopp'); ?></a> | </span>
						<span class='delete'><a class='submitdelete' title='<?php _e('Delete','Shopp'); ?> &quot;<?php echo esc_attr($CategoryName); ?>&quot;' href="<?php echo $deleteurl; ?>" rel="<?php echo $Category->id; ?>"><?php _e('Delete','Shopp'); ?></a> | </span>
						<span class='view'><a href="<?php echo shoppurl(SHOPP_PRETTYURLS?"category/$Category->uri":array('shopp_category'=>$Category->id)); ?>" title="<?php _e('View','Shopp'); ?> &quot;<?php echo esc_attr($CategoryName); ?>&quot;" rel="permalink" target="_blank"><?php _e('View','Shopp'); ?></a></span>
					</div>
				</td>
				<td width="5%" class="num links column-links<?php echo in_array('links',$hidden)?' hidden':''; ?>"><?php echo $Category->total; ?></td>
				<td width="5%" class="templates column-templates<?php echo ($Category->spectemplate == "on")?' spectemplates':''; echo in_array('templates',$hidden)?' hidden':''; ?>">&nbsp;</td>
				<td width="5%" class="menus column-menus<?php echo ($Category->facetedmenus == "on")?' facetedmenus':''; echo in_array('menus',$hidden)?' hidden':''; ?>">&nbsp;</td>
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

		pagenow = 'shopp_page_shopp-categories';
		columns.init(pagenow);

	});
	</script>
	<div class="wrap shopp">

	
    
	<form name="catmap" id="general" action="%3C?=%20esc_url($_SERVER['REQUEST_URI'])%20?%3E" method="post">
	<?// wp_nonce_field('shopp-importer') ?>
    
	<div id="main-container" style="line-height:1.5">
		<h2><? _e('Category Mapper','Shopp') ?>
		</h2>
    
		<table class="category_map">
			<thead>
				<tr>
					<th>EDGE Category ID / Name</th>
    
					<th>Shopp Categories</th>
    
					<th>Shopp Tags</th>
				</tr>
			</thead>
			
			
			<?= next_category_level(null,null,0,0); ?>
    
			</table>
	</div><!-- main-container -->
	</form><!-- catmap -->
</div><!-- wrap shopp -->
<script type="text/javascript" charset="utf-8">
	jQuery(document).ready(function(){
		jQuery("button").click(function(){
			$td = jQuery(this).parent().children('div');
			jQuery($td).children('span.template').clone().appendTo($td).removeClass('template');

		});
	});
</script>


<? 
	
function next_category_level($parent_id,$parent_name,$id,$level) {
	global $cat_parents;
	ob_start();
	if ($cat_parents[$id]): // if this cat has children: ?>	
		<? foreach($cat_parents[$id] as $child_id => $child_name): ?>
		<? if (!$child_name) $child_name = '&lt;no name&gt;' ?>
			<? if (isset($cat_parents[$child_id])): ?>	
				<? $families .= add_cat_head($parent_name,$child_id,$child_name,$level) ?>
				<? $families .= next_category_level($id,$child_name,$child_id,$level+1); ?>
			<? else: ?>
				<?= add_cat_row($cat_parents[$id],$child_id,$level) ?>
			<? endif ?>
		<? endforeach ?>
		<?= $families; unset($families) ?>
		
	<? else: ?>
		<?= add_cat_row($cat_parents[$parent_id],$id,$level) ?>
	<? endif ?>
	
	<? $return = ob_get_contents();
	ob_end_clean();
	return $return;
}
function add_cat_head($parent_mame,$child_id,$child_name,$level)
{
	global $cat_parents;
	ob_start();
	?>
	<thead class="category tab_level_<?=$level?>">
		<tr>
			<th colspan="4"><? $i=0; while ($i<$level): ?><span class="tri_bullet">&#9660;</span><? $i++; endwhile ?><?= $parent_name ? "{$parent_name} / " : '' ?><?= $child_name ?>
			</th>
		</tr>
	</thead>
	<? $return = ob_get_contents();
	ob_end_clean();
	return $return;
}
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
function add_cat_select_row($hide=false)
{
	ob_start();
	?>
	<span class="category_select<? if ($hide): ?> template<? endif ?>">
		<select name="name">
			<optgroup label="12">
				<optgroup label="sd">
					<option value="value">
						value
					</option>
				</optgroup>
			</optgroup>
		</select>
	</span>
	
	
	<? $return = ob_get_contents();
	ob_end_clean();
	return $return;
}
function add_tag_select_row($hide=false)
{
	ob_start();
	?>
	<span class="tag_select<? if ($hide): ?> template<? endif ?>">
		<select name="name">
			<optgroup label="12">
				<optgroup label="sd">
					<option value="value">
						value
					</option>
				</optgroup>
			</optgroup>
		</select>
	</span>
	
	
	<? $return = ob_get_contents();
	ob_end_clean();
	return $return;
} ?>
