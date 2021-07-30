<?php

// Set the namespace defined in your config file
namespace STPH\exportDataDictionaryChanges;


// Declare your module class, which must extend AbstractExternalModule 
class exportDataDictionaryChanges extends \ExternalModules\AbstractExternalModule {

    private $moduleName = "Export Data Dictionary Changes";
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

        $this->develop();

        $this->setSettings();

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
                    $this->processExportsForAutomatic();                
                }            
                
                //  Process Manual Approvals
                //  Hook into "Automatic Changes" Dialog
                if(strpos(PAGE, "Design/draft_mode_notified.php") !== false && $_GET["action"] == "approve" ) {
                    $this->processExportsForManual();
                }

            }
        }          
    }

   /**
    * Set module configuration settings
    * @since 1.0.0
    */
    private function setSettings(){

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
    * Process Exports for Automatic Approvals
    * @since 1.0.0
    */
    private function processExportsForAutomatic() {
        //  Check if Export Download is enabled for Automatic Approvals
        if( $this->hasExportDownloadForAutomatic ) {                
            ?>
            <script> 
                $(function() {
                    $(document).ready(function(){
                        STPH_exportDataDictionaryChanges.initDownloadForAutomatic();
                    })
                });
            </script>            
            <?php
        }

        //  Check if Export Email is enabled for Automatic Approvals
        if( $this->hasExportMailForAutomatic ) {
            //$this->triggerCSVMail();
        }
    }

   /**
    * Process Exports for Manual Approvals
    * @since 1.0.0
    */    
    private function processExportsForManual() {    
        //  Check if Export Download is enabled for Manual Approvals
        if( $this->hasExportDownloadForManual ) {   
            ?>
            <script> 
                $(function() {
                    $(document).ready(function(){
                        STPH_exportDataDictionaryChanges.initDownloadForManual();
                    })
                });
            </script>   
            <?php
        }

        //  Check if Export Email is enabled for Manual Approvals
        if( $this->hasExportMailForManual ) {
            //$this->triggerCSVMail(true);
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
     * Generate Difference Report of new and old Data Dictionaries
     * 
     * 
     * @since 1.0.0
     */  
    private function generateDiffReport() {

        $diff = array();
        $new_mod_fields = array();
        $deleted_fields = array();

        //  Get Data Dictionary of current state and before changes
        //  Current
        $currentDataDictionary = \REDCap::getDataDictionary("array");

        //  Before: Get all project revisions and pick last one`s pr_Id to generate Data Dictionary
        $revisions = $this->getRevisions();
        $beforeRevision = end($revisions);
        dump($beforeRevision);
        $beforeDataDictionary = \MetaData::getDataDictionary("array", true, array(), array(), false, false, $beforeRevision->pr_id);

        // Check if there are new, modified or deleted fields
        // Check for new and modified fields
        foreach( $currentDataDictionary as $field => $metadata )
        {

            //  Check for new fields 
            if ( !isset($beforeDataDictionary[$field]) ) {
                $metadata["change_type"] = "added";
                $metadata["change_history"] = null;                

                $new_fields[$field] = $metadata;
            }
            //  Check for modified fields
            else if( $metadata !== $beforeDataDictionary[$field] ) {

                $changeHistory = [];
                foreach ($metadata as  $i => $attr ) {

                    $attr = strip_tags($attr);

                    $old_value = strip_tags( $beforeDataDictionary[$field][$i] );
                    
                    if ($attr != $old_value)
                    {
                        $value = $attr ? $attr : "";
                        $old_value = $old_value ? $old_value : "";
                        $changeHistory[ $i ] = $old_value;
                    }
                }

                $metadata["change_type"] = "modified";
                $metadata["change_history"] = $changeHistory;
                $mod_fields[$field] = $metadata;
            }
        }

        //  Check for deleted fields 
        foreach ($beforeDataDictionary as $field => $metadata) {
             if ( !isset($currentDataDictionary[$field]) ) {

                $changeHistory = [];
                foreach ($metadata as  $i => $attr ) {

                    if($attr) {
                        $changeHistory[ $i ] = $attr;
                    }
                   
                }

                $metadata["change_type"] = "deleted";
                $metadata["change_history"] = $changeHistory;
                $deleted_fields[$field] = $metadata;
            }
        }

        $diff = array_merge( (array)$new_fields, (array) $mod_fields, (array) $deleted_fields);
        dump($diff);

    }

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

}