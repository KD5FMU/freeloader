<?php
// freeloader.inc
// Standalone Freeloader - Upload to any directory
// N5AD - July 2026
?>
<div id="freeloader" style="background: #f8f9fa; border: 2px solid #2c3e50; border-radius: 10px; padding: 25px; margin-top: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
    <h1 style="text-align:center; color:#2c3e50; margin-top:0;">Freeloader - Upload Any File</h1>
    <hr style="border: 1px solid #bdc3c7; margin: 20px 0;">
   
    <!-- Upload Section -->
    <div class="section" style="background: #ffffff; border: 2px solid #4caf7a; border-radius: 8px; padding: 25px; margin-bottom: 25px; text-align: center;">
        <h3 style="color:#7dd9a1; margin-top:0;">Upload File</h3>
        
        <div style="margin-bottom:15px;">
            <label>Target Directory: </label>
            <input type="text" id="target_dir" value="/my_uploads" style="width:350px; padding:6px;">
        </div>
        
        <div style="display: inline-flex; align-items: center; gap: 12px; flex-wrap: wrap; justify-content: center; margin: 15px 0;">
            <input type="file" id="freeloader_file" style="background:#1e252f; color:#e0e0e0;">
            <button id="freeloader_upload_btn" class="submit">Upload</button>
        </div>
        <div id="freeloader_upload_status" style="margin-top:12px; font-weight:bold;"></div>
    </div>

    <!-- File List -->
    <div class="section" style="background: #ffffff; border: 2px solid #3498db; border-radius: 8px; padding: 20px;">
        <h3 style="color:#2980b9; margin-top:0;">Files in Selected Directory</h3>
        <div id="freeloader_filelist" style="max-height: 420px; overflow-y: auto; background:#f8f9fa; padding:10px; border: 1px solid #ddd; border-radius: 6px;">
            Loading files...
        </div>
    </div>
</div>

<script>
// Upload
$("#freeloader_upload_btn").click(function() {
    let fileInput = $("#freeloader_file")[0];
    if (!fileInput.files.length) {
        alert("Please select a file first.");
        return;
    }
    let targetDir = $("#target_dir").val().trim();
    if (!targetDir) {
        alert("Please enter a target directory.");
        return;
    }

    let formData = new FormData();
    formData.append("file", fileInput.files[0]);
    formData.append("target_dir", targetDir);

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
            $("#freeloader_file").val('');
        },
        error: function(xhr) {
            $("#freeloader_upload_status").html("Upload failed.<br>" + (xhr.responseText || "")).css("color", "red");
        }
    });
});

// Load File List
function loadFreeloaderFiles() {
    let targetDir = $("#target_dir").val().trim() || '/my_uploads';
    $.get("/freeloader/freeloader_upload.php?action=list&dir=" + encodeURIComponent(targetDir), function(html) {
        $("#freeloader_filelist").html(html);
    }).fail(function() {
        $("#freeloader_filelist").html("<p style='color:red;'>Error loading file list.</p>");
    });
}

// Delete Function
window.deleteFreeloaderFile = function(filename) {
    if (!confirm("Delete " + filename + "?")) return;
    let targetDir = $("#target_dir").val().trim() || '/my_uploads';
    $.post("/freeloader/freeloader_delete.php", { file: filename, dir: targetDir }, function(response) {
        $("#freeloader_upload_status").html(response).css("color", "green");
        loadFreeloaderFiles();
    }).fail(function() {
        alert("Delete failed.");
    });
};

// Initial load + auto-refresh when directory changes
$("#target_dir").on('change', loadFreeloaderFiles);
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
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    #freeloader_filelist button:hover {
        background: #c82333;
    }
</style>
