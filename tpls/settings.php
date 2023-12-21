<?php
if(!defined('ABSPATH')) die('Dream more!');
?>
<input type="hidden" name="save_gtm_settings" value="1">
<div class="panel panel-default">
	<div class="panel-heading">Google Tag</div>
	<div class="panel-body">
		<div class="form-group">
			<label for="lead_source">Google Tag Manager ID</label>
			<input class="form-control" type="text" name="__wpdm_gtag_id" id="k"
			       value="<?php echo get_option( '__wpdm_gtag_id' ); ?>" placeholder="GTM-XXXXX">
		</div>
		<div class="form-group">
			<label>Environment <code>gtm_auth</code> parameter</label>
			<input class="form-control" type="text" name="__wpdm_gtm_auth" value="<?= get_option( '__wpdm_gtm_auth' ) ?>" />
		</div>

		<div class="form-group">
			<label>Environment <code>gtm_preview</code> parameter</label>
			<input class="form-control" type="text" name="__wpdm_gtm_preview" value="<?= get_option( '__wpdm_gtm_preview' ) ?>" />
		</div>
	</div>
</div>
<div class="panel panel-default">
	<div class="panel-heading">Track Events</div>
	<div class="panel-body">

		<div class="form-group">
			<input type="hidden" name="__wpdm_gtag_signup" value="0" />
			<label><input type="checkbox" name="__wpdm_gtag_signup" value="1" <?php checked(1, get_option( '__wpdm_gtag_signup' )); ?> > Track User Signup <code>event: WPDM.newSignup</code></code></label>
		</div>

		<div class="form-group">
			<input type="hidden" name="__wpdm_gtag_login" value="0" />
			<label><input type="checkbox" name="__wpdm_gtag_login" value="1" <?php checked(1, get_option( '__wpdm_gtag_login' )); ?> > Track User Login <code>event: WPDM.userLogin</code></code></label>
		</div>

		<div class="form-group">
			<input type="hidden" name="__wpdm_gtag_dle" value="0" />
			<label><input type="checkbox" name="__wpdm_gtag_dle" value="1" <?php checked(1, get_option( '__wpdm_gtag_dle' )); ?> > Track User Download <code>event: WPDM.newDownload</code></label>
		</div>

		<div class="form-group">
			<input type="hidden" name="__wpdm_gtag_purchase" value="0" />
			<label><input type="checkbox" name="__wpdm_gtag_purchase" value="1" <?php checked(1, get_option( '__wpdm_gtag_purchase' )); ?> > Track New Purchase <code>event: WPDM.newPurchase</code></label>
		</div>

		<div class="form-group">
			<input type="hidden" name="__wpdm_gtag_renew" value="0" />
			<label><input type="checkbox" name="__wpdm_gtag_renew" value="1" <?php checked(1, get_option( '__wpdm_gtag_renew' )); ?> > Track Order Renewal <code>event: WPDM.orderRenewal</code></label>
		</div>





	</div>


</div>
