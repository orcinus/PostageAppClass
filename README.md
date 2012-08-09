PostageAppClass
===============

A generic PHP class meant to ease/abstract the use of PostageApp (http://postageapp.com/) mailing services.

# Contents
Just a single file with the class, that's it. Config boils down to listing your projects and API keys, so it's in the same file. Move it into a separate include if you think it's necessary.

# Installation
Check it out, move it wherever appropriate, list your project names and accompanying API keys in an array marked "ADD OUR POSTAGE APP PROJECTS HERE", include it and start using it. Note: requires cURL!

# Methods

### Static
- listProjects() - returns an array of all the project names hardcoded into the class

### Setters
- setProject($project) - sets current project to $project
- setRecipients($recipients) - sets the e-mail recipients to $recipients; can be an array or a comma separated string - both versions get internally converted into an array to avoid divulging e-mail addresses to third parties (by default, PostageApp shows all addresses to all recipients if they're passed as a comma separated string)
- setSubject($subject) - sets the e-mail subject to $subject
- setFrom($from) - sets the from field to $from
- setReplyto($replyto) - sets the reply-to field to $replyto; if not set, it defaults to same value as the from field
- setHeader($header) - sets the additional header fields to $header; fields are passed as an array, but if a single string is passed, it will get converted into an array; refer to PostageApp documentation for further info (field names)
- setBody($bodyPlain, $bodyHTML) - sets the e-mail body to $bodyPlain for plain text and $bodyHTML for HTML; if $bodyHTML is ommitted, it defaults to same as $bodyPlain
- setTemplate($template) - sets the current message template to $template
- setGlobalVars($values) - sets the global template variable values to ones specified in the $values array (formed as "variable" => "value")
- setRecipientsWithVars($recipients) - alternative to setRecipients that also sets per-recipient template variables; accepts an array of recipients with subarrays of [variable] => "value" pairs for each recipient; recipient addresses are stored in array keys
- setAttachments($attachments) - sets the message attachments as specified by the array $attachments; can be either a single attachment or an array of attachments; allows passing mixes of file contents and URLs; refer to source for proper array form

### API endpoints
- customMail($uid) - sends a custom (meaning non-template) e-mail; if $uid is not supplied, it gets generated from the API call arguments and current timestamp; requires recipients, from, subject and either plaintext or HTML body to be set; returns UID on success, error on failure
- templateMail($uid) - sends a template based e-mail; if $uid is not supplied, it gets generated from the API call arguments and current timestamp; requires recipients and template to be set; returns UID on success, error on failure
- getMessageReceipt($uid) - retrieves a receipt (checks if message was received by PostageApp successfully) for message with UID $uid; returns status (usually "ok") on success, error on failure
- getMessages() - retrieves a list of messages in the current project; returns the list on success, error on failure
- getMessageStatus($uid) - retrieves the status of a message with the UID $uid - namely, its transmissions; returns transmissions on success, error on failure
- getMetrics() - retrieves metrics for the currently active project; returns metrics data on success, error on failure

### Properties
- $response - contains the current API call's response or FALSE if it failed
- $error - contains the error from an API call's failure or FALSE if it succeeded

# Usage
All the setters are chainable. All the API endpoint methods are not. The idea being that you will chain the setters and finish with an API call. The sole static method is used before instantiating the object, to get a list of projects. You can set the current project during instantiation ($mypostage = new PostageApp("myproject")) or omit it and set it after the fact with setProject(). API call methods don't tell you if an operation succeeded or errored out. You can either parse the returned value yourself, or check the $response and $error properties to find that out. Beware the fact that internal properties for recipients, attachments, subject etc. DO NOT get reset after executing customMail() and templateMail()! That will change in the future versions. They do, however, get reset if you switch projects using setProject().

# Example
      $projects = PostageApp::listProjects();
      $mypostage = new PostageApp($projects[0]);
      $mypostage->setRecipients($recipients)->setFrom($us)->setReplyto($us);
      $response = $mypostage->setSubject("Our Fancy Subject")->setBody($body)->customMail();
      
      $mypostage->setProject($projects[1])->setRecipients($recipients2);
      $response = $mypostage->setTemplate("FANCY_PANTS_TEMPLATE")->templateMail();
      if($response->status) $transmissions = $mypostage->getMessageStatus($response->uid);