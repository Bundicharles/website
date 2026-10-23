<?php
// ============================================================
// api/src/controllers/ContactController.php
// ============================================================
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__)    . '/middleware/response.php';

class ContactController {
    public function submit(): void {
        $body    = getJsonBody();
        $name    = trim($body['name']    ?? '');
        $email   = trim($body['email']   ?? '');
        $subject = trim($body['subject'] ?? '');
        $message = trim($body['message'] ?? '');
        $errors  = [];

        if ($name    === '' || mb_strlen($name)    > 200) $errors[] = 'Valid name required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'Valid email required.';
        if ($subject === '' || mb_strlen($subject) > 300) $errors[] = 'Valid subject required.';
        if (mb_strlen($message) < 10)                      $errors[] = 'Message too short.';
        if (mb_strlen($message) > 5000)                    $errors[] = 'Message too long.';

        if ($errors) jsonError(422, 'Validation failed.', $errors);

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

        getDB()->prepare(
            "INSERT INTO contact_messages (name, email, subject, message, ip_address)
             VALUES (?,?,?,?,?)"
        )->execute([$name, $email, $subject, $message, $ip]);

        jsonSuccess(null, 'Your message has been sent. We will be in touch shortly.');
    }
}
