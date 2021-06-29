# Moodle Panopto Student Submission Activity

## Pre-requisites 

- The [Panopto Block for Moodle](https://github.com/Panopto/Moodle-2.0-plugin-for-Panopto) must be installed


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


## Usage

### Pre-Requisites
- The target course must be provisioned with Panopto

### Teacher Usage
**Activity Creation**
1. In the target course turn on editing.
2. Click the Add and activity or resource link in the section of the course you wish to add an assignment to. 
3. Select the Panopto Student Submission tool
4. Give the assignment a name
5. (Optional) Customize any of the other options to suit your needs (e.g. grade, due date, allow late submission, …, etc)
6. Click either Save and Display or Save and Return to course. 

**Activity Grading**
1. Navigate to the activity you want to grade
2. Click the grade button.
3. Select the Grade or Update button for the student you wish to grade.
4. Input the desired grade and comment if wanted. Click Save Changes to submit the grade to Moodle. 

### Student Usage
**Activity Submission**
1. Navigate to the activity
2. Click ‘Add Panopto Submission’
3. If your submission is not yet ready you may upload or record it from this window in any folder you have creator access. If your submission is ready you may select it from your folder. Once you have selected, recorded, or uploaded your submission click Insert
4. Note: Pressing Insert here is what triggers the Panopto side of the student submission behavior. Any submission that is inserted will get copied/moved to the student submissions folder
5. The user may now view their submission in the Moodle page, if they are happy with their submission they can click Submit. If they do not like their submission they may click replace if re-submission is enabled.
