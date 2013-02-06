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

Make avaliable the ajam_cookie file/directory with read/write permission to http user

