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

    private $moduleName;
    private $settings = [];

    private $hasExport;
    private $isExportActive;

    private $hasExportDownloadForAutomatic;
    private $hasExportMailForAutomatic;

    private $hasExportDownloadForManual;
    private $hasExportMailForManual;    

    //  
    private $lastRevision;
    private $user;

   /**
    * Constructs the class
    *
    */
    public function __construct()
    {        
        parent::__construct();
       // Other code to run when object is instantiated
    }

   /**
    * Hook into redcap_every_page_top
    *
    */
    public function redcap_every_page_top($project_id = null) {
        
        $this->before();
        $this->run();

    }

   /**
    * Before Run: Set module configuration settings
    * @since 1.0.0
    */
    private function before(){

        $this->moduleName = $this->getConfig("name");        

        $settingHasExportDownload = $this->getProjectSetting("has-export-download");
        $this->hasExportDownloadForAutomatic = ($settingHasExportDownload == 1 || $settingHasExportDownload == 2) ? true : false;
        $this->hasExportDownloadForManual = ( $settingHasExportDownload == 1 || $settingHasExportDownload == 3) ? true : false;

        $settingHasExportMail = $this->getProjectSetting("has-export-mail");
        $this->hasExportMailForAutomatic = ($settingHasExportMail == 1 || $settingHasExportMail == 2) ? true : false;
        $this->hasExportMailForManual = ($settingHasExportMail == 1 || $settingHasExportMail == 3) ? true : false;

        $this->hasExport = $settingHasExportDownload ||  $settingHasExportMail;
        $this->isExportActive = $this->getProjectSetting("is-export-active");
    }

   /**
    * Hookup module to process Exports
    * @since 1.0.0
    */
    private function run() {

        //  Check if Export Download or Export Mail are enabled
        if( $this->hasExport ) {

            //  Include basic Javascript
            $this->includeJavascriptBase();

            //  Include Switch for toggle Export active state before Submit
            //  Hook into "Submit Changes" Dialog
            if(strpos(PAGE, "Design/online_designer.php") !== false ) {
                $this->includeJavascriptSwitch();
            }
            
            //  Check if Export active state is true
            if($this->isExportActive) {

                //  Process Automatic Approvals
                //  Hook into "Automatic Changes" Dialog
                if(strpos(PAGE, "Design/online_designer.php") !== false && isset($_GET['msg']) && $_GET['msg'] == "autochangessaved" ) { 
                    if($this->saveReport()){
                        $this->processExports('automatic');
                    }
                }            
                
                //  Process Manual Approvals
                //  Hook into "Automatic Changes" Dialog
                if(strpos(PAGE, "Design/draft_mode_notified.php") !== false && isset($_GET['action']) && $_GET["action"] == "approve" ) {
                    if($this->saveReport()){
                        $this->processExports('manual');
                    }                    
                }

            }
        }     
    }

   /**
    * Process Exports for Automatic & Manual Approvals
    * @since 1.0.0
    */
    private function processExports($approval){

        if($approval == 'automatic') {

             //  Check if Export Download is enabled for Automatic Approvals
             if( $this->hasExportDownloadForAutomatic ) {                
                ?>
                <script> 
                    $(function() {
                        STPH_exportDataDictionaryChanges.initDownloadForAutomatic();
                    });
                </script>            
                <?php
            }
    
            //  Check if Export Email is enabled for Automatic Approvals
            if( $this->hasExportMailForAutomatic ) {
                $this->sendMail($approval);
            }
            
        } elseif($approval == 'manual') {

            //  Check if Export Download is enabled for Manual Approvals
            if( $this->hasExportDownloadForManual ) {   
                ?>
                <script> 
                    $(function() {
                        STPH_exportDataDictionaryChanges.initDownloadForManual();
                    });
                </script>   
                <?php
            }

            //  Check if Export Email is enabled for Manual Approvals
            if( $this->hasExportMailForManual ) {
                $this->sendMail($approval);
            }            
        }

    }
 
   /**
    * Include Base JavaScript files
    *
    * @since 1.0.0
    */
    private function includeJavascriptBase() {
        ?>        
        <script src="<?php print $this->getUrl('js/main.js'); ?>"></script>
        <script> 
            $(function() {
                STPH_exportDataDictionaryChanges.requestHandler = "<?= $this->getUrl("requestHandler.php") ?>";
            });
        </script>
        <?php
    }

    /**
     * Include Javascript for Auto Download Switch 
     * Hooked into "SUBMIT CHANGES FOR REVIEW?" Dialog (#confirm-review) 
     * 
     * @since 1.0.0
     */    
    private function includeJavascriptSwitch() {
        ?>        
        <script> 
            $(function() {
                $(document).ready(function(){
                    STPH_exportDataDictionaryChanges.initSwitch();
                })
            });
        </script>
        <?php        
    }

    /**
     * Include Javascript for Export Download
     * Hooked into "SUBMIT CHANGES FOR REVIEW?" Dialog (#confirm-review) 
     * 
     * @since 1.0.0
     */    
    private function includeJavascriptExportAsDownload($isAutomaticApproval) {
        if($isAutomaticApproval): ?>        
            <script> 
                $(function() {
                    $(document).ready(function(){
                        STPH_exportDataDictionaryChanges.initDownloadForAutomatic();
                    })
                });
            </script>
        <?php else : ?>
            <script> 
                $(function() {
                    $(document).ready(function(){
                        STPH_exportDataDictionaryChanges.initDownloadForManual();
                    })
                });
            </script>            
        <?php
        endif;
    }

    /**
     * Get module configuration setting "active-auto-download" and returns its value in json
     * Called from RequestHandler via Ajax
     * 
     * @since 1.0.0
     */
    public function getExportActiveState() {
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
     */
    public function toggleExportActive($checked) {

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
     * Get CSV as File Download
     * Called from RequestHandler via Ajax
     * 
     * @since 1.0.0
     */    
    public function getDownload() {

        $filename = $this->getFilename();

        //  Set CSV File Headers
        header('Content-Description: File Transfer');
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        //  Handle file generation
        try {
            
            $fp = fopen('php://output', 'w+');
            if ( !$fp ) {
                throw new Exception('File open failed.');
            }

            if( !fwrite( $fp, $this->generateCSV() )) {
                throw new Exception('File write failed.');                
            };

            if( !fclose($fp) ) {
                throw new Exception('File close failed.');                
            };

        } catch(Exception $e) {
            \REDCap::logEvent( $this->moduleName . " - Error: ", $e->getMessage(), null, null, null, PROJECT_ID );
            http_response_code(500);
            die("There was an error while preparing the download. Please check the log.");
        }
    }

    /**
     * Get filename with Project ID and Date
     * 
     * @return String 
     * @since 1.0.0
     */        
    private function getFilename() {
        return "DataDictionaryChanges_".PROJECT_ID."_".date("Y-m-d_H-i-s").".csv";
    }

    /**
     * Send email to recipient 
     * 
     * @param String Mode of approval
     * @since 1.0.0
     */          
    private function sendMail($approval) {

    //Create an instance; passing `true` enables exceptions
    $mail = new PHPMailer(true);
    $csv = $this->generateCSV();
    $filename = $this->getFilename();

        try {
            // Init
            $mail = new PHPMailer;
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);        //Set email format to HTML


            // Recipient
            $mail->setFrom('from@example.com', 'Mailer');
            $mail->addAddress($this->user->user_email, $this->user->user_firstname . ' ' . $this->user->user_lastname);     //Add a recipient

            // Content
            $mail->Subject = ucfirst($approval) . " Approval -" . $filename;
            $mail->Body    = 'This is email has been created automatically by REDCap Module "Export Data Dictionary Changes".<br>If you do not wish to receive this email please edit module configuration "Automatic Emails" for your project!';

            //  Attach CSV from string
            $mail->AddStringAttachment($csv, $filename, 'base64', 'application/csv');

            //  Send Email finally
            $mail->send();

        } catch(Exception $e) {
            \REDCap::logEvent( $this->moduleName, "Message could not be sent. Mailer Error: " . $mail->ErrorInfo, null, null, null, PROJECT_ID );
            http_response_code(500);
            die("There was an error while sending the Email. Please check the log.");
        }

    }

    /**
     * Generate CSV from report
     * 
     * @return File A CSV file with UTF8 Encoding.
     * @since 1.0.0
     */      
    private function generateCSV(){

        //  Retrieve contents
        $diff = json_decode($this->getProjectSetting("storage"), true);
        $csv = "";

        //  Write headers
        $headers = array_keys( current($diff) );
        $csv = implode($headers, ", ") . PHP_EOL;


        //  Write fields
        foreach ($diff as $key => $row) {
            
            $line = "";

            foreach ($row as $key => $column) {                

                if( is_array($column) ) {
                  
                    $value = "";
                    foreach ($column as $key => $item) {
                        
                        $value .= "[".$key.":".$item ."]";
                        
                    }
                    
                } else {
                    $value = $column;
                }
                
                //  Replace commas & escape linebreaks so it does not break our CSV!!!
                $value = str_replace( ',' , '-', $value  );
                $value = str_replace( '\n' , '\\n', $value  );
                $line .=  $value .  ", ";
                

            }

            $csv .= $line . PHP_EOL;
            
        }

        return $csv;

    }

    /**
     * Save Report to Storage
     * This is needed because getDownload is triggered async via AJAX and has no report context
     * 
     * @return File A CSV file with UTF8 Encoding.
     * @since 1.0.0
     */              
    private function saveReport() {

        //  Set Last Revision
        $this->setLastRevision();

        //  Set User Info
        $this->setUser();
        
        $report = $this->generateReport();        
        $this->recipient = $report["email"];

        //  Only save diff report to database if there were changes
        if( !empty($report) && isset($report) ) {
            $this->setProjectSetting("storage", json_encode($report) );     
            return true;
        }

        return false;
    }


    /**
     * Get Report of new and old Data Dictionaries Differences
     * @return Array Differences merged from new, edited and deleted fields.
     * 
     * @since 1.0.0
     */  
    private function generateReport() {

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
                        $value = $attr ? $attr : "";
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
        return array_merge( (array)$new_fields, (array) $mod_fields, (array) $deleted_fields);        

    }

    private function setLastRevision() {
        
        //  Get latest revision (needed for previous Data Dictionary State)
        $revisions = $this->getRevisions();
        $lastRevision = end($revisions);

        $this->lastRevision = $lastRevision;

    }

    /**
     * Get all revisions for the current project
     * @return Array Revisions that match the given project id.
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
     * Get user from given user id
     * @param String $ui_ud The user id of a REDCap user.
     * @return Object Object of user information with name and email. Returns NULL in event of error.
     * @since 1.0.0
     */      
    private function setUser()
    {
        $ui_id = $this->lastRevision->ui_id_requester;
        $user = NULL;

        $sql = "select username,user_email, user_firstname, user_lastname from redcap_user_information where ui_id = ?";   
        if($result = $this->query($sql, $ui_id))        
        {
            $user = $result->fetch_object();
            $result->close();
        }

        $this->user = (object) $user;
        
        return;
    }

}