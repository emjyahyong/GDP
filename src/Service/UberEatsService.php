<?php

namespace App\Service;

use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UberEatsService
{
    private $client;
    private $params;
    private $accessToken;
    private $isDev;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->isDev = $params->get('kernel.environment') === 'dev';
        
        if (!$this->isDev) {
            $this->client = new Client([
                'base_uri' => 'https://api.uber.com/v1/eats/',
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'verify' => false,
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                ]
            ]);
        }
    }

    private function getAccessToken()
    {
        if ($this->isDev) {
            return 'mock_token';
        }

        if (!$this->accessToken) {
            try {
                $response = $this->client->post('https://login.uber.com/oauth/v2/token', [
                    'form_params' => [
                        'client_id' => $this->params->get('uber_eats.client_id'),
                        'client_secret' => $this->params->get('uber_eats.client_secret'),
                        'grant_type' => 'client_credentials',
                        'scope' => 'eats.store.orders.read eats.store.orders.write eats.store.menu.write'
                    ],
                    'verify' => false
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
        if ($this->isDev) {
            // Simuler une synchronisation réussie en mode développement
            return [
                'status' => 'success',
                'message' => 'Menu synchronisé avec succès (Mode développement)',
                'menu_id' => uniqid('menu_')
            ];
        }

        $token = $this->getAccessToken();
        
        $menuData = [
            'items' => [[
                'title' => $menu->getNom(),
                'description' => $menu->getDescription(),
                'price' => [
                    'amount' => $menu->getPrix() * 100,
                    'currency' => 'EUR'
                ],
                'available' => true
            ]]
        ];

        try {
            $response = $this->client->post('stores/' . $this->params->get('uber_eats.store_id') . '/menu', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'json' => $menuData,
                'verify' => false
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la synchronisation avec Uber Eats: ' . $e->getMessage());
        }
    }

    public function getOrders()
    {
        if ($this->isDev) {
            // Retourner des commandes fictives pour le développement
            return [
                [
                    'id' => 'order_' . uniqid(),
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'items' => [
                        [
                            'quantity' => 2,
                            'title' => 'Menu Test'
                        ]
                    ],
                    'total_price' => '25.90',
                    'status' => 'PENDING'
                ],
                [
                    'id' => 'order_' . uniqid(),
                    'created_at' => (new \DateTime())->modify('-1 hour')->format('Y-m-d H:i:s'),
                    'items' => [
                        [
                            'quantity' => 1,
                            'title' => 'Menu Spécial'
                        ]
                    ],
                    'total_price' => '15.90',
                    'status' => 'COMPLETED'
                ]
            ];
        }

        $token = $this->getAccessToken();

        try {
            $response = $this->client->get('stores/' . $this->params->get('uber_eats.store_id') . '/orders', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'verify' => false
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la récupération des commandes: ' . $e->getMessage());
        }
    }

    public function updateOrderStatus($orderId, $status)
    {
        if ($this->isDev) {
            // Simuler une mise à jour réussie en mode développement
            return [
                'status' => 'success',
                'message' => 'Statut mis à jour avec succès (Mode développement)',
                'order_id' => $orderId,
                'new_status' => $status
            ];
        }

        $token = $this->getAccessToken();

        try {
            $response = $this->client->post('orders/' . $orderId . '/status', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'json' => ['status' => $status],
                'verify' => false
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la mise à jour du statut: ' . $e->getMessage());
        }
    }
}
