Cloudfront Invalidator Daemon
=============================

*Created:* 01/13/2013
*Version:* 1

###Introduction<hr />
We all know that Amazon CloudFront is an excellent service for distributing your content to end user with highest speed and lowest latency.
Often, times come when we have files dynamically being created by different programs on our origin server and they now need to be updated
on CloudFront too. But there are still couple of hours left on CloudFront for the cache to expire. This was the inspiration in building this tool.
I had a similar situation where i needed to invalidate a bunch of file being generated by different programs, some perl, shell, php etc. Using a command line
tool becomes very convinient in a situation like this where all services can call this tool and pass the filenames they want to invalidate and this tool will
take care of the rest. It will also write a log file which can be monitored by administrator for debugging.

This daemon will take CloudFront object names from command line, and will monitor the request until it gets completed. Will also write to log file for convenience.

###License<hr />
Please feel free to use or modify this code as per your needs. It is free for commercial or personal use

###System Requirements<hr />
Any computer with PHP installed along with pear's HTTP_REQUEST2.
If you do not have it, install using:

`pear install --onlyreqdeps HTTP_Request2`

You also need php-posix on your system in order for process forking to work. If you get an error saying "Call to undefined function posix_setsid()",
install it using:
`yum install php-posix`


###Installation<hr />
1. Place CloudFront.php and invalidateObjects.php in any folder. If you intend to run the tool from anywhere, i would suggest copying the files in your
/usr/local/bin folder.
2. Make sure that invalidateObjects.php is executable or you will have to run it by using `php invalidateObjects.php /dir1/file1 /dir2/file2`
3. Put valid settings for your cloudfront access key, secret key, distribution id and log file name in invalidateObjects.php file.

You are good to go now.

###Usage<hr />
Usage is very simple and self explanatory. Simply run it like this:
`invalidateObjects.php /path1/image1 /path2/image2 /path3/image3`

If you want to run the tool from another program i.e. PHP or PERL, you can redirect the STDOUT & STDERR to /dev/null so that the caller does not wait for the tool to finish execution.
something like `invalidateObjects.php /path1/image1 /path2/image2 /path3/image3 >/dev/null 2>/dev/null` would work for that purpose.

Enjoy!!!
