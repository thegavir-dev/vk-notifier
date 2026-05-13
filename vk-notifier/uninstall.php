<?php
/**
 * Удаление плагина: очистка таблицы логов и удаление опций
 */

// Защита: файл должен вызываться только при удалении плагина через WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Удаляем таблицу логов
$vk_notifier_table = $wpdb->prefix . 'vk_notifier_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $vk_notifier_table ) . '`' );

// Удаляем запланированную очистку логов
wp_clear_scheduled_hook( 'vk_notifier_cleanup_logs' );

// Удаляем опции плагина
delete_option( 'vk_notifier_options' );
delete_option( 'vk_notifier_db_version' );
