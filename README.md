# Orkestapay-Magento-Cards

Extensión de pagos con tarjeta de crédito de Orkestapay para Magento2 (v2.4.0)

## Instalación

Ir a la carpeta raíz del proyecto de Magento y seguir los siguiente pasos:

```bash
composer require orkestapay/magento-cards
php bin/magento module:enable Orkestapay_Cards --clear-static-content
php bin/magento setup:upgrade
php bin/magento cache:clean
```

## Actualización

En caso de ya contar con el módulo instalado y sea necesario actualizar, seguir los siguientes pasos:

```bash
composer clear-cache
composer update orkestapay/magento-cards
bin/magento setup:upgrade
php bin/magento cache:clean
```
