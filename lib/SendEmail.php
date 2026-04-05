<?php

class SendEmail {
    private $smtp_host = 'serwer2638807.hosting-home.pl';
    private $smtp_port = 587;
    private $smtp_user = 'pawel@grochowskidev.pl';
    private $smtp_pass = 'Pawel123!';
    private $from_email = 'pawel@grochowskidev.pl';
    private $from_name = 'Clanker CHAT';

    
    public function send($to_email, $to_name, $subject, $message) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= 'From: <' . $this->from_email . '>' . "\r\n";
        $headers .= 'Reply-To: <' . $this->from_email . '>' . "\r\n";

        
        $email_message = $this->buildEmailTemplate($to_name, $message);

        
        return @mail(
            $to_email,
            $subject,
            $email_message,
            $headers
        );
    }

    
    private function buildEmailTemplate($name, $message) {
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
                    .header { background-color: #1e293b; color: #f1f5f9; padding: 20px; text-align: center; border-radius: 5px; }
                    .content { background-color: #fff; padding: 20px; margin: 20px 0; border-radius: 5px; }
                    .button { display: inline-block; background-color: #3b82f6; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Clanker CHAT</h1>
                    </div>
                    <div class='content'>
                        <p>Cześć $name,</p>
                        $message
                        <div class='footer'>
                            <p>Wiadomość została wysłana automatycznie. Proszę nie odpisywać na tego emaila.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";
    }
}
?>
