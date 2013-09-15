<div class="wrap shopp">
	<div class="icon32"></div>
	<h2><?php _e('Order Only Items','Shopp'); ?></h2>
	<?php if (!empty($this->Notice)): ?><div id="message" class="updated fade"><p><?php echo $this->Notice; ?></p></div><?php endif; ?>
	<div style="text-align:center; color:red;"><?=$message?></div>
	<form action="" id="item-list" method="post">

	<div class="tablenav">
		<div class="alignleft actions inline">
			<button type="submit" id="delete-button" name="deleting" value="customer" class="button-secondary" onclick="return confirm('Are you sure?')"><?php _e('Delete','Shopp'); ?></button>
		</div>
	</div>


<table class="widefat" cellspacing="0">
		<thead>
		<tr>
		<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox"></th>
		<th scope="col" id="sku" class="manage-column column-sku" style="">SKU</th>
		<th scope="col" id="desc" class="manage-column column-desc" style="">Description</th>
		<th scope="col" id="image" class="manage-column column-image" style="">Image</th>
		</tr>
		<tfoot>
		<tr>
		<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox"></th>
		<th scope="col" id="sku" class="manage-column column-sku" style="">SKU</th>
		<th scope="col" id="desc" class="manage-column column-desc" style="">Description</th>
		<th scope="col" id="image" class="manage-column column-image" style="">Image</th>
		</tr>
		</tfoot>
	<?php if (sizeof($Items) > 0): ?>
		<tbody id="customers-table" class="list orders">
		<?php
			$hidden = get_hidden_columns($this->screen);

			$even = false;
			foreach ($Items as $i => $Item):
			?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' name='selected[]' value='<?php echo $Item->itemid; ?>' /></th>
			<td><?php echo esc_html($Item->sku); ?></td>
			<td><?php echo esc_html($Item->name); ?></td>
			<td><img src="?siid=<?php echo $ProductImages[$i]->id; ?>&amp;<?php echo $ProductImages[$i]->resizing(96,0,1); ?>" width="96" height="96" /></td>


		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="6"><?php _e('No','Shopp'); ?> <?php _e('order only items.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>




	</form>
</div>

<script type="text/javascript">

jQuery(document).ready( function() {


$('#selectall').change( function() {
	$('#customers-table th input').each( function () {
		if (this.checked) this.checked = false;
		else this.checked = true;
	});
});


}
</script>
