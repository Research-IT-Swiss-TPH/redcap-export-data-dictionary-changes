# Export Data Dictionary Changes
Export recent changes of a REDCap Data Dictionary through automated Downloads or Emails.

## Setup

Install the module from REDCap module repository and enable over Control Center.

## Configuration

- Enable Download: Configure Downloads for approval modes.
- Enable Mail: Configure Emails for approval modes.

## Changelog

Version | Description
------- | --------------------
v1.0.0  | Initial release.
v1.0.1  | Set $mail->from over system settings.
v1.0.2  | Add 'data_dictionary_upload.php' to export routes.
v1.0.3  | Fix UTF-8 encoding.
v1.1.0  | Minor fix and additional check for project state.