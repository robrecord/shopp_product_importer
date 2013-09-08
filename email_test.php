<?
	function send_email_report($result=null) {
		$subject = "Email test";
		$body = 'This is a test email, sent by PHP. The settings are the same as with the Seita Shopp importer. This email was sent at '.date('g.ia')." today, and the timezone is ".date('e')."\n";
		$body .= $result ? "$result\n" : '';
		foreach (array('robotix@gmail.com','judy@karmoxie.com') as $to)
			mail($to, $subject, $body);
		 	
	}

	date_default_timezone_set('Etc/GMT+5');
	
	send_email_report();

?>