<?php

class WooApi
{
    private $store;
    private $baseUrl;

    public function __construct($store)
    {
        $this->store   = $store;
        $this->baseUrl = rtrim($store['url'], '/') . '/wp-json/wc/v3/';
    }

    public function get($endpoint, $params = array())
    {
        $url = $this->baseUrl . ltrim($endpoint, '/');
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERPWD        => $this->store['consumer_key'] . ':' . $this->store['consumer_secret'],
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERAGENT      => 'WooDashboard/' . APP_VERSION,
            CURLOPT_HTTPHEADER     => array('Accept: application/json'),
        ));

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) return array('__error' => 'cURL: ' . $curlError);
        if ($httpCode === 401) return array('__error' => 'Neautorizováno (401) — zkontroluj API klíče');
        if ($httpCode === 404) return array('__error' => 'Endpoint nenalezen (404)');
        if ($httpCode !== 200) return array('__error' => "HTTP $httpCode");

        $data = json_decode($response, true);
        if (!is_array($data)) return array('__error' => 'Neplatná JSON odpověď');

        return $data;
    }

    public function isError($response)
    {
        return isset($response['__error']);
    }

    public function errorMessage($response)
    {
        return isset($response['__error']) ? $response['__error'] : 'Neznámá chyba';
    }

    public function fetchOrders($limit = 50)
    {
        $allOrders = array();
        $perPage   = min($limit, 50);
        $remaining = $limit;
        $page      = 1;

        while ($remaining > 0) {
            $batch = $this->get('orders', array(
                'per_page' => min($perPage, $remaining),
                'page'     => $page,
                'orderby'  => 'date',
                'order'    => 'desc',
                '_fields'  => 'id,number,date_created,status,total,currency,billing',
            ));

            if ($this->isError($batch)) return $page === 1 ? $batch : $allOrders;
            if (empty($batch)) break;

            $allOrders = array_merge($allOrders, $batch);
            $remaining -= count($batch);
            if (count($batch) < $perPage) break;
            $page++;
        }

        return $allOrders;
    }

    public function fetchOrderDetail($orderId)
    {
        return $this->get("orders/$orderId", array(
            '_fields' => 'id,number,line_items,billing,total,currency,status,date_created,customer_note,payment_method_title',
        ));
    }

    public function fetchAllOrdersPaged($callback, $perPage = 100)
    {
        $page   = 1;
        $total  = 0;
        $errors = array();

        while (true) {
            $batch = $this->get('orders', array(
                'per_page' => $perPage,
                'page'     => $page,
                'orderby'  => 'id',
                'order'    => 'asc',
                '_fields'  => 'id,status,total,currency,date_created',
            ));

            if ($this->isError($batch)) { $errors[] = $this->errorMessage($batch); break; }
            if (empty($batch)) break;

            call_user_func($callback, $batch, $page);
            $total += count($batch);
            if (count($batch) < $perPage) break;
            $page++;
            if ($page > 500) break; // bezpečnostní limit
        }

        return array('total' => $total, 'pages' => $page - 1, 'errors' => $errors);
    }

    public function testConnection()
    {
        $result = $this->get('system_status', array('_fields' => 'environment'));
        if ($this->isError($result)) {
            return array('ok' => false, 'message' => $this->errorMessage($result));
        }
        return array('ok' => true, 'message' => 'Připojení OK');
    }
}
