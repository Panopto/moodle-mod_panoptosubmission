# Moodle Panopto Student Submission Activity
This plugin adds a Panopto Student Submission activity to Moodle. This tool can be used to create activities that will allow students to submit Panopto Content to be graded by graders for the course.

## Documentation
For the most up to date documentation for the Panopto Student Submission plugin please see our [online documentation at Panopto](https://support.panopto.com/s/article/How-to-Enable-Student-Submission-for-Moodle).


## Installation
1. Download the Moodle Panopto Student Submission zip file from the [github repository](https://github.com/Panopto/Moodle-Panopto-Student-Submission/releases). We will work with Moodle.org to add this into the app directory. Until then please use our github as the official release site.
2. Navigate to the target Moodle site and log in as an administrator
3. Navigate to Site Administration -> Plugins -> Install Plugins
4. Drag the zip file into the drag & drop box and go through the installation process.
5. An LTI Tool for the Panopto server must be configured on the Moodle site. If one does not already exist for your Panopto site please navigate to Site administration -> Plugins -> Activity modules -> External tool -> Manage preconfigured tools
6. Click Add Preconfigured tool.
7. Input the following information
    - Tool Name: [panoptoServer] Student Submission Tool
    - Tool Url: https://[panoptoServer]/Panopto/LTI/LTI.aspx
    - Consumer Key:[Identity Provider instance name]
    - Shared secret: [Identity Provided application key]
    - Custom Parameters:
        ```
        panopto_student_submission_tool=true
        panopto_single_selection=true
        panopto_assignment_submission_content_item=true
        use_panopto_sandbox=true
            - This custom parameter will give students personal folders regardless of IdP setting.
        ```
8. Save the LTI Tool

## Pre-Requisites
- The [Panopto block for Moodle](https://github.com/Panopto/Moodle-2.0-plugin-for-Panopto) is installed on the Moodle site.
- The target course must be provisioned with Panopto.
