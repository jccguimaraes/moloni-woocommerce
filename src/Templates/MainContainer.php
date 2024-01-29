<?php

use Moloni\Exceptions\Core\MoloniException;

if (!defined('ABSPATH')) {
    exit;
}

$tab = $_GET['tab'] ?? '';
?>

<section id="moloni" class="moloni">
    <div class="header">
        <img src="<?= MOLONI_IMAGES_URL ?>logo.svg" width='300px' alt="Moloni">
    </div>

    <?php settings_errors(); ?>

    <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
        <a href="<?= admin_url('admin.php?page=moloni') ?>"
           class="nav-tab <?= $tab === '' ? 'nav-tab-active' : '' ?>">
            <?= __('Encomendas') ?>
        </a>

        <a href="<?= admin_url('admin.php?page=moloni&tab=settings') ?>"
           class="nav-tab <?= $tab === 'settings' ? 'nav-tab-active' : '' ?>">
            <?= __('Configurações') ?>
        </a>

        <a href="<?= admin_url('admin.php?page=moloni&tab=logs') ?>"
           class="nav-tab <?= $tab === 'logs' ? 'nav-tab-active' : '' ?>">
            <?= __('Registos') ?>
        </a>

        <a href="<?= admin_url('admin.php?page=moloni&tab=tools') ?>"
           class="nav-tab <?= $tab === 'tools' ? 'nav-tab-active' : '' ?>">
            <?= __('Ferramentas') ?>
        </a>
    </nav>

    <div class="moloni__container">
        <?php

        if (isset($pluginErrorException) && $pluginErrorException instanceof MoloniException) {
            $pluginErrorException->showError();
        }

        switch ($tab) {
            case 'tools':
                include MOLONI_TEMPLATE_DIR . 'Containers/Tools.php';
                break;
            case 'settings':
                include MOLONI_TEMPLATE_DIR . 'Containers/Settings.php';
                break;
            case 'logs':
                include MOLONI_TEMPLATE_DIR . 'Containers/Logs.php';
                break;
            default:
                include MOLONI_TEMPLATE_DIR . 'Containers/PendingOrders.php';
                break;
        }
        ?>
    </div>

</section>
