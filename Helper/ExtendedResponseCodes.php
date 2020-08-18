<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\Worldpay\Helper;

use Magento\Store\Model\Store;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * MinSaleQty value manipulation helper
 */
class ExtendedResponseCodes
{
    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Math\Random
     */
    protected $mathRandom;
    
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Math\Random $mathRandom
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Math\Random $mathRandom,
        SerializerInterface $serializer
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->mathRandom = $mathRandom;
        $this->serializer = $serializer;
    }

    /**
     * Generate a storable representation of a value
     *
     * @param int|float|string|array $value
     * @return string
     */
    protected function serializeValue($value)
    {
        if (is_array($value)) {
            $data = [];
            foreach ($value as $key => $value) {
                if (!array_key_exists($key, $data)) {
                    $data[$key] = $value;
                }
            }

            return $this->serializer->serialize($data);
        } else {
            return '';
        }
    }

    /**
     * Create a value from a storable representation
     *
     * @param int|float|string $value
     * @return array
     */
    protected function unserializeValue($value)
    {
        if (is_string($value) && !empty($value) && $value != 'a:0:{}') {
            return $this->serializer->unserialize($value);
        } else {
            return [];
        }
    }

    /**
     * Check whether value is in form retrieved by _encodeArrayFieldValue()
     *
     * @param string|array $value
     * @return bool
     */
    protected function isEncodedArrayFieldValue($value)
    {
        if (!is_array($value)) {
            return false;
        }
        unset($value['__empty']);
        foreach ($value as $row) {
            if (!is_array($row)
                || !array_key_exists('wpay_code', $row)
                || !array_key_exists('wpay_desc', $row)
                || !array_key_exists('custom_msg', $row)
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Encode value to be used in \Magento\Config\Block\System\Config\Form\Field\FieldArray\ExtendedResponseCodesArray
     *
     * @param array $value
     * @return array
     */
    protected function encodeArrayFieldValue(array $value)
    {
        $result = [];
        foreach ($value as $exceptionkey => $exceptiondetail) {
            $resultId = $this->mathRandom->getUniqueHash('_');
            $result[$resultId] = [
                                'wpay_code' => $exceptionkey,
                                'wpay_desc' => $exceptiondetail['wpay_desc'],
                                'custom_msg' => $exceptiondetail['custom_msg'],
                               ];
        }
        return $result;
    }

    /**
     * Decode value from used in \Magento\Config\Block\System\Config\Form\Field\FieldArray\ExtendedResponseCodesArray
     *
     * @param array $value
     * @return array
     */
    protected function decodeArrayFieldValue(array $value)
    {
        $result = [];
        unset($value['__empty']);
        foreach ($value as $row) {
            if (!is_array($row)
                || !array_key_exists('wpay_code', $row)
                || !array_key_exists('wpay_desc', $row)
                || !array_key_exists('custom_msg', $row)
            ) {
                continue;
            }
            if (!empty($row['wpay_code'])
               && !empty($row['wpay_desc'])
               || !empty($row['custom_msg'])
            ) {
                $payment_type = $row['wpay_code'];
                $rs['wpay_desc'] = $row['wpay_desc'];
                $rs['custom_msg'] = $row['custom_msg'];
                $result[$payment_type] = $rs;
            }
        }
        return $result;
    }

    /**
     * Make value readable by \Magento\Config\Block\System\Config\Form\Field\FieldArray\ExtendedResponseCodesArray
     *
     * @param string|array $value
     * @return array
     */
    public function makeArrayFieldValue($value)
    {
        $value = $this->unserializeValue($value);
        if (!$this->isEncodedArrayFieldValue($value)) {
            $value = $this->encodeArrayFieldValue($value);
        }
        return $value;
    }

    /**
     * Make value ready for store
     *
     * @param string|array $value
     * @return string
     */
    public function makeStorableArrayFieldValue($value)
    {

        if ($this->isEncodedArrayFieldValue($value)) {
            $value = $this->decodeArrayFieldValue($value);
        }
        $value = $this->serializeValue($value);

        return $value;
    }

    /**
     * Retrieve merchant detail value from config
     *
     * @param int $customerGroupId
     * @param null|string|bool|int|Store $store
     * @return float|null
     */
    public function getConfigValue($wpcode, $store = null)
    {
        $value = $this->scopeConfig->getValue(
            'worldpay_exceptions/extended_response_codes/response_codes',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
        $value = $this->unserializeValue($value);
        if ($this->isEncodedArrayFieldValue($value)) {
            $value = $this->decodeArrayFieldValue($value);
        }

        if ($value[$wpcode]) {
            $response = !empty($value[$wpcode]['custom_msg']) ?
                $value[$wpcode]['custom_msg'] : $value[$wpcode]['wpay_desc'];
            return $response;
        }
    }
}
