#!/usr/bin/php
<?php
require_once 'CloudFront.php';
$accessKey = '';
$secretKey = '';
$distributionId = '';
$logFileName = '';


$objectsToInvalidate = getObjectNamesFromCommandLine();

daemonizeProcess();
invalidateCloudfrontObjects($accessKey, $secretKey, $distributionId, $objectsToInvalidate);


/**
 * Takes the object names to be invalidated from the command line
 * If anything is not entered, gives the user a message on how to use th program.
 * @return array
 */
function getObjectNamesFromCommandLine()
{
    global $argv;
    if (count($argv) > 1) {
        $objectNames = array_slice($argv, 1);
        return $objectNames;
    } else {
        echo "Please enter object names to be invalidated on the command line separated by space." . "\n";
        echo "Example: invalidateObjects.php /images/image1.jpg /images/image2.jpg" . "\n";
        exit();
    }
}

/**
 * Write messages to a log file
 * @param $message
 */
function writeLog($message)
{
    global $logFileName;
    if (!$logFileName) {
        return;
    }

    $date = date('m.d.Y h:i:s');
    $message = '[ ' . $date . ' ] ' . $message;
    if (!error_log($message . "\n", 3, $logFileName)) {
        echo "There was some problem writing to logfile " . $logFileName;
    }
}

/**
 *Demonize the process
 */
function daemonizeProcess()
{
    declare(ticks = 1) ;
    $processId = pcntl_fork();
    if ($processId == -1) {
        echo "\n Error:  Error forking process. \n";
    } else if ($processId) {
        exit;
    }

    if (posix_setsid() == -1) {
        echo "\n Error: Unable to detach from the terminal window. \n";
    }
//$posixProcessID = posix_getpid();
}

/**
 * This function will handle the invalidation requests.
 * If it gets status code 400, it will keep waiting for a few hours and try to execute the invalidation until successful
 * If it gets through the request and the logging file is specified, it will run the checkForRequestCompletion function
 * On any other HTTP response code other than these 2, it will exit.
 * @param $accessKeyId
 * @param $secretKey
 * @param $distributionId
 * @param $objectsToInvalidate
 */
function invalidateCloudfrontObjects($accessKeyId, $secretKey, $distributionId, $objectsToInvalidate)
{
    if(!$accessKeyId || !$secretKey || !$distributionId)
    {
        print "Improper AWS credentials found. Aborting...\n";
        return;
    }
    $cloudFront = new CloudFront($accessKeyId, $secretKey, $distributionId);

//Try to invalidate Objects on CloudFront
    $didObjectsInvalidate = $cloudFront->invalidateObjects($objectsToInvalidate);

//We have something unexpected. Lets log it and exit.
    if (!$didObjectsInvalidate) {
        // If response code is not 400, we have some unexpected error.
        if ($cloudFront->getResponseCode() != 400) {
            writeLog($cloudFront->getResponseMessage());
            return;
        }

        //If Response code is 400, too many requests are going on.We will retry for some hours
        if ($cloudFront->getResponseCode() == 400) {
            writeLog($cloudFront->getResponseMessage());
            for ($i = 1; $i <= 48; $i++) {
                sleep(300);
                $didObjectsInvalidate = $cloudFront->invalidateObjects($objectsToInvalidate);
                if ($didObjectsInvalidate) {
                    writeLog("Retry Successful...");
                    break; //Exit the for loop and execute the IF block below
                }

            }
            //If the whole for loop executed, and nothing happened, we will write an error to log.
            if (!$didObjectsInvalidate) {
                writeLog("No retries for invalidation were successful. Exiting.");
            }
        }
    }

//We are putting an explicit IF here and not an else because we want to execute this
//when we get a response code 400 and a retry was successful
    if ($didObjectsInvalidate) {
        //We got 201, log it
        writeLog($cloudFront->getResponseMessage());
        checkForRequestCompletion($cloudFront);
    }
}

/**
 * This function checks if the Invalidation ID we got from previous invalidation request
 * It keeps on looping until the invalidation request gets completed in AWS.
 * Once it detects a completed request, it logs it and exits.
 *
 * If no logfile has to be written, there is no point in running this function. We exit at that condition.
 * @param CloudFront $cloudFront
 */
function checkForRequestCompletion(CloudFront $cloudFront)
{
    global $logFileName;
    //If logging is off, we don't need to wait and check if request got completed.
    if (!$logFileName) {
        return;
    }

    $invalidationComplete = false;
    while ($invalidationComplete == false) {
        //Now we need to wait until our invalidation is complete
        sleep(60);
        $successfulInvalidationId = $cloudFront->getSuccessfulInvalidationId();
        if(!$successfulInvalidationId) {
            writeLog('No valid invalidation id found to check.');
            return;
        }
        $invalidationComplete = $cloudFront->didInvalidationComplete($successfulInvalidationId);

        if (!$invalidationComplete) {
            if ($cloudFront->getResponseCode() == 200) {
                //Invalidation is in progress. Wait another 5 minutes and retry.
                continue;
            }

            if ($cloudFront->getResponseCode() != 200) {
                //We have some other problem. We need to log it and exit
                writeLog($cloudFront->getResponseMessage());
                break; //Exit while loop
            }

        }

        if ($invalidationComplete) {
            writeLog($cloudFront->getResponseMessage());
        }
    }
}

