<?php
/**
 *    This file is part of oxchkversion.
 *
 *    oxchkversion is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    You can redistribute it and/or modify it under the terms of the
 *    GNU General Public License as published by the Free Software Foundation,
 *    either version 3 of the License, or (at your option) any later version.
 *
 *    See <http://www.gnu.org/licenses/>.
 *
 * @link http://www.oxid-esales.com
 */

error_reporting (E_ALL);

DEFINE("WEBSERVICE_SCRIPT", "http://oxchkversion.oxid-esales.com/webService.php");
//DEFINE("WEBSERVICE_SCRIPT", "http://testshops/webService.php");

DEFINE("WEBSERVICE_URL", WEBSERVICE_SCRIPT."?md5=%MD5%&ver=%VERSION%&rev=%REVISION%&edi=%EDITION%&fil=%FILE%");

/**
 * Version of this file
 */
define("MYVERSION", "3.0.14");

/**
 * Template which is printed out. Should not be in here but as
 * we have to keep everything in one file ...
 *
 * @var string
 */
$sHTMLTemplate = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-15">
    <title>OXID Check Version</title>
</head>

<body>

%HTMLCONTENT%

</body>
</html>';


/*****************************************************************************
 *
 * Interface which all classes here have to implement
 *
 *****************************************************************************/
interface oxchkversion
{
    /**
     * Main method
     *
     * @return null
     */
    public function run();

    /**
     * Returns result
     *
     * @return string
     */
    public function getResult();
}

/*****************************************************************************/

/**
 * Very base implememntation with some methods already implemented
  */
abstract class oxchkversionBase implements oxchkversion
{

    /**
     * constructor
     */
    public function __construct ()
    {
        include_once "config.inc.php";
        mysql_connect($this->dbHost, $this->dbUser, $this->dbPwd) or die("can't connect");
        mysql_select_db($this->dbName) or die("Can't select DB");
    }

    /**
     * Detects version in database
     *
     * @return string
     */
    public function getVersion ()
    {
        $res = mysql_query("select oxversion from oxshops limit 1");
        $row = mysql_fetch_array($res);
        return $row[0];
    }

    /**
     * Detects edition in this database
     *
     * @return string
     */
    public function getEdition ()
    {
        $sRetVal = '(unknown)';

        $res = mysql_query("select oxedition from oxshops limit 1");

        if (mysql_num_rows($res) > 0) {
            $row = mysql_fetch_array($res);
            $sRetVal = $row[0];
        }

        return $sRetVal;
    }

    /**
     * Detects revision from pkg.rev
     *
     * @return unknown
     */
    public function getRevision ()
    {
        $sRevision = "";

        if (file_exists('pkg.rev')) {
            $aRevision = file('pkg.rev');
            $sRevision = trim($aRevision[0]);
        }

        return $sRevision;
    }

    /**
     * Builds full webservice URL
     *
     * @param string $sJob      Job to execute, if needed
     * @param string $sVersion  Version to take, if needed
     * @param string $sRevision Revision to take, if needed
     * @param string $sEdition  Edition to take, if needed
     * @param string $sFile     Filename to take, if needed
     * @param string $sMD5      MD5 to take, if needed
     *
     * @return string Full URL
     */
    public function getFullWebServiceURL ($sJob='', $sVersion='', $sRevision='', $sEdition='', $sFile='', $sMD5='')
    {
        $sWebservice_url = WEBSERVICE_URL;

        $sWebservice_url = str_replace ('%MD5%',      urlencode($sMD5),      $sWebservice_url);
        $sWebservice_url = str_replace ('%VERSION%',  urlencode($sVersion),  $sWebservice_url);
        $sWebservice_url = str_replace ('%REVISION%', urlencode($sRevision), $sWebservice_url);
        $sWebservice_url = str_replace ('%EDITION%',  urlencode($sEdition),  $sWebservice_url);
        $sWebservice_url = str_replace ('%FILE%',     urlencode($sFile),     $sWebservice_url);
        $sWebservice_url .= '&job='.urlencode($sJob);

        return $sWebservice_url;
    }

    /**
     * Gets _GET[$sParamName]
     *
     * @param string $sParamName Parameter to read from _GET
     *
     * @return string Parameter
     */
    public function getParam($sParamName)
    {
        $sRetVal = '';
        if (isset($_GET[$sParamName]) && !empty($_GET[$sParamName])) {
            $sRetVal = $_GET[$sParamName];
        }
        return $sRetVal;
    }
}

/*****************************************************************************/

/**
 * Returns some intro information
 */
class shopintro extends oxchkversionBase
{

    /**
     * Keeps HTML template for later output
     *
     * @var string
     */
    private $_sHTMLTemplate = "";

    /**
     * Text information to display
     *
     * @var string
     */
    private $_sIntroinformation = '
<h2>oxchkversion v %MYVERSION% at %MYURL% at %DATETIME%</h2>

<p>
This script is intended to check consistency of your OXID eShop. It collects names of php files and templates,
detects their MD5 checksum, connects for each file to OXID\'s webservice to determine if it fits this shop version.
</p>

<p>
It does neither collect nor transmit any license or personal information.
</p>

<p>
Data to be transmitted to OXID is:
</p>

<ul>
    <li>Filename to be checked</li>
    <li>MD5 checksum</li>
    <li>Version which was detected</li>
    <li>Revision which was detected</li>
</ul>

<p>
For more detailed information check out <a href="http://www.oxid-esales.com/de/news/blog/shop-checking-tool-oxchkversion-v3" target=_blank>OXID eSales\' Blog</a>.
</p>

%NEXTSTEP%';

    /**
     * Form button for next step
     *
     * @var string
     */
    private $_sForm = '
<form action = "">
    <input type="hidden" name="job" value="checker" >
    <input type=checkbox name="listAllFiles" value="listAllFiles">List all files (also those which were OK)<br><br>
    <input type="submit" name="" value=" Start to check this eShop right now (may take some time) " >
</form>';

    /**
     * Container for all error messages
     *
     * @var string
     */
    private $_sErrorMessageTemplate = '<p><font color="red"><b>These error(s) occured</b></font></p><ul>%ERRORS%</ul>';

    /**
     * Error message if support server ist not accessible
     *
     * @var unknown_type
     */
    private $_sErrorMessageCannotReachSupportServer = '<li><font color="red">Cannot access support server <a href="%WEBSERVICE_SCRIPT%">%WEBSERVICE_SCRIPT%</a>. This check cannot be executed. Please check firewall settings.</font></li>';

    /**
     * Error message if detected version does not exist in our database
     *
     * @var unknown_type
     */
    private $_sErrorMessageVersionDoesNotExist = '<li><font color="red">OXID eShop %EDITION% %VERSION% in Revision %REVISION% does not exist.</font></li>';

    /**
     * Error message if user has choosen "no" for updating database from 4.4.0 to 4.4.1
     *
     * @var unknown_type
     */
    private $_sErrorMessageNoCheckPossible = '<li><font color="red">Sorry. No oxchkversion check possible.</font></li>';

    /**
     * Error message if detected version does not exist in our database
     *
     * @var unknown_type
     */
    private $_sErrorMessageUpdateVersionInDB = '<li><font color="red">I\'ve detected 4.4.1 revision number but 4.4.0 version in database. Fix database?</font></li><br />
    <form action = "">
    <input type="submit" name="updatedb" value="yes" >
    <input type="submit" name="nupdatedb" value="no" >
</form>';

    /**
     * Text information to display
     *
     * @var string
     */
    private $_sUpdateinformation = '
<p>
<font color="green"><b>Database was successfully updated to 4.4.1.</b></font>
</p>

%NEXTSTEP%';

    /**
     * Error message for system requirements check
     *
     * @var string
     */
    private $_sErrorMessage = "";

    /**
     * for requirements check
     *
     * @var boolean
     */
    private $_blCanReachSupportserver = false;

    /**
     * Contains if there was any error in initializing class
     *
     * @var bool
     */
    private $_blError = false;

    /**
     * Constructor
     *
     * @param string $sHTMLTemplate HTML template to use
     */
    public function __construct($sHTMLTemplate)
    {
        parent::__construct();

        $this->_sHTMLTemplate = $sHTMLTemplate;
        if ( $this->checkForUpdate() ) {
            $this->checkSystemRequirements();
        }
    }

    /**
     * Runs shop version update in db (according to bug #2003)
     *
     * @return bool
     */
    private function _updateDb()
    {
        $res = mysql_query("UPDATE `oxshops` SET `OXVERSION` = '4.4.1'");
        return $res;
    }

    /**
     * If button yes was clicked, updates shop version. If - no, returns only error message.
     * (according to bug #2003)
     *
     * @return bool
     */
    public function checkForUpdate()
    {
        if ( $this->getParam('updatedb') ) {
            if ( $this->_updateDb() ) {
                $this->_sIntroinformation = str_replace ('%NEXTSTEP%', $this->_sUpdateinformation, $this->_sIntroinformation);
            }
            return true;
        } elseif ($this->getParam('nupdatedb')) {
            $this->_blError = true;
            $this->_sErrorMessage = $this->_sErrorMessageNoCheckPossible;
            return false;
        }
        return true;
    }

    /**
     * Checks system requirements and builds error messages if there are some
     *
     * @return null
     */
    public function checkSystemRequirements()
    {
        // First check if Supportserver is reachable
        $rContent = @fopen(WEBSERVICE_SCRIPT, 'r');
        if ($rContent !== false) {
            fclose($rContent);
            $this->_blCanReachSupportserver = true;
        } else {
            $this->_blError = true;
            $this->_sErrorMessage = str_replace ('%WEBSERVICE_SCRIPT%', WEBSERVICE_SCRIPT, $this->_sErrorMessageCannotReachSupportServer);
        }

        // Then check if detected version exists in our database
        $sVersion   = $this->getVersion();
        $sEdition   = $this->getEdition();
        $sRevision  = $this->getRevision();

        // Additional check for bug #2003 in oxid bugtrack
        if ( $sVersion == "4.4.0" && $sRevision == "28950") {
            $this->_blError = true;
            $sError = $this->_sErrorMessageUpdateVersionInDB;
            $this->_sErrorMessage .= $sError;
            return;
        }

        $sURL = $this->getFullWebServiceURL('existsversion', $sVersion, $sRevision, $sEdition);

        if ($sXML = @file_get_contents($sURL)) {
            $oXML = new SimpleXMLElement($sXML);
            if ($oXML->exists == 0) {
                $this->_blError = true;
                $sError = $this->_sErrorMessageVersionDoesNotExist;

                $sError = str_replace ('%EDITION%', $sEdition, $sError);
                $sError = str_replace ('%VERSION%', $sVersion, $sError);
                $sError = str_replace ('%REVISION%', $sRevision, $sError);

                $this->_sErrorMessage .= $sError;
            }
        }
    }

    /**
     * Main method
     *
     * @return null
     */
    public function run ()
    {
        // does nothing
    }


    /**
     * Returns result of classes operations
     *
     * @return string
     */
    public function getResult ()
    {

        $sMessage = str_replace ('%HTMLCONTENT%', $this->_sIntroinformation, $this->_sHTMLTemplate);
        $sMessage = str_replace ('%MYVERSION%', MYVERSION, $sMessage);

        $sMyUrl = 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];
        $sMyUrl = '<a href="'.$sMyUrl.'">'.$sMyUrl."</a>";
        $sMessage = str_replace ('%MYURL%', $sMyUrl, $sMessage);

        $sDateTime = date('Y-m-d H:i:s', time());
        $sMessage = str_replace ('%DATETIME%', $sDateTime, $sMessage);

        if (!$this->_blError) {
            $sMessage = str_replace('%NEXTSTEP%', $this->_sForm, $sMessage);
        } else {
            // first build complete error text from template + specific errors
            $sError = str_replace('%ERRORS%', $this->_sErrorMessage, $this->_sErrorMessageTemplate);

            // then insert error tag where button should be
            $sMessage = str_replace('%NEXTSTEP%', $sError, $sMessage);
        }

        return $sMessage;
    }
}

/*****************************************************************************/

/**
 * This one collects to be checked files, checks each file and prints
 * result of checks
 */
class shopchecker extends oxchkversionBase
{
    /**
     * Useful hints if we detect modfied files
     *
     * @var unknown_type
     */
    private $_sModifiedHints1 = "OXID eShop has sophisticated possibility to extend it by modules without changing
    shipped files. It's not recommended and not needed to change shop files. See also our
    <a href=\"http://www.oxidforge.org/wiki/Tutorials#How_to_Extend_OXID_eShop_With_Modules_.28Part_1.29\" target=_blank>tutorials</a>.";
    private $_sModifiedHints2 = "Since OXID eShop 4.2.0 it's possible to use
    <a href=\"http://www.oxidforge.org/wiki/Downloads/4.2.0#New_Features\" target=_blank>your
    own templates without changing shipped ones</a>.";

    /**
     * Useful hints if we detect modfied files
     *
     * @var unknown_type
     */
    private $_versionMismatchHints = 'Apparently one or more updates went wrong. See details link for more
    information about more details for each file. A left over file which is not any longer included in OXID eShop
    could also be a <u>possible</u> reason for version mismatch. For more information see
    <a href="http://www.oxid-esales.com/en/resources/help-faq/manual-eshop-pe-ce-4-0-0-0/upgrade-update-eshop" target=_blank>handbook</a>.';

    /**
     * Keeps HTML template for later output
     *
     * @var unknown_type
     */
    private $_sHTMLTemplate = "";

    /**
     * For table's contents
     *
     * @var string
     */
    private $_sTableContent = "";

    /**
     * Array of all files which are to be checked
     *
     * @var array
     */
    private $_aFiles = array();

    /**
     * Edition of THIS OXID eShop - detected automatically
     *
     * @var string
     */
    private $_sEdition = "";

    /**
     * Version of THIS OXID eShop - detected automatically
     *
     * @var string
     */
    private $_sVersion = "";

    /**
     * Revision of THIS OXID eShop - detected automatically
     *
     * @var string
     */
    private $_sRevision = "";

    /**
     * Full Version tag of this OXID eShop
     *
     * @var string
     */
    private $_sVersionTag = "";

    /**
     * Counts number of matches for each type of result
     *
     * @var array
     */
    private $_aResultCount = array();

    /**
     * If the variable is true, the script will show all files, even they are ok.
     *
     * @var bool
     */
    private $_blListAllFiles = false;


    /**
     * Class constructor
     *
     * @param string $sHTMLTemplate HTML template to use
     */
    public function __construct($sHTMLTemplate)
    {
        parent::__construct();

        $this->_aFiles     = $this->_getOXIDFiles();
        $this->_sVersion   = $this->getVersion();
        $this->_sEdition   = $this->getEdition();
        $this->_sRevision  = $this->getRevision();
        $this->_sVersionTag = $this->_sEdition."_".$this->_sVersion."_".$this->_sRevision;

        if ( $this->getParam('listAllFiles') == 'listAllFiles' ) {
            $this->_blListAllFiles = true;
        }

        $this->_sTableContent .=  "<tr><td colspan=2><h2>oxchkversion v %MYVERSION% detected at %MYURL% at %DATETIME%</h2></td></tr>".PHP_EOL;
        $this->_sTableContent .=  "<tr><td><b>Edition</b>    </td><td>$this->_sEdition</td></tr>".PHP_EOL;
        $this->_sTableContent .=  "<tr><td><b>Version</b>    </td><td>$this->_sVersion</td></tr>".PHP_EOL;
        $this->_sTableContent .=  "<tr><td><b>Revision</b>   </td><td>$this->_sRevision</td></tr>".PHP_EOL;

        $this->_sTableContent = str_replace ('%MYVERSION%', MYVERSION, $this->_sTableContent);

        $sMyUrl = 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];
        $sMyUrl = '<a href="'.$sMyUrl.'">'.$sMyUrl."</a>";
        $this->_sTableContent = str_replace ('%MYURL%', $sMyUrl, $this->_sTableContent);

        $sDateTime = date('Y-m-d H:i:s', time());
        $this->_sTableContent = str_replace ('%DATETIME%', $sDateTime, $this->_sTableContent);

        $this->_aResultCount['OK'] = 0;
        $this->_aResultCount['VERSIONMISMATCH'] = 0;
        $this->_aResultCount['UNKNOWN'] = 0;
        $this->_aResultCount['MODIFIED'] = 0;

        $this->_sHTMLTemplate = $sHTMLTemplate;

        $this->sResultOutput = "";
        $this->_blShopIsOK = true;
    }

    /**
     * Collects recursevly all files
     *
     * @param string $sDirName Directoryname where to start
     * @param string $ext      Extension to search
     *
     * @return unknown
     */
    private function _getFilesRecursively($sDirName, $ext)
    {
        $aDirs = array($sDirName);
        $aFiles = array();
        while (!empty($aDirs)) {
            $check = array_pop($aDirs);
            foreach ( glob($check.'/*') as $sFileName) {
                if (is_dir($sFileName)) {
                    $aDirs[] = $sFileName;
                } else {
                    if (preg_match('/'.preg_quote($ext).'$/', $sFileName)) {
                        $this->_aFiles[] = $sFileName;
                    }
                }
            }
        }
        return $aFiles;
    }

    /**
     * Finds recursively files matching a specific pattern
     *
     * @param string $sPattern Pattern to search for
     * @param int    $iFlags   Flags passed to glob function
     * @param string $aPath    Path where to begin to search
     *
     * @return array single dimensional array of files
     */
    public function findFilesWhichEndsAsPattern($sPattern, $iFlags = 0, $aPath = '')
    {
        $aFiles = array();

        if (!$aPath && ($sDir = dirname($sPattern)) != '.') {
            if ($sDir == '\\' || $sDir == '/') $sDir = '';
            return $this->findFilesWhichEndsAsPattern(basename($sPattern), $iFlags, $sDir . '/');
        }

        $aPaths = glob($aPath . '*', GLOB_ONLYDIR | GLOB_NOSORT);

        // sometimes aGlob returns false
        $mTmp = glob($aPath . $sPattern, $iFlags);

        if (is_array($mTmp)) {
            $aFiles = $mTmp;
        }

        if (is_array($aPaths)) {
            foreach ($aPaths as $p) {
                $aFiles = array_merge($aFiles, $this->findFilesWhichEndsAsPattern($sPattern, $iFlags, $p . '/'));
            }
        }

        return $aFiles;
    }

    /**
     * Selects important directors and returns files in there
     *
     * @return array
     */
    private function _getOXIDFiles()
    {
        $this->_aFiles = glob( '*.php');

        $aTmp = glob( 'core/*.php');
        if (is_array($aTmp)) {
            $this->_aFiles = array_merge( $this->_aFiles, $aTmp);
        }

        $aTmp = glob( 'admin/*.php');
        if (is_array($aTmp)) {
            $this->_aFiles = array_merge( $this->_aFiles, $aTmp);
        }

        $aTmp = glob( 'views/*.php');
        if (is_array($aTmp)) {
            $this->_aFiles = array_merge( $this->_aFiles, $aTmp);
        }

        $this->_aFiles = array_merge( $this->_aFiles, $this->findFilesWhichEndsAsPattern( '*.php', 0, 'modules/'));

        $this->_aFiles = array_merge( $this->_aFiles, $this->findFilesWhichEndsAsPattern( '*.tpl', 0, 'out/'));

        return $this->_aFiles;
    }

    /**
     * Queries checksum-webservice according to md5, version, revision, edition and filename
     *
     * @param string $sMD5  MD5 to check
     * @param string $sFile File to check
     *
     * @return unknown
     */
    private function _getFilesVersion ($sMD5, $sFile)
    {
        $oRetVal = null;

        $sWebservice_url = $this->getFullWebServiceURL('md5check', $this->_sVersion, $this->_sRevision, $this->_sEdition, $sFile, $sMD5);
        if ($sXML = @file_get_contents($sWebservice_url)) {
            $oRetVal = new SimpleXMLElement($sXML);
        }

        return $oRetVal;
    }

    /**
     * Main method
     *
     * @return null
     */
    public  function run ()
    {
        foreach ( $this->_aFiles as $sFile) {

            $sMD5 = md5_file ( $sFile );
            $oXML = null;
            $iTryCount = 0;

            // Try max 5 times to get XML, just in case there are some connection errors
            while (!is_object($oXML) && ($iTryCount <= 5)) {

                // if connection error. Just give 'em some time to recover
                if ($iTryCount > 0) {
                    sleep(1);
                }

                $oXML = $this->_getFilesVersion ($sMD5, $sFile);

                $sColor = "blue";
                $sMessage = "This text is not supposed to be here. Please try again. If it still appears, call OXID support.";

                if (is_object($oXML)) {
                    if ($oXML->res == 'OK') {
                        // If recognized, still can be source or snapshot
                        $aMatch = array();
                        if (preg_match ('/(SOURCE|SNAPSHOT)/', $oXML->pkg, $aMatch)) {
                            $this->_blShopIsOK = false;
                            $sMessage = $aMatch[0];
                            $sColor = 'red';
                        } else {
                            $sMessage = '';
                            if ( $this->_blListAllFiles ) {
                                $sMessage = 'OK';
                            }
                            $sColor = "green";
                        }
                    } elseif ($oXML->res == 'VERSIONMISMATCH') {
                        $sMessage = 'Version mismatch';
                        $sColor = 'red';
                        $this->_blShopIsOK = false;
                    } elseif ($oXML->res == 'MODIFIED') {
                        $sMessage = 'Modified';
                        $sColor = 'red';
                        $this->_blShopIsOK = false;
                    } elseif ($oXML->res == 'UNKNOWN') {
                        $sMessage = '';
                        $sColor = "green";
                    }

                    $this->_aResultCount[strval($oXML->res)]++;
                }

                if ( in_array( $sFile, array( "oxchkversion.php", 'config.inc.php', 'oxchkversion2.php'))) {
                    continue;
                }
                if ($sMessage) {
                    $sMyURL=$_SERVER['SCRIPT_NAME']."?md5=$sMD5&amp;ver=".$this->_sVersion."&amp;rev=".$this->_sRevision."&amp;edi=".$this->_sEdition."&amp;fil=$sFile&amp;job=details";
                    $sMessage .= " (<a href=\"$sMyURL\" target=_blank>details</a>)";

                    $this->sResultOutput .= "<tr><td>$sFile</td>";
                    $this->sResultOutput .= "<td>";
                    $this->sResultOutput .= "<b><font color=\"$sColor\">";
                    $this->sResultOutput .= $sMessage;
                    $this->sResultOutput .= "</font></b>";
                    $this->sResultOutput .= "</td></tr>".PHP_EOL;
                }
            }
        }
    }

    /**
     * Returns result of classes operations
     *
     * @return string
     */
    public function getResult ()
    {

        // first build summary table
        $this->_sTableContent .=  "<tr><td><b>&nbsp;</b>   </td><td>&nbsp;</td></tr>".PHP_EOL;
        $this->_sTableContent .=  "<tr><td colspan=\"2\"><h2>Summary</h2>   </td></tr>".PHP_EOL;
        $this->_sTableContent .=  "<tr><td><b>OK</b>   </td><td>".$this->_aResultCount['OK']."</td></tr>".PHP_EOL;
        $this->_sTableContent .=  "<tr><td><b>Modified</b>   </td><td>".$this->_aResultCount['MODIFIED']."</td></tr>".PHP_EOL;
        $this->_sTableContent .=  "<tr><td><b>Version mismatch</b>   </td><td>".$this->_aResultCount['VERSIONMISMATCH']."</td></tr>".PHP_EOL;
        $this->_sTableContent .=  "<tr><td><b>Unknown</b>   </td><td>".$this->_aResultCount['UNKNOWN']."</td></tr>".PHP_EOL;
        $this->_sTableContent .=  "<tr><td><b>Number of investigated files in total:</b>   </td><td>".count($this->_aFiles)."</td></tr>".PHP_EOL;

        // then according to detectded erroros, print hints
        $iSum = $this->_aResultCount['OK']
              + $this->_aResultCount['MODIFIED']
              + $this->_aResultCount['VERSIONMISMATCH']
              + $this->_aResultCount['UNKNOWN'];
        if ($iSum <> count($this->_aFiles)) {
            $this->_sTableContent .=  "<tr><td colspan=\"2\"><b>&nbsp;</b></td></tr>".PHP_EOL;
            $this->_sTableContent .=  "<tr><td colspan=2><b><font color=\"red\">Attention: Count does not match. Please tell OXID support! ".$this->_sVersionTag.".</font></b></td></tr>".PHP_EOL;
        }

        $this->_sTableContent .=  "<tr><td><b>&nbsp;</b>   </td><td>&nbsp;</td></tr>".PHP_EOL;
        if ($this->_blShopIsOK) {
            $this->_sTableContent .=  "<tr><td colspan=2><b><font color=\"green\">This OXID eShop was not modified and is fully original.</font></b></td></tr>".PHP_EOL;
        } else {
            $this->_sTableContent .=  "<tr><td colspan=2><b><font color=\"red\">This OXID eShop does not fit 100% ".$this->_sVersionTag.".</font></b></td></tr>".PHP_EOL;
        }

        $sHints = "";
        if ($this->_aResultCount['MODIFIED'] > 0) {
            $sHints .=  "<tr><td colspan=\"2\">* ".$this->_sModifiedHints1."</td></tr>".PHP_EOL;
            $sHints .=  "<tr><td colspan=\"2\">* ".$this->_sModifiedHints2."</td></tr>".PHP_EOL;
        }

        if ($this->_aResultCount['VERSIONMISMATCH'] > 0) {
            $sHints .=  "<tr><td colspan=\"2\">* ".$this->_versionMismatchHints."</td></tr>".PHP_EOL;
        }

        if ($sHints) {
            $this->_sTableContent .=  "<tr><td colspan=\"2\"><b>&nbsp;</b></td></tr>".PHP_EOL;
            $this->_sTableContent .=  "<tr><td colspan=\"2\"><h2>Hints</h2>   </td></tr>".PHP_EOL;
            $this->_sTableContent .= $sHints;
        }


        // then print result output
        if ($this->sResultOutput) {
            $this->_sTableContent .=  "<tr><td><b>&nbsp;</b></td><td>&nbsp;</td></tr>".PHP_EOL;
            $this->_sTableContent .=  "<tr><td colspan=\"2\"><h2>Details</h2>   </td></tr>".PHP_EOL;
            $this->_sTableContent .=  $this->sResultOutput;
        }

        $this->_sTableContent = "<table>".PHP_EOL.$this->_sTableContent.PHP_EOL."</table>";

        return str_replace ('%HTMLCONTENT%', $this->_sTableContent, $this->_sHTMLTemplate);
    }
}

/*****************************************************************************/

/**
 * This class requests file details from MD5 webservice and prints it
 */
class shopdetails extends oxchkversionBase
{
    /**
     * Keeps HTML template for later output
     *
     * @var unknown_type
     */
    private $_sHTMLTemplate = "";

    /**
     * Container for webservice result
     *
     * @var unknown_type
     */
    private $_oXML = null;

    /**
     * Class constructor
     *
     * @param string $sHTMLTemplate HTML template to use
     */
    public function __construct($sHTMLTemplate)
    {
        $this->_sEdition  = preg_replace('/([^OCPE])/', '',         $this->getParam('edi'));
        $this->_sVersion  = preg_replace('/([^1234567890\.])/', '', $this->getParam('ver'));
        $this->_sRevision = preg_replace('/([^1234567890])/', '',   $this->getParam('rev'));
        $this->_sFileName = preg_replace('/([^abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890\.\/_-])/', '', $this->getParam('fil'));
        $this->_sMD5      = preg_replace('/([^1234567890abcdef])/', '', $this->getParam('md5'));

        $this->_sHTMLTemplate = $sHTMLTemplate;
    }

    /**
     * Queries checksum webservice according to md5, version, revision, edition and filename
     *
     * @return object
     */
    private function _getFileInfo ()
    {
        $oRetVal = null;

        $sWebservice_url = $this->getFullWebServiceURL('details', $this->_sVersion, $this->_sRevision, $this->_sEdition, $this->_sFileName, $this->_sMD5);
        if ($sXML = @file_get_contents($sWebservice_url)) {
            $oRetVal = new SimpleXMLElement($sXML);
        }
        return $oRetVal;
    }

    /**
     * Main method
     *
     * @return null

     */
    public function run ()
    {
        $this->_oXML = $this->_getFileInfo();
    }

    /**
     * Returns result of classes operations
     *
     * @return string
     */
    public function getResult ()
    {

        $sTableContent =   "<tr><td colspan=2><h2>Details</h2></td></tr>".PHP_EOL;
        $sTableContent .=  "<tr><td colspan=2>&nbsp;</td></tr>".PHP_EOL;
        $sTableContent .=  "<tr><td><b>Filename</b></td><td>".$this->_oXML->fil."</td></tr>".PHP_EOL;
        $sTableContent .=  "<tr><td colspan=2>&nbsp;</td></tr>".PHP_EOL;
        $sTableContent .=  "<tr><td><b>MD5</b></td><td>".$this->_oXML->md5."</td></tr>".PHP_EOL;
        $sTableContent .=  "<tr><td colspan=2>&nbsp;</td></tr>".PHP_EOL;

        $aResult = $this->_oXML->xpath('possible_sources/pkg');
        $sHeadline = "Source(s) of this MD5&nbsp;&nbsp;";
        while (list( , $node) = each($aResult)) {
            $sTableContent .=  "<tr><td><b>$sHeadline </b></td><td>".$node[0]."</td></tr>".PHP_EOL;
            $sHeadline = "";
        }
        if ($sHeadline != "") {
            $sTableContent .=  "<tr><td><b>$sHeadline </b></td><td>(not found)</td></tr>".PHP_EOL;
        }

        $sTableContent .=  "<tr><td colspan=2>&nbsp;</td></tr>".PHP_EOL;

        $aResult = $this->_oXML->xpath('file_also_in/pkg');
        $sHeadline = "Filename also found in";
        while (list( , $node) = each($aResult)) {
            $sTableContent .=  "<tr><td><b>$sHeadline </b></td><td>".$node[0]."</td></tr>".PHP_EOL;
            $sHeadline = "";
        }
        if ($sHeadline != "") {
            $sTableContent .=  "<tr><td><b>$sHeadline </b></td><td>(not found)</td></tr>".PHP_EOL;
        }

        $sTableContent = "<table>".PHP_EOL.$sTableContent.PHP_EOL."</table>";
        return str_replace ('%HTMLCONTENT%', $sTableContent, $this->_sHTMLTemplate);
    }

}

/*****************************************************************************/
/*****************************************************************************/
/*****************************************************************************/

/**
 * Main program
 */

// Sanitize _GET stuff and remove what we don't know
$sJob = oxchkversionBase::getParam('job');
if (isset($sJob)) {
    $sJob = preg_replace('/([^detailschecker])/', '', $sJob);
}

if ($sJob == "details") {
    $oShopchecker = new shopdetails($sHTMLTemplate);
} elseif ($sJob == "checker") {
    $oShopchecker = new shopchecker($sHTMLTemplate);
} else {
    $oShopchecker = new shopintro($sHTMLTemplate);
}

$oShopchecker->run();
echo $oShopchecker->getResult();
