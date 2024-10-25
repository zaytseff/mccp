<?php

use ApironeApi\Apirone;
use ApironeApi\LoggerWrapper;

trait MCCP_Db {

    /**
     * Return order invoces list sort by time descending
     *
     * @param mixed $order_id 
     * @return void 
     */
    public static function get_order_invoices ($order_id) {
        global $wpdb, $table_prefix;

        $result = $wpdb->get_results(\ApironeApi\Db::getOrderInvoiceQuery($order_id, $table_prefix), ARRAY_A);
        if ($result) {
            foreach ($result as $key => $invoice) {
                $result[$key]['details'] = json_decode($invoice['details']);
                $result[$key]['meta'] = unserialize($invoice['meta']);
            }
            
            return json_decode(json_encode($result));
        }

        if($wpdb->last_error !== '') {
            LoggerWrapper::error($wpdb->last_error);
        }

        return false;    
    }

    /**
     * Return invoice object by invoice ID
     *
     * @param mixed $invoice_id 
     * @return mixed 
     */
    public static function get_invoice ($invoice_id) {
        global $wpdb, $table_prefix;

        $result = $wpdb->get_results(\ApironeApi\Db::getInvoiceQuery($invoice_id, $table_prefix), ARRAY_A);

        if ($result) {
            $invoice = $result[0];
            $invoice['details'] = json_decode($invoice['details']);
            $invoice['meta'] = unserialize($invoice['meta']);
            
            return json_decode(json_encode($invoice));
        }
        if($wpdb->last_error !== '') {
            LoggerWrapper::error($wpdb->last_error);
        }

        return false;    
    }

    /**
     * Invoice record update
     *
     * @param mixed $order 
     * @param mixed $obj_invoice 
     * @return mixed 
     */
    public static function invoice_update($order, $obj_invoice) {
        global $wpdb, $table_prefix;

        $params = array();
        $invoice = self::get_invoice($obj_invoice->invoice);

        if ($invoice) {
            $params['invoice'] = $obj_invoice->invoice;
            $params['status'] = $obj_invoice->status;
            $params['details'] = $obj_invoice;

            $result = $wpdb->query(ApironeApi\Db::updateInvoiceQuery($params, $table_prefix));
        }
        else {
            $_details = Apirone::invoiceInfoPublic($obj_invoice->invoice);
            $params['order_id'] = $order->get_id();
            $params['account'] = $obj_invoice->account;
            $params['invoice'] = $obj_invoice->invoice;
            $params['status'] = $obj_invoice->status;
            $params['details'] = $_details ? $_details : $obj_invoice; // fallback if service return false

            $result = $wpdb->query(ApironeApi\Db::insertInvoiceQuery($params, $table_prefix));
        }

        if ($result) {
            $savedInvoice = self::get_invoice($obj_invoice->invoice);

            WC_MCCP::order_status_update($order, $savedInvoice);

            return $savedInvoice;
        }

        if($wpdb->last_error !== '') {
            LoggerWrapper::error($wpdb->last_error);
        }

        return false;
    }

    /**
     * Order status update
     *
     * @param mixed $order 
     * @param mixed $invoice 
     * @return void 
     */
    public static function order_status_update($order, $invoice) {
        $last_status = WC_MCCP::get_invoice_meta($invoice->invoice, 'order-status');
        $cur_status = $order->get_status();
        $new_status = WC_MCCP::get_order_status_by_invoice($invoice);

        // Set status for new invoice
        if ($last_status === false && $new_status == 'pending') {
            if ($cur_status == 'pending') {
                WC_MCCP::add_invoice_meta($invoice->invoice, 'order-status', $new_status);
            }
            if ($cur_status == 'failed') {
                $order->update_status('wc-' . $new_status);
                WC_MCCP::add_invoice_meta($invoice->invoice, 'order-status', $new_status);
            }
            return;
        }

        if($last_status == $cur_status && $last_status != $new_status) {
            $order->update_status('wc-' . $new_status);
            WC_MCCP::update_invoice_meta($invoice->invoice, 'order-status', $new_status);
        }

        return;    
    }

    /**
     * Returm invoce meta
     * @param mixed $invoice_id 
     * @param bool $name 
     * @return mixed 
     */
    public static function get_invoice_meta($invoice_id, $name = false) {
        global $wpdb, $table_prefix;

        $res = $wpdb->get_row(ApironeApi\Db::getInvoiceMetadataQuery($invoice_id, $table_prefix));
        $meta = unserialize($res->meta);
        $meta = (!$meta) ? new stdClass() : $meta;

        if ($name) {
            return property_exists($meta, $name) ? $meta->$name : false;
        }

        return $meta;
    }

    /**
     * Add meta to invoice
     *
     * @param mixed $invoice_id 
     * @param mixed $name 
     * @param mixed $value 
     * @return mixed 
     */
    public static function add_invoice_meta($invoice_id, $name, $value) {
        global $wpdb, $table_prefix;

        $meta = WC_MCCP::get_invoice_meta($invoice_id);

        $meta->$name = $value;

        $result    = $wpdb->query(ApironeApi\Db::setInvoiceMetadataQuery($invoice_id, $meta, $table_prefix));
        if ($result) {
            return $meta;
        }

        if($wpdb->last_error !== '') {
            LoggerWrapper::error($wpdb->last_error);
        }

        return $result;
    }

    /**
     * Alias for add_invoice_meta()
     *
     * @param mixed $invoice_id 
     * @param mixed $name 
     * @param mixed $value 
     * @return mixed 
     */
    public static function update_invoice_meta($invoice_id, $name, $value) {
        return WC_MCCP::add_invoice_meta($invoice_id, $name, $value);
    }

    /**
     * delete invoice meta by name
     * @param mixed $invoice_id 
     * @param mixed $name 
     * @return mixed 
     */
    public static function delete_invoice_meta($invoice_id, $name) {
        global $wpdb, $table_prefix;

        $meta = WC_MCCP::get_invoice_meta($invoice_id);
        unset($meta->{$name});

        $result    = $wpdb->query(ApironeApi\Db::setInvoiceMetadataQuery($invoice_id, $meta, $table_prefix));
        if ($result) {
            return $meta;
        }

        if($wpdb->last_error !== '') {
            LoggerWrapper::error($wpdb->last_error);
        }

        return $result;
    
    }

    public static function is_table_exists() {
        global $wpdb, $table_prefix;

        $result = $wpdb->query(ApironeApi\Db::isTableExists(DB_NAME, $table_prefix));
        if ($result == 0) {
            $table = $table_prefix . ApironeApi\Db::TABLE_INVOICE;
            LoggerWrapper::error('Table "' . $table . '" doesn\'t exist.' );
        }
        return (bool) $result;
    }
}