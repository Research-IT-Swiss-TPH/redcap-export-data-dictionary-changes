<?php

// Set the namespace defined in your config file
namespace STPH\exportDataDictionaryChanges;

//  Autoload composer files if in dev environment and composer installed
if( $GLOBALS["is_development_server"] && file_exists("vendor/autoload.php")){
    require 'vendor/autoload.php';
}


// Declare your module class, which must extend AbstractExternalModule 
class exportDataDictionaryChanges extends \ExternalModules\AbstractExternalModule {

    private $settings = [];

    private $hasExport;
    private $isExportActive;

    private $hasExportDownloadForAutomatic;
    private $hasExportMailForAutomatic;

    private $hasExportDownloadForManual;
    private $hasExportMailForManual;


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

        $this->setup();

        //  Check if Export Download or Export Mail are enabled
        if( $this->hasExport ) {
            //  Hook module up
            $this->hookup();
        }          
    }

   /**
    * Setup module configuration settings
    * @since 1.0.0
    */
    private function setup(){

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
    private function hookup() {

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
                if($this->saveDiffToStorage()){
                    $this->processExports('automatic');
                }
            }            
            
            //  Process Manual Approvals
            //  Hook into "Automatic Changes" Dialog
            if(strpos(PAGE, "Design/draft_mode_notified.php") !== false && isset($_GET['action']) && $_GET["action"] == "approve" ) {
                if($this->saveDiffToStorage()){
                    $this->processExports('manual');
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
                //$this->triggerCSVMail();
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
                //$this->triggerCSVMail(true);
            }            
        }

    }
 
   /**
    * Include JavaScript files
    *
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
     * Includes Javascript for Auto Download Switch 
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
     * Includes Javascript for Export Download
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
     * Gets module configuration setting "active-auto-download" and returns its value in json
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
     * Toogles module configuration setting "active-auto-download" and returns its value in json
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
     * Gets download
     * Called from RequestHandler via Ajax
     * 
     * @since 1.0.0
     */    
    public function getDownload() {

        $filename = "DataDictionaryChanges_".PROJECT_ID."_".date("Y-m-d_H-i-s").".csv";

        header('Content-Description: File Transfer');
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $fp = fopen('php://output', 'w+');
        fwrite($fp, $this->generateCSV());
        fclose($fp);
        
    }

    public function develop() {

        if(isset($_GET["pid"])){

            $csv = $this->generateCSV();
            dump($csv);


            //   generate CSV
            //   processDownload
            //   processEmail
        }

    }

    /**
     * Generate CSV from difference report
     * @return File A CSV file with UTF8 Encoding.
     * @since 1.0.0
     */      
    private function generateCSV(){
        $diff = json_decode($this->getProjectSetting("storage"), true);
        $csv = "";

        $headers = array_keys( current($diff) );

        $csv = implode($headers, ", ") . PHP_EOL;

        //  Write headers

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

    private function saveDiffToStorage() {
        
        $diff = $this->generateDiffReport();

        //  Only save diff report to database if there were changes
        if( !empty($diff) && isset($diff) ) {
            $this->setProjectSetting("storage", json_encode($diff) );     
            return true;
        }

        return false;
    }


    /**
     * Generate Difference Report of new and old Data Dictionaries
     * @return Array Differences merged from new, edited and deleted fields.
     * @since 1.0.0
     */  
    private function generateDiffReport() {

        $diff = array();
        $new_mod_fields = array();
        $deleted_fields = array();

        //  Get latest revision (needed for previous Data Dictionary State)
        $revisions = $this->getRevisions();
        $lastRevision = end($revisions);

        //  Get Data Dictionary of current state
        $currentDataDictionary = \REDCap::getDataDictionary("array");

        //  Get Data Dictionary of previous state 
        $lastDataDictionary = \MetaData::getDataDictionary("array", true, array(), array(), false, false, $lastRevision->pr_id);

        //  Get date when change request has been approved
        $changeDate = $lastRevision->ts_approved;

        //  Get username from user id that has authored the change request
        $changeAuthor = $this->getUsername($lastRevision->ui_id_requester);

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
        $diff = array_merge( (array)$new_fields, (array) $mod_fields, (array) $deleted_fields);
        return $diff;

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
     * Get username from given user id
     * @param String $ui_ud The user id of a REDCap user.
     * @return String The username. Returns "" in event of error.
     * @since 1.0.0
     */      
    private function getUsername($ui_id)
    {
        $username = "";
        $sql = "select username from redcap_user_information where ui_id = ?";                
        if($result = $this->query($sql, $ui_id))        
        {
            $username = $result->fetch_object()->username;
            $result->close();
        }

        return $username;

    }

}