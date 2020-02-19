<?php
	use PHPMAILER\PHPMAILER\PHPMAILER;
	use PHPMAILER\PHPMAILER\EXCEPTION;

	require '../../PHPMailer/src/Exception.php';
	require '../../PHPMailer/src/PHPMailer.php';
	require '../../PHPMailer/src/SMTP.php';

	// function that enables us to send email from the main SCOTUSApp email (uses PHPMailer library)
	// returns true upon successful send, false otherwise
	function sendEmail($to,$subject,$body) {
		// Instantiation and passing `true` enables exceptions
		$mail = new PHPMailer(true);

		try {
		    //Server settings
		    //$mail->SMTPDebug = 2;                                       // Enable verbose debug output
		    $mail->isSMTP();                                            // Set mailer to use SMTP
		    $mail->Host       = 'smtp.gmail.com';  // Specify main and backup SMTP servers
		    $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
		    $mail->Username   = getenv("APP_EMAIL");                     // SMTP username
		    $mail->Password   = getenv("EMAIL_PASSWORD");                               // SMTP password
		    $mail->SMTPSecure = 'tls';                                  // Enable TLS encryption, `ssl` also accepted
		    $mail->Port       = 587;                                    // TCP port to connect to
		    $displayname = "SCOTUSApp";

		    //Recipients
            $mail->setFrom($mail->Username, $displayname);
            foreach($to as $name=>$email) {
                $mail->addAddress($email,$name);
            }
		     
		    // Content
		    $mail->isHTML(true);                                  // Set email format to HTML
		    $mail->Subject = $subject;
		    $mail->Body    = $body;
		    $mail->send();
            return true;
		} 
		catch (Exception $e) {
            //echo {$mail->ErrorInfo}"; 
            return false;
		}
	}
?>