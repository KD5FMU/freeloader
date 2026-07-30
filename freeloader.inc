<?php

// freeloader.inc

// Standalone Freeloader - Upload + Download + Edit (hardened)

// N5AD - July 2026


if (session_status() !== PHP_SESSION_ACTIVE) {

    session_start();

}

require_once __DIR__ . '/freeloader_common.php';

$csrf = freeloader_csrf_token();

?>

<div id="freeloader" style="background: #f8f9fa; border: 2px solid #2c3e50; border-radius: 10px; padding: 25px; margin-top: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">

    <h1 style="text-align:center; color:#2c3e50; margin-top:0;">Freeloader - ASL3 File Management Utility</h1>

    <hr style="border: 1px solid #bdc3c7; margin: 20px 0;">


    <!-- Upload Section -->

    <div class="section" style="background: #ffffff; border: 2px solid #4caf7a; border-radius: 8px; padding: 25px; margin-bottom: 25px; text-align: center;">

        <h3 style="color:#7dd9a1; margin-top:0;">Upload File</h3>


        <div style="margin-bottom:15px;">

            <label>Target Directory: </label>

            <input type="text" id="target_dir" value="/my_uploads" style="width:350px; padding:6px;"

                   title="Allowed: /my_uploads, /etc/asterisk, /var/lib/asterisk, /var/www/html/supermon">

        </div>


        <div style="display: inline-flex; align-items: center; gap: 12px; flex-wrap: wrap; justify-content: center; margin: 15px 0;">

            <input type="file" id="freeloader_file" style="background:#1e252f; color:#e0e0e0;">

            <button id="freeloader_upload_btn" class="submit">Upload</button>

        </div>

        <div id="freeloader_upload_status" style="margin-top:12px; font-weight:bold;"></div>

        <p style="font-size:12px; color:#666; margin-top:8px;">Only directories on the server whitelist are accepted.</p>

    </div>


    <!-- File List -->

    <div class="section" style="background: #ffffff; border: 2px solid #3498db; border-radius: 8px; padding: 20px; margin-bottom: 20px;">

        <h3 style="color:#2980b9; margin-top:0;">Files in Selected Directory</h3>

        <div id="freeloader_filelist" style="max-height: 420px; overflow-y: auto; background:#f8f9fa; padding:10px; border: 1px solid #ddd; border-radius: 6px;">

            Loading files...

        </div>

    </div>


    <!-- System Controls (compact, bottom) -->

    <div class="section" style="background: #ffffff; border: 1px solid #e67e22; border-radius: 6px; padding: 12px 18px; text-align: center;">

        <button id="restart_asterisk_btn" style="padding:8px 18px; background:#e67e22; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px;">

            Restart Asterisk

        </button>

        <div id="restart_status" style="margin-top:8px; font-weight:bold; font-size:13px;"></div>

    </div>

</div>


<!-- Edit Modal -->

<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1000; overflow-y:auto;">

    <div style="background:white; margin:40px auto; width:90%; max-width:1000px; padding:25px; border-radius:10px; box-shadow:0 8px 32px rgba(0,0,0,0.3);">

        <h3 style="margin-top:0; color:#2c3e50;">

            Editing: <span id="editFilename" style="color:#007bff;"></span>

            <small id="editDir" style="display:block; font-weight:normal; color:#666; font-size:14px; margin-top:4px;"></small>

        </h3>

        <div style="margin-bottom:12px;">

            <label for="editSaveAsName" style="font-weight:bold; margin-right:8px;">Save as filename:</label>

            <input type="text" id="editSaveAsName" style="width:60%; max-width:420px; padding:8px; border:1px solid #ccc; border-radius:4px; font-family:monospace;">

        </div>

        <textarea id="editContent" spellcheck="false" style="width:100%; height:420px; font-family:Consolas,Monaco,'Courier New',monospace; font-size:13px; padding:12px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; resize:vertical; line-height:1.4;"></textarea>

        <div style="margin-top:15px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">

            <div id="editStatus" style="font-weight:bold; flex:1;"></div>

            <div>

                <button id="editCancel" style="padding:10px 20px; background:#6c757d; color:white; border:none; border-radius:5px; cursor:pointer; margin-right:8px;">Cancel</button>

                <button id="editSaveAs" style="padding:10px 20px; background:#17a2b8; color:white; border:none; border-radius:5px; cursor:pointer; margin-right:8px;">Save As</button>

                <button id="editSave" style="padding:10px 20px; background:#28a745; color:white; border:none; border-radius:5px; cursor:pointer;">Save Changes</button>

            </div>

        </div>

    </div>

</div>


<script>

var FREELOADER_CSRF = <?= json_encode($csrf) ?>;

var freeloaderEditFile = null;

var freeloaderEditDir = null;


$("#restart_asterisk_btn").click(function() {

    if (!confirm("WARNING: This will restart the Asterisk service.\n\nAll active connections will be dropped.\n\nAre you sure you want to continue?")) {

        return;

    }

    $("#restart_status").text("Restarting Asterisk...").css("color", "orange");

    $("#restart_asterisk_btn").prop("disabled", true);

    $.post("/freeloader/freeloader_upload.php", {

        action: "restart_asterisk",

        csrf: FREELOADER_CSRF

    }, function(response) {

        $("#restart_asterisk_btn").prop("disabled", false);

        var isSuccess = response.indexOf("SUCCESS") !== -1;

        $("#restart_status").html(response).css("color", isSuccess ? "green" : "red");

    }).fail(function(xhr) {

        $("#restart_asterisk_btn").prop("disabled", false);

        $("#restart_status").html("Restart failed. Check console / permissions.").css("color", "red");

        console.error(xhr);

    });

});


$("#freeloader_upload_btn").click(function() {

    var fileInput = $("#freeloader_file")[0];

    if (!fileInput.files.length) {

        alert("Please select a file first.");

        return;

    }

    var targetDir = $("#target_dir").val().trim();

    if (!targetDir) {

        alert("Please enter a target directory.");

        return;

    }

    var formData = new FormData();

    formData.append("file", fileInput.files[0]);

    formData.append("target_dir", targetDir);

    formData.append("csrf", FREELOADER_CSRF);

    $("#freeloader_upload_status").text("Uploading...").css("color", "orange");

    $.ajax({

        url: "/freeloader/freeloader_upload.php",

        type: "POST",

        data: formData,

        processData: false,

        contentType: false,

        success: function(response) {

            $("#freeloader_upload_status").html(response).css("color", "green");

            loadFreeloaderFiles();

            $("#freeloader_file").val("");

        },

        error: function(xhr) {

            $("#freeloader_upload_status").html("Upload failed.<br>" + (xhr.responseText || "")).css("color", "red");

        }

    });

});


function loadFreeloaderFiles() {

    var targetDir = $("#target_dir").val().trim() || "/my_uploads";

    $.get("/freeloader/freeloader_upload.php?action=list&dir=" + encodeURIComponent(targetDir), function(html) {

        $("#freeloader_filelist").html(html);

    }).fail(function() {

        $("#freeloader_filelist").html("<p style='color:red;'>Error loading file list (directory may not be permitted).</p>");

    });

}


window.downloadFreeloaderFile = function(filename) {

    var targetDir = $("#target_dir").val().trim() || "/my_uploads";

    window.location.href = "/freeloader/freeloader_download.php?file=" + encodeURIComponent(filename) + "&dir=" + encodeURIComponent(targetDir);

};


window.deleteFreeloaderFile = function(filename) {

    var targetDir = $("#target_dir").val().trim() || "/my_uploads";

    var isSystemDir = targetDir.indexOf("/etc/") === 0 ||

                      targetDir.indexOf("supermon") !== -1 ||

                      targetDir.indexOf("asterisk") !== -1;

    var message = "Delete " + filename + " from " + targetDir + "?";

    if (isSystemDir) {

        message = "WARNING: This is a SYSTEM directory!\n\nAre you ABSOLUTELY sure you want to delete " + filename + "?";

    }

    if (!confirm(message)) return;


    $.post("/freeloader/freeloader_delete.php", {

        file: filename,

        dir: targetDir,

        csrf: FREELOADER_CSRF

    }, function(response) {

        $("#freeloader_upload_status").html(response).css("color", "green");

        loadFreeloaderFiles();

    }).fail(function(xhr) {

        alert("Delete failed. Check console.");

        console.error(xhr);

    });

};


window.editFreeloaderFile = function(filename) {

    var targetDir = $("#target_dir").val().trim() || "/my_uploads";

    freeloaderEditFile = filename;

    freeloaderEditDir = targetDir;


    $("#editFilename").text(filename);

    $("#editDir").text("Directory: " + targetDir);

    $("#editSaveAsName").val(filename);

    $("#editContent").val("Loading...");

    $("#editStatus").text("").css("color", "");

    $("#editModal").show();

    $("body").css("overflow", "hidden");


    $.get("/freeloader/freeloader_upload.php?action=get&file=" + encodeURIComponent(filename) + "&dir=" + encodeURIComponent(targetDir), function(data) {

        if (typeof data === "string" && data.indexOf("ERROR:") === 0) {

            $("#editContent").val("");

            $("#editStatus").html(data).css("color", "red");

        } else {

            $("#editContent").val(data);

            $("#editStatus").text("Ready to edit. Make your changes and click Save.").css("color", "#666");

        }

    }).fail(function(xhr) {

        $("#editContent").val("");

        $("#editStatus").html("Failed to load file content.").css("color", "red");

        console.error(xhr);

    });

};


$("#editCancel").click(function() {

    $("#editModal").hide();

    $("body").css("overflow", "");

    freeloaderEditFile = null;

    freeloaderEditDir = null;

});


$("#editModal").click(function(e) {

    if (e.target === this) {

        $("#editCancel").click();

    }

});


function freeloaderDoSave(targetFilename, isSaveAs) {

    if (!freeloaderEditDir) {

        alert("No file selected for editing.");

        return;

    }

    targetFilename = (targetFilename || "").trim();

    if (!targetFilename) {

        alert("Please enter a filename.");

        return;

    }

    // Reject path separators and empty/dot names (server still validates)

    if (targetFilename.indexOf("/") !== -1 || targetFilename.indexOf("\\") !== -1 ||

        targetFilename === "." || targetFilename === "..") {

        alert("Invalid filename.");

        return;

    }


    if (isSaveAs && targetFilename === freeloaderEditFile) {

        if (!confirm("Filename is unchanged. This will overwrite the existing file. Continue?")) {

            return;

        }

    }


    var content = $("#editContent").val();

    $("#editStatus").text(isSaveAs ? "Saving as new file..." : "Saving...").css("color", "orange");

    $("#editSave, #editSaveAs").prop("disabled", true);


    $.post("/freeloader/freeloader_upload.php", {

        action: "save",

        file: targetFilename,

        dir: freeloaderEditDir,

        content: content,

        csrf: FREELOADER_CSRF

    }, function(response) {

        $("#editSave, #editSaveAs").prop("disabled", false);

        var isSuccess = response.indexOf("SUCCESS") !== -1;

        $("#editStatus").html(response).css("color", isSuccess ? "green" : "red");

        if (isSuccess) {

            freeloaderEditFile = targetFilename;

            $("#editFilename").text(targetFilename);

            $("#editSaveAsName").val(targetFilename);

            loadFreeloaderFiles();

        }

    }).fail(function(xhr) {

        $("#editSave, #editSaveAs").prop("disabled", false);

        $("#editStatus").html("Save failed. Check console / permissions.").css("color", "red");

        console.error(xhr);

    });

}


$("#editSave").click(function() {

    freeloaderDoSave(freeloaderEditFile, false);

});


$("#editSaveAs").click(function() {

    freeloaderDoSave($("#editSaveAsName").val(), true);

});


$(document).on("keydown", function(e) {

    if (e.key === "Escape" && $("#editModal").is(":visible")) {

        $("#editCancel").click();

    }

});


$("#target_dir").on("change", loadFreeloaderFiles);

loadFreeloaderFiles();

setInterval(loadFreeloaderFiles, 20000);

</script>


<style>

    #freeloader_filelist table {

        width: 100%;

        border-collapse: collapse;

        background: white;

    }

    #freeloader_filelist th, #freeloader_filelist td {

        padding: 8px 10px;

        text-align: center;

        border: 1px solid #ddd;

    }

    #freeloader_filelist th {

        background: #2c3e50;

        color: white;

    }

    #freeloader_filelist tr:nth-child(even) {

        background: #f9f9f9;

    }

    #freeloader_filelist button {

        padding: 5px 12px;

        color: white;

        border: none;

        border-radius: 4px;

        cursor: pointer;

        margin: 0 3px;

    }

    #freeloader_filelist .download-btn { background: #28a745; }

    #freeloader_filelist .edit-btn { background: #007bff; }

    #freeloader_filelist .delete-btn { background: #dc3545; }


    @media (max-width: 700px) {

        #editModal > div {

            width: 96% !important;

            margin: 20px auto !important;

            padding: 15px !important;

        }

        #editContent {

            height: 300px !important;

        }

    }

</style>
