# beanstalk-to-bugzilla
 * A php script to integrate [bugzilla](https://www.bugzilla.org/) with [beanstalk](http://beanstalkapp.com/)'s webhook integration.
 * Takes a POST from beanstalk and formats an email to send to bugzilla.

It requires that:
* you've configured [email_in.pl](https://www.bugzilla.org/docs/3.0/html/api/email_in.html) on your bugzilla server
* SMTP is configured and accessible from the host that this script runs on.

The flow is beanstalkapp webhook -> this script -> email_in.pl.
