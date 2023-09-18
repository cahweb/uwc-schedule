<?php
namespace CAH\UWC;

ini_set('display_errors', 1);

require_once "recaptcha.php";
require_once "util/class.dot-env-lite.php";

$dotEnv = new \CAH\Util\DotEnvLite(dirname(__FILE__));

require_once "includes/uwc-schedule-functions.php";
require_once "includes/enum.alert-types.php";
require_once "includes/class.bootstrap-alert.php";

// Make sure we include the correct files
require_once "PHPMailer/src/Exception.php";
require_once "PHPMailer/src/PHPMailer.php";
require_once "PHPMailer/src/SMTP.php";

use \PHPMailer\PHPMailer\PHPMailer;
use \PHPMailer\PHPMailer\Exception;

$formJSON = json_decode(file_get_contents("./lib/form-values.json"), true);
$formValues = $formJSON['form-values'];
unset($formJSON);

$success = false;

$formVars = [];
// Extract the names of the form elements, if possible
parseFormVars($formValues, $formVars);
// Creating and initializing the schedule array
$formVars['schedule'] = [];

// Getting the list of required fields and their labels, for validation
$reqFields = [];
parseRequiredFields($formValues, $reqFields);

$emailText = "";

// Container for any alerts that are added to the queue
$alerts = [];
// For the sake of DRY code, we're going to assume everything fails
// until we're sure it succeeds.
$hasError = true;

if (isset($_POST['submit'])) {
    // Form validation
    if (isset($_POST['g-recaptcha-response'])) {
        $recaptcha = new \ReCaptcha\ReCaptcha($secret);
        $resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
        
        if (!$resp->isSuccess()) {
            // Captcha failed message
            $alerts[] = new BootstrapAlert(AlertTypes::DANGER, "ReCaptcha validation failed.");
        }
    } else {
        // Captcha required message
        $alerts[] = new BootstrapAlert(AlertTypes::DANGER, "ReCaptcha field is required.");
    }
    
    // Populate the form values array
    foreach ($_POST as $key => $value) {
        if ($key == 'email') {
            $filteredEmail = filter_var($value, FILTER_VALIDATE_EMAIL);
            if ($filteredEmail === false) {
                $alerts[] = new BootstrapAlert(AlertTypes::WARNING, "Please use a valid email address.");
            } else {
                $value = $filteredEmail;
            }
        }

        if (array_key_exists($key, $formVars)) {
            $formVars[$key] = is_string($value) ? htmlentities(trim($value)) : $value;
        }
        
        // This field gets created by a function, so its structure won't
        // be in $formVars
        if ($key == 'schedule') {
            foreach ($_POST[$key] as $day => $hours) {
                foreach ($hours as $hour => $priority) {
                    if (is_numeric($priority)) {
                        $formVars[$key][$day][$hour] = $priority;
                    }
                }
            }
        }
    }

    // Validate required fields. The HTML5 form stuff should handle most of this,
    // but this is here both as a failsafe and to more clearly communicate
    // problems to the user.
    $missingValues = [];
    foreach ($reqFields as $fieldName => $fieldLabel) {
        if (!isset($formVars[$fieldName]) || empty($formVars[$fieldName])) {
            $missingValues[] = $fieldLabel;
        }
    }
    if (!empty($missingValues)) {
        $alerts[] = new BootstrapAlert(AlertTypes::DANGER, "Some required fields are missing: " . implode(", ", $missingValues) . ".");
    }

    if (empty($alerts)) {
        try {
            $success = false;

            $mail = new PHPMailer();

            $mail->IsSMTP();
            $mail->SMTPAuth   = true;
            $mail->SMTPSecure = false;
            $mail->Host       = getenv('SMTP_HOST');
            $mail->Port       = getenv('SMTP_PORT');
            $mail->Username   = getenv('SMTP_USERNAME');
            $mail->Password   = getenv('EMAIL_API_KEY');
            $mail->IsHTML(true);
            $mail->CharSet    = 'utf-8';
            $mail->SetFrom("uwc@ucf.edu", "UCF University Writing Center");
            $mail->Subject    = "UWC-Orlando Peer Consultant Schedule for {$formVars['fullName']}";

            ob_start();
            include "includes/email-body.php";
            $emailText = ob_get_clean();
            $mail->Body = "<body>\n$emailText\n</body>";

            if (!IS_DEV) {
                $mail->AddAddress("mariana.chao@ucf.edu", "Mariana Chao");
                $mail->AddAddress("mary.tripp@ucf.edu", "Mary Tripp");
            }

            if ($formVars['requestReceipt'] || IS_DEV) {
                $mail->AddAddress($formVars['email'], $formVars['fullName']);
            }

            $success = $mail->Send();
        } catch (\Exception $e) {
            log($e);
        } finally {
            if (!$success) {
                $alerts[] = new BootstrapAlert(AlertTypes::DANGER, 'The system failed to submit the required information. Please try submitting again. If the problem persists, please contact <a href="mailto: cahweb@ucf.edu">CAH Web</a>.');
            } else {
                $alerts[] = new BootstrapAlert(AlertTypes::SUCCESS, "New work schedule information submitted successfully! If you requested a receipt, a copy of the below information should arrive in your Inbox shortly, using the email address you provided.");
                $hasError = false;
            }
        }
    }
}

$baseurl = getenv('BASEURL');
?>
<!DOCTYPE html>
<html>
    <head>
        <title>UWC - Work Schedule Submission Form</title>

        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <link rel="shortcut icon" href="<?= $baseurl ?>/wp-content/uploads/sites/9/2015/08/uwc-favicon-16px.png" type="image/x-icon">
        <link rel="apple-touch-icon" href="<?= $baseurl ?>/wp-content/uploads/sites/9/2015/08/uwc-favicon-57px.png">
        <link rel="apple-touch-icon" sizes="114x114" href="<?= $baseurl ?>/wp-content/uploads/sites/9/2015/08/uwc-favicon-114px.png">
        <link rel="apple-touch-icon" sizes="72x72" href="<?= $baseurl ?>/wp-content/uploads/sites/9/2015/08/uwc-favicon-72px.png">
        <link rel="apple-touch-icon" sizes="144x144" href="<?= $baseurl ?>/wp-content/uploads/sites/9/2015/08/uwc-favicon-144px.png">
        
        
        <!-- Bootstrap -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
        
        <!-- Local styles -->
        <link rel="stylesheet" href="css/style.css">

        <!-- ReCaptcha -->
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    </head>
    <body>
        <script type="text/javascript" id="ucfhb-script" src="//universityheader.ucf.edu/bar/js/university-header.js?use-1200-breakpoint=1"></script>
        <div class="container-fluid" id="uwc-contact-header">
            <div id="contact-info" class="container d-flex flex-row justify-content-end align-items-center">
                <p class="text-inverse mt-3"><strong>University Writing Center Phone: <a href="tel:+14078232197" id="uwc-phone">(407) 823-2197</a></strong></p>
            </div>
        </div>
        <div class="container mb-5 mt-2">
            <nav class="navbar navbar-expand-lg" id="site-nav">
                <div class="container-fluid">
                    <a class="navbar-brand" href="<?= $baseurl ?>">
                        <img src="img/uwc-logo.png" alt="UWC Logo" title="University Writing Center">
                    </a>
                </div>
            </nav>
        <?php if ($success) : ?>
            <?php
            foreach ($alerts as $alert) {
                echo $alert;
            }
            ?>
            <div class="row">
                <div class="col">
                    <p>Your submitted information:</p>
                </div>
            </div>
            <div class="mx-auto">
                <?= $emailText ?>
            </div>
        <?php else : ?>
            <?php if ($hasError && !empty($alerts)) : ?>
                <?php foreach ($alerts as $alert) : ?>
                    <?= $alert ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <div id="header-img" class="mb-3">
                <img src="img/header-img.jpg" class="img-fluid" alt="UWC header image: a photo of the interior of the Writing Center." title="University Writing Center">
            </div>
            <div id="preamble">
                <h1 class="ps-5 py-2 mb-4">Peer Consultant Work Schedule Request</h1>
                <p>While filling out the form, keep the following in mind: travel time, lunch, time off campus needed for your studies, whether you prefer long days on campus or a few hours on campus, and what your UWC schedule will look like in addition to your course schedule. Please fill in the schedule availability form as accurately as possible. All fields in the schedule grid will need to be selected in order to submit the form.</p>
            </div>
            <div id="form">
                <form id="requestForm" method="post">
                    <?php displayFormValues($formValues, $formVars); ?>
                    <div class="g-recaptcha mb-4" data-sitekey="<?= $siteKey ?>"></div>
                    <button type="reset" class="btn btn-primary btn-lg me-2">Reset</button>
                    <button type="submit" class="btn btn-primary btn-lg" id="submitButton" name="submit">Submit</button>
                </form>
            </div>
        <?php endif; ?>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.min.js" integrity="sha384-cuYeSxntonz0PPNlHhBs68uyIAVpIIOZZ5JqeqvYYIcEL727kskC66kF92t6Xl2V" crossorigin="anonymous"></script>
    </body>
</html>