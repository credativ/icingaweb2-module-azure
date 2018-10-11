<a id="Installation"></a>Installation
=====================================

Requirements
------------

* Icinga Web 2 (&gt;= 2.4.1)
* PHP (&gt;= 5.3 or 7.x)
* php-curl

Once you got Icinga Web 2 up and running, all required dependencies should
already be there. Php-curl is available on all major Linux distributions and
can be installed with your package manager (yum, apt...).
Same applies for non-Linux systems. Please do not forget to restart your
web server or phpfpm service afterwards.

Installation from .tar.gz
-------------------------

Download the latest version and extract it to a folder named `azure`
in one of your Icinga Web 2 module path directories.

Enable the newly installed module
---------------------------------

Enable the `azure` module either on the CLI by running...

```sh
icingacli module enable azure
```

...or go to your Icinga Web 2 frontend, choose `Configuration` -&gt; `Modules`
-&gt; `azure` module - and `enable` it.




