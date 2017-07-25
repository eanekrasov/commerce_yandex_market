<?php

namespace Drupal\commerce_yandex_market;

use Drupal\commerce_store\Entity\Store;

class YandexMarketLoader {

  static function _clearVocab($name) {
    $controller = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $entities = $controller->loadMultiple(\Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $name)
      ->execute());
    $controller->delete($entities);
  }

  static function saveFile($whom, $where) {
    try {
      if (($fp = fopen($whom, "r")) && ($sp = fopen($where, "w"))) {
        while ($data = fread($fp, 4096)) {
          fwrite($sp, $data);
        }
        fclose($fp);
        fclose($sp);
        return TRUE;
      }
      else {
        return FALSE;
      }
    } catch (\ErrorException $e) {
      return FALSE;
    }
  }

  function getFirstFileName($where) {
    $za = new \ZipArchive();
    $za->open($where);
    $stat = $za->statIndex(0);
    return "zip://" . $where . "#" . $stat['name'];
  }

  function loadXml(Store $store) {
    $buffer = new XmlOffersBuffer();
    $whom = $store->get("field_xml")->value;
    $dateStr = $store->get("field_xml_updated_at")->value;
    $last_date = $dateStr ? date_create_from_format('Y-m-d H:i:s', $dateStr) : NULL;
    $handler = new XmlParserHandler($buffer, $last_date);
    $this->loadXmlGeneric($handler, "XML", $whom);
    $buffer->flush();
  }

  function loadXmlGeneric(XmlParserHandler $handler, $prefix, $whom) {
    $where = tempnam(sys_get_temp_dir(), $prefix);
    if (!YandexMarketLoader::saveFile($whom, $where)) {
      // file not saved
      return;
    }
    $parser = xml_parser_create();
    $fp = fopen($this->getFirstFileName($where), 'r');
    if (!$fp) {
      return;
    }
    xml_set_character_data_handler($parser, [
      $handler,
      'character_data_handler',
    ]);
    xml_set_element_handler($parser, [
      $handler,
      'start_element_handler',
    ], [
      $handler,
      'end_element_handler',
    ]);
    register_shutdown_function(function () {
      $error = error_get_last();
      if (NULL !== $error) {
        drupal_set_message($error['message']);
      }
    });

    while ($data = fread($fp, 4096)) {
      if (!xml_parse($parser, $data, feof($fp))) {
        drupal_set_message(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($parser)), xml_get_current_line_number($parser)));
        break;
      }
    }
    fclose($fp);
    xml_parser_free($parser);
  }

  function loadYml(Store $store) {
    $buffer = new YmlOffersBuffer();
    $whom = $store->get("field_yml")->value;
    $dateStr = $store->get("field_yml_updated_at")->value;
    $last_date = $dateStr ? date_create_from_format('Y-m-d H:i:s', $dateStr) : NULL;
    $handler = new YmlParserHandler($buffer, $last_date);
    $this->loadXmlGeneric($handler, "YML", $whom);
    $buffer->flush();
    $d = $handler->resetData();
    $date = $handler->date;
    if (!$d->alreadyUpdated && $date && $date instanceof \DateTime) {
      $store->set("field_yml_updated_at", [$date->format("Y-m-d H:i:s")]);
      $store->save();
    }
  }

  function loadAllYml($stores) {
    ini_set("memory_limit", "1G");
    foreach ($stores as $store) {
      $this->loadYml($store);
    }
  }

  function loadAllXml($stores) {
    ini_set("memory_limit", "1G");
    foreach ($stores as $store) {
      $this->loadXml($store);
    }
  }

}