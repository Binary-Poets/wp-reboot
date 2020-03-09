<?php

/**
 * WP Reboot
 * https://www.binarypoets.net/wp-reboot
 * (c) BinaryPoets, 2017-2019
 */


// include only file
if (!defined('ABSPATH')) {
  die('Do not open this file directly.');
}


/**
 * Reboots the site to the default values without modifying any files.
 */
class WP_Reboot_CLI extends WP_CLI_Command
{

  /**
   * Reboot the site database to default values. No files are modified.
   *
   * ## OPTIONS
   *
   * [--reactivate-theme]
   * : Reactivate currently active theme after reboot.
   *
   * [--reactivate-plugins]
   * : Reactivate all currently active plugins after reboot.
   *
   * [--reactivate-webhooks]
   * : Reactivate WP Webhooks plugin after reboot.
   *
   * [--deactivate-wp-reboot]
   * : Deactivate WP Reboot plugin after reboot. By default it will stay active after reboot.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * ## EXAMPLES
   *
   * $ wp reboot reboot --yes
   * Success: Database has been reboot.
   *
   * @when after_wp_load
   */
  function reboot($args, $assoc_args)
  {
    WP_CLI::confirm('Are you sure you want to reboot the site? There is NO UNDO!', $assoc_args);

    global $wp_reboot;
    $params = array();

    if (!empty($assoc_args['reactivate-theme'])) {
      $params['reactivate_theme'] = true;
    }
    if (!empty($assoc_args['disable-wp-reboot'])) {
      $params['reactivate_wpreboot'] = false;
    } else {
      $params['reactivate_wpreboot'] = true;
    }
    if (!empty($assoc_args['reactivate-plugins'])) {
      $params['reactivate_plugins'] = true;
    }
    if (!empty($assoc_args['reactivate-webhooks'])) {
      $params['reactivate_webhooks'] = true;
    }

    $result = $wp_reboot->do_reinstall($params);
    if (is_wp_error($result)) {
      WP_CLI::error($result->get_error_message);
    } else {
      WP_CLI::success('Database has been reboot.');
    }
  } // reboot


  /**
   * Display WP Reboot version.
   *
   * @when after_wp_load
   */
  function version($args, $assoc_args)
  {
    global $wp_reboot;

    WP_CLI::line('WP Reboot v' . $wp_reboot->version);
  } // version


  /**
   * Delete selected WordPress objects.
   *
   * ## OPTIONS
   *
   * <plugins|themes|transients|uploads|custom-tables|htaccess|theme-options>
   * : WP objects to delete.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * [--empty]
   * : Empty (truncate) custom tables instead of deleting (dropping) them.
   *
   * ## EXAMPLES
   *
   * $ wp reboot delete themes --yes
   * Success: 3 themes have been deleted.
   *
   * $ wp reboot delete custom-tables --truncate --yes
   * Success: 3 custom tables have been emptied.
   *
   * $ wp reboot delete htaccess --yes
   * Success: Htaccess file has been deleted.
   *
   * @when after_wp_load
   */
  function delete($args, $assoc_args)
  {
    global $wp_reboot, $wpdb;

    if (empty($args[0])) {
      WP_CLI::error('Please choose a subcommand: plugins, themes, transients, uploads, htaccess or custom-tables.');
      return;
    } elseif (false == in_array($args[0], array('themes', 'plugins', 'transients', 'uploads', 'htaccess', 'custom-tables', 'theme-options'))) {
      WP_CLI::error('Unknown subcommand. Please choose from: plugins, themes, transients, uploads, htaccess, custom tables or theme-options.');
    } else {
      $subcommand = $args[0];
    }

    switch ($subcommand) {
      case 'themes':
        WP_CLI::confirm('Are you sure you want to delete all themes?', $assoc_args);
        $cnt = $wp_reboot->do_delete_themes(false);
        WP_CLI::success($cnt . ' themes have been deleted.');
        break;
      case 'plugins':
        WP_CLI::confirm('Are you sure you want to delete all plugins?', $assoc_args);
        $cnt = $wp_reboot->do_delete_plugins(true, false);
        WP_CLI::success($cnt . ' plugins have been deleted.');
        break;
      case 'transients':
        WP_CLI::confirm('Are you sure you want to delete all transients?', $assoc_args);
        $cnt = $wp_reboot->do_delete_transients();
        WP_CLI::success($cnt . ' transient database entries have been deleted.');
        break;
      case 'uploads':
        WP_CLI::confirm('Are you sure you want to delete all files & folders in /uploads/ folder?', $assoc_args);
        $cnt = $wp_reboot->do_delete_uploads();
        WP_CLI::success($cnt . ' files & folders have been deleted.');
        break;
      case 'custom-tables':
        if (!empty($assoc_args['empty'])) {
          WP_CLI::confirm('Are you sure you want to empty (truncate) all custom tables (prefix: ' . $wpdb->prefix . ')?', $assoc_args);
          $cnt = $wp_reboot->do_truncate_custom_tables();
          WP_CLI::success($cnt . ' custom tables have been emptied.');
        } else {
          WP_CLI::confirm('Are you sure you want to delete (drop) all custom tables (prefix: ' . $wpdb->prefix . ')?', $assoc_args);
          $cnt = $wp_reboot->do_drop_custom_tables();
          WP_CLI::success($cnt . ' custom tables have been deleted.');
        }
        break;
      case 'htaccess':
        WP_CLI::confirm('Are you sure you want to delete the .htaccess file?', $assoc_args);
        $tmp = $wp_reboot->do_delete_htaccess();
        if (!is_wp_error($tmp)) {
          WP_CLI::success('Htaccess file has been deleted.');
        } else {
          WP_CLI::error('Htaccess file has not been deleted. ' . $tmp->get_error_message());
        }
        break;
      case 'theme-options':
        WP_CLI::confirm('Are you sure you want to reboot all options (mods) for all themes?', $assoc_args);
        $cnt = $wp_reboot->do_reboot_theme_options();
        WP_CLI::success('Options for ' . $cnt . ' themes have been reboot.');
        break;
      default:
        // should never come to this but can't hurt
        WP_CLI::error('Unknown subcommand. Please choose from: plugins, themes, transients, uploads, htaccess, custom-tables or theme-options.');
        return;
    }
  } // delete


  /**
   * List and manipulate DB snapshots.
   *
   * ## OPTIONS
   *
   * <list|create|restore|export|delete>
   * : Action to perform with snapshot.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * [--id=<snapshot-id>]
   * : Specify snapshot ID when doing restore, export and delete.
   *
   * [--name=<snapshot-name>]
   * : When creating a new snapshot specify an optional name.
   *
   * ## EXAMPLES
   *
   * wp reboot snapshots create --yes
   * Success: New snapshot with ID 089bea has been created.
   *
   * $ wp reboot snapshots delete --id=123456
   * Success: Snapshot has been deleted.
   *
   * $ wp reboot snapshots export --id=123456
   * Success: Snapshot has been exported and saved to: https://test.site/wp-content/wp-reboot-snapshots-export/wp-reboot-snapshot-123456.sql.gz
   *
   * @when after_wp_load
   */
  function snapshots($args, $assoc_args)
  {
    global $wp_reboot;

    if (empty($args[0])) {
      WP_CLI::error('Please choose a subcommand: list, create, restore, export or delete.');
      return;
    } elseif (false == in_array($args[0], array('list', 'create', 'restore', 'export', 'delete'))) {
      WP_CLI::error('Unknown subcommand. Please choose from: list, create, restore, export or delete.');
    } else {
      $subcommand = $args[0];
    }

    switch ($subcommand) {
      case 'list':
        if ($snapshots = $wp_reboot->get_snapshots()) {
          $table = array();
          foreach ($snapshots as $ss) {
            $tmp = array();
            $tmp['id'] = $ss['uid'];
            if (!empty($ss['name'])) {
              $tmp['name'] = $ss['name'];
            } else {
              $tmp['name'] = 'n/a';
            }
            $tmp['created'] = date(get_option('date_format'), strtotime($ss['timestamp'])) . ' @ ' . date(get_option('time_format'), strtotime($ss['timestamp']));
            $tmp['info'] = $ss['tbl_core'] . ' standard & ';
            if ($ss['tbl_custom']) {
              $tmp['info'] .= $ss['tbl_custom'] . ' custom table' . ($ss['tbl_custom'] == 1 ? '' : 's');
            } else {
              $tmp['info'] .= 'no custom tables';
            }
            $tmp['info'] .= ' totaling ' . $wp_reboot->format_size($ss['tbl_size']) . ' in ' . number_format($ss['tbl_rows']) . ' rows';

            $table[] = $tmp;
          } // foreach
          WP_CLI\Utils\format_items('table', $table, array('id', 'name', 'created', 'info'));
        } else {
          WP_CLI::line('There are no saved snapshots.');
        }
        break;
      case 'create':
        if (!empty($assoc_args['name'])) {
          $name = trim($assoc_args['name']);
        } else {
          $name = '';
        }

        WP_CLI::confirm('Are you sure you want to create a new snapshot?', $assoc_args);
        $new = $wp_reboot->do_create_snapshot($name);
        if (is_wp_error($new)) {
          WP_CLI::error($new->get_error_message());
        } else {
          WP_CLI::success('New snapshot with ID ' . $new['uid'] . ' has been created.');
        }
        break;
      case 'restore':
        if (empty($assoc_args['id'])) {
          WP_CLI::error('Please specify the snapshot ID with the "--id=123456" param. Use "wp reboot snapshots list" to get a list of all snapshots.');
          break;
        } else {
          WP_CLI::confirm('Are you sure you want to restore the site to the snapshot with ID ' . $assoc_args['id'] . '?', $assoc_args);
          $restore = $wp_reboot->do_restore_snapshot($assoc_args['id']);
          if (is_wp_error($restore)) {
            WP_CLI::error($restore->get_error_message());
          } else {
            WP_CLI::success('Site has been restored to the selected snapshot.');
          }
        }
        break;
      case 'export':
        if (empty($assoc_args['id'])) {
          WP_CLI::error('Please specify the snapshot ID with the "--id=123456" param. Use "wp reboot snapshots list" to get a list of all snapshots.');
          break;
        } else {
          $export = $wp_reboot->do_export_snapshot($assoc_args['id']);
          if (is_wp_error($export)) {
            WP_CLI::error($export->get_error_message());
          } else {
            $url = content_url() . '/' . $wp_reboot->snapshots_folder . '/' . $export;
            WP_CLI::success('Snapshot has been exported and saved to: ' .  $url);
          }
        }
        break;
      case 'delete':
        if (empty($assoc_args['id'])) {
          WP_CLI::error('Please specify the snapshot ID with the "--id=123456" param. Use "wp reboot snapshots list" to get a list of all snapshots.');
          break;
        } else {
          WP_CLI::confirm('Are you sure you want to delete the snapshot with ID ' . $assoc_args['id'] . '?', $assoc_args);
          $del = $wp_reboot->do_delete_snapshot($assoc_args['id']);
          if (is_wp_error($del)) {
            WP_CLI::error($del->get_error_message());
          } else {
            WP_CLI::success('Snapshot has been deleted.');
          }
        }
        break;
      default:
        // it should never come to this but can't hurt
        WP_CLI::error('Unknown subcommand. Please choose from: list, create, restore, export or delete.');
        return;
    }
  } // snapshots


  /**
   * This command is no longer available. Please use "wp reboot snapshots create" instead.
   */
  function backups($args, $assoc_args)
  {
    WP_CLI::error('This command is no longer available. Please use: wp reboot snapshots create');
  } // backups
} // WP_Reboot_CLI

WP_CLI::add_command('reboot', 'WP_Reboot_CLI');
