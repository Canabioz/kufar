<?php

namespace AppBundle\Command;

use AppBundle\Entity\Log;
use AppBundle\Entity\Phone;
use Doctrine\ORM\EntityManager;
use Sunra\PhpSimple\HtmlDomParser;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ParsingKufar extends ContainerAwareCommand
{
    const URL = 'https://www.kufar.by/беларусь';

    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
        parent::__construct();
    }


    protected function configure()
    {
        $this
            ->setName('app:parsing:kufar')
            ->setDescription('...');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
		ini_set('memory_limit','2G');
        $output->writeln('Parsing start... ' .ParsingKufar::URL);
        $doc = HtmlDomParser::str_get_html($this->getSite(ParsingKufar::URL));
        if ($this->checkURL($doc, ParsingKufar::URL) == false) {
			$output->writeln('Connect cite return');
            $this->saveLogInDB("Error");
            exit;
        }
        $categories = $doc->find('#left_categories li ');
        foreach ($categories as $category) {
            if (isset($category->attr['data-submenu-id']) || (trim($category->children[0]->text()) == "Рекорды Куфара")) {
                continue;
            }
            $countPagination = 1;
            $categoryName = $category->text();
            $output->writeln($categoryName);
            sleep(0.5);
            $docSubCategory = HtmlDomParser::str_get_html($this->getSite($category->children[0]->href));
            if ($this->checkURL($docSubCategory, $category->children[0]->href) == false) {
                $output->writeln('Error Category'.$categoryName);
				$this->saveLogInDB("Error1");
                exit;
            }
            $subCategories = $docSubCategory->find(".list_ads__title");
			$categoryURL = $category->children[0]->href;
            $this->getAllProductsSubCategory($subCategories, $docSubCategory, $countPagination, $categoryURL, $output);
        }

        $output->writeln('All');
    }


    /**
     * @param $subCategories
     * @param $docSubCategory
     * @param $countPagination
     * @param $categoryURL
     * @param OutputInterface $output
     */
    public function getAllProductsSubCategory($subCategories, $docSubCategory, $countPagination, $categoryURL,$output)
    {
        foreach ($subCategories as $subCategory) {
            try {
                sleep(0.2);
                $docSubCategory = HtmlDomParser::str_get_html($this->getSite($subCategory->href));
                if ($this->checkURL($docSubCategory, $subCategory->href) == false) {
                    $output->writeln(' Error SubCategory');
					$this->saveLogInDB("Error2");
                    break;
                }
                if ($phoneClass = $docSubCategory->find(".js_adview_phone_link")) {
                    $name = $subCategory->text();
                    $output->write('__' . $name);
                    $phoneId = preg_replace("#[^0-9]+#", "", $phoneClass[0]->href);
                    $phoneJSON = json_decode($this->getPhoneItem($phoneId));
                    if (empty($phoneJSON)) {
                        break;
                    }
                    if ($licences = $docSubCategory->find('.adview_content__licence')) {
                        $ynp = $licences[0]->children[0]->text();
                    } else $ynp = null;
                    $phone = $phoneJSON->phone;
                    $output->write(' | ' . $phone);
                    $seller = $docSubCategory->find('.adview_contact__name')[0]->nodes[0]->text();
                    $output->writeln(' | ' . $seller);
                    $this->savePhoneInDB($data = [
                        'name' => $name,
                        'url' => $subCategory->href,
                        'phone' => $phone,
                        'seller' => $seller,
                        'ynp' => $ynp,
                    ]);
                   
                }
            } catch (\Exception $exception) {
				$output->writeln('Error3' . $exception);
                $this->saveLogInDB("Error3");
            }
        }
        if (!$docSubCategory->find(".alert_type_search")) {
            sleep(0.5);
            $docSubCategory = HtmlDomParser::str_get_html($this->getSite($categoryURL . "?cu=BYR&o=" . ++$countPagination));
            if ($this->checkURL($docSubCategory, $categoryURL . "?cu=BYR&o=" . $countPagination) == false) {
                $output->writeln("ElementConnectNo");
				$this->saveLogInDB("Error4");
                exit;
            }
            $subCategories = $docSubCategory->find(".list_ads__title");
            $output->writeln('Pagination ' . $countPagination);
			$this->em->clear();
            $this->getAllProductsSubCategory($subCategories, $docSubCategory, $countPagination, $categoryURL, $output);
        }

    }

    /**
     * @param $data
     */
    public function saveLogInDB($data)
    {
        $phone = new Log();
        $phone->setName($data);

        $this->em->persist($phone);
        $this->em->flush();

    }


    /**
     * @param $data
     */
    public function savePhoneInDB($data)
    {
        $phone = new Phone();
        $phone->setName($data['name']);
        $phone->setPhone($data['phone']);
        $phone->setUrl($data['url']);
        $phone->setSeller($data['seller']);
        if (isset($data['log'])) {
            $phone->setLog($data['log']);
        }
        if (isset($data['ynp'])) {
            $phone->setUnp($data['ynp']);
        }

        $this->em->persist($phone);
        $this->em->flush();

    }

    /**
     * @param $doc
     * @param $url
     * @return bool
     */
    public function checkURL($doc, $url)
    {
        if (is_bool($doc)) {
            for ($i = 0; $i < 3; ++$i) {
                sleep(0.5);
                $doc = HtmlDomParser::str_get_html($this->getSite($url));
                if (!is_bool($doc)) {
                    break;
                }
                if ($i >= 3) {
                    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!";
                    return false;
                }
            }
            return $doc;
        }

        return true;
    }

    /**
     * @param mixed $url
     * @return mixed
     */
    public function getSite($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    /**
     * @param mixed $data
     * @return mixed
     */
    public function getPhoneItem($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.kufar.by/get_full_phone.json');
        curl_setopt($ch, CURLOPT_HEADER, "Content-Type: application/x-www-form-urlencoded; charset=UTF-8");
        curl_setopt($ch, CURLOPT_HEADER, "Accept: application/json, text/javascript");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'list_id=' . $data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
