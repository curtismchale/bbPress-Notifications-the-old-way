# bbPress Notifications the Old Way

Seems that bbPress updated it's notification scheme and [that can cause some problems](https://bbpress.org/forums/topic/cc-instead-of-bcc-in-notification-emails/).

This plugin moves away from the BCC field and loops through each subscribed emails sending each user a notification email.

Sort of like bbPress used to do.

## Releases

### 1.1

- manually stripping all BCC from the phpmailer object **note this means no BCC comes out of any email for any reason**

### 1.0

- initial plugin release so that we have looped notifications not BCC notifications
