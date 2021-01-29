<?php

/**
 * Copyright © Magento. All rights reserved.
 */
namespace MagentoEse\DataInstall\Model;

use FireGento\FastSimpleImport\Model\ImporterFactory as Importer;

class AdvancedPricing
{
    const DEFAULT_IMAGE_PATH = '/media/catalog/product';
    const DEFAULT_WEBSITE = 'All Websites [USD]';
    const DEFAULT_CUSTOMER_GROUP = 'ALL GROUPS';

    /** @var Importer */
    protected $importer;

    /**
     * Products constructor.
     * @param Importer $importer
     */
    public function __construct(
        Importer $importer
    ) {
        $this->importer = $importer;
    }

    /**
     * @param array $rows
     * @param array $header
     * @param string $modulePath
     * @param array $settings
     */
    public function install(array $rows, array $header, string $modulePath, array $settings)
    {
        //need to set default for tier_price_website = settings[site_code],tier_price_customer_group
        //advanced_pricing
        if (!empty($settings['product_image_import_directory'])) {
            $imgDir = $settings['product_image_import_directory'];
        } else {
            $imgDir = $modulePath . self::DEFAULT_IMAGE_PATH;
        }

        if (!empty($settings['product_validation_strategy'])) {
            $productValidationStrategy = $settings['product_validation_strategy'];
        } else {
            $productValidationStrategy =  'validation-skip-errors';
        }

        foreach ($rows as $row) {
            $productsArray[] = array_combine($header, $row);
        }
        //set default group and website if they arent included
        foreach($productsArray as $productRow){
            if(empty($productRow['tier_price_website'])){
                $productRow['tier_price_website'] = self::DEFAULT_WEBSITE;
            }
            if(empty($productRow['tier_price_customer_group'])){
                $productRow['tier_price_customer_group'] = self::DEFAULT_CUSTOMER_GROUP;
            }
            $updatedProductsArray[]=$productRow;
        }

        $this->import($updatedProductsArray,$imgDir,$productValidationStrategy);

        return true;
    }
     private function import($productsArray,$imgDir,$productValidationStrategy){
        $importerModel = $this->importer->create();
        $importerModel->setEntityCode('advanced_pricing');
        $importerModel->setImportImagesFileDir($imgDir);
        $importerModel->setValidationStrategy($productValidationStrategy);
        if($productValidationStrategy == 'validation-stop-on-errors'){
            $importerModel->setAllowedErrorCount(1);
        }else{
            $importerModel->setAllowedErrorCount(100);
        }
        try {
            $importerModel->processImport($productsArray);
        } catch (\Exception $e) {
            print_r($e->getMessage());
        }

        print_r($importerModel->getLogTrace());
        print_r($importerModel->getErrorMessages());

        unset($importerModel);
    }

    /**
     * @param array $products
     * @return array
     */
    private function restrictNewProductsFromOtherStoreViews(array $products,$storeViewCode)
    {
        $newProductArray = [];
        $allStoreCodes = $this->stores->getAllViewCodes();
        foreach ($products as $product) {
            if (!empty($product['store_view_code'])) {
                $storeViewCode = $product['store_view_code'];
            }
            //add restrictive line for each
            foreach ($allStoreCodes as $storeCode) {
                if ($storeCode != $storeViewCode) {
                    $newProductArray[] = ['sku'=>$product['sku'],'store_view_code'=>$storeCode,'visibility'=>'Not Visible Individually'];
                }
            }
        }

        return $newProductArray;
    }

    /**
     * @param $storeViewCodeToRestrict
     * @return array
     */
    private function restrictExistingProducts($storeViewCodeToRestrict)
    {
        $newProductArray = [];
        $search = $this->searchCriteriaBuilder
            //->addFilter(ProductInterface::SKU, '', 'neq')->create();
            ->addFilter(ProductInterface::VISIBILITY, '4', 'eq')->create();
        $productCollection = $this->productRepository->getList($search)->getItems();
        foreach ($productCollection as $product) {
            $newProductArray[] = ['sku'=>$product->getSku(),'store_view_code'=>$storeViewCodeToRestrict,'visibility'=>'Not Visible Individually'];
        }

        return $newProductArray;
    }
}