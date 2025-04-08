<?php
/**
 * Task class
 *
 * @package SeQura/Helper
 */

namespace Sequra\Helper\Model\Task;

use Sequra\Core\Setup\DatabaseHandler;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Task class
 */
class ConfigureDummyTask extends Task
{

    /**
     * Check if dummy merchant configuration is in use
     *
     * @param bool $widgets
     */
    private function isDummyConfigInUse(bool $widgets): bool
    {
        $expected_rows = $widgets ? 2 : 1;
        $table_name = DatabaseHandler::SEQURA_ENTITY_TABLE;
        $query      = "SELECT * FROM $table_name 
        WHERE (`type` = 'ConnectionData' 
        AND `data` LIKE '%\"username\":\"dummy_automated_tests\"%') 
        OR (`type` = 'WidgetSettings' AND `data` LIKE '%\"displayOnProductPage\":true%')";
        $result     = $this->conn->getConnection()->fetchAll($query);
        return is_array($result) && count($result) === $expected_rows;
    }

    /**
     * Set configuration for dummy merchant
     *
     * @param bool $widgets
     */
    private function setDummyConfig(bool $widgets): void
    {
        $encryptor = ObjectManager::getInstance()->get(EncryptorInterface::class);
        $password = $encryptor->encrypt(getenv('SQ_USER_SECRET'));

        $table_name = DatabaseHandler::SEQURA_ENTITY_TABLE;
        $conn = $this->conn->getConnection();

        // Get the max id from the table or set it to 1
        $id = max((int) $conn->fetchOne("SELECT MAX(id) FROM $table_name"), 0);
        $time = time();
        
        $conn->insert(
            $table_name,
            [
                'id'      => ++$id,
                'type'    => 'ConnectionData',
                'index_1' => '1',
                'data'    => '{"class_name":"SeQura\\\\Core\\\\BusinessLogic\\\\DataAccess\\\\ConnectionData\\\\Entities\\\\ConnectionData","id":'. $id .',"storeId":"1","connectionData":{"environment":"sandbox","merchantId":null,"authorizationCredentials":{"username":"dummy_automated_tests","password":"'. $password .'"}}}',
            ]
        );
        $conn->insert(
            $table_name,
            [
                'id'      => ++$id,
                'type'    => 'StatisticalData',
                'index_1' => '1',
                'data'    => '{"class_name":"SeQura\\\\Core\\\\BusinessLogic\\\\DataAccess\\\\StatisticalData\\\\Entities\\\\StatisticalData","id":'. $id .',"storeId":"1","statisticalData":{"sendStatisticalData":true}}',
            ]
        );
        $conn->insert(
            $table_name,
            [
                'id'      => ++$id,
                'type'    => 'SendReport',
                'index_1' => '1',
                'index_2' => $this->timeToString($time),
                'data'    => '{"class_name":"SeQura\\\\Core\\\\BusinessLogic\\\\DataAccess\\\\SendReport\\\\Entities\\\\SendReport","id":'. $id .',"context":"1","sendReportTime":'.$time.',"sendData":{"sendReportTime":'.$time.'}',
            ]
        );
        $conn->insert(
            $table_name,
            [
                'id'      => ++$id,
                'type'    => 'CountryConfiguration',
                'index_1' => '1',
                'data'    => '{"class_name":"SeQura\\\\Core\\\\BusinessLogic\\\\DataAccess\\\\CountryConfiguration\\\\Entities\\\\CountryConfiguration","id":'. $id .',"storeId":"1","countryConfigurations":[{"countryCode":"ES","merchantId":"dummy_automated_tests"},{"countryCode":"FR","merchantId":"dummy_automated_tests_fr"},{"countryCode":"IT","merchantId":"dummy_automated_tests_it"},{"countryCode":"PT","merchantId":"dummy_automated_tests_pt"}]}',
            ]
        );
        $conn->insert(
            $table_name,
            [
                'id'      => ++$id,
                'type'    => 'PaymentMethods',
                'index_1' => '1',
                'index_2' => 'dummy_automated_tests',
                'data'    => '{"class_name":"Sequra\\\\Core\\\\DataAccess\\\\Entities\\\\PaymentMethods","id":'. $id .',"storeId":"1","merchantId":"dummy_automated_tests","paymentMethods":[{"product":"i1","title":"Paga Despu\\u00e9s","longTitle":"Recibe tu compra antes de pagar","startsAt":"2024-08-29 13:25:00","endsAt":"3333-09-01 14:25:00","campaign":"","claim":"Sin coste adicional","description":"Compra ahora, recibe primero y paga despu\\u00e9s. Cuando tu pedido salga de la tienda tendr\\u00e1s 7 d\\u00edas para realizar el pago desde el enlace que recibir\\u00e1s en tu email o mediante transferencia bancaria.","icon":"<?xml version=\\"1.0\\" encoding=\\"UTF-8\\"?>\\r\\n<svg id=\\"Capa_1\\" xmlns=\\"http:\\/\\/www.w3.org\\/2000\\/svg\\" height=\\"40\\" width=\\"92\\" viewBox=\\"0 0 129 56\\">\\r\\n  <defs>\\r\\n    <style>\\r\\n      .cls-1 {\\r\\n        fill: #00c2a3;\\r\\n      }\\r\\n      .cls-2 {\\r\\n        fill: #fff;\\r\\n        fill-rule: evenodd;\\r\\n      }\\r\\n    <\\/style>\\r\\n  <\\/defs>\\r\\n  <rect class=\\"cls-1\\" width=\\"129\\" height=\\"56\\" rx=\\"8.2\\" ry=\\"8.2\\"\\/>\\r\\n  <g>\\r\\n    <path class=\\"cls-2\\" d=\\"M69.3,36.45c-.67-.02-1.36-.13-2.05-.32,1.29-1.55,2.21-3.41,2.65-5.41,.65-2.96,.22-6.06-1.21-8.73-1.43-2.67-3.78-4.76-6.61-5.87-1.52-.6-3.12-.9-4.76-.9-1.4,0-2.78,.22-4.1,.67-2.88,.97-5.33,2.93-6.9,5.53-1.57,2.59-2.16,5.67-1.67,8.65,.49,2.98,2.04,5.7,4.36,7.67,2.23,1.88,5.07,2.95,8.02,3.03,.32,0,.61-.13,.83-.38,.16-.19,.25-.45,.25-.72v-2.12c0-.6-.47-1.07-1.07-1.09-1.93-.07-3.78-.79-5.23-2.01-1.54-1.3-2.57-3.1-2.89-5.07-.32-1.97,.07-4.01,1.1-5.72,1.04-1.72,2.67-3.02,4.58-3.65,.88-.3,1.79-.45,2.73-.45,1.08,0,2.14,.2,3.15,.6,1.89,.74,3.44,2.12,4.37,3.88,.95,1.77,1.23,3.82,.8,5.77-.33,1.52-1.09,2.93-2.2,4.07-.73-.75-1.32-1.63-1.75-2.64-.4-.94-.61-1.93-.65-2.95-.02-.6-.5-1.07-1.09-1.07h-2.13c-.28,0-.55,.1-.73,.26-.24,.21-.38,.51-.38,.84,.04,1.57,.37,3.1,.98,4.56,.65,1.55,1.58,2.94,2.78,4.14,1.2,1.19,2.6,2.12,4.17,2.77,1.47,.61,3.01,.93,4.59,.97h.02c.32,0,.62-.14,.83-.38,.16-.19,.25-.45,.25-.72v-2.1c.02-.29-.08-.56-.28-.77-.2-.21-.48-.34-.77-.35Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M21.14,28.97c-.63-.45-1.3-.79-1.99-1.02-.75-.26-1.52-.5-2.31-.69-.57-.12-1.11-.26-1.6-.42-.47-.14-.91-.32-1.3-.53l-.08-.03c-.15-.07-.27-.15-.39-.23-.1-.07-.2-.16-.26-.22-.05-.05-.08-.1-.08-.12-.02-.06-.02-.11-.02-.16,0-.15,.07-.3,.19-.43,.15-.17,.35-.3,.6-.41,.27-.12,.58-.2,.94-.25,.24-.03,.48-.05,.73-.05,.12,0,.24,0,.4,.02,.58,.02,1.14,.16,1.61,.41,.31,.15,1.01,.73,1.56,1.22,.14,.12,.32,.19,.51,.19,.16,0,.31-.05,.43-.13l2.22-1.52c.17-.12,.29-.3,.33-.5,.04-.21,0-.41-.13-.58-.58-.85-1.4-1.53-2.34-1.98-1.13-.58-2.4-.92-3.76-1.01-.27-.02-.54-.03-.81-.03-.62,0-1.23,.05-1.83,.15-.82,.13-1.62,.38-2.4,.75-.73,.35-1.38,.87-1.89,1.52-.52,.67-.82,1.47-.87,2.3-.09,.83,.07,1.66,.48,2.4,.37,.63,.9,1.17,1.53,1.57,.64,.4,1.34,.73,2.13,.98,.85,.28,1.65,.51,2.43,.7,.43,.11,.87,.23,1.36,.38,.38,.12,.71,.26,1.01,.45l.08,.04c.21,.13,.38,.29,.49,.45,.1,.16,.14,.32,.12,.56,0,.21-.09,.4-.22,.54-.17,.19-.41,.35-.6,.44h-.06l-.06,.02c-.33,.14-.69,.23-1.09,.28-.24,.03-.48,.04-.72,.04-.17,0-.35,0-.54-.02-.78-.02-1.55-.24-2.23-.62-.52-.33-.97-.71-1.34-1.12-.14-.16-.35-.26-.57-.26-.15,0-.31,.04-.45,.14l-2.34,1.62c-.18,.12-.3,.32-.33,.53-.03,.21,.03,.43,.17,.6,.72,.88,1.64,1.59,2.61,2.03l.04,.04,.05,.02c1.34,.56,2.73,.87,4.15,.93,.33,.02,.66,.04,1,.04,.59,0,1.18-.04,1.78-.11,.89-.11,1.75-.36,2.58-.73,.78-.37,1.48-.92,2-1.59,.56-.75,.88-1.65,.92-2.56,.08-.8-.07-1.61-.41-2.34-.32-.64-.8-1.22-1.41-1.67Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M112.44,20.49c-4.91,0-8.9,3.92-8.9,8.75s3.99,8.75,8.9,8.75c2.55,0,4.02-1.01,4.72-1.68v.6c0,.6,.49,1.09,1.09,1.09h2c.6,0,1.09-.49,1.09-1.09v-7.66c0-4.82-3.99-8.75-8.9-8.75Zm0,4.14c2.59,0,4.69,2.07,4.69,4.6s-2.11,4.6-4.69,4.6-4.7-2.06-4.7-4.6,2.11-4.6,4.7-4.6Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M101.34,20.5h0c-1.07,.04-2.11,.26-3.09,.67-1.1,.44-2.08,1.09-2.92,1.91-.83,.82-1.49,1.79-1.94,2.87-.41,.98-.63,2-.65,3.1v7.86c0,.6,.5,1.09,1.11,1.09h2.02c.61,0,1.11-.49,1.11-1.09v-7.89c.02-.52,.14-1.02,.34-1.5,.24-.57,.58-1.08,1.02-1.51,.44-.43,.96-.77,1.54-1.01,.47-.2,.99-.31,1.55-.35,.59-.03,1.06-.51,1.06-1.1v-1.99c-.02-.29-.15-.57-.36-.76-.21-.19-.46-.29-.78-.29Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M88.76,20.34h-2c-.6,0-1.1,.49-1.1,1.09v8.44c-.12,1.07-.55,1.97-1.26,2.67-.4,.4-.87,.72-1.39,.93-.54,.22-1.1,.33-1.66,.33s-1.12-.11-1.66-.33c-.53-.21-1-.53-1.39-.93-.71-.71-1.14-1.61-1.26-2.74v-8.37c0-.6-.49-1.09-1.09-1.09h-2c-.6,0-1.09,.49-1.09,1.09v8.07c0,1.14,.22,2.23,.65,3.25,.42,1.02,1.04,1.95,1.85,2.75,.79,.78,1.71,1.4,2.76,1.84,1.05,.43,2.15,.65,3.26,.65s2.24-.22,3.26-.65c1.02-.42,1.95-1.04,2.76-1.84,1.4-1.4,2.26-3.23,2.48-5.29v-8.78c-.01-.6-.51-1.08-1.11-1.08Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M34.62,20.53c-.38-.05-.77-.08-1.15-.08-1.73,0-3.41,.51-4.85,1.48-1.77,1.19-3.04,2.97-3.58,5.01-.55,2.05-.33,4.23,.61,6.13,.93,1.9,2.52,3.4,4.49,4.22,1.07,.44,2.19,.66,3.34,.66,.96,0,1.9-.16,2.81-.46,1.49-.52,2.82-1.41,3.83-2.59,.21-.24,.3-.56,.24-.88-.06-.32-.26-.6-.56-.75l-1.58-.82c-.16-.09-.35-.13-.54-.13-.31,0-.61,.12-.82,.34-.54,.52-1.16,.91-1.84,1.14-.51,.17-1.04,.25-1.57,.25-.64,0-1.26-.12-1.84-.37-1.08-.45-1.97-1.28-2.49-2.34-.03-.05-.05-.1-.07-.15h12.07c.61,0,1.1-.49,1.1-1.1v-.91c-.01-2.11-.78-4.14-2.17-5.72-1.4-1.6-3.33-2.63-5.43-2.91Zm-3.86,4.58c.81-.54,1.76-.83,2.73-.83,.22,0,.43,.01,.65,.04,1.18,.16,2.26,.74,3.05,1.63,.35,.4,.63,.85,.84,1.35h-9.07c.37-.89,1-1.66,1.81-2.2Z\\"\\/>\\r\\n  <\\/g>\\r\\n<\\/svg>\\r\\n","costDescription":"sin coste adicional","minAmount":null,"maxAmount":null},{"product":"sp1","title":"Divide tu pago en 3","longTitle":"Divide en 3 partes de 0,00 \\u20ac\\/mes \\u00a1Gratis!","startsAt":"2024-11-26 08:25:00","endsAt":"3333-11-29 08:25:00","campaign":"permanente","claim":"\\u00a1Gratis!","description":"Paga tu compra en tres meses sin que te cueste nada extra. Al instante, sin papeleo ni trucos ocultos.","icon":"<?xml version=\\"1.0\\" encoding=\\"UTF-8\\"?>\\r\\n<svg id=\\"Capa_1\\" xmlns=\\"http:\\/\\/www.w3.org\\/2000\\/svg\\" height=\\"40\\" width=\\"92\\" viewBox=\\"0 0 129 56\\">\\r\\n  <defs>\\r\\n    <style>\\r\\n      .cls-1 {\\r\\n        fill: #00c2a3;\\r\\n      }\\r\\n      .cls-2 {\\r\\n        fill: #fff;\\r\\n        fill-rule: evenodd;\\r\\n      }\\r\\n    <\\/style>\\r\\n  <\\/defs>\\r\\n  <rect class=\\"cls-1\\" width=\\"129\\" height=\\"56\\" rx=\\"8.2\\" ry=\\"8.2\\"\\/>\\r\\n  <g>\\r\\n    <path class=\\"cls-2\\" d=\\"M69.3,36.45c-.67-.02-1.36-.13-2.05-.32,1.29-1.55,2.21-3.41,2.65-5.41,.65-2.96,.22-6.06-1.21-8.73-1.43-2.67-3.78-4.76-6.61-5.87-1.52-.6-3.12-.9-4.76-.9-1.4,0-2.78,.22-4.1,.67-2.88,.97-5.33,2.93-6.9,5.53-1.57,2.59-2.16,5.67-1.67,8.65,.49,2.98,2.04,5.7,4.36,7.67,2.23,1.88,5.07,2.95,8.02,3.03,.32,0,.61-.13,.83-.38,.16-.19,.25-.45,.25-.72v-2.12c0-.6-.47-1.07-1.07-1.09-1.93-.07-3.78-.79-5.23-2.01-1.54-1.3-2.57-3.1-2.89-5.07-.32-1.97,.07-4.01,1.1-5.72,1.04-1.72,2.67-3.02,4.58-3.65,.88-.3,1.79-.45,2.73-.45,1.08,0,2.14,.2,3.15,.6,1.89,.74,3.44,2.12,4.37,3.88,.95,1.77,1.23,3.82,.8,5.77-.33,1.52-1.09,2.93-2.2,4.07-.73-.75-1.32-1.63-1.75-2.64-.4-.94-.61-1.93-.65-2.95-.02-.6-.5-1.07-1.09-1.07h-2.13c-.28,0-.55,.1-.73,.26-.24,.21-.38,.51-.38,.84,.04,1.57,.37,3.1,.98,4.56,.65,1.55,1.58,2.94,2.78,4.14,1.2,1.19,2.6,2.12,4.17,2.77,1.47,.61,3.01,.93,4.59,.97h.02c.32,0,.62-.14,.83-.38,.16-.19,.25-.45,.25-.72v-2.1c.02-.29-.08-.56-.28-.77-.2-.21-.48-.34-.77-.35Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M21.14,28.97c-.63-.45-1.3-.79-1.99-1.02-.75-.26-1.52-.5-2.31-.69-.57-.12-1.11-.26-1.6-.42-.47-.14-.91-.32-1.3-.53l-.08-.03c-.15-.07-.27-.15-.39-.23-.1-.07-.2-.16-.26-.22-.05-.05-.08-.1-.08-.12-.02-.06-.02-.11-.02-.16,0-.15,.07-.3,.19-.43,.15-.17,.35-.3,.6-.41,.27-.12,.58-.2,.94-.25,.24-.03,.48-.05,.73-.05,.12,0,.24,0,.4,.02,.58,.02,1.14,.16,1.61,.41,.31,.15,1.01,.73,1.56,1.22,.14,.12,.32,.19,.51,.19,.16,0,.31-.05,.43-.13l2.22-1.52c.17-.12,.29-.3,.33-.5,.04-.21,0-.41-.13-.58-.58-.85-1.4-1.53-2.34-1.98-1.13-.58-2.4-.92-3.76-1.01-.27-.02-.54-.03-.81-.03-.62,0-1.23,.05-1.83,.15-.82,.13-1.62,.38-2.4,.75-.73,.35-1.38,.87-1.89,1.52-.52,.67-.82,1.47-.87,2.3-.09,.83,.07,1.66,.48,2.4,.37,.63,.9,1.17,1.53,1.57,.64,.4,1.34,.73,2.13,.98,.85,.28,1.65,.51,2.43,.7,.43,.11,.87,.23,1.36,.38,.38,.12,.71,.26,1.01,.45l.08,.04c.21,.13,.38,.29,.49,.45,.1,.16,.14,.32,.12,.56,0,.21-.09,.4-.22,.54-.17,.19-.41,.35-.6,.44h-.06l-.06,.02c-.33,.14-.69,.23-1.09,.28-.24,.03-.48,.04-.72,.04-.17,0-.35,0-.54-.02-.78-.02-1.55-.24-2.23-.62-.52-.33-.97-.71-1.34-1.12-.14-.16-.35-.26-.57-.26-.15,0-.31,.04-.45,.14l-2.34,1.62c-.18,.12-.3,.32-.33,.53-.03,.21,.03,.43,.17,.6,.72,.88,1.64,1.59,2.61,2.03l.04,.04,.05,.02c1.34,.56,2.73,.87,4.15,.93,.33,.02,.66,.04,1,.04,.59,0,1.18-.04,1.78-.11,.89-.11,1.75-.36,2.58-.73,.78-.37,1.48-.92,2-1.59,.56-.75,.88-1.65,.92-2.56,.08-.8-.07-1.61-.41-2.34-.32-.64-.8-1.22-1.41-1.67Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M112.44,20.49c-4.91,0-8.9,3.92-8.9,8.75s3.99,8.75,8.9,8.75c2.55,0,4.02-1.01,4.72-1.68v.6c0,.6,.49,1.09,1.09,1.09h2c.6,0,1.09-.49,1.09-1.09v-7.66c0-4.82-3.99-8.75-8.9-8.75Zm0,4.14c2.59,0,4.69,2.07,4.69,4.6s-2.11,4.6-4.69,4.6-4.7-2.06-4.7-4.6,2.11-4.6,4.7-4.6Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M101.34,20.5h0c-1.07,.04-2.11,.26-3.09,.67-1.1,.44-2.08,1.09-2.92,1.91-.83,.82-1.49,1.79-1.94,2.87-.41,.98-.63,2-.65,3.1v7.86c0,.6,.5,1.09,1.11,1.09h2.02c.61,0,1.11-.49,1.11-1.09v-7.89c.02-.52,.14-1.02,.34-1.5,.24-.57,.58-1.08,1.02-1.51,.44-.43,.96-.77,1.54-1.01,.47-.2,.99-.31,1.55-.35,.59-.03,1.06-.51,1.06-1.1v-1.99c-.02-.29-.15-.57-.36-.76-.21-.19-.46-.29-.78-.29Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M88.76,20.34h-2c-.6,0-1.1,.49-1.1,1.09v8.44c-.12,1.07-.55,1.97-1.26,2.67-.4,.4-.87,.72-1.39,.93-.54,.22-1.1,.33-1.66,.33s-1.12-.11-1.66-.33c-.53-.21-1-.53-1.39-.93-.71-.71-1.14-1.61-1.26-2.74v-8.37c0-.6-.49-1.09-1.09-1.09h-2c-.6,0-1.09,.49-1.09,1.09v8.07c0,1.14,.22,2.23,.65,3.25,.42,1.02,1.04,1.95,1.85,2.75,.79,.78,1.71,1.4,2.76,1.84,1.05,.43,2.15,.65,3.26,.65s2.24-.22,3.26-.65c1.02-.42,1.95-1.04,2.76-1.84,1.4-1.4,2.26-3.23,2.48-5.29v-8.78c-.01-.6-.51-1.08-1.11-1.08Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M34.62,20.53c-.38-.05-.77-.08-1.15-.08-1.73,0-3.41,.51-4.85,1.48-1.77,1.19-3.04,2.97-3.58,5.01-.55,2.05-.33,4.23,.61,6.13,.93,1.9,2.52,3.4,4.49,4.22,1.07,.44,2.19,.66,3.34,.66,.96,0,1.9-.16,2.81-.46,1.49-.52,2.82-1.41,3.83-2.59,.21-.24,.3-.56,.24-.88-.06-.32-.26-.6-.56-.75l-1.58-.82c-.16-.09-.35-.13-.54-.13-.31,0-.61,.12-.82,.34-.54,.52-1.16,.91-1.84,1.14-.51,.17-1.04,.25-1.57,.25-.64,0-1.26-.12-1.84-.37-1.08-.45-1.97-1.28-2.49-2.34-.03-.05-.05-.1-.07-.15h12.07c.61,0,1.1-.49,1.1-1.1v-.91c-.01-2.11-.78-4.14-2.17-5.72-1.4-1.6-3.33-2.63-5.43-2.91Zm-3.86,4.58c.81-.54,1.76-.83,2.73-.83,.22,0,.43,.01,.65,.04,1.18,.16,2.26,.74,3.05,1.63,.35,.4,.63,.85,.84,1.35h-9.07c.37-.89,1-1.66,1.81-2.2Z\\"\\/>\\r\\n  <\\/g>\\r\\n<\\/svg>\\r\\n","costDescription":"","minAmount":null,"maxAmount":null},{"product":"pp3","title":"Paga Fraccionado","longTitle":"Paga Fraccionado","startsAt":"2024-08-30 13:25:00","endsAt":"3333-09-29 14:25:00","campaign":"","claim":"en 3, 6, 12, 18 o 24 meses","description":"Fracciona el pago al momento y sin papeleo. Elige entre 3, 6, 12, 18 o 24 meses solo con un peque\\u00f1o coste fijo al mes.","icon":"<?xml version=\\"1.0\\" encoding=\\"utf-8\\"?>\\r\\n<!-- Generator: Adobe Illustrator 27.0.0, SVG Export Plug-In . SVG Version: 6.00 Build 0)  -->\\r\\n<svg version=\\"1.1\\" id=\\"Capa_1\\" xmlns=\\"http:\\/\\/www.w3.org\\/2000\\/svg\\" xmlns:xlink=\\"http:\\/\\/www.w3.org\\/1999\\/xlink\\" x=\\"0px\\" y=\\"0px\\"\\r\\n\\t viewBox=\\"0 0 129 56\\" height=\\"40\\" width=\\"92\\" style=\\"enable-background:new 0 0 129 56;\\" xml:space=\\"preserve\\">\\r\\n<style type=\\"text\\/css\\">\\r\\n\\t.st0{fill-rule:evenodd;clip-rule:evenodd;fill:#FFFFFF;}\\r\\n<\\/style>\\r\\n<path d=\\"M8.2,0h112.6c4.5,0,8.2,3.7,8.2,8.2v39.6c0,4.5-3.7,8.2-8.2,8.2H8.2C3.7,56,0,52.3,0,47.8V8.2C0,3.7,3.7,0,8.2,0z\\"\\/>\\r\\n<g>\\r\\n\\t<g>\\r\\n\\t\\t<path class=\\"st0\\" d=\\"M69.3,36.5c-0.7,0-1.4-0.1-2-0.3c1.3-1.5,2.2-3.4,2.7-5.4c0.7-3,0.2-6.1-1.2-8.7c-1.4-2.7-3.8-4.8-6.6-5.9\\r\\n\\t\\t\\tc-1.5-0.6-3.1-0.9-4.8-0.9c-1.4,0-2.8,0.2-4.1,0.7c-2.9,1-5.3,2.9-6.9,5.5c-1.6,2.6-2.2,5.7-1.7,8.7c0.5,3,2,5.7,4.4,7.7\\r\\n\\t\\t\\tc2.2,1.9,5.1,3,8,3c0.3,0,0.6-0.1,0.8-0.4c0.2-0.2,0.2-0.4,0.2-0.7v-2.1c0-0.6-0.5-1.1-1.1-1.1c-1.9-0.1-3.8-0.8-5.2-2\\r\\n\\t\\t\\tc-1.5-1.3-2.6-3.1-2.9-5.1c-0.3-2,0.1-4,1.1-5.7c1-1.7,2.7-3,4.6-3.7c0.9-0.3,1.8-0.4,2.7-0.4c1.1,0,2.1,0.2,3.1,0.6\\r\\n\\t\\t\\tc1.9,0.7,3.4,2.1,4.4,3.9c1,1.8,1.2,3.8,0.8,5.8c-0.3,1.5-1.1,2.9-2.2,4.1c-0.7-0.7-1.3-1.6-1.8-2.6c-0.4-0.9-0.6-1.9-0.6-2.9\\r\\n\\t\\t\\tc0-0.6-0.5-1.1-1.1-1.1h-2.1c-0.3,0-0.6,0.1-0.7,0.3c-0.2,0.2-0.4,0.5-0.4,0.8c0,1.6,0.4,3.1,1,4.6c0.6,1.5,1.6,2.9,2.8,4.1\\r\\n\\t\\t\\tc1.2,1.2,2.6,2.1,4.2,2.8c1.5,0.6,3,0.9,4.6,1h0c0.3,0,0.6-0.1,0.8-0.4c0.2-0.2,0.2-0.4,0.2-0.7v-2.1c0-0.3-0.1-0.6-0.3-0.8\\r\\n\\t\\t\\tC69.9,36.6,69.6,36.5,69.3,36.5z\\"\\/>\\r\\n\\t<\\/g>\\r\\n\\t<g>\\r\\n\\t\\t<path class=\\"st0\\" d=\\"M21.1,29c-0.6-0.5-1.3-0.8-2-1c-0.7-0.3-1.5-0.5-2.3-0.7c-0.6-0.1-1.1-0.3-1.6-0.4c-0.5-0.1-0.9-0.3-1.3-0.5\\r\\n\\t\\t\\tl-0.1,0c-0.1-0.1-0.3-0.1-0.4-0.2c-0.1-0.1-0.2-0.2-0.3-0.2c0-0.1-0.1-0.1-0.1-0.1c0-0.1,0-0.1,0-0.2c0-0.2,0.1-0.3,0.2-0.4\\r\\n\\t\\t\\tc0.1-0.2,0.3-0.3,0.6-0.4c0.3-0.1,0.6-0.2,0.9-0.3c0.2,0,0.5-0.1,0.7-0.1c0.1,0,0.2,0,0.4,0c0.6,0,1.1,0.2,1.6,0.4\\r\\n\\t\\t\\tc0.3,0.2,1,0.7,1.6,1.2c0.1,0.1,0.3,0.2,0.5,0.2c0.2,0,0.3,0,0.4-0.1l2.2-1.5c0.2-0.1,0.3-0.3,0.3-0.5c0-0.2,0-0.4-0.1-0.6\\r\\n\\t\\t\\tc-0.6-0.8-1.4-1.5-2.3-2c-1.1-0.6-2.4-0.9-3.8-1c-0.3,0-0.5,0-0.8,0c-0.6,0-1.2,0.1-1.8,0.2c-0.8,0.1-1.6,0.4-2.4,0.8\\r\\n\\t\\t\\tc-0.7,0.3-1.4,0.9-1.9,1.5c-0.5,0.7-0.8,1.5-0.9,2.3c-0.1,0.8,0.1,1.7,0.5,2.4c0.4,0.6,0.9,1.2,1.5,1.6c0.6,0.4,1.3,0.7,2.1,1\\r\\n\\t\\t\\tc0.9,0.3,1.6,0.5,2.4,0.7c0.4,0.1,0.9,0.2,1.4,0.4c0.4,0.1,0.7,0.3,1,0.4l0.1,0c0.2,0.1,0.4,0.3,0.5,0.5c0.1,0.2,0.1,0.3,0.1,0.6\\r\\n\\t\\t\\tc0,0.2-0.1,0.4-0.2,0.5c-0.2,0.2-0.4,0.3-0.6,0.4h-0.1l-0.1,0c-0.3,0.1-0.7,0.2-1.1,0.3c-0.2,0-0.5,0-0.7,0c-0.2,0-0.3,0-0.5,0\\r\\n\\t\\t\\tc-0.8,0-1.6-0.2-2.2-0.6c-0.5-0.3-1-0.7-1.3-1.1C11.2,32.1,11,32,10.8,32c-0.2,0-0.3,0-0.4,0.1L8,33.8c-0.2,0.1-0.3,0.3-0.3,0.5\\r\\n\\t\\t\\tc0,0.2,0,0.4,0.2,0.6c0.7,0.9,1.6,1.6,2.6,2l0,0l0.1,0c1.3,0.6,2.7,0.9,4.1,0.9c0.3,0,0.7,0,1,0c0.6,0,1.2,0,1.8-0.1\\r\\n\\t\\t\\tc0.9-0.1,1.8-0.4,2.6-0.7c0.8-0.4,1.5-0.9,2-1.6c0.6-0.8,0.9-1.6,0.9-2.6c0.1-0.8-0.1-1.6-0.4-2.3C22.2,30,21.7,29.4,21.1,29z\\"\\/>\\r\\n\\t<\\/g>\\r\\n\\t<g>\\r\\n\\t\\t<path class=\\"st0\\" d=\\"M112.4,20.5c-4.9,0-8.9,3.9-8.9,8.7s4,8.7,8.9,8.7c2.5,0,4-1,4.7-1.7v0.6c0,0.6,0.5,1.1,1.1,1.1h2\\r\\n\\t\\t\\tc0.6,0,1.1-0.5,1.1-1.1v-7.7C121.3,24.4,117.4,20.5,112.4,20.5z M112.4,24.6c2.6,0,4.7,2.1,4.7,4.6s-2.1,4.6-4.7,4.6\\r\\n\\t\\t\\tc-2.6,0-4.7-2.1-4.7-4.6S109.8,24.6,112.4,24.6z\\"\\/>\\r\\n\\t<\\/g>\\r\\n\\t<g>\\r\\n\\t\\t<path class=\\"st0\\" d=\\"M101.3,20.5C101.3,20.5,101.3,20.5,101.3,20.5c-1.1,0-2.1,0.3-3.1,0.7c-1.1,0.4-2.1,1.1-2.9,1.9\\r\\n\\t\\t\\tc-0.8,0.8-1.5,1.8-1.9,2.9c-0.4,1-0.6,2-0.7,3.1v7.9c0,0.6,0.5,1.1,1.1,1.1h2c0.6,0,1.1-0.5,1.1-1.1V29c0-0.5,0.1-1,0.3-1.5\\r\\n\\t\\t\\tc0.2-0.6,0.6-1.1,1-1.5c0.4-0.4,1-0.8,1.5-1c0.5-0.2,1-0.3,1.5-0.3c0.6,0,1.1-0.5,1.1-1.1v-2c0-0.3-0.2-0.6-0.4-0.8\\r\\n\\t\\t\\tC101.9,20.6,101.7,20.5,101.3,20.5z\\"\\/>\\r\\n\\t<\\/g>\\r\\n\\t<g>\\r\\n\\t\\t<path class=\\"st0\\" d=\\"M88.8,20.3h-2c-0.6,0-1.1,0.5-1.1,1.1l0,8.4c-0.1,1.1-0.6,2-1.3,2.7c-0.4,0.4-0.9,0.7-1.4,0.9\\r\\n\\t\\t\\tc-0.5,0.2-1.1,0.3-1.7,0.3s-1.1-0.1-1.7-0.3c-0.5-0.2-1-0.5-1.4-0.9c-0.7-0.7-1.1-1.6-1.3-2.7v-8.4c0-0.6-0.5-1.1-1.1-1.1h-2\\r\\n\\t\\t\\tc-0.6,0-1.1,0.5-1.1,1.1v8.1c0,1.1,0.2,2.2,0.6,3.2c0.4,1,1,1.9,1.8,2.8c0.8,0.8,1.7,1.4,2.8,1.8c1.1,0.4,2.1,0.6,3.3,0.6\\r\\n\\t\\t\\tc1.1,0,2.2-0.2,3.3-0.6c1-0.4,2-1,2.8-1.8c1.4-1.4,2.3-3.2,2.5-5.3l0-8.8C89.9,20.8,89.4,20.3,88.8,20.3z\\"\\/>\\r\\n\\t<\\/g>\\r\\n\\t<g>\\r\\n\\t\\t<path class=\\"st0\\" d=\\"M34.6,20.5c-0.4-0.1-0.8-0.1-1.2-0.1c-1.7,0-3.4,0.5-4.9,1.5c-1.8,1.2-3,3-3.6,5c-0.5,2.1-0.3,4.2,0.6,6.1\\r\\n\\t\\t\\tc0.9,1.9,2.5,3.4,4.5,4.2c1.1,0.4,2.2,0.7,3.3,0.7c1,0,1.9-0.2,2.8-0.5c1.5-0.5,2.8-1.4,3.8-2.6c0.2-0.2,0.3-0.6,0.2-0.9\\r\\n\\t\\t\\tc-0.1-0.3-0.3-0.6-0.6-0.8l-1.6-0.8c-0.2-0.1-0.4-0.1-0.5-0.1c-0.3,0-0.6,0.1-0.8,0.3c-0.5,0.5-1.2,0.9-1.8,1.1\\r\\n\\t\\t\\tc-0.5,0.2-1,0.3-1.6,0.3c-0.6,0-1.3-0.1-1.8-0.4c-1.1-0.5-2-1.3-2.5-2.3c0,0,0-0.1-0.1-0.2h12.1c0.6,0,1.1-0.5,1.1-1.1v-0.9\\r\\n\\t\\t\\tc0-2.1-0.8-4.1-2.2-5.7C38.7,21.8,36.7,20.8,34.6,20.5z M30.8,25.1c0.8-0.5,1.8-0.8,2.7-0.8c0.2,0,0.4,0,0.6,0\\r\\n\\t\\t\\tc1.2,0.2,2.3,0.7,3,1.6c0.4,0.4,0.6,0.8,0.8,1.4h-9.1C29.3,26.4,30,25.6,30.8,25.1z\\"\\/>\\r\\n\\t<\\/g>\\r\\n<\\/g>\\r\\n<\\/svg>\\r\\n","costDescription":"desde 0,00 \\u20ac\\/cuota","minAmount":null,"maxAmount":15000}]}',
            ]
        );
        $conn->insert(
            $table_name,
            [
                'id'      => ++$id,
                'type'    => 'PaymentMethods',
                'index_1' => '1',
                'index_2' => 'dummy_automated_tests_fr',
                'data'    => '{"class_name":"Sequra\\\\Core\\\\DataAccess\\\\Entities\\\\PaymentMethods","id":'. $id .',"storeId":"1","merchantId":"dummy_automated_tests_fr","paymentMethods":[{"product":"pp3","title":"Payez en plusieurs fois","longTitle":"Payez en plusieurs fois","startsAt":"2018-12-31 23:00:00","endsAt":"2999-12-31 23:00:00","campaign":"","claim":"en 3, 6 o 12 mois","description":"\u00c9chelonnez le paiement imm\u00e9diatement et sans paperasse. Choisissez entre 3, 6 o 12 mois avec uniquement un petit co\u00fbt fixe mensuel.","icon":"<?xml version=\\"1.0\\" encoding=\\"UTF-8\\"?>\\r\\n<svg id=\\"Capa_1\\" xmlns=\\"http:\\/\\/www.w3.org\\/2000\\/svg\\" height=\\"40\\" width=\\"92\\" viewBox=\\"0 0 129 56\\">\\r\\n  <defs>\\r\\n    <style>\\r\\n      .cls-1 {\\r\\n        fill: #00c2a3;\\r\\n      }\\r\\n      .cls-2 {\\r\\n        fill: #fff;\\r\\n        fill-rule: evenodd;\\r\\n      }\\r\\n    <\\/style>\\r\\n  <\\/defs>\\r\\n  <rect class=\\"cls-1\\" width=\\"129\\" height=\\"56\\" rx=\\"8.2\\" ry=\\"8.2\\"\\/>\\r\\n  <g>\\r\\n    <path class=\\"cls-2\\" d=\\"M69.3,36.45c-.67-.02-1.36-.13-2.05-.32,1.29-1.55,2.21-3.41,2.65-5.41,.65-2.96,.22-6.06-1.21-8.73-1.43-2.67-3.78-4.76-6.61-5.87-1.52-.6-3.12-.9-4.76-.9-1.4,0-2.78,.22-4.1,.67-2.88,.97-5.33,2.93-6.9,5.53-1.57,2.59-2.16,5.67-1.67,8.65,.49,2.98,2.04,5.7,4.36,7.67,2.23,1.88,5.07,2.95,8.02,3.03,.32,0,.61-.13,.83-.38,.16-.19,.25-.45,.25-.72v-2.12c0-.6-.47-1.07-1.07-1.09-1.93-.07-3.78-.79-5.23-2.01-1.54-1.3-2.57-3.1-2.89-5.07-.32-1.97,.07-4.01,1.1-5.72,1.04-1.72,2.67-3.02,4.58-3.65,.88-.3,1.79-.45,2.73-.45,1.08,0,2.14,.2,3.15,.6,1.89,.74,3.44,2.12,4.37,3.88,.95,1.77,1.23,3.82,.8,5.77-.33,1.52-1.09,2.93-2.2,4.07-.73-.75-1.32-1.63-1.75-2.64-.4-.94-.61-1.93-.65-2.95-.02-.6-.5-1.07-1.09-1.07h-2.13c-.28,0-.55,.1-.73,.26-.24,.21-.38,.51-.38,.84,.04,1.57,.37,3.1,.98,4.56,.65,1.55,1.58,2.94,2.78,4.14,1.2,1.19,2.6,2.12,4.17,2.77,1.47,.61,3.01,.93,4.59,.97h.02c.32,0,.62-.14,.83-.38,.16-.19,.25-.45,.25-.72v-2.1c.02-.29-.08-.56-.28-.77-.2-.21-.48-.34-.77-.35Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M21.14,28.97c-.63-.45-1.3-.79-1.99-1.02-.75-.26-1.52-.5-2.31-.69-.57-.12-1.11-.26-1.6-.42-.47-.14-.91-.32-1.3-.53l-.08-.03c-.15-.07-.27-.15-.39-.23-.1-.07-.2-.16-.26-.22-.05-.05-.08-.1-.08-.12-.02-.06-.02-.11-.02-.16,0-.15,.07-.3,.19-.43,.15-.17,.35-.3,.6-.41,.27-.12,.58-.2,.94-.25,.24-.03,.48-.05,.73-.05,.12,0,.24,0,.4,.02,.58,.02,1.14,.16,1.61,.41,.31,.15,1.01,.73,1.56,1.22,.14,.12,.32,.19,.51,.19,.16,0,.31-.05,.43-.13l2.22-1.52c.17-.12,.29-.3,.33-.5,.04-.21,0-.41-.13-.58-.58-.85-1.4-1.53-2.34-1.98-1.13-.58-2.4-.92-3.76-1.01-.27-.02-.54-.03-.81-.03-.62,0-1.23,.05-1.83,.15-.82,.13-1.62,.38-2.4,.75-.73,.35-1.38,.87-1.89,1.52-.52,.67-.82,1.47-.87,2.3-.09,.83,.07,1.66,.48,2.4,.37,.63,.9,1.17,1.53,1.57,.64,.4,1.34,.73,2.13,.98,.85,.28,1.65,.51,2.43,.7,.43,.11,.87,.23,1.36,.38,.38,.12,.71,.26,1.01,.45l.08,.04c.21,.13,.38,.29,.49,.45,.1,.16,.14,.32,.12,.56,0,.21-.09,.4-.22,.54-.17,.19-.41,.35-.6,.44h-.06l-.06,.02c-.33,.14-.69,.23-1.09,.28-.24,.03-.48,.04-.72,.04-.17,0-.35,0-.54-.02-.78-.02-1.55-.24-2.23-.62-.52-.33-.97-.71-1.34-1.12-.14-.16-.35-.26-.57-.26-.15,0-.31,.04-.45,.14l-2.34,1.62c-.18,.12-.3,.32-.33,.53-.03,.21,.03,.43,.17,.6,.72,.88,1.64,1.59,2.61,2.03l.04,.04,.05,.02c1.34,.56,2.73,.87,4.15,.93,.33,.02,.66,.04,1,.04,.59,0,1.18-.04,1.78-.11,.89-.11,1.75-.36,2.58-.73,.78-.37,1.48-.92,2-1.59,.56-.75,.88-1.65,.92-2.56,.08-.8-.07-1.61-.41-2.34-.32-.64-.8-1.22-1.41-1.67Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M112.44,20.49c-4.91,0-8.9,3.92-8.9,8.75s3.99,8.75,8.9,8.75c2.55,0,4.02-1.01,4.72-1.68v.6c0,.6,.49,1.09,1.09,1.09h2c.6,0,1.09-.49,1.09-1.09v-7.66c0-4.82-3.99-8.75-8.9-8.75Zm0,4.14c2.59,0,4.69,2.07,4.69,4.6s-2.11,4.6-4.69,4.6-4.7-2.06-4.7-4.6,2.11-4.6,4.7-4.6Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M101.34,20.5h0c-1.07,.04-2.11,.26-3.09,.67-1.1,.44-2.08,1.09-2.92,1.91-.83,.82-1.49,1.79-1.94,2.87-.41,.98-.63,2-.65,3.1v7.86c0,.6,.5,1.09,1.11,1.09h2.02c.61,0,1.11-.49,1.11-1.09v-7.89c.02-.52,.14-1.02,.34-1.5,.24-.57,.58-1.08,1.02-1.51,.44-.43,.96-.77,1.54-1.01,.47-.2,.99-.31,1.55-.35,.59-.03,1.06-.51,1.06-1.1v-1.99c-.02-.29-.15-.57-.36-.76-.21-.19-.46-.29-.78-.29Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M88.76,20.34h-2c-.6,0-1.1,.49-1.1,1.09v8.44c-.12,1.07-.55,1.97-1.26,2.67-.4,.4-.87,.72-1.39,.93-.54,.22-1.1,.33-1.66,.33s-1.12-.11-1.66-.33c-.53-.21-1-.53-1.39-.93-.71-.71-1.14-1.61-1.26-2.74v-8.37c0-.6-.49-1.09-1.09-1.09h-2c-.6,0-1.09,.49-1.09,1.09v8.07c0,1.14,.22,2.23,.65,3.25,.42,1.02,1.04,1.95,1.85,2.75,.79,.78,1.71,1.4,2.76,1.84,1.05,.43,2.15,.65,3.26,.65s2.24-.22,3.26-.65c1.02-.42,1.95-1.04,2.76-1.84,1.4-1.4,2.26-3.23,2.48-5.29v-8.78c-.01-.6-.51-1.08-1.11-1.08Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M34.62,20.53c-.38-.05-.77-.08-1.15-.08-1.73,0-3.41,.51-4.85,1.48-1.77,1.19-3.04,2.97-3.58,5.01-.55,2.05-.33,4.23,.61,6.13,.93,1.9,2.52,3.4,4.49,4.22,1.07,.44,2.19,.66,3.34,.66,.96,0,1.9-.16,2.81-.46,1.49-.52,2.82-1.41,3.83-2.59,.21-.24,.3-.56,.24-.88-.06-.32-.26-.6-.56-.75l-1.58-.82c-.16-.09-.35-.13-.54-.13-.31,0-.61,.12-.82,.34-.54,.52-1.16,.91-1.84,1.14-.51,.17-1.04,.25-1.57,.25-.64,0-1.26-.12-1.84-.37-1.08-.45-1.97-1.28-2.49-2.34-.03-.05-.05-.1-.07-.15h12.07c.61,0,1.1-.49,1.1-1.1v-.91c-.01-2.11-.78-4.14-2.17-5.72-1.4-1.6-3.33-2.63-5.43-2.91Zm-3.86,4.58c.81-.54,1.76-.83,2.73-.83,.22,0,.43,.01,.65,.04,1.18,.16,2.26,.74,3.05,1.63,.35,.4,.63,.85,.84,1.35h-9.07c.37-.89,1-1.66,1.81-2.2Z\\"\\/>\\r\\n  <\\/g>\\r\\n<\\/svg>\\r\\n","costDescription":"\\u00e0 partir de 0,00 \\u20ac\\/\\u00e9ch\\u00e9ance","minAmount":null,"maxAmount":null}]}',
            ]
        );
        $conn->insert(
            $table_name,
            [
                'id'      => ++$id,
                'type'    => 'PaymentMethods',
                'index_1' => '1',
                'index_2' => 'dummy_automated_tests_it',
                'data'    => '{"class_name":"Sequra\\\\Core\\\\DataAccess\\\\Entities\\\\PaymentMethods","id":'. $id .',"storeId":"1","merchantId":"dummy_automated_tests_it","paymentMethods":[{"product":"pp3","title":"Pagamento a rate","longTitle":"Pagamento a rate","startsAt":"2018-12-31 23:00:00","endsAt":"2999-12-31 23:00:00","campaign":"","claim":"in 3, 6 o 12 mesi","description":"Rateizza il pagamento subito e senza burocrazia. Scegli tra 3, 6 o 12 mesi solo con un piccolo costo fisso al mese.","icon":"<?xml version=\\"1.0\\" encoding=\\"UTF-8\\"?>\\r\\n<svg id=\\"Capa_1\\" xmlns=\\"http:\\/\\/www.w3.org\\/2000\\/svg\\" height=\\"40\\" width=\\"92\\" viewBox=\\"0 0 129 56\\">\\r\\n  <defs>\\r\\n    <style>\\r\\n      .cls-1 {\\r\\n        fill: #00c2a3;\\r\\n      }\\r\\n      .cls-2 {\\r\\n        fill: #fff;\\r\\n        fill-rule: evenodd;\\r\\n      }\\r\\n    <\\/style>\\r\\n  <\\/defs>\\r\\n  <rect class=\\"cls-1\\" width=\\"129\\" height=\\"56\\" rx=\\"8.2\\" ry=\\"8.2\\"\\/>\\r\\n  <g>\\r\\n    <path class=\\"cls-2\\" d=\\"M69.3,36.45c-.67-.02-1.36-.13-2.05-.32,1.29-1.55,2.21-3.41,2.65-5.41,.65-2.96,.22-6.06-1.21-8.73-1.43-2.67-3.78-4.76-6.61-5.87-1.52-.6-3.12-.9-4.76-.9-1.4,0-2.78,.22-4.1,.67-2.88,.97-5.33,2.93-6.9,5.53-1.57,2.59-2.16,5.67-1.67,8.65,.49,2.98,2.04,5.7,4.36,7.67,2.23,1.88,5.07,2.95,8.02,3.03,.32,0,.61-.13,.83-.38,.16-.19,.25-.45,.25-.72v-2.12c0-.6-.47-1.07-1.07-1.09-1.93-.07-3.78-.79-5.23-2.01-1.54-1.3-2.57-3.1-2.89-5.07-.32-1.97,.07-4.01,1.1-5.72,1.04-1.72,2.67-3.02,4.58-3.65,.88-.3,1.79-.45,2.73-.45,1.08,0,2.14,.2,3.15,.6,1.89,.74,3.44,2.12,4.37,3.88,.95,1.77,1.23,3.82,.8,5.77-.33,1.52-1.09,2.93-2.2,4.07-.73-.75-1.32-1.63-1.75-2.64-.4-.94-.61-1.93-.65-2.95-.02-.6-.5-1.07-1.09-1.07h-2.13c-.28,0-.55,.1-.73,.26-.24,.21-.38,.51-.38,.84,.04,1.57,.37,3.1,.98,4.56,.65,1.55,1.58,2.94,2.78,4.14,1.2,1.19,2.6,2.12,4.17,2.77,1.47,.61,3.01,.93,4.59,.97h.02c.32,0,.62-.14,.83-.38,.16-.19,.25-.45,.25-.72v-2.1c.02-.29-.08-.56-.28-.77-.2-.21-.48-.34-.77-.35Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M21.14,28.97c-.63-.45-1.3-.79-1.99-1.02-.75-.26-1.52-.5-2.31-.69-.57-.12-1.11-.26-1.6-.42-.47-.14-.91-.32-1.3-.53l-.08-.03c-.15-.07-.27-.15-.39-.23-.1-.07-.2-.16-.26-.22-.05-.05-.08-.1-.08-.12-.02-.06-.02-.11-.02-.16,0-.15,.07-.3,.19-.43,.15-.17,.35-.3,.6-.41,.27-.12,.58-.2,.94-.25,.24-.03,.48-.05,.73-.05,.12,0,.24,0,.4,.02,.58,.02,1.14,.16,1.61,.41,.31,.15,1.01,.73,1.56,1.22,.14,.12,.32,.19,.51,.19,.16,0,.31-.05,.43-.13l2.22-1.52c.17-.12,.29-.3,.33-.5,.04-.21,0-.41-.13-.58-.58-.85-1.4-1.53-2.34-1.98-1.13-.58-2.4-.92-3.76-1.01-.27-.02-.54-.03-.81-.03-.62,0-1.23,.05-1.83,.15-.82,.13-1.62,.38-2.4,.75-.73,.35-1.38,.87-1.89,1.52-.52,.67-.82,1.47-.87,2.3-.09,.83,.07,1.66,.48,2.4,.37,.63,.9,1.17,1.53,1.57,.64,.4,1.34,.73,2.13,.98,.85,.28,1.65,.51,2.43,.7,.43,.11,.87,.23,1.36,.38,.38,.12,.71,.26,1.01,.45l.08,.04c.21,.13,.38,.29,.49,.45,.1,.16,.14,.32,.12,.56,0,.21-.09,.4-.22,.54-.17,.19-.41,.35-.6,.44h-.06l-.06,.02c-.33,.14-.69,.23-1.09,.28-.24,.03-.48,.04-.72,.04-.17,0-.35,0-.54-.02-.78-.02-1.55-.24-2.23-.62-.52-.33-.97-.71-1.34-1.12-.14-.16-.35-.26-.57-.26-.15,0-.31,.04-.45,.14l-2.34,1.62c-.18,.12-.3,.32-.33,.53-.03,.21,.03,.43,.17,.6,.72,.88,1.64,1.59,2.61,2.03l.04,.04,.05,.02c1.34,.56,2.73,.87,4.15,.93,.33,.02,.66,.04,1,.04,.59,0,1.18-.04,1.78-.11,.89-.11,1.75-.36,2.58-.73,.78-.37,1.48-.92,2-1.59,.56-.75,.88-1.65,.92-2.56,.08-.8-.07-1.61-.41-2.34-.32-.64-.8-1.22-1.41-1.67Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M112.44,20.49c-4.91,0-8.9,3.92-8.9,8.75s3.99,8.75,8.9,8.75c2.55,0,4.02-1.01,4.72-1.68v.6c0,.6,.49,1.09,1.09,1.09h2c.6,0,1.09-.49,1.09-1.09v-7.66c0-4.82-3.99-8.75-8.9-8.75Zm0,4.14c2.59,0,4.69,2.07,4.69,4.6s-2.11,4.6-4.69,4.6-4.7-2.06-4.7-4.6,2.11-4.6,4.7-4.6Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M101.34,20.5h0c-1.07,.04-2.11,.26-3.09,.67-1.1,.44-2.08,1.09-2.92,1.91-.83,.82-1.49,1.79-1.94,2.87-.41,.98-.63,2-.65,3.1v7.86c0,.6,.5,1.09,1.11,1.09h2.02c.61,0,1.11-.49,1.11-1.09v-7.89c.02-.52,.14-1.02,.34-1.5,.24-.57,.58-1.08,1.02-1.51,.44-.43,.96-.77,1.54-1.01,.47-.2,.99-.31,1.55-.35,.59-.03,1.06-.51,1.06-1.1v-1.99c-.02-.29-.15-.57-.36-.76-.21-.19-.46-.29-.78-.29Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M88.76,20.34h-2c-.6,0-1.1,.49-1.1,1.09v8.44c-.12,1.07-.55,1.97-1.26,2.67-.4,.4-.87,.72-1.39,.93-.54,.22-1.1,.33-1.66,.33s-1.12-.11-1.66-.33c-.53-.21-1-.53-1.39-.93-.71-.71-1.14-1.61-1.26-2.74v-8.37c0-.6-.49-1.09-1.09-1.09h-2c-.6,0-1.09,.49-1.09,1.09v8.07c0,1.14,.22,2.23,.65,3.25,.42,1.02,1.04,1.95,1.85,2.75,.79,.78,1.71,1.4,2.76,1.84,1.05,.43,2.15,.65,3.26,.65s2.24-.22,3.26-.65c1.02-.42,1.95-1.04,2.76-1.84,1.4-1.4,2.26-3.23,2.48-5.29v-8.78c-.01-.6-.51-1.08-1.11-1.08Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M34.62,20.53c-.38-.05-.77-.08-1.15-.08-1.73,0-3.41,.51-4.85,1.48-1.77,1.19-3.04,2.97-3.58,5.01-.55,2.05-.33,4.23,.61,6.13,.93,1.9,2.52,3.4,4.49,4.22,1.07,.44,2.19,.66,3.34,.66,.96,0,1.9-.16,2.81-.46,1.49-.52,2.82-1.41,3.83-2.59,.21-.24,.3-.56,.24-.88-.06-.32-.26-.6-.56-.75l-1.58-.82c-.16-.09-.35-.13-.54-.13-.31,0-.61,.12-.82,.34-.54,.52-1.16,.91-1.84,1.14-.51,.17-1.04,.25-1.57,.25-.64,0-1.26-.12-1.84-.37-1.08-.45-1.97-1.28-2.49-2.34-.03-.05-.05-.1-.07-.15h12.07c.61,0,1.1-.49,1.1-1.1v-.91c-.01-2.11-.78-4.14-2.17-5.72-1.4-1.6-3.33-2.63-5.43-2.91Zm-3.86,4.58c.81-.54,1.76-.83,2.73-.83,.22,0,.43,.01,.65,.04,1.18,.16,2.26,.74,3.05,1.63,.35,.4,.63,.85,.84,1.35h-9.07c.37-.89,1-1.66,1.81-2.2Z\\"\\/>\\r\\n  <\\/g>\\r\\n<\\/svg>\\r\\n","costDescription":"da 0,00 \\u20ac\\/rata","minAmount":null,"maxAmount":null}]}',
            ]
        );
        $conn->insert(
            $table_name,
            [
                'id'      => ++$id,
                'type'    => 'PaymentMethods',
                'index_1' => '1',
                'index_2' => 'dummy_automated_tests_pt',
                'data'    => '{"class_name":"Sequra\\\\Core\\\\DataAccess\\\\Entities\\\\PaymentMethods","id":'. $id .',"storeId":"1","merchantId":"dummy_automated_tests_pt","paymentMethods":[{"product":"pp3","title":"Pagamento Fracionado","longTitle":"Pagamento Fracionado","startsAt":"2018-12-31 23:00:00","endsAt":"2999-12-31 23:00:00","campaign":"","claim":"em 3, 6 o 12 meses","description":"Fracione o pagamento no momento e sem papelada. Escolha entre 3, 6 o 12 meses s\\u00f3 com um pequeno custo fixo por m\\u00eas.","icon":"<?xml version=\\"1.0\\" encoding=\\"UTF-8\\"?>\\r\\n<svg id=\\"Capa_1\\" xmlns=\\"http:\\/\\/www.w3.org\\/2000\\/svg\\" height=\\"40\\" width=\\"92\\" viewBox=\\"0 0 129 56\\">\\r\\n  <defs>\\r\\n    <style>\\r\\n      .cls-1 {\\r\\n        fill: #00c2a3;\\r\\n      }\\r\\n      .cls-2 {\\r\\n        fill: #fff;\\r\\n        fill-rule: evenodd;\\r\\n      }\\r\\n    <\\/style>\\r\\n  <\\/defs>\\r\\n  <rect class=\\"cls-1\\" width=\\"129\\" height=\\"56\\" rx=\\"8.2\\" ry=\\"8.2\\"\\/>\\r\\n  <g>\\r\\n    <path class=\\"cls-2\\" d=\\"M69.3,36.45c-.67-.02-1.36-.13-2.05-.32,1.29-1.55,2.21-3.41,2.65-5.41,.65-2.96,.22-6.06-1.21-8.73-1.43-2.67-3.78-4.76-6.61-5.87-1.52-.6-3.12-.9-4.76-.9-1.4,0-2.78,.22-4.1,.67-2.88,.97-5.33,2.93-6.9,5.53-1.57,2.59-2.16,5.67-1.67,8.65,.49,2.98,2.04,5.7,4.36,7.67,2.23,1.88,5.07,2.95,8.02,3.03,.32,0,.61-.13,.83-.38,.16-.19,.25-.45,.25-.72v-2.12c0-.6-.47-1.07-1.07-1.09-1.93-.07-3.78-.79-5.23-2.01-1.54-1.3-2.57-3.1-2.89-5.07-.32-1.97,.07-4.01,1.1-5.72,1.04-1.72,2.67-3.02,4.58-3.65,.88-.3,1.79-.45,2.73-.45,1.08,0,2.14,.2,3.15,.6,1.89,.74,3.44,2.12,4.37,3.88,.95,1.77,1.23,3.82,.8,5.77-.33,1.52-1.09,2.93-2.2,4.07-.73-.75-1.32-1.63-1.75-2.64-.4-.94-.61-1.93-.65-2.95-.02-.6-.5-1.07-1.09-1.07h-2.13c-.28,0-.55,.1-.73,.26-.24,.21-.38,.51-.38,.84,.04,1.57,.37,3.1,.98,4.56,.65,1.55,1.58,2.94,2.78,4.14,1.2,1.19,2.6,2.12,4.17,2.77,1.47,.61,3.01,.93,4.59,.97h.02c.32,0,.62-.14,.83-.38,.16-.19,.25-.45,.25-.72v-2.1c.02-.29-.08-.56-.28-.77-.2-.21-.48-.34-.77-.35Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M21.14,28.97c-.63-.45-1.3-.79-1.99-1.02-.75-.26-1.52-.5-2.31-.69-.57-.12-1.11-.26-1.6-.42-.47-.14-.91-.32-1.3-.53l-.08-.03c-.15-.07-.27-.15-.39-.23-.1-.07-.2-.16-.26-.22-.05-.05-.08-.1-.08-.12-.02-.06-.02-.11-.02-.16,0-.15,.07-.3,.19-.43,.15-.17,.35-.3,.6-.41,.27-.12,.58-.2,.94-.25,.24-.03,.48-.05,.73-.05,.12,0,.24,0,.4,.02,.58,.02,1.14,.16,1.61,.41,.31,.15,1.01,.73,1.56,1.22,.14,.12,.32,.19,.51,.19,.16,0,.31-.05,.43-.13l2.22-1.52c.17-.12,.29-.3,.33-.5,.04-.21,0-.41-.13-.58-.58-.85-1.4-1.53-2.34-1.98-1.13-.58-2.4-.92-3.76-1.01-.27-.02-.54-.03-.81-.03-.62,0-1.23,.05-1.83,.15-.82,.13-1.62,.38-2.4,.75-.73,.35-1.38,.87-1.89,1.52-.52,.67-.82,1.47-.87,2.3-.09,.83,.07,1.66,.48,2.4,.37,.63,.9,1.17,1.53,1.57,.64,.4,1.34,.73,2.13,.98,.85,.28,1.65,.51,2.43,.7,.43,.11,.87,.23,1.36,.38,.38,.12,.71,.26,1.01,.45l.08,.04c.21,.13,.38,.29,.49,.45,.1,.16,.14,.32,.12,.56,0,.21-.09,.4-.22,.54-.17,.19-.41,.35-.6,.44h-.06l-.06,.02c-.33,.14-.69,.23-1.09,.28-.24,.03-.48,.04-.72,.04-.17,0-.35,0-.54-.02-.78-.02-1.55-.24-2.23-.62-.52-.33-.97-.71-1.34-1.12-.14-.16-.35-.26-.57-.26-.15,0-.31,.04-.45,.14l-2.34,1.62c-.18,.12-.3,.32-.33,.53-.03,.21,.03,.43,.17,.6,.72,.88,1.64,1.59,2.61,2.03l.04,.04,.05,.02c1.34,.56,2.73,.87,4.15,.93,.33,.02,.66,.04,1,.04,.59,0,1.18-.04,1.78-.11,.89-.11,1.75-.36,2.58-.73,.78-.37,1.48-.92,2-1.59,.56-.75,.88-1.65,.92-2.56,.08-.8-.07-1.61-.41-2.34-.32-.64-.8-1.22-1.41-1.67Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M112.44,20.49c-4.91,0-8.9,3.92-8.9,8.75s3.99,8.75,8.9,8.75c2.55,0,4.02-1.01,4.72-1.68v.6c0,.6,.49,1.09,1.09,1.09h2c.6,0,1.09-.49,1.09-1.09v-7.66c0-4.82-3.99-8.75-8.9-8.75Zm0,4.14c2.59,0,4.69,2.07,4.69,4.6s-2.11,4.6-4.69,4.6-4.7-2.06-4.7-4.6,2.11-4.6,4.7-4.6Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M101.34,20.5h0c-1.07,.04-2.11,.26-3.09,.67-1.1,.44-2.08,1.09-2.92,1.91-.83,.82-1.49,1.79-1.94,2.87-.41,.98-.63,2-.65,3.1v7.86c0,.6,.5,1.09,1.11,1.09h2.02c.61,0,1.11-.49,1.11-1.09v-7.89c.02-.52,.14-1.02,.34-1.5,.24-.57,.58-1.08,1.02-1.51,.44-.43,.96-.77,1.54-1.01,.47-.2,.99-.31,1.55-.35,.59-.03,1.06-.51,1.06-1.1v-1.99c-.02-.29-.15-.57-.36-.76-.21-.19-.46-.29-.78-.29Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M88.76,20.34h-2c-.6,0-1.1,.49-1.1,1.09v8.44c-.12,1.07-.55,1.97-1.26,2.67-.4,.4-.87,.72-1.39,.93-.54,.22-1.1,.33-1.66,.33s-1.12-.11-1.66-.33c-.53-.21-1-.53-1.39-.93-.71-.71-1.14-1.61-1.26-2.74v-8.37c0-.6-.49-1.09-1.09-1.09h-2c-.6,0-1.09,.49-1.09,1.09v8.07c0,1.14,.22,2.23,.65,3.25,.42,1.02,1.04,1.95,1.85,2.75,.79,.78,1.71,1.4,2.76,1.84,1.05,.43,2.15,.65,3.26,.65s2.24-.22,3.26-.65c1.02-.42,1.95-1.04,2.76-1.84,1.4-1.4,2.26-3.23,2.48-5.29v-8.78c-.01-.6-.51-1.08-1.11-1.08Z\\"\\/>\\r\\n    <path class=\\"cls-2\\" d=\\"M34.62,20.53c-.38-.05-.77-.08-1.15-.08-1.73,0-3.41,.51-4.85,1.48-1.77,1.19-3.04,2.97-3.58,5.01-.55,2.05-.33,4.23,.61,6.13,.93,1.9,2.52,3.4,4.49,4.22,1.07,.44,2.19,.66,3.34,.66,.96,0,1.9-.16,2.81-.46,1.49-.52,2.82-1.41,3.83-2.59,.21-.24,.3-.56,.24-.88-.06-.32-.26-.6-.56-.75l-1.58-.82c-.16-.09-.35-.13-.54-.13-.31,0-.61,.12-.82,.34-.54,.52-1.16,.91-1.84,1.14-.51,.17-1.04,.25-1.57,.25-.64,0-1.26-.12-1.84-.37-1.08-.45-1.97-1.28-2.49-2.34-.03-.05-.05-.1-.07-.15h12.07c.61,0,1.1-.49,1.1-1.1v-.91c-.01-2.11-.78-4.14-2.17-5.72-1.4-1.6-3.33-2.63-5.43-2.91Zm-3.86,4.58c.81-.54,1.76-.83,2.73-.83,.22,0,.43,.01,.65,.04,1.18,.16,2.26,.74,3.05,1.63,.35,.4,.63,.85,.84,1.35h-9.07c.37-.89,1-1.66,1.81-2.2Z\\"\\/>\\r\\n  <\\/g>\\r\\n<\\/svg>\\r\\n","costDescription":"desde 0,00 \\u20ac\\/quota","minAmount":null,"maxAmount":null}]}',
            ]
        );
        $conn->insert(
            $table_name,
            [
                'id'      => ++$id,
                'type'    => 'WidgetSettings',
                'index_1' => '1',
                'data'    => '{"class_name":"SeQura\\\\Core\\\\BusinessLogic\\\\DataAccess\\\\PromotionalWidgets\\\\Entities\\\\WidgetSettings","id":'. $id .',"storeId":"1","widgetSettings":{"enabled":true,"assetsKey":"' . getenv('SQ_ASSETS_KEY') . '","displayOnProductPage":' . ( $widgets ? 'true' : 'false' ) . ',"showInstallmentsInProductListing":false,"showInstallmentsInCartPage":false,"miniWidgetSelector":"","widgetConfiguration":"{\"alignment\":\"center\",\"amount-font-bold\":\"true\",\"amount-font-color\":\"#1C1C1C\",\"amount-font-size\":\"15\",\"background-color\":\"white\",\"border-color\":\"#B1AEBA\",\"border-radius\":\"\",\"class\":\"\",\"font-color\":\"#1C1C1C\",\"link-font-color\":\"#1C1C1C\",\"link-underline\":\"true\",\"no-costs-claim\":\"\",\"size\":\"M\",\"starting-text\":\"only\",\"type\":\"banner\"}","widgetLabels":{"messages":[],"messagesBelowLimit":[]}}}',
            ],
        );
    }

    /**
     * Execute the task
     *
     * @param array $args Arguments for the task
     *
     * @throws \Exception If the task fails
     */
    public function execute(array $args = [])
    {
        $widgets = isset($args['widgets']) ? (bool) $args['widgets'] : true;
        if (! $this->isDummyConfigInUse($widgets)) {
            $this->removeStoreDataFromEntityTable();
            $this->setDummyConfig($widgets);
        }
        return $this->httpSuccessResponse();
    }
}
