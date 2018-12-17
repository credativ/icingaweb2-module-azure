Troubleshooting
===============

All breaking errors should raise an Exception which will be displayed
in the Icinga Director Web UI. Make shure, you check the configured
importers by looking into the Director / import source, select the
import source to test and click **check for changes** and **trigger
import run** manually. If there is no error message, this is a good
sign.

You also can make use of the **preview** tab in the importer setup web
page in the Icinga Web2 UI right where you manage or create the
importers.

For extended trouble shooting, the Azure API pluging makes use of the
Icingaweb2 standard logging function. Depending on your Icingaweb2
configuration this might be a log file or routing log messages to your
syslog facility.

The Icinga Web2 Azure API module logs a good bunch of information
level messages into syslog. This will enable you to nail down where the
import run breaks if it does. There is, of course, also some more
logging, if switching log level to **debug**. But don't expect to much
as I only add debug log level messages for killing bugs or testing. So
not any piece of code is covered with debug level logging stuff. 

Additionally, the importer will log warnings and errors with these
log levels set correctly if encountered. Make shure not to mask these
in your syslog service.

The somewhat verbose information level log entries can be ignored
safely in normal operation mode. Same goes to debug log level.
