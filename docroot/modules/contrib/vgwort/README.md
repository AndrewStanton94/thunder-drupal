## Introduction
Integrates the [VG Wort service](https://tom.vgwort.de/portal/index) with Drupal.

Install this module to add VG Wort's tracking 1x1 pixel to content entity types
of your choice. Once the content is published, it will be added to the queue in
order to be sent VG Wort. The queue will send the text and author information to
VG  Wort 14 days after publishing (the delay is configurable).

**NOTE:** By default the queue is set up to be processed by a daemon or Drush.
No author or content information will be sent to VG Wort without additional
steps.

## Adding existing content entities to the VG Wort queue
The module provides a [Drush command](https://www.drush.org/latest/) to add
existing content entities to the VG Wort queue. Once you've configured the
module, you can execute
```shell
vendor/bin/drush vgwort:queue node --batch-size=100
```
to add all the existing nodes with counter IDs to the queue.

### How to handle missing author information
Commonly, there will be some delay between installing the module on a site and
having all the necessary author information added. In this instance, it is
recommended that you do not process the queue until this information is
complete. By default, the queue is set to be processed by a daemon or drush.
Unless additional actions are taken no information will be sent to VG Wort. Once
all the author information has been entered the queue can be set to be processed
by cron, or you can configure a daemon or drush job to process the queue.

## Excluding an entity from VG Wort
If you've configured entities to have the VG Wort counter ID you can implement
`hook_vgwort_enable_for_entity()` to exclude specific entities from VG Wort.
See [vgwort.api.php](vgwort.api.php) for more information.

## Altering information sent to VG Wort
If you need to alter the information sent to VGWort you can implement
`hook_vgwort_new_message_alter()`. This hook will allow you to alter the URL and
legal flags for an entity. This is useful for decoupled sites. See
[vgwort.api.php](vgwort.api.php) for more information.

## Using an entity reference field to list participants
The module provides the ability to use any entity reference field to determine
VG Wort participant info. For example, if you have an author node type and
you've added a VG Wort Participant info field to it, then you can configure the
module to get participant information from this field. There is no UI for this.
You can add this configuration using Drush.

For example, to add participant information from a field called
"field_author_node" on nodes, execute:
```shell
vendor/bin/drush ev "_vgwort_add_entity_reference_to_participant_map('node', 'field_author_node');"
```
The command will update the `vgwort.settings` configuration which can deployed
to production like any other configuration change.

## GraphQL
The module provides an integration with the GraphQL module. It is recommended
that projects use the rendered 1x1 pixel in order to benefit from not having to
maintain the HTML and also to respect the test_mode setting.

## Test mode
For testing purposes, you may wish to comment out the 1x1 tracking pixel in the
HTML output. You can do this by setting test_mode to TRUE in your settings.php:

```php
$config['vgwort.settings']['test_mode'] = TRUE;
```

If this setting is changed after a site install clear the cache to ensure it is
reflected on any cached pages.

Enabling the test mode will ensure that articles are sent to VG Wort's test
system and not the live system if cron is run. Note, if the article only exists
on the test system, the message will be rejected.
