<?php
/**
 * Plugin Name: D2D Push Notifications
 * Description: Send push notifications to mobile devices.
 * Version: 1.0
 * Author: Computers and Controls (CLALLA)
 */
 
 // Exit if accessed directly
 if (!defined('ABSPATH')) {
     exit;
 }
 
 // Create the database table on plugin activation
 register_activation_hook(__FILE__, 'd2d_create_notification_table');
 
 function d2d_create_notification_table() {
     global $wpdb;
     $table_name = $wpdb->prefix . 'd2d_notifications'; // Define table name
     $charset_collate = $wpdb->get_charset_collate();
 
     // SQL to create table
     $sql = "CREATE TABLE $table_name (
         id mediumint(9) NOT NULL AUTO_INCREMENT,
         title tinytext NOT NULL,
         message text NOT NULL,
         device_id varchar(255) DEFAULT '',
         values text NOT NULL,
         timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
         PRIMARY KEY  (id)
     ) $charset_collate;";
 
     // Include the WordPress file for database creation
     require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
     dbDelta($sql);
 }
 
 // Add the admin menu
 add_action('admin_menu', 'd2d_push_notifications_menu');
 
 function d2d_push_notifications_menu() {
     add_menu_page('D2D Push Notifications', 'D2D Notifications', 'manage_options', 'd2d-push-notifications', 'd2d_push_notifications_page');
     add_submenu_page('d2d-push-notifications', 'View Notifications', 'View Notifications', 'manage_options', 'd2d-view-notifications', 'd2d_view_notifications_page');
 }
 
 // Render the settings page
 function d2d_push_notifications_page() {
     ?>
     <div class="wrap">
         <h1>D2D Push Notifications</h1>
         <form method="post" action="">
             <table class="form-table">
                 <tr valign="top">
                     <th scope="row">Title</th>
                     <td><input type="text" name="notification_title" required /></td>
                 </tr>
                 <tr valign="top">
                     <th scope="row">Message</th>
                     <td><textarea name="notification_message" required></textarea></td>
                 </tr>
                 <tr valign="top">
                     <th scope="row">Device Id</th>
                     <td><input type="text" name="device_id" /></td>
                 </tr>
                 <tr valign="top">
                     <th scope="row">Values</th>
                     <td>
                         <input type="text" name="key" placeholder="Key (e.g., category)" />
                         <input type="text" name="value" placeholder="Value (e.g., 10001)" />
                     </td>
                 </tr>
             </table>
             <p class="submit">
                 <input type="submit" name="send_notification" class="button-primary" value="Send Notification" />
             </p>
         </form>
     </div>
     <?php
 
     // Handle form submission
     if (isset($_POST['send_notification'])) {
         d2d_send_push_notification();
     }
 }
 
 function d2d_send_push_notification() {
     global $wpdb;
     $table_name = $wpdb->prefix . 'd2d_notifications'; // Define table name
 
     // Check if required fields are set
     $title = sanitize_text_field($_POST['notification_title']);
     $message = sanitize_textarea_field($_POST['notification_message']);
     $device_id = sanitize_text_field($_POST['device_id']);
     $key = sanitize_text_field($_POST['key']);
     $value = sanitize_text_field($_POST['value']);
 
     // Prepare the FCM URL
     $url = 'https://fcm.googleapis.com/fcm/send';
     $api_key = 'YOUR_FIREBASE_SERVER_KEY'; // Replace with your Firebase Server Key
 
     // Prepare the notification payload
     $notification = [
         'title' => $title,
         'body' => $message,
     ];
 
     // Prepare the data payload
     $data = [];
     if (!empty($key) && !empty($value)) {
         $data[$key] = $value;
     }
 
     // Prepare the FCM message
     $fcm_msg = [
         'to' => empty($device_id) ? '/topics/all' : $device_id,
         'notification' => $notification,
         'data' => $data,
     ];
 
     // Set request headers
     $headers = [
         'Authorization: key=' . $api_key,
         'Content-Type: application/json',
     ];
 
     // Send the request to FCM
     $ch = curl_init();
     curl_setopt($ch, CURLOPT_URL, $url);
     curl_setopt($ch, CURLOPT_POST, true);
     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcm_msg));
 
     $result = curl_exec($ch);
     curl_close($ch);
 
     // Log notification to database
     $values = json_encode(['key' => $key, 'value' => $value]);
     $wpdb->insert($table_name, [
         'title' => $title,
         'message' => $message,
         'device_id' => $device_id,
         'values' => $values,
     ]);
 
     // Display result
     if ($result) {
         echo '<div class="updated"><p>Notification sent successfully!</p></div>';
     } else {
         echo '<div class="error"><p>Failed to send notification.</p></div>';
     }
 }
 
 // Render the notifications view page
 function d2d_view_notifications_page() {
     global $wpdb;
     $table_name = $wpdb->prefix . 'd2d_notifications';
 
     // Fetch notifications from the database
     $notifications = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC");
 
     ?>
     <div class="wrap">
         <h1>Stored Notifications</h1>
         <table class="widefat">
             <thead>
                 <tr>
                     <th>ID</th>
                     <th>Title</th>
                     <th>Message</th>
                     <th>Device ID</th>
                     <th>Values</th>
                     <th>Timestamp</th>
                 </tr>
             </thead>
             <tbody>
                 <?php
                 if ($notifications) {
                     foreach ($notifications as $notification) {
                         echo '<tr>';
                         echo '<td>' . esc_html($notification->id) . '</td>';
                         echo '<td>' . esc_html($notification->title) . '</td>';
                         echo '<td>' . esc_html($notification->message) . '</td>';
                         echo '<td>' . esc_html($notification->device_id) . '</td>';
                         echo '<td>' . esc_html($notification->values) . '</td>';
                         echo '<td>' . esc_html($notification->timestamp) . '</td>';
                         echo '</tr>';
                     }
                 } else {
                     echo '<tr><td colspan="6">No notifications found.</td></tr>';
                 }
                 ?>
             </tbody>
         </table>
     </div>
     <?php
 }
 ?>
 