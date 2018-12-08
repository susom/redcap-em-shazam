// CREATE SHAZAM
if(typeof Shazam === 'undefined') { var Shazam = {}; }

// Table
Shazam.post = function(action,field_name) {
    // console.log("Creating " + field_name);
    var action = $('<input>')
        .attr('name','action')
        .val(action);
    var field_name = $('<input>')
        .attr('name','field_name')
        .val(field_name);
    var form = $('#action-form').append(action).append(field_name).submit();
};

Shazam.initAceEditors = function() {

    // Init read-only editor for the example:
    var e = $('#shazam-example-code');
    if (e.length) {
        //console.log('setting up ace editor');
        Shazam.exampleEditor = ace.edit('shazam-example-code');
        Shazam.exampleEditor.setOptions({
            readOnly: true
        });
        Shazam.exampleEditor.setTheme("ace/theme/clouds");
        Shazam.exampleEditor.getSession().setMode("ace/mode/html");
        Shazam.exampleEditor.$blockScrolling = Infinity;
        e.width('100%');

        e.css({"height":"300px"});

        Shazam.exampleEditor.getSession().setValue($('#example-data').val());

        //console.log(e);
    }


    // console.log("initAceEditors");
    var langTools = ace.require("ace/ext/language_tools");

    // Add list of fields
    var redcapFieldCompleter = {
        getCompletions: function(editor, session, pos, prefix, callback) {
            // var wordList = ["foo", "bar", "baz"];
            // callback(null, wordList.map(function(word) {
            callback(null, Shazam.fields.map(function(word) {
                return {
                    caption: word,
                    value: word,
                    meta: "field"
                };
            }));
        }
    };
    langTools.addCompleter(redcapFieldCompleter);

    // Add shazam command hints
    var shazamCompleter = {
        getCompletions: function(editor, session, pos, prefix, callback) {
            var wordList = ["shazam", "data-shazam-mirror-visibility", "shazam-icons"];
            callback(null, wordList.map(function(word) {
                return {
                    caption: word,
                    value: word,
                    meta: "Shazam Commands"
                };
            }));

        }
    };
    langTools.addCompleter(shazamCompleter);

    // Add label hints
    var labelCompleter = {
        getCompletions: function(editor, session, pos, prefix, callback) {
            var field_labels = Shazam.fields.map(function(word) { return word + ":label" });
            callback(null, field_labels.map(function(word) {
                return {
                    caption: word,
                    value: word,
                    meta: "Field Label"
                };
            }));

        }
    };
    langTools.addCompleter(labelCompleter);

    Shazam.initEditor('editor_html', 'html');
    Shazam.initEditor('editor_css', 'css');
    Shazam.initEditor('editor_js', 'javascript');
};

Shazam.initEditor = function(id, mode) {
    // console.log("initEditor" + id + " / " + mode);
    // Create an ACE editor on the id element with mode mode
    var editorElement = $('#'+id);


    var currentValue = $(editorElement).html();
    if (Shazam.config[mode]) {
        var currentValue = Shazam.config[mode];
    }

    // console.log("EditorElement",editorElement, currentValue);
    var editor = ace.edit(id);

    editor.setOptions({
        enableBasicAutocompletion: true,
        enableSnippets: true,
        enableLiveAutocompletion: true
    });

    editor.setTheme("ace/theme/clouds");
    editor.getSession().setMode("ace/mode/" + mode);
    editor.$blockScrolling = Infinity;


    editorElement.width('100%');
    editor.commands.addCommand({
        name: 'saveFile',
        bindKey: {
            win: 'Ctrl-S',
            mac: 'Command-S',
            sender: 'editor|cli'
        },
        exec: function(env, args, request) {
            Shazam.save();
        }
    });

    // Set the value of the editor from memory
    editor.setValue(currentValue,1);

    // Get fancy with the default view of the default js template
    if (mode=='javascript') {
        if(currentValue == "$(document).ready(function(){\n\t//Add javascript here...\n\t\n});") {
            editor.gotoLine(3, 1);
        }

        // Handle JS editing - only for superusers
        if(Shazam.su !== 1) {
            editor.setOptions({
                readOnly: true
            });

            var jsWarn = $('<div></div>').addClass('alert alert-warning text-center').text("Javascript can only be edited by a REDCap Administrator.").insertBefore(editorElement);

        } else {
            var jsWarn = $('<span></span>').addClass('badge badge-danger text-center').text("Javascript Editing Enabled (You are a Super User)").wrap('<div/>').parent().addClass('text-center').insertBefore(editorElement);
        }
    }

    // For html, remove a few annotations that don't apply
    if (mode=='html') {
        var session = editor.getSession();
        session.on("changeAnnotation", function () {
            var annotations = session.getAnnotations() || [], i = len = annotations.length;
            while (i--) {
                if (/doctype first\. Expected/.test(annotations[i].text)) {
                    annotations.splice(i, 1);
                }
                else if (/Unexpected End of file\. Expected/.test(annotations[i].text)) {
                    annotations.splice(i, 1);
                }
            }
            if (len > annotations.length) {
                session.setAnnotations(annotations);
            }
        });
        editor.focus();
    }

    // Cache the editor objects to Shazam js object (used for resizing and saving)
    Shazam.editors.push( {id: id, instance: editor, mode: mode});
}

// Try to set a reasonable size to the ACE editor based on size of the display/window
Shazam.resizeAceEditors = function () {
    // Top of div
    var tabTop = $('div.shazam-editor').position().top;

    // Space in window
    var w = window.innerHeight;

    // Top of south footer (if present)
    var footerTop = $('#south').position().top;

    // Calculate a suitable bottom
    var bottom = footerTop == 0 ? w : Math.min(w,footerTop);

    // Space for submit and other fudge stuff
    var fudge = 90;

    // Calc Height
    var h = max(200,bottom - tabTop - fudge);

    // Apply height to all editors (even hidden ones)
    $(Shazam.editors).each(function(i,e){
        var editorDiv = $('#'+ e.id);
        $(editorDiv).css('height', h.toString() + 'px');
    });
};

Shazam.save = function(callback) {
    // Shazam.log('SAVE!');
    var field_name = $('div.shazam-editor').data('field-name');
    var status = 1; // $('div.shazam-editor').data('status')
    var comments = $('#save_comments').val();
    var data = {
        "action": "save",
        "field_name": field_name,
        "comments": comments,
        "params": Shazam.config
    };

    // data.params['status'] = 1;

    var saveBtn = $('button[name="save"]');
    var saveBtnHtml = saveBtn.html();
    saveBtn.html('<img src="'+app_path_images+'progress_circle.gif"> Saving...');
    saveBtn.prop('disabled',true);

    // Get the values from the three panes
    $(Shazam.editors).each(function (i, e) {
        var editor = e.instance;
        var val = editor.getValue();
        var mode = e.mode;
        // console.log(editor, val);
        data.params[mode] = val;
    });

    // Post back saved version
    var jqxhr = $.ajax({
        method: "POST",
        data: data,
        dataType: "json"
    })
        .done(function (data) {
            //Shazam.log(data);
            saveBtn.html(saveBtnHtml);
            saveBtn.prop('disabled',false);
            if (callback) {
                //Shazam.log("callback", field_name, callback, data);
                callback(data);
                return false;
            }
        })
        .fail(function () {
            alert("error");
        });
};

Shazam.closeEditor = function() {
    // go back to the table by making a get to the same url
    var url = window.location.href.replace(/#$/, "");
    $(location).attr('href', url);
};


Shazam.makeBeautiful = function() {
    var html_beautify = ace.require("ace/ext/html_beautify"); // get reference to extension

    // get active editor and beautify it!
    var panel_id = $('div.tab-pane:visible').attr('id');
    var id = panel_id.replace("panel_","");
    var editor = ace.edit(id);
    html_beautify.beautify(editor, false, false, {});
};


// A prepare the editor page by doing all necessary javascript add-ons
Shazam.prepareEditors = function() {

    // Create the ACE objects
    Shazam.editors = [];
    Shazam.initAceEditors();
    Shazam.resizeAceEditors();

    // Add some event handlers
    $(window).on('resize', function () {
        Shazam.resizeAceEditors();
    });

    $('.shazam-edit-buttons button').on('click', function() {
        var action = $(this).attr('name');

        if (action === 'save') {
            Shazam.save();
        }

        if (action === 'save_and_close') {
            Shazam.save(Shazam.closeEditor);
        }

        if (action === 'cancel') {
            Shazam.closeEditor();
        }

        if (action === 'beautify') {
            Shazam.makeBeautiful();
        }

    });

};

// Prepare event handlers for the table-view
Shazam.prepareTable = function() {
    // Handle the ADD button
    $('.add-shazam a').on('click', function() {
        // Create a new shazam entry
        var field_name = $(this).data('field-name');
        if (field_name.length) Shazam.post('create', field_name);
    });



    // Handle the Previous Version button
    $('.previous-shazam a').on('click', function() {
        // Recover version from old version
        var ts = $(this).data('ts');
        if (ts > 0) Shazam.post('restore', ts );
    });



    // Handle the in-table action buttons
    $('.shazam-table div.actions a').on('click', function() {
        var action = $(this).data('action');
        var field_name = $(this).closest('tr').find('td:first').text();

        // Confirm Deletions
        if (action === 'delete') {
            // Give confirmation popup to reset all others
            // function simpleDialog(content,title,id,width,onCloseJs,closeBtnTxt,okBtnJs,okBtnTxt) {
            simpleDialog('Are you sure you want to delete the Shazam configuration for ' + field_name + '?',
                'Confirm Deletion',
                null,
                600,
                function() {
                    // Do nothing
                    return false;
                },
                "Cancel",
                function() {
                    // Delete
                    Shazam.post(action, field_name);
                },
                "Delete"
            );
        } else {
            Shazam.post(action, field_name);
        }
    });

    // Handle the add example button
    $('div.add-example').on('click', function() {
        Shazam.post('add-example', 'shaz_ex_desc_field');
    });

};