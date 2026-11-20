# HTECH LeadFlow · Client setup guide

**Version 0.9.0 · Staging only** until the release checks in the test report are completed.

## 1. Install and publish your first form

1. Back up your WordPress site. On staging, open **Plugins → Add Plugin → Upload Plugin**.
2. Choose `htech-leadflow-0.9.0.zip`, select **Install Now**, and then **Activate Plugin**.
3. Select **LeadFlow → Overview → Open setup wizard**. Enter your business name and email. If using WhatsApp, use your international number (for example `+234…`). Enable email notifications only if someone should receive them.
4. Copy the shortcode shown after saving. Edit a page, add a **Shortcode** block and paste it. In a page builder with shortcode support, use its equivalent widget. Publish the page.
5. Open the page in a private browser window and send a test enquiry. Make sure the lead appears under **LeadFlow → Leads**. If you turned on email, check the receiving inbox independently.

## 2. Capture useful information

In **Forms**, open the form or choose **New form**. Use **Add field** to add a field. For a dropdown, put each choice on its own line. Map fields such as name, email, telephone and enquiry to the corresponding lead field so they appear in the CRM. Leave other questions as custom fields. Use a checkbox and your own wording for consent; do not pre-check marketing permission or require it without a valid reason. Link your published privacy policy in **General Settings**.

Choose a card or plain form style. Use a same-site success redirect if you built a separate thank-you page; otherwise visitors see the success message. Give repeat visitors accurate confirmation by excluding pages with the form from full-page caching.

## 3. Handle an enquiry

1. Open a lead by name or ID. Review the submitted information and source.
2. Update its stage from **New** to **Contacted**, **Interested**, **Qualified**, **Converted**, or **Lost**, as appropriate. The **Pipeline** is useful for seeing the latest enquiries by stage.
3. Assign a team member if needed. Assignment records responsibility; it does not make a record private to that person.
4. Add internal notes and schedule a follow-up date. Open **Follow-ups** to see today’s work or overdue tasks. Mark a task complete after doing it. No automated messages are sent.
5. If a phone is present, open the WhatsApp conversation. Review the prefilled message yourself before sending. Opening the link does not send, track, or confirm the message.

## 4. Report, export and import

Go to **Analytics**, choose dates, then **Print report**. Your browser may also offer “Save as PDF.” In **Leads**, select rows and choose **Export selected**. In **Tools**, export all leads or upload a UTF-8 CSV, match its columns, review errors, and import valid rows. Review likely duplicates before merging; records with different nonempty emails cannot be merged. Export files contain personal information; store them securely and delete them when no longer needed.

## 5. Review settings before going live

- **WhatsApp Settings:** verify the business number and choose whether buttons show on desktop and mobile.
- **Notifications:** start with only the fields the recipient needs. A notification does not replace the private CRM record.
- **General Settings:** decide how long to keep lead data. Zero days keeps it indefinitely. Scheduled deletion depends on WordPress cron. Optional first-page attribution uses session storage for 30 minutes; enable it only after reviewing your privacy requirements.
- **Permissions:** give staff only the access they need. Do not share an administrator’s login.
- **Uninstall:** leave the purge option off unless you intend to destroy all plugin records. Back up before enabling it.
