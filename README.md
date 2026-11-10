# Moodle Panopto Student Submission

A Moodle activity where students submit a Panopto video and teachers grade it in Moodle, similar to an assignment.

## Who it is for

- **Teachers** create the activity, set due dates, and grade submitted videos.
- **Students** record or choose a Panopto video and submit it for the activity.
- **Administrators** install the plugin, configure a Panopto LTI tool, and make sure the Panopto block is set up.

## Requirements

- A supported **Moodle** version. Each [GitHub release](https://github.com/Panopto/Moodle-Panopto-Student-Submission/releases) lists the Moodle versions that zip supports — check those notes and pick the release that matches your site.
- The [Panopto block](https://github.com/Panopto/Moodle-2.0-Plugin-for-Panopto) (this plugin will not install without a current enough block)
- Moodle’s **External tool (LTI)** module
- A **preconfigured LTI tool** for your Panopto server
- The course **provisioned** with Panopto through the block

## Installation

1. Download a release from [GitHub](https://github.com/Panopto/Moodle-Panopto-Student-Submission/releases) or from [moodle.org](https://moodle.org/plugins/mod_panoptosubmission).
2. In Moodle, go to **Site administration → Plugins → Install plugins**.
3. Drop the zip onto the install page and complete the upgrade.
4. Confirm it appears under **Site administration → Plugins → Activity modules → Panopto student submission**.

## Configure the Panopto LTI tool

Students pick a video through LTI. Add a preconfigured tool if you do not already have one for this server.

1. Go to **Site administration → Plugins → Activity modules → External tool → Manage tools**.
2. Click **configure a tool manually** (or **Add preconfigured tool**, depending on your Moodle version).
3. Fill in the fields from your Panopto identity provider.

### LTI 1.1

| Field | Value |
| --- | --- |
| Tool name | `[panoptoServer] Student Submission Tool` |
| Tool URL | `https://[panoptoServer]/Panopto/LTI/LTI.aspx` |
| Consumer key | Identity provider → **Instance name** |
| Shared secret | Identity provider → **Application key** |

Custom parameters:

```
panopto_student_submission_tool=true
panopto_single_selection=true
panopto_assignment_submission_content_item=true
use_panopto_sandbox=true
```

`use_panopto_sandbox=true` gives students a personal folder for submissions even when the identity provider would not otherwise.

### LTI 1.3

| Field | Value |
| --- | --- |
| Tool name | `[panoptoServer] Student Submission Tool` |
| URL | `https://[panoptoServer]/Panopto/LTI/LTI.aspx` |
| LTI version | LTI 1.3 |
| Client ID | Identity provider → **LTI 1.3 Client Identifier** |
| Public key type | Keyset URL |
| Public keyset | Identity provider → **LTI 1.3 Tool JWKS URL** |
| Initiate login URL | Identity provider → **LTI 1.3 Tool Login URL** |
| Redirection URI(s) | Identity provider → **LTI 1.3 Tool Redirection URL** |

Save the tool.

## Using it on a course

1. Provision the course with the Panopto block if you have not already.
2. Turn editing on and add the **Panopto student submission** activity.
3. Set availability, due date, and cut-off as you would for an assignment.
4. Students open the activity, choose or record a video in Panopto, and submit it.
5. Teachers open the activity to watch submissions and enter grades.

## Support

- How-to article: [Enable Student Submission for Moodle](https://support.panopto.com/s/article/How-to-Enable-Student-Submission-for-Moodle)
- Product documentation: [Panopto Support](https://support.panopto.com)
