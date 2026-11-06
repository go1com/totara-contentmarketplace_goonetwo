# Go1 content marketplace plugin (contentmarketplace_goonetwo) changelog

`contentmarketplace_goonetwo` is the uplifted core plugin `contentmarketplace_goone` with a new name. The changes below are relative to the core plugin in Totara 20.0.0. The text uses the new names (`contentmarketplace_goonetwo`, `marketplace_goonetwo_*`, `contentmarketplace/goonetwo:curatecontent`). The plugin has no upgrade steps. Where the text mentions an upgrade step, a new install gets the same result.

The technical name has no digits. Some core content marketplace pages clean the plugin name with `PARAM_ALPHA` or `PARAM_ALPHAEXT`, which remove digits. A name with a digit would then change into a different plugin name.

# Version 2.0.0

Compatible Totara versions: Totara 20.0 and later.

## Platform

- **Go1 API v3.** All calls go to `gateway.go1.com` with the `Api-Version: 2025-01-01` header. The plugin does not use the v2 account, configuration and collection-item endpoints. New endpoints:
  - User accounts: search, get, create, update and login.
  - Enrollments.
  - Webhooks.
  - Learning-object list with scroll, for large collections.
- **New collection names.** The old names "all", "subscribed" and "collection" are now "free", "subscribe" and "custom". An upgrade step changes the stored content access setting to the new names.
- **OAuth changes.**
  - Authorisation asks for more scopes: `lo.read lo.write enrollment.read portal.read portal.write user.read user.write user.login webhook.read webhook.write`.
  - If Go1 rejects the refresh token, the token refresh uses the client credentials.
  - If a scope is missing, the plugin shows a special error. The error tells the user to speak to Go1 support.

## New functionality

- **Content curation page** (`curate.php`).
  - The page shows the Go1 Content Hub in an iframe. It uses a one-time token.
  - The plugin finds the Go1 account for the Totara user. It looks in the mapping table first, then it compares the email. If there is no account, the plugin makes one.
  - The plugin gives the account the Content administrator role.
  - The "Sync content" button syncs the custom collection through AJAX. Only the scheduled task syncs the Free and Subscribed collections.
- **Content sync** (new `sync_learning_objects` sync action).
  - The core marketplace scheduled task `\totara_contentmarketplace\task\sync_task` runs the sync for all the selected collections. The "Sync content" button runs the sync for the custom collection only. It works only if the custom collection is selected for sync.
  - The sync gets the published learning objects from the selected collections. A region relevance filter is optional. The sync then makes the courses automatically.
  - You can configure the course category, the course type (single activity or multi activity) and the shortname strategy.
  - Each course gets these items:
    - The Go1 image.
    - A description with an overview, learning outcomes, topics, skills, and duration.
    - A SCORM module.
    - Completion criteria.
- **Retired content process.**
  - The sync finds learning objects in the retired state. Then it does actions on the courses that use them.
  - Actions for retired content (configurable): a banner on the course image ("Leaving on ..."), a notice in the summary, a move to a category, and hide.
  - Actions for removed content: move and hide.
  - The scope is the sync category only or the full site.
  - The plugin records the actions for each learning object. Thus each action occurs one time only.
- **Webhook** (`payload.php`).
  - The webhook subscribes to `enrollment.complete`.
  - The plugin examines the Go1 signature and the timestamp. The timestamp must be in the permitted tolerance.
  - The plugin uses different methods to find the Totara user for the Go1 user.
  - The plugin records the completion of the related SCORM activities.
  - If an error occurs, the plugin writes it to a new log table. It does not send an HTTP error.
  - If the signature is not correct, the log keeps only the error message. It does not keep the request, because anyone can send a request to the endpoint.
  - A new scheduled task (`prune_webhook_log_task`) removes old log entries. It runs one time each day. The "Webhook log retention (days)" setting on the Webhook tab gives the retention period, from 1 to 365 days (default 30).
  - A new user data item ("Go1 webhook log entries") counts, exports and purges the log entries of a Totara user.
  - The admin tab shows the webhook of this site. The Go1 portal can also have webhooks of other sites, which the tab ignores. The tab shows each problem of the webhook: inactive, incorrect secret key, or a missing event type. The tab shows one button: "Create webhook" if there is no webhook, "Fix webhook" if the webhook has a problem, or "Disable webhook" if the webhook is correct. "Fix webhook" also makes the webhook active. "Disable webhook" asks for confirmation, then sets the webhook to inactive, because the Go1 API cannot delete a webhook. If Go1 does not accept an action, the tab shows the error message from Go1.
- **Add an activity to a course.** New `create_marketplace_activity` workflow and `activitycreate.php` page with a confirmation step. The install and the upgrade enable this workflow.
- **Manual course creation setting.**
  - If manual creation is disabled, the "Add courses from Go1" workflow opens the curation page.
  - The default is disabled for new sites and upgraded sites.
  - Manual creation uses the same completion defaults as the sync.
- **Migration from the core plugin.**
  - The General tab shows a warning if some Go1 data belongs to the core plugin, or if the core plugin is enabled. The warning has a link to the new Migration tab.
  - The Migration tab shows the data that is not migrated. A button starts the migration. An ad-hoc task (`migrate_task`) moves the learning objects, the course module source rows and the SCORM package file sources to this plugin. Then it disables the core plugin. If there is no data to migrate, the button disables the core plugin immediately, with no task.
  - When all data belongs to this plugin, the button moves the data back to the core plugin and disables this plugin. It does not enable the core plugin. This action has two confirmation steps: a dialog and a confirmation page.
  - When this plugin is disabled, its settings page opens Manage content marketplaces and shows the migration status there.
  - The tab shows the status of the migration: pending, complete or failed. The setting `migration_pending` keeps the status `completed` after a migration to this plugin, and `reverted` after a migration back to the core plugin, until the next migration in the other direction.
  - With the status `completed` and a disabled core plugin, the tabs do not examine the tables for core data. When the tables have no core data, the status changes to `completed`.
  - If the core plugin is disabled, a restored activity from an old core Go1 backup belongs to this plugin.
- **Uninstall script** (`db/uninstall.php`). Before core removes the tables, the config and the capabilities, the script:
  - Sets the Go1 webhook of this site to inactive. The Go1 API cannot delete a webhook. Webhooks of other sites do not change. An error does not stop the uninstall.
  - Disables the three workflows that the install enabled.
  - Deletes the course module source rows of this plugin. The catalogue and the reports cannot load a marketplace plugin that is not installed. The courses and their SCORM activities stay.
  - Deletes the queued migration tasks.

## Changed functionality

- **Settings page.** It is now an admin page with these tabs: General, Content sync, Retired content, Content access, Webhook and Migration. The General tab shows the Client ID.
- **Settings log.** The settings tabs and the content access step of the setup wizard log each changed setting in the "Config changes" report, the same as admin settings. The plugin does not log the values that it sets automatically, such as the OAuth tokens.
- **Setup wizard.**
  - The account step is removed.
  - Content access is a multi-select list of collections. The default is "custom".
  - After setup, the wizard opens the marketplaces list, not the explorer.
- **Explorer.**
  - The filters use the v3 facets. "Tags" is now "Topics".
  - The provider and language filters use the new facet structure.
  - The availability filter shows free, subscribe and custom, as set in the content access settings.
  - You can select one learning object only. The multi-select function is removed.
- **"Explore marketplace" workflow.** It opens the curation page, not the explorer.
- **Course creation.**
  - It uses the container course helper.
  - It applies the default audience visibility.
  - For SCORM completion, it selects both Passed and Completed.
- **Learning object model.** It reads the v3 fields (`core.title`, `core.image.value`, `relevance.language`).
- **REST client.** It can send PATCH requests. You can configure the timeouts.

## Removed functionality

- The account and plan display: `account.php`, the account template and the `account_plan_*` config values.
- The standalone content settings page.
- Curation of Go1 collections through the API. The collection class now throws an exception. The exception tells users to use the Go1 Content Hub in Totara.
- Pay-per-seat sync with the Go1 configuration.
- Behat features for course creation, filters and OAuth, and the v2 fixture set. New PHPUnit tests replace them. These tests are for the API, OAuth, search, helper, sync, webhook, embed, upgrade and manual course creation.

## New capability

- `contentmarketplace/goonetwo:curatecontent` (system context, write, `RISK_CONFIG`). The Manager archetype has this capability. A user must have this capability for these items:
  - The curation page.
  - The "Manage available content" button (manual course creation).

## Database

- New tables:
  - `marketplace_goonetwo_user_map`: the Totara user and the related Go1 user. One row for each Totara user.
  - `marketplace_goonetwo_webhook_log`, with an index on `timecreated`.
- `marketplace_goonetwo_learning_object`: new columns `retired_time`, `retired_actioned`, `removed_time` and `removed_actioned`.

## New config keys

`create_course_manual`, `create_courses`, `course_category`, `course_type`, `course_shortname`, `sync_collections`, `content_regions`, `retired_content_process`, `retired_content_location`, `retired_content_retired_actions`, `retired_content_removed_actions`, `retired_content_actions_move_category`, `retired_content_dateformat`, `webhook_secret_key`, `webhook_log_retention_days`, `last_synced_new_content`.
