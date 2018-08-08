<?php
namespace AligentN98\Dev;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class MediaPullCommand extends AbstractMagentoCommand
{
    protected $_remoteBase = null;
    protected $_output = null;
    protected $_input = null;
    protected $_restPeriod = 1000; // How long between image pulls in seconds

    protected function configure()
    {
        $this
            ->setName('dev:media:pull')
            ->setDescription('Pull media data for a given category or SKU')
            ->addArgument('base-url', InputArgument::REQUIRED, 'The remote base URL')
            ->addOption('category','c',InputOption::VALUE_OPTIONAL,'The category ID')
            ->addOption('sku', 's', InputOption::VALUE_OPTIONAL, 'SKU')
        ;


        $help = <<<HELP
Imports media from a remote base url for a given category or SKU

   $ n98-magerun.phar dev:media:pull http://www.production.com.au/ --categoryid=4 | --sku=SKU123

HELP;
        $this->setHelp($help);
    }

    protected function runCmd($cmd, $output){
        $this->getApplication()->run(new StringInput($cmd), $output);
    }

    protected function remoteUrl($fileName){
        $baseDir = \Mage::getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_WEB);
        return str_replace($baseDir, $this->_remoteBase, $fileName);
    }

    protected function downloadFile($remoteUrl, $localFile){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $remoteUrl);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        file_put_contents($localFile, curl_exec($ch));
        curl_close($ch);
        // This is the horrors, but images need to be executable because...reasons?
        chmod($localFile, 0777);
    }

    /**
     * @param $category \Mage_Catalog_Model_Category
     */
    protected function importCategory($category){
        $products = $category->getProductCollection();
        $total = $products->count();
        $i = 1;
        $this->_output->writeln("Importing category " . $category->getName() . " ($total products)");
        foreach($products as $product){
            $product->load($product->getId());
            $this->importMediaSku($product, $i, $total);
            $i++;
        }
    }


    /**
     * @param $sku \Mage_Catalog_Model_Product
     */
    protected function importMediaSku($sku, $i=null, $total = null){
        if($i && $total) $this->_output->write("$i / $total - ");
        $this->_output->write("Importing " . $sku->getSku() . "...");
        foreach($sku->getMediaGalleryImages() as $image){
            // Only attempt to pull the file if it doesn't already exist
            if(!file_exists($image->getPath())){
                $remoteUrl = $this->remoteUrl($image->getUrl());
                $this->downloadFile($remoteUrl, $image->getPath());
                usleep($this->_restPeriod);
            }
        }
        $this->_output->write("done.\n");
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output, true);
        if (!$this->initMagento()) {
            return;
        }
        $this->_output = $output;

        $this->_remoteBase = $input->getArgument('base-url');
        $categoryId = $input->getOption('category');
        $sku = $input->getOption('sku');

        if($categoryId == null && $sku == null){
            throw new \Exception("Please specify either category ID or SKU");
        }

        if($sku !== null){
            /** @var \Mage_Catalog_Model_Product $skuObj */
            $skuObj = \Mage::getModel('catalog/product');
            $skuId = $skuObj->getIdBySku($sku);
            if(!$skuId){
                throw new \Exception("Invalid SKU $sku");
            }else{
                $skuObj->load($skuId);
                $this->importMediaSku($skuObj);
            }
        }

        if($categoryId !== null){
            /** @var \Mage_Catalog_Model_Category $categoryObj */
            $categoryObj = \Mage::getModel('catalog/category');
            $categoryObj->load($categoryId);
            if(!$categoryObj->getId()){
                throw new \Exception("Invalid category ID $categoryId");
            }
            $this->importCategory($categoryObj);
        }
        $output->writeln("Import images from {$this->_remoteBase} for SKU $sku and category $categoryId");
    }
}