/**
 * Export Data Dictionary Changes - a REDCap External Module
 * Author: Ekin Tertemiz
*/

var STPH_exportDataDictionaryChanges = STPH_exportDataDictionaryChanges || {};
 
// Switch Initialization -  Register Event Listeners
STPH_exportDataDictionaryChanges.initSwitch = function() {
        
        //  Using jquery Dialog Widget Events: https://api.jqueryui.com/dialog/#event-open to hook into Dialog
        //  Add switch on open (jQuery API)
        $( "#confirm-review" ).on( "dialogopen", function( event, ui ) {

            //  HTML Markup
            var html = `<div id="auto-download-wrapper" class="custom-control custom-switch" style="line-height:22px;margin-top:20px;">
                                <input disabled type="checkbox" class="custom-control-input" id="autoDownloadSwitch">
                                <label class="custom-control-label" for="autoDownloadSwitch">
                                Export Data Dictionary Changes (<span id="state-msg"></span>)
                                </label>
                            </div>`;

            $(".ui-dialog-content").append(html);

            //  Set initial state of "is-export-active"
            $.get( STPH_exportDataDictionaryChanges.requestHandler + "&action=getExportActive")
            .done(function(response){
                var state = response.state;
                var input = $("#autoDownloadSwitch");
                if(state) {
                    input.prop('checked', true);
                } else {
                    input.prop('checked', false);
                }
                STPH_exportDataDictionaryChanges.setStateMsg(response);
            })
            .fail(function(err){
                alert(err);
                $("#auto-download-wrapper").remove();
            })
            .always(function(){
                $("#autoDownloadSwitch").prop('disabled', false);
            });
            
            //  Toggle "is-export-active" on Switch Change
            $("#autoDownloadSwitch").on("click", () => { 
                var checked = $(this).find("input").is(":checked");                                
                //  Trigger Ajax Request: setProjectSetting("active-auto-download")    
                $.post( STPH_exportDataDictionaryChanges.requestHandler + "&action=toggleExportActive", {checked:checked})
                .done(function(response){
                    STPH_exportDataDictionaryChanges.setStateMsg(response);
                    //console.log(response.message)
                })
                .fail(function(err){
                    alert("Module Error - Export Data Dictionaty Changes: "+err);
                    $("#auto-download-wrapper").remove();
                });
            })                        
        });

        //  Remove switch on close
        $( "#confirm-review" ).on( "dialogclose", function( event, ui ) {
            $("#auto-download-wrapper").remove();
        })        

}

//  Set State message inside Switch
STPH_exportDataDictionaryChanges.setStateMsg = function(response) {
    var state = response.state;
    if(state) {
        $("#state-msg").text("Enabled");
    } else {
        $("#state-msg").text("Disabled");
    }
}

//  Initialize Download for Automatic Appprovals
STPH_exportDataDictionaryChanges.initDownloadForAutomatic = function() {
    //  Append to "Changes Were Made Automatically" dialog on .ui-dialog-buttonpane element

    console.log($("#autochangessaved"));

    $( "#autochangessaved" ).on( "dialogopen", function( event, ui ) {       
        STPH_exportDataDictionaryChanges.appendDownload($(".ui-dialog-buttonpane"));
        //STPH_exportDataDictionaryChanges.callAsyncDownload();
    });
}

//  Initialize Download for Manual Appprovals
STPH_exportDataDictionaryChanges.initDownloadForManual = function() {
    //  Append to "Project Changes Committed / User Notified" page on #center element
    var target = $("#center");
    if(target) {
        STPH_exportDataDictionaryChanges.appendDownload(target);
    }
}

//  Append download markup
STPH_exportDataDictionaryChanges.appendDownload = function(target) {
    //  Append download with counter message to target
    var dl_message = '<div id="adl_msg" style="float:left;line-height:44px;margin-left:15px;font-weight:bold;">Automatic Download is starting in <span id="adl_counter">3</span></div>';                        
    target.append(dl_message);

    //  Set a download timer of 3 seconds
    var timeleft = 3;
    var downloadTimer = setInterval(function(){

        //  Trigger Download when timer is zero
        if(timeleft <= 0){        
            clearInterval(downloadTimer);            
            STPH_exportDataDictionaryChanges.triggerAJAXDownload();
        }

        //  Update counter
        $("#adl_counter").text(timeleft)                            
        timeleft -= 1;

    }, 1000);
}

//  Trigger AJAX Download
//  https://stackoverflow.com/a/23797348/3127170
STPH_exportDataDictionaryChanges.triggerAJAXDownload = function() {

    $.get( STPH_exportDataDictionaryChanges.requestHandler + "&action=downloadCSV" )
     .done( function(response, status, xhr){
        var blob = new Blob([response], { type: 'text/csv;charset=utf-8;' });
        // check for a filename
        var filename = "";
        var disposition = xhr.getResponseHeader('Content-Disposition');

        if (disposition && disposition.indexOf('attachment') !== -1) {
            var filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
            var matches = filenameRegex.exec(disposition);
            if (matches != null && matches[1]) filename = matches[1].replace(/['"]/g, '');
        }

        if (typeof window.navigator.msSaveBlob !== 'undefined') {
            // IE workaround for "HTML7007: One or more blob URLs were revoked by closing the blob for which they were created. These URLs will no longer resolve as the data backing the URL has been freed."
            window.navigator.msSaveBlob(blob, filename);
        }         
        else {
            var URL = window.URL || window.webkitURL;
            var downloadUrl = URL.createObjectURL(blob);

            if (filename) {
                // use HTML5 a[download] attribute to specify filename
                var a = document.createElement("a");
                // safari doesn't support this yet
                if (typeof a.download === 'undefined') {
                    window.location.href = downloadUrl;
                } 
                else {
                    a.href = downloadUrl;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                }
            } 
            else {
                window.location.href = downloadUrl;
            }
            setTimeout(function () { URL.revokeObjectURL(downloadUrl); }, 100); // cleanup
        }

        $("#adl_msg").text("Download started!")

     })
     .fail( function(err){
        console.log(err);
        $("#adl_msg").text("Download aborted!")
     });
    
}




