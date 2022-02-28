<?php

namespace Moloni;

use Moloni\Controllers\Documents;
use Moloni\Scripts\Enqueue;

/**
 * Main constructor
 * Class Plugin
 * @package Moloni
 */
class Plugin
{
    public function __construct()
    {
        $this->actions();
        $this->crons();
    }

    private function actions()
    {
        new Menus\Admin($this);
        new Hooks\ProductUpdate($this);
        new Hooks\ProductView($this);
        new Hooks\OrderView($this);
        new Hooks\OrderPaid($this);
        new Hooks\OrderList($this);
        new Hooks\UpgradeProcess($this);
        new Ajax($this);
    }

    /**
     * Setting up the crons if needed
     */
    private function crons()
    {
        add_filter('cron_schedules', '\Moloni\Crons::addCronInterval');
        add_action('moloniProductsSync', '\Moloni\Crons::productsSync');

        if (!wp_next_scheduled('moloniProductsSync')) {
            wp_schedule_event(time(), 'everyficeminutes', 'moloniProductsSync');
        }
    }

    /**
     * Main function
     * This will run when accessing the page "moloni" and the routing shoud be done here with and $_GET['action']
     */
    public function run()
    {
        try {
            /** If the user is not logged in show the login form */
            if (Start::login()) {
                $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';

                switch ($action) {
                    case 'remInvoice':
                        $this->removeOrder((int)$_GET['id']);
                        break;
                    case 'remInvoiceAll':
                        $this->removeOrdersAll();
                        break;
                    case 'genInvoice':
                        $orderId = (int)$_REQUEST['id'];
                        /** @var Documents $document intended */
                        $document = $this->createDocument($orderId);
                        break;
                    case 'syncStocks':
                        $this->syncStocks();
                        break;
                    case 'remLogs':
                        Log::removeLogs();
                        add_settings_error('moloni', 'moloni-rem-logs', __('A limpeza de logs foi concluída.'), 'updated');
                        break;
                    case 'getInvoice':
                        $document = false;
                        $documentId = (int)$_REQUEST['id'];

                        if ($documentId > 0) {
                            $document = Documents::showDocument($documentId);
                        }

                        if (!$document) {
                            add_settings_error('moloni', 'moloni-document-not-found', __('Documento não encontrado'));
                        }
                        break;
                    case 'downloadDocument':
                        $documentId = (int)$_REQUEST['id'];

                        if ($documentId > 0) {
                            Documents::downloadDocument($documentId);
                            exit;
                        }

                        break;
                }

                if (!wp_doing_ajax()) {
                    include MOLONI_TEMPLATE_DIR . 'MainContainer.php';
                }
            }
        } catch (Error $error) {
            $error->showError();
        }
    }

    /**
     * Create a single document from order
     *
     * @param int $orderId Order Id
     *
     * @return Documents
     *
     * @throws Error
     */
    private function createDocument($orderId)
    {
        $document = new Documents($orderId);
        $document->createDocument();

        if ($document->document_id) {
            $viewUrl = ' <a href="' . admin_url('admin.php?page=moloni&action=getInvoice&id=' . $document->document_id) . '" target="_BLANK">Ver documento</a>';
            add_settings_error('moloni', 'moloni-document-created-success', __('O documento foi gerado!') . $viewUrl, 'updated');
        }

        return $document;
    }

    /**
     * Discard single order from pending list
     *
     * @param int $orderId
     *
     * @return void
     */
    private function removeOrder($orderId)
    {
        if (isset($_GET['confirm']) && sanitize_text_field($_GET['confirm']) === 'true') {
            add_post_meta($orderId, '_moloni_sent', '-1', true);
            add_settings_error('moloni', 'moloni-order-remove-success', __('A encomenda ' . $orderId . ' foi marcada como gerada!'), 'updated');
        } else {
            add_settings_error(
                'moloni',
                'moloni-order-remove',
                __('Confirma que pretende marcar a encomenda ' . $orderId . " como paga? <a href='" . admin_url('admin.php?page=moloni&action=remInvoice&confirm=true&id=' . $orderId) . "'>Sim confirmo!</a>")
            );
        }
    }

    /**
     * Discard all orders from pending list
     *
     * @return void
     */
    private function removeOrdersAll()
    {
        if (isset($_GET['confirm']) && sanitize_text_field($_GET['confirm']) === 'true') {
            $allOrders = Controllers\PendingOrders::getAllAvailable();
            if (!empty($allOrders) && is_array($allOrders)) {
                foreach ($allOrders as $order) {
                    add_post_meta($order['id'], '_moloni_sent', '-1', true);
                }
                add_settings_error('moloni', 'moloni-order-all-remove-success', __('Todas as encomendas foram marcadas como geradas!'), 'updated');
            } else {
                add_settings_error('moloni', 'moloni-order-all-remove-not-found', __('Não foram encontradas encomendas por gerar'));
            }
        } else {
            add_settings_error(
                'moloni', 'moloni-order-remove', __("Confirma que pretende marcar todas as encomendas como já geradas? <a href='" . admin_url('admin.php?page=moloni&action=remInvoiceAll&confirm=true') . "'>Sim confirmo!</a>")
            );
        }
    }

    /**
     * Force stock syncronization
     *
     * @return void
     */
    private function syncStocks()
    {
        $date = isset($_GET['since']) ? sanitize_text_field($_GET['since']) : gmdate('Y-m-d', strtotime('-1 week'));

        $syncStocksResult = (new Controllers\SyncProducts($date))->run();

        if ($syncStocksResult->countUpdated() > 0) {
            add_settings_error('moloni', 'moloni-sync-stocks-updated', __('Foram actualizados ' . $syncStocksResult->countUpdated() . ' artigos.'), 'updated');
        }

        if ($syncStocksResult->countEqual() > 0) {
            add_settings_error('moloni', 'moloni-sync-stocks-updated', __('Existem ' . $syncStocksResult->countEqual() . ' artigos com stock igual.'), 'updated');
        }

        if ($syncStocksResult->countNotFound() > 0) {
            add_settings_error('moloni', 'moloni-sync-stocks-not-found', __('Não foram encontrados no WooCommerce ' . $syncStocksResult->countNotFound() . ' artigos.'));
        }
    }
}
