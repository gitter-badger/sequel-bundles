<?php

/**
 * @package In2pire
 * @subpackage BeautifySql
 * @author Nhat Tran <nhat.tran@inspire.vn>
 */

namespace In2pire\Sql;

class SqlBeautifier
{
    protected static function createPayload($sql)
    {
        $sql = trim($sql);

        if (empty($sql)) {
            return false;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<sqlpp_request>
    <clientid>dpriver-9094-8133-2031</clientid>
    <dbvendor>mysql</dbvendor>
    <outputfmt>SQL</outputfmt>
    <inputsql></inputsql>
    <formatoptions>
        <keywordcs>Uppercase</keywordcs>
        <tablenamecs>Lowercase</tablenamecs>
        <columnnamecs>Lowercase</columnnamecs>
        <functioncs>InitCap</functioncs>
        <datatypecs>Uppercase</datatypecs>
        <variablecs>Unchanged</variablecs>
        <aliascs>Unchanged</aliascs>
        <quotedidentifiercs>Unchanged</quotedidentifiercs>
        <identifiercs>Lowercase</identifiercs>
        <lnbrwithcomma>after</lnbrwithcomma>
        <liststyle>stack</liststyle>
        <salign>sleft</salign>
        <quotechar>"</quotechar>
        <maxlenincm>80</maxlenincm>
    </formatoptions>
</sqlpp_request>';

        $doc = new \DomDocument('1.0', 'utf-8');
        $doc->loadXML($xml);
        $xpath = new \DOMXpath($doc);
        $nodes = $xpath->query('//inputsql');

        if (empty($nodes->length)) {
            return false;
        }

        $inputsql = new \DomText($sql);

        $node = $nodes->item(0);
        $node->appendChild($inputsql);

        return $doc->saveXML();
    }

    protected static function parseResponse($xml)
    {
        if (empty($xml)) {
            return false;
        }

        $doc = new \DomDocument('1.0', 'utf-8');
        $doc->loadXML($xml);
        $xpath = new \DOMXpath($doc);

        $nodes = $xpath->query('//retmessage');
        $message = $nodes->length ? $nodes->item(0)->textContent : '';

        if ($message != 'success') {
            return false;
        }

        $nodes = $xpath->query('//formattedsql');
        $sql = $nodes->length ? trim($nodes->item(0)->textContent) : '';

        return $sql;
    }

    public static function format($sql)
    {
        $payload = static::createPayload($sql);

        if (empty($payload)) {
            return false;
        }

        // Prepare to connect to dpriver.
        $url = 'http://www.dpriver.com/cgi-bin/ppserver';
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $result = curl_exec($ch);

        if (!$result) {
            // Use offline method.
            return \SqlFormatter::format($sql, false);
        }

        $beautifiedSql = static::parseResponse($result);

        return empty($beautifiedSql) ? $sql : $beautifiedSql;
    }
}
