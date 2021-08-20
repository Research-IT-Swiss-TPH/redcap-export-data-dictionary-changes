<?php

// Set the namespace defined in your config file
namespace STPH\exportDataDictionaryChanges;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//  Autoload composer files if in dev environment and composer installed
if( $GLOBALS["is_development_server"] && file_exists("vendor/autoload.php")){
    require 'vendor/autoload.php';
}


// Declare your module class, which must extend AbstractExternalModule 
class exportDataDictionaryChanges extends \ExternalModules\AbstractExternalModule {

    /** @var string */
    private $moduleName;

    /** @var bool */
    private  $hasExport;
    /** @var bool */
    private $isExportActive;
    /** @var bool */
    private $isEmailActive;

    /** @var bool */
    private $hasExportDownloadForAutomatic;
    /** @var bool */
    private $hasExportMailForAutomatic;

    /** @var bool */
    private $hasExportDownloadForManual;
    /** @var bool */
    private $hasExportMailForManual;

    /** @var bool */
    private $isPageBase;
    /** @var bool */
    private $isPageApprovedAutomatic;
    /** @var bool */
    private $isPageApprovedManual;

    /** @var bool */
    private $hasChanges;
    /** @var bool */
    private $isAjaxRequest;    
        
    /** @var array */
    private $report;
    /** @var object */
    private $lastRevision;
    /** @var object */
    private $user;

   /**
    * Constructs the class
    *
    */
    public function __construct()
    {        
        parent::__construct();
       
        //  Module Settings
        $this->moduleName = ($this->getConfig())["name"];

        //  Page Indicators
        $this->isPageBase = false;
        $this->isPageApprovedAutomatic = false;
        $this->isPageApprovedManual = false;

        //  Objects used in methods
        $this->report = [];
        $this->lastRevision = (object)[];
        $this->user = (object)[];

        $this->hasChanges = false;
        $this->isAjaxRequest = false;
    }

   /**
     * Hook into redcap_every_page_top
     *
     * @param integer $project_id
     * @return void
     */
    public function redcap_every_page_top($project_id = null) {
        
        //  Set Project Settings
        $this->initialize();

        //  Start Main Process
        $this->main();
    }

   /**
     * Set variables from Project Settings 
     *
     * @return void
     */
    private function initialize() {

        //  Download 
        $hasExportDownload = $this->getProjectSetting("has-export-download");
        $this->hasExportDownloadForAutomatic = ($hasExportDownload == 1 || $hasExportDownload == 2) ? true : false;
        $this->hasExportDownloadForManual = ( $hasExportDownload == 1 || $hasExportDownload == 3) ? true : false;

        //  Email 
        $hasExportMail = $this->getProjectSetting("has-export-mail");
        $this->hasExportMailForAutomatic = ($hasExportMail == 1 || $hasExportMail == 2) ? true : false;
        $this->hasExportMailForManual = ($hasExportMail == 1 || $hasExportMail == 3) ? true : false;

        //  Any
        $this->hasExport = $hasExportDownload ||  $hasExportMail;

        //  per Submit
        $this->isExportActive = $this->getProjectSetting("is-export-active");
        

    }

   /**
     * Main Process of the module
     *
     * @since 1.0.0
     *
     * @return void
     */        
    private function main() {

        //  If Exports are active
        if( $this->hasExport ) {

            $this->checkPage();

            //  If Page is relevant
            if( $this->isPageBase ) {

                //  Include Javascript
                $this->includeJavascript();

                //  Prepare Report
                $this->prepareReport();

                //  Process Export
                $this->processExport();
            }
        }
        
    }    

   /**
     * Check Pages and set Indicators
     *
     * @since 1.0.0
     *
     * @return void
     */    
    private function checkPage() {

        if( $this->isPage("Design/online_designer.php") || $this->isPage("Design/draft_mode_notified.php") || $this->isPage("Design/data_dictionary_upload.php")) {
            $this->isPageBase = true;
        }

        if( $this->isPage("Design/online_designer.php") && isset($_GET['msg']) && $_GET['msg'] == "autochangessaved" ) {
            $this->isPageApprovedAutomatic = true;
        }

        if( $this->isPage("Design/draft_mode_notified.php") && isset($_GET['action']) && $_GET["action"] == "approve" ) {
            $this->isPageApprovedManual = true;
        }

    }

   /**
     * Include Javascript
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function includeJavascript() {
        ?>
        <script src="<?php print $this->getUrl('js/main.js'); ?>"></script>
        <script> 
            $(function() {
                STPH_exportDataDictionaryChanges.requestHandler = "<?= $this->getUrl("requestHandler.php") ?>";
                $(document).ready(function(){
                    STPH_exportDataDictionaryChanges.initSwitch();
                });          
            });
        </script>        
        <?php
    }    

   /**
     * Process Exports by preparing Report Data, sending Emails and finally including Javascript.
     *
     * @return void
     * 
     * @since 1.0.0
     */        
    private function processExport() {

        if($this->isExportActive && $this->hasChanges) {
            
            //  Handle Emails
            $this->processEmail();

            //  Include Javascript
            $this->processDownload();

        }

    }

   /**
     * Process Email
     *
     * @return void
     * 
     * @since 1.0.0
     */      
    private function processEmail() {

        $isEmailActive = $this->isPageApprovedAutomatic && $this->hasExportMailForAutomatic || $this->isPageApprovedManual && $this->hasExportMailForManual;

        if( $isEmailActive ) {
            $this->sendMail();
        }
    }
    
   /**
     * Process Download.
     *
     * @return void
     * 
     * @since 1.0.0
     */      
    private function processDownload() {
        ?>
        <script> 
            $(function() {
            <?php if( $this->isPageApprovedAutomatic && $this->hasExportDownloadForAutomatic ):?>
                STPH_exportDataDictionaryChanges.initDownloadForAutomatic();
            <?php endif;?>
            <?php if( $this->isPageApprovedManual && $this->hasExportDownloadForManual ):?>
                STPH_exportDataDictionaryChanges.initDownloadForManual();
            <?php endif;?>
            });
        </script>
        <?php
        
    }

   /**
     * Prepare Report.
     *
     * @return void
     * 
     * @since 1.0.0
     */        
    private function prepareReport() {

        if($this->isPageApprovedAutomatic || $this->isPageApprovedManual) {

            //  Get last revision from end of all revisions
            $revisions = $this->getRevisions();
            $this->lastRevision = end($revisions);

            //  Get request user by id from last revision
            $ui_id_requester = $this->lastRevision->ui_id_requester;
            $this->user = $this->getUserById($ui_id_requester);

            //  Get report and indicate that Data Dictionary Changes exist
            $this->report = $this->getReport();
            $this->hasChanges = !empty($this->report);            
            
            //  Save report to storage even if is empty (otherwise an AJAX request would retrieve invalid data)
            $this->setProjectSetting("storage", json_encode( $this->report) );

        }

    }

    /**
     * Send email to recipient
     *
     * @return void
     * 
     * @since 1.0.0 
     */
    private function sendMail() {

        $csv = $this->streamCSV( $this->report );        
        $filename = $this->getFilename();

            try {
                // Init
                //Create an instance; passing `true` enables exceptions
                $mail = new PHPMailer(true);
                $mail->CharSet = 'UTF-8';
                $mail->isHTML(true);        //Set email format to HTML
    
                //  From
                $config = \System::getConfigVals();
                $name = $config["homepage_contact"];
                $email = $config["homepage_contact_email"];                
                $mail->setFrom($email, $name);

                // Recipient            
                $mail->addAddress($this->user->user_email, $this->user->user_firstname . ' ' . $this->user->user_lastname);
    
                // Content
                $mail->Subject = "Export Data Dictionary Changes - " . $filename;
                $mail->Body    = '<hr>This is email has been created automatically by REDCap Module "Export Data Dictionary Changes".<br>If you do not wish to receive this email please edit module configuration "Automatic Emails" for your project!';
    
                //  Attach CSV from string
                $mail->AddStringAttachment($csv, $filename, 'base64', 'application/csv');
    
                //  Send Email finally
                $mail->send();
    
            } catch( Exception $e ) {
                \REDCap::logEvent( $this->moduleName, "Message could not be sent. Mailer Error: " . $mail->ErrorInfo, null, null, null, PROJECT_ID );
                http_response_code(500);
                die("There was an error while sending the Email. Please check the log.");
            }
    
        }    

    /**
     * Get all revisions for the current project
     * 
     * @return array Revisions that match the given project id.
     * 
     * @since 1.0.0
     */  
    private function getRevisions() {

        $pid = $_GET["pid"];
        $previous_versions = array();
        $sql = "select p.pr_id, p.ts_approved,p.ts_req_approval, p.ui_id_requester, p.ui_id_approver
                from redcap_metadata_prod_revisions p                     
                where p.project_id = $pid and p.ts_approved is not null order by p.pr_id";

        $revisions = [];                    

        if ($result = $this->query($sql, [])) {
            while ($row = $result->fetch_object()) {
                $revisions[] = $row;
            }
            $result->close();
        }

        return $revisions;
    }
    
    /**
     * Get User by id
     *
     * @param integer $ui_id User id
     * @return object Containing username,user_email, user_firstname, user_lastname. Returns emtpy object on error.
     *
     * @since 1.0.0
     */    
    private function getUserById($ui_id) {
        $user = (object) [];
        $sql = "select username,user_email, user_firstname, user_lastname from redcap_user_information where ui_id = ?";   
        if($result = $this->query($sql, $ui_id))        
        {
            $user = $result->fetch_object();
            $result->close();
        }
        return $user;
    }

    /**
     * Get Report of new and old Data Dictionaries Differences
     * 
     * @return Array Differences merged from new, edited and deleted fields.
     * 
     * @since 1.0.0
     */  
    private function getReport() {

        $report = array();
        $new_mod_fields = array();
        $deleted_fields = array();

        //  Get Data Dictionary of current state
        $currentDataDictionary = \REDCap::getDataDictionary("array");

        //  Get Data Dictionary of previous state 
        $lastDataDictionary = \MetaData::getDataDictionary("array", true, array(), array(), false, false, $this->lastRevision->pr_id);

        //  Get date when change request has been approved
        $changeDate = $this->lastRevision->ts_approved;

        //  Get author username of request
        $changeAuthor = $this->user->username;

        // Check if there are new, edited or deleted fields
        // Check for new and edited fields
        foreach( $currentDataDictionary as $field => $metadata )
        {
            //  ADDED
            if ( !isset($lastDataDictionary[$field]) ) {

                $metadata["change_date"] = $changeDate;
                $metadata["change_author"] = $changeAuthor;


                $metadata["change_type"] = "added";
                $metadata["change_history"] = null;                

                $new_fields[$field] = $metadata;
            }
            //  EDITED
            else if( $metadata !== $lastDataDictionary[$field] ) {

                $changeHistory = [];
                foreach ($metadata as  $i => $attr ) {

                    $attr = strip_tags($attr);

                    $old_value = strip_tags( $lastDataDictionary[$field][$i] );
                    
                    if ($attr != $old_value)
                    {
                        //$value = $attr ? $attr : " ";
                        $old_value = $old_value ? $old_value : "";
                        $changeHistory[ $i ] = $old_value;
                    } 
                }

                $metadata["change_date"] = $changeDate;
                $metadata["change_author"] = $changeAuthor;

                $metadata["change_type"] = "edited";
                $metadata["change_history"] = $changeHistory;



                $mod_fields[$field] = $metadata;
            }
        }

        //  Check for deleted fields 
        foreach ($lastDataDictionary as $field => $metadata) {

            // DELETED 
            if ( !isset($currentDataDictionary[$field]) ) {

                $changeHistory = [];
                foreach ($metadata as  $i => $attr ) {

                    if($attr) {
                        $changeHistory[ $i ] = $attr;
                    }
                   
                }

                $metadata["change_date"] = $changeDate;
                $metadata["change_author"] = $changeAuthor;

                $metadata["change_type"] = "deleted";
                $metadata["change_history"] = $changeHistory;
                

                $deleted_fields[$field] = $metadata;
            }
        }

        //  Merge all changes
       $report = array_merge( (array)$new_fields, (array) $mod_fields, (array) $deleted_fields);

       return $report;
    }


    /**
     * Stream CSV from temp
     * https://stackoverflow.com/a/31118307/3127170
     * 
     * @param array $report
     * @return string
     * 
     * @since 1.0.0
     */    
    public function streamCSV($report) {

        $headers = array_keys( current($report) );
        
        // Write to memory (unless buffer exceeds 2mb when it will write to /tmp)
        $fp = fopen('php://temp', 'w+');

        //  add BOM to fix UTF-8 in Excel https://stackoverflow.com/a/21988713/3127170            
        fputs($fp, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));     
       
        fputcsv($fp, $headers);
        //  Write fields
        foreach ($report as $key => $row) {
    
            $line = "";
            $csv_row = [];

            foreach ($row as $key => $column) {            
                $value = "";

                if( is_array($column) ) {
                    
                    foreach ($column as $key => $item) {                       
                        $value .= $key.":".$item . PHP_EOL;                    
                    }
                    
                } else {
                    $value = $column;
                }                
                $csv_row[] = $value;
            }
            fputcsv($fp, $csv_row);     
        }

        rewind($fp); // Set the pointer back to the start        
        $csv_contents = stream_get_contents($fp); // Fetch the contents of our CSV
        fclose($fp); // Close our pointer and free up memory and /tmp space
        
        return $csv_contents;
    }

    /**
     * Get filename with Project ID and Date
     * 
     * @return string
     * 
     * @since 1.0.0
     */        
    private function getFilename() {
        return "DataDictionaryChanges_".PROJECT_ID."_".date("Y-m-d_H-i-s").".csv";
    }    

    //  API Handles

    /**
     * Get module configuration setting "active-auto-download" and returns its value in json
     * Called from RequestHandler via Ajax
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function handleGetExportActive() {
        header('Content-Type: application/json; charset=UTF-8');
        $value = $this->getProjectSetting("is-export-active");
        echo json_encode(
            array( "state" => $value )
        );
    }

    /**
     * Toogle module configuration setting "active-auto-download" and returns its value in json
     * Called from RequestHandler via Ajax
     *
     * @since 1.0.0
     * @param string $checked
     * @return void
     */
    public function handleToggleExportActive($checked) {

        if($checked === "true") {
           $this->setProjectSetting("is-export-active", true);
        } else {
           $this->setProjectSetting("is-export-active", false);
        }

        $value = $this->getProjectSetting("is-export-active");
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(
            array( "state" => $value )
        );

    }

    /**
     * Stream CSV File to Browser
     * Called from RequestHandler via Ajax
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function handleDownloadCSV() {

        $report = json_decode($this->getProjectSetting("storage"), true);
        $filename = $this->getFilename();

        //  Set CSV File Headers
        header('Content-Description: File Transfer');
        header('Content-Type: application/csv');
        //header('Content-Encoding: UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        //  Handle file generation
        try {
            
            $fp = fopen('php://output', 'w+');

            if ( !$fp ) {
                throw new \Exception('File open failed.');
            }

            if( !fwrite( $fp, $this->streamCSV($report) )) {
                throw new \Exception('File write failed.');                
            };

            if( !fclose($fp) ) {
                throw new \Exception('File close failed.');                
            };

            fclose($fp);

        } catch(\Exception $e) {
            \REDCap::logEvent( $this->moduleName . " - Error: ", $e->getMessage(), null, null, null, PROJECT_ID );
            http_response_code(500);
            die("There was an error while preparing the download. Please check the log.");
        }
    }
}