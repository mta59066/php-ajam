php-ajam
========

Forked from http://ccesario.blogspot.com/2010/04/php-ajam.html

Original Author: Carlos Alberto Cesario

License: http://www.gnu.org/copyleft/gpl.html GPL

Class to work with AJAM in Asterisk using PHP 5.x
-------------------------------------------------

Details:
You need to edit your Asterisk configuration files to enable the following

### In http.conf:

    [general]
    enabled = yes
    prefix=asterisk
    enablestatic = yes

### In manager.conf

    [general]
    enabled = yes
    webenabled = yes

##### Asterisk-1.4.x

    [admin]
    secret = test
    read = system,call,log,verbose,command,agent,user,config
    write = system,call,log,verbose,command,agent,user,config

##### Asterisk-1.6.x

    [admin]
    secret = test
    read=system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan
    write=system,call,agent,user,config,command,reporting,originate

##### Asterisk-1.8 and higher
    [admin]
    secret = test
    read = system,call,log,verbose,command,agent,user,config,dtmf,reporting,cdr,dialplan,originate
    write = system,call,log,verbose,command,agent,user,config,dtmf,reporting,cdr,dialplan,originate

Make avaliable the ajam_cookie file/directory with read/write permission to http user


### Sample Usage
```php
<?php
require_once('Ajam.php');

// Configuration Options, edit to suit environment:
$config['urlraw'] = 'http://<server_ip>:8088/asterisk/rawman';  // use pbx ip, port number, http prefix, full URL to rawman
$config['admin'] = 'admin';  // as defined in manager.conf
$config['secret'] = 'secret';  // as defined in manager.conf
$config['authtype'] = 'plaintext'; 
$config['cookiefile'] = null;
$config['debug'] = null;  // set to true for verbose debug output, older versions don't accept false

$ajam = new Ajam($config);
if ($ajam) {
	// ping connection and get response
	$ajam->doCommand('ping');
	$test = $ajam->getResult();
	echo $test['Response'];  // will echo 'Success' if command was successful

	// sample command to bridge local extension 100 with external number 555-1212
	$foo['Channel'] = "local/100@from-internal";
	$foo['Context'] = 'from-internal';
	$foo['Exten'] = '5551212';
	$foo['Priority'] = '1';
	$foo['Async'] = 'yes';
	$foo['Timeout'] = '30000';
	$foo['CALLERID'] = '"AJAM"<5556666>';
	$test = $ajam->doCommand('originate', $foo);
}

else {
	echo "ajam connection failed</p>";
}
```
