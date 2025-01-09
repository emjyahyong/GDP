<?php

namespace App\Service;

use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UberEatsService
{
    private $client;
    private $params;
    private $accessToken;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->client = new Client([
            'base_uri' => 'https://api.uber.com/v1/eats/',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'verify' => false, // Désactive la vérification SSL (uniquement pour le développement)
            'curl' => [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]
        ]);
    }

    private function getAccessToken()
    {
        if (!$this->accessToken) {
            try {
                $response = $this->client->post('https://login.uber.com/oauth/v2/token', [
                    'form_params' => [
                        'client_id' => $this->params->get('uber_eats.client_id'),
                        'client_secret' => $this->params->get('uber_eats.client_secret'),
                        'grant_type' => 'client_credentials',
                        'scope' => 'eats.store.orders.read eats.store.orders.write eats.store.menu.write'
                    ],
                    'verify' => false // Désactive la vérification SSL pour cette requête
                ]);

                $data = json_decode($response->getBody(), true);
                $this->accessToken = $data['access_token'];
            } catch (\Exception $e) {
                throw new \Exception('Erreur d\'authentification Uber Eats: ' . $e->getMessage());
            }
        }

        return $this->accessToken;
    }

    public function syncMenu($menu)
    {
        $token = $this->getAccessToken();
        
        $menuData = [
            'items' => [[
                'title' => $menu->getNom(),
                'description' => $menu->getDescription(),
                'price' => [
                    'amount' => $menu->getPrix() * 100, // Convert to cents
                    'currency' => 'EUR'
                ],
                'available' => true
            ]]
        ];

        try {
            $response = $this->client->post('stores/' . $this->params->get('uber_eats.store_id') . '/menu', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'json' => $menuData,
                'verify' => false // Désactive la vérification SSL pour cette requête
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la synchronisation avec Uber Eats: ' . $e->getMessage());
        }
    }

    public function getOrders()
    {
        $token = $this->getAccessToken();

        try {
            $response = $this->client->get('stores/' . $this->params->get('uber_eats.store_id') . '/orders', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'verify' => false // Désactive la vérification SSL pour cette requête
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la récupération des commandes: ' . $e->getMessage());
        }
    }

    public function updateOrderStatus($orderId, $status)
    {
        $token = $this->getAccessToken();

        try {
            $response = $this->client->post('orders/' . $orderId . '/status', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'json' => ['status' => $status],
                'verify' => false // Désactive la vérification SSL pour cette requête
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la mise à jour du statut: ' . $e->getMessage());
        }
    }
}
