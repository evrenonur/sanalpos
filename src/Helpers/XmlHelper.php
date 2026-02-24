<?php

namespace EvrenOnur\SanalPos\Helpers;

/**
 * XML çözümleme yardımcı sınıfı.
 */
class XmlHelper
{
    /**
     * XML string'i düz dictionary'ye çevirir.
     * SOAP yanıtlarından veri çekme işlemleri için kullanılır.
     *
     * @return array<string, string>
     */
    public static function xmlToDictionary(string $xml): array
    {
        if (empty($xml)) {
            return [];
        }

        try {
            // Namespace temizleme
            $xml = preg_replace('/(<\/?)(\w+:)/', '$1', $xml);

            $doc = new \SimpleXMLElement($xml);
            $result = [];
            self::flattenXml($doc, $result);

            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * SimpleXMLElement'i düz array'e çevirir (recursive).
     */
    private static function flattenXml(\SimpleXMLElement $element, array &$result): void
    {
        foreach ($element->children() as $child) {
            $name = $child->getName();

            if ($child->count() > 0) {
                self::flattenXml($child, $result);
            } else {
                $result[$name] = (string) $child;
            }
        }
    }
}
