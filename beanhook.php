<?php

/**
 * beanhook.php
 *
 * A php script to integrate with beanstalk's webhook integration.
 * Takes a POST from beanstalk and formats an email to send to bugzilla.
 *
 */

// This address is whatever feeds into your local copy of email-in.pl
// Read the bugzilla docs to configure it properly.
$BUGZILLA_EMAIL = "bugzilla@yourbugzillaserver";

 // The message templates

$MSG_HEADER = <<<EOF
From: %s <%s>
EOF;

$MSG_COMMENT_TEMPLATE = <<<EOF
@bug_id=%s

Comment from SVN
---------------------------
revision:
%s
author:
%s
comment:
%s
changed:
%s
URL: %s
EOF;

$MSG_CLOSE_TEMPLATE   = <<<EOF
@bug_id=%s
@resolution=FIXED
@bug_status=RESOLVED

Closing from SVN
---------------------------
revision:
%s
author:
%s
comment:
%s
changed:
%s
URL: %s
EOF;

/**
 * indent
 *
 * Prepends $number tabs to a $string
 *
 * @param string $string
 * @param integer $number
 * @return string
 */

function indent($string, $number = 1) {

    $tabs = "";
    for ($i=0; $i < $number; $i++) {
        $tabs .= "\t";
    }
    $pattern = '/^(.*)/';
    $replace = $tabs . '$1';
    return preg_replace($pattern, $replace, $string);
}

/**
 * buildHeader
 *
 * Builds a header for SMTP mail based on the passed parameters.
 *
 * @param string $headerTemplate
 * @param string $authorName
 * @param string $authorEmail
 * @return string
 */

function buildHeader($headerTemplate, $authorName, $authorEmail) {
    return sprintf ($headerTemplate, $authorName, $authorEmail);
}

/**
 * buildMessage
 *
 * Builds a message text based on the passed template.
 *
 * @param string $msgTemplate
 * @param string $bugId
 * @param string $revision
 * @param string $author
 * @param string $comment
 * @param string $changes
 * @param string $url
 * @return string
 */

function buildMessage($msgTemplate, $bugId, $revision, $author, $comment, $changes, $url) {
    return sprintf ($msgTemplate, $bugId, indent($revision), indent($author), $comment, $changes, $url);
}

/**
 * sendMessage
 *
 * sends a Message via SMTP
 *
 * @param string $to
 * @param string $subject
 * @param string $message
 * @param string $header
 */

function sendMessage($to, $subject, $message, $header) {

    $msg = wordwrap($message,70);

    mail($to, $subject, $msg, $header);
    return;
}
if ($_REQUEST['commit'] == null) {
    // didn't get any data, log it and die.
    error_log("Didn't receive any commit data.");
    die;
}
$data = json_decode($_REQUEST['commit'],true);
$revision = $data['revision'];
$comment = $data['message'];
$author = $data['author'];
$changed_files = $data['changed_files'];
$changed_dirs = $data['changed_dirs'];
$url = $data['changeset_url'];
$email = $data['author_email'];
$name = $data['author_full_name'];

// Header will be the same for all bugs
$header = buildHeader($MSG_HEADER,$name,$email);
$changes = "";
// Process the changed files and directories into a string called changes.
$allChanges = array_merge($changed_files,$changed_dirs);

foreach ($allChanges as $change) {
    switch ($change[1]) {
        case 'add':
            $changes .= "Added " . $change[0] . "\n";
            break;
        case 'edit':
            $changes .= "Modified " . $change[0] . "\n";
            break;
        case 'delete':
            $changes .= "Deleted " . $change[0] . "\n";
            break;
        case 'copy':
            $changes .= "Copied " . $change[0][1] . " revision r" . $change[0][2] . " to " . $change[0][0] . "\n";
            break;
        case 'move':
            $changes .= "Moved " . $change[0][1] . " revision r" . $change[0][2] . " to " . $change[0][0] . "\n";
            break;
        default:
            $changes .= "Unknown: " . print_r($change,true);
            break;
    }

}
// Process the comments and pick out all the Comment: # and Closes: #
$matches = array();
if (preg_match_all('/(Comment|Closes|Close|Fixes|Resolves): #(\d+)/i',$comment,$matches)) {
    foreach ($matches[1] as $index => $value) {
        $bugId = $matches[2][$index];
        if (preg_match('/Comment/i',$value)) {
            $message = buildMessage($MSG_COMMENT_TEMPLATE, $bugId, $revision, $author, $comment, $changes, $url);
            sendMessage($BUGZILLA_EMAIL, "Bug " . $bugId, $message, $header);
        } else {
            $message = buildMessage($MSG_CLOSE_TEMPLATE, $bugId, $revision, $author, $comment, $changes, $url);
            sendMessage($BUGZILLA_EMAIL, "Bug " . $bugId, $message, $header);
        }
    }
}
