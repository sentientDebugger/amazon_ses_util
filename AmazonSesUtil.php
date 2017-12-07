<?php
use Aws\Ses\SesClient;

/**
 * Function for sending an email with html body and attachments with Amazon SES
 * @param string $from email sender
 * @param string|array $to email recipient(s)
 * @param string $subject The email's subject
 * @param string $htmlBody The email's body (html). Example: $htmlBody="<html><body><h1>foobar</h1></body></html>";
 * @param array $files Array with the values for the attachments. Each array element must have:
 *              [
 *                  "name" => "file_name.ext (no greater than 60 char)",
 *                  "mime" => "application/pdf",
 *                  "contents" => "file contents as string (without base64). May be the value of file_get_contents..."
 *              ]
 * @param array $amazonSesConfig
 *              Array with the values for Amazon SES client with the following:
 *              [
 *                  "key" => "skeleton",
 *                  "secret" => "shh",
 *                  "version" => "117",
 *                  "region" => "where am I?",
 *              ]
 * @param string $replyTo (Default = null) Reply to email address
 * @return Aws\Result Amazon SES repsponse object
 * @author Carlos Sifuentes
 * @see http://docs.aws.amazon.com/ses/latest/DeveloperGuide/send-email-raw.html
 * @see http://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.Ses.SesClient.html
 * @see http://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.AwsClient.html#___construct
 * @see http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-email-2010-12-01.html#sendrawemail
 */
function sendHtmlMail($from, $to, $subject, $htmlBody, $files, $amazonSesConfig, $replyTo = null)
{
    # Start with the email headers
    $msg = "From: {$from}\n";
    $toArr;
    if (is_array($to)) {
        $toStr = rtrim(implode(',', $to), ',');
        $toArr = $to;
    } else {
        $toStr = $to;
        $toArr = [$to];
    }
    $msg .= "To: {$toStr}\n";
    if ($replyTo) {
        $msg .= "Reply-To: $replyTo\n";
    }
    $subject = mb_encode_mimeheader($subject, 'UTF-8');
    $msg .= "Subject: {$subject}\n";
    $msg .= "Content-Type: multipart/mixed;\n";
    $boundary = uniqid("_Part_".time(), true); //random unique string
    $msg .= " boundary=\"{$boundary}\"\n";
    $msg .= "MIME-Version: 1.0\n";
    $msg .= "\n";
    $msg .= "--{$boundary}\n";
    # message starts here
    # TODO: add alternative text
    $msg .= "Content-Type: text/html; charset=\"utf-8\"\n";
    $msg .= $htmlBody;
    $msg .= "\n";
    # message ends here; begin adding attachments
    foreach ($files as $file) {
        $msg .= "--{$boundary}\n";
        $cleanName = mb_substr($file['name'], 0, 60);//use 60 as max length for an attachment name
        $msg .= "Content-Type: {$file['mime']}; name=\"{$cleanName}\"\n";
        $msg .= "Content-Description: {$cleanName}\n";
        # filesize looks unnecessary (?)
        // $fileSize = filesize($file['filepath']);
        // $msg .= "Content-Disposition: attachment; filename=\"{$file['filepath']}\"; size={$fileSize};\n";
        $msg .= "Content-Disposition: attachment; filename=\"{$cleanName}\";\n";
        $msg .= "Content-Transfer-Encoding: base64\n";
        $msg .= "\n";
        $msg .= chunk_split(base64_encode($file['contents']), 996);
        $msg .= "\n";
    }
    # finaly boundary and line break to end the email
    $msg .= "--{$boundary}\n";
    # make sure the email isn't too big, max is 10 MB
    if (strlen($msg) > 10000000) {//10,000,000
        throw new Exception("Mensaje de correo demasiado grande");
    }
    # Start building the SesClient to send the email
    $sesClient = new SesClient([
        "credentials" => [
            "key" => $amazonSesConfig["key"],
            "secret" => $amazonSesConfig["secret"]
        ],
        "version" => $amazonSesConfig["version"],
        "region" => $amazonSesConfig["region"]
    ]);
    return $sesClient->sendRawEmail([
        'RawMessage' => [
            'Data' => $msg
        ],
        'Source' => $from,
        'Destinations' => $toArr
    ]);
}
