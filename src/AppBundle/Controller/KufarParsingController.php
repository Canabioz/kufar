<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 17.03.2017
 * Time: 15:04
 */
namespace AppBundle\Controller;

use AppBundle\Entity\Log;
use AppBundle\Entity\Phone;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sunra\PhpSimple\HtmlDomParser;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class KufarParsingController extends Controller
{
    const URL = 'https://www.kufar.by/беларусь';

    /**
     * @Route("/kufar")
     */
    public function indexAction(Request $request)
    {
        $doc = HtmlDomParser::str_get_html($this->getSite(KufarParsingController::URL));
        if ($this->checkURL($doc, KufarParsingController::URL) == false) {
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
            sleep(0.5);
            $docSubCategory = HtmlDomParser::str_get_html($this->getSite($category->children[0]->href));
            if ($this->checkURL($docSubCategory, $category->children[0]->href) == false) {
                $this->saveLogInDB("Error1");
                exit;
            }
            $subCategories = $docSubCategory->find(".list_ads__title");
            $this->getAllProductsSubCategory($subCategories, $docSubCategory, $countPagination, $category);
        }
    }


    /**
     * @param $subCategories
     * @param $docSubCategory
     * @param $countPagination
     * @param $category
     */
    public function getAllProductsSubCategory($subCategories, $docSubCategory, $countPagination, $category)
    {
        foreach ($subCategories as $subCategory) {
            try {
                sleep(0.2);
                $docSubCategory = HtmlDomParser::str_get_html($this->getSite($subCategory->href));
                if ($this->checkURL($docSubCategory, $subCategory->href) == false) {
                    $this->saveLogInDB("Error2");
                    break;
                }
                if ($phoneClass = $docSubCategory->find(".js_adview_phone_link")) {
                    $name = $subCategory->text();
                    $phoneId = preg_replace("#[^0-9]+#", "", $phoneClass[0]->href);
                    $phoneJSON = json_decode($this->getPhoneItem($phoneId));
                    if (empty($phoneJSON)) {
                        break;
                    }
                    if ($licences = $docSubCategory->find('.adview_content__licence')) {
                        $ynp = $licences[0]->children[0]->text();
                    }
                    $phone = $phoneJSON->phone;
                    $seller = $docSubCategory->find('.adview_contact__name')[0]->nodes[0]->text();
                    $this->savePhoneInDB($data = [
                        'name' => $name,
                        'url' => $subCategory->href,
                        'phone' => $phone,
                        'seller' => $seller,
                        'ynp' => $ynp,
                    ]);
                    $ynp = null;
                }
            } catch (\Exception $exception) {
                $this->saveLogInDB("Error3");
            }
        }
        if (!$docSubCategory->find(".alert_type_search")) {
            sleep(0.5);
            $docSubCategory = HtmlDomParser::str_get_html($this->getSite($category->children[0]->href . "?cu=BYR&o=" . ++$countPagination));
            if ($this->checkURL($docSubCategory, $category->children[0]->href . "?cu=BYR&o=" . $countPagination) == false) {
                $this->saveLogInDB("Error4");
                exit;
            }
            $subCategories = $docSubCategory->find(".list_ads__title");
            $this->getAllProductsSubCategory($subCategories, $docSubCategory, $countPagination, $category);
        }

    }

    /**
     * @param $data
     */
    public function saveLogInDB($data)
    {
        $em = $this->getDoctrine()->getManager();
        $phone = new Log();
        $phone->setName($data);

        $em->persist($phone);
        $em->flush();

    }


    /**
     * @param $data
     */
    public function savePhoneInDB($data)
    {
        $em = $this->getDoctrine()->getManager();
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

        $em->persist($phone);
        $em->flush();

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
                    echo "gg";
                    break;
                }
                if ($i >= 3) {
                    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!";
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param string $url
     * @return mixed
     */
    public function getSite(string $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    /**
     * @param string $data
     * @return mixed
     */
    public function getPhoneItem(string $data)
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