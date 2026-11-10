# Go1 v2 content marketplace plugin for Totara

`contentmarketplace_goonetwo` connects the [Go1](https://www.go1.com) content library to Totara Learn. It uses the Totara content marketplace framework. With this plugin, a site administrator can do these tasks:

- Connect a Go1 portal to Totara.
- Curate content in the Go1 Content Hub, which shows in Totara.
- Sync the selected learning objects into Totara as courses.
- Receive completion data from Go1.

This repository contains the uplifted version of the core Go1 plugin, with a new name. The plugin uses the Go1 API v3 and Totara 20. It is separate from the core plugin `contentmarketplace_goone`. The two plugins can be installed on the same site, but only one of them must be enabled at a time. The **Migration** tab moves the Go1 data between the two plugins and disables the plugin that is no longer used.

## Compatibility

The plugin is compatible with Totara 20 only. It is not compatible with earlier Totara versions.

## Repository contents

The repository is the plugin directory. Install it at this location:

```
server/totara/contentmarketplace/contentmarketplaces/goonetwo
```

Main parts:

| Path | Function |
|------|----------|
| `classes/api.php`, `classes/rest_client.php`, `classes/oauth*.php` | Go1 API v3 client, OAuth authorisation and token refresh. |
| `classes/contentmarketplace.php`, `classes/plugininfo.php` | Registration of the plugin in the Totara content marketplace framework. |
| `classes/sync_action/sync_learning_objects.php` | Content sync. Gets learning objects from the selected collections and makes courses. |
| `classes/helper.php` | Course creation, completion defaults and retired content process. |
| `classes/webhook.php`, `payload.php` | Webhook subscription. Endpoint that receives `enrollment.complete` events. |
| `classes/task/prune_webhook_log_task.php`, `webhook_log.php` | Webhook log report. A daily scheduled task removes old entries. The retention period is a setting on the Webhook tab (default 30 days). |
| `classes/migration.php`, `classes/task/migrate_task.php`, `migrate.php` | Migration of Go1 data between the core plugin and this plugin. |
| `classes/embed.php`, `curate.php` | Go1 Content Hub in Totara, for content curation. |
| `classes/workflow/`, `coursecreate.php`, `activitycreate.php` | "Add course" and "add activity" workflows. |
| `classes/controllers/`, `tabs/`, `settings.php`, `setup.php` | Admin settings page with tabs, and setup wizard. |
| `classes/learning_object/`, `classes/model/`, `classes/entity/` | Learning object model, ORM entities and repositories. |
| `db/` | Capabilities, caches, hooks, scheduled tasks, install schema, install steps and upgrade steps. |
| `amd/`, `templates/`, `styles.css`, `pix/` | Frontend assets and Mustache templates. |
| `lang/en/` | Language strings. |
| `tests/` | PHPUnit tests for the API client, OAuth, search, sync, webhook, embed, install schema and manual course creation. |
| `CHANGELOG.md` | Release notes. |

## Features

- **Go1 API v3.** The plugin uses API version `2025-01-01`.
- **Content curation.** The Go1 Content Hub shows in Totara. The plugin finds the Go1 account for the Totara user. If there is no account, the plugin makes one. The plugin gives the account the Content administrator role.
- **Content sync.** The plugin gets the published learning objects from the selected collections (free, subscribed, custom). It makes a course for each learning object. Each course has an image, a description, a SCORM module and completion criteria. The core marketplace scheduled task runs the sync. To sync the custom collection immediately, use the "Sync content" button.
- **Retired content process.** Go1 can retire or remove a learning object. The plugin can then do these actions on the related courses: add a banner, add a notice, move the course to a category, or hide the course.
- **Completion webhook.** Go1 sends `enrollment.complete` notifications to the plugin. The plugin examines each notification. Then it records the completion of the related SCORM activity in Totara.
- **"Add course" and "add activity" workflows.** By default, a site manager or a course creator can make a course from a Go1 learning object manually. A site manager or an editing trainer can add a learning object as an activity to a course.
- **Settings tabs.** The settings page has these tabs: General, Content sync, Retired content, Content access, Webhook and Migration.
- **Migration from the core plugin.** The Migration tab moves the Go1 activities of the core plugin to this plugin, and disables the core plugin. It can also move the data back to the core plugin.

For the full list of changes from the core plugin, refer to [CHANGELOG.md](CHANGELOG.md).

## Manual installation

1. Clone this repository, or download a release and uncompress the archive, into the `goonetwo` directory.
2. Do the Totara upgrade. Use Site administration or the CLI:

   ```bash
   php server/admin/cli/upgrade.php
   ```

The plugin does not upgrade from the core plugin `contentmarketplace_goone`. The install does not copy settings or data from the core plugin. To move the Go1 activities of the core plugin to this plugin, use the **Migration** tab after the configuration. The migration does not copy the settings.

## Configuration

1. Go to **Site administration > Content Marketplace > Manage content marketplaces**. Set up Go1 v2. The setup wizard shows the steps for OAuth authorisation and content access.
2. On the **Content sync** tab, select these items:
   - The collections to sync.
   - The target course category.
   - The course type.
   - The shortname strategy.
3. On the **Webhook** tab, create the webhook. Go1 then sends completions to Totara.
4. On the **Retired content** tab, the site administrator can set the actions for courses that use retired or removed Go1 content. This step is optional.
5. If the site has courses from the core plugin, go to the **Migration** tab. Click the button to migrate the data and disable the core plugin. The next cron run does the migration.

To curate content, a user must have the `contentmarketplace/goonetwo:curatecontent` capability. By default, the Manager archetype has this capability.
6. Go to **Site administration > Content Marketplace > Manage content marketplaces**. Disable the core Go1 plugin.

## Data migration

Each Go1 activity belongs to one content marketplace plugin. The core plugin and Go1 v2 keep this link in three places: the learning object table of the plugin, the course module source table of the content marketplace, and the source of the SCORM package file. The **Migration** tab moves these links from one plugin to the other. It does not change the courses, the SCORM activities or the completions. It does not copy the settings of the plugin.

- **To Go1 v2.** The Migration tab shows the number of items that belong to the core plugin. The site administrator clicks the button to migrate the data. An ad-hoc task does the migration at the next cron run, then it disables the core plugin. If there is no data to migrate, the button disables the core plugin immediately.
- **Back to the core plugin.** When all the data belongs to Go1 v2, the button moves the data back to the core plugin and disables Go1 v2. The site administrator must confirm this two times. The migration does not enable the core plugin. After the migration, disable Go1 v.2, then set up and enable the core plugin in **Manage content marketplaces**.
- **Status.** The tab shows the status of the migration: pending, complete or failed. If the migration fails, the task rolls back all its changes.
- **Restored activities.** If the core plugin is disabled, an activity that is restored from an old core Go1 backup belongs to Go1 v2.

## Go1 requirements

The OAuth client asks Go1 for these scopes:

```
lo.read lo.write enrollment.read portal.read portal.write user.read user.write user.login webhook.read webhook.write
```

If Go1 does not give all the requested scopes, the plugin shows an error. The error tells the site administrator to speak to Go1 support. After Go1 support adds the missing scope, click "Set up" again. This authorises the integration with all the necessary scopes.

## Uninstallation

If the site will use the core Go1 plugin again, the site administrator must first migrate the data back to the core plugin on the **Migration** tab. Do this before the uninstall, because the uninstall deletes the links between the SCORM activities and Go1 v2, and it removes the Migration tab.

Go to **Site administration > Plugins > Plugins overview** and uninstall Go1 v2. Before core removes the plugin data, the uninstall script does these steps:

- It sets the Go1 webhook of this site to inactive. The Go1 API cannot delete a webhook. To delete it, use the Go1 portal. If Go1 cannot be reached, the uninstall continues.
- It disables the "Add course", "Explore marketplace" and "Add activity" workflows of the plugin.
- It deletes the links between the SCORM activities and the plugin. The courses, the SCORM activities and the completions stay.
- It deletes the queued migration tasks.

## Licence

GNU General Public License v3 or later. Refer to the file headers for the copyright notices.
