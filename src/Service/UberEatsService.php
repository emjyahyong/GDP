<?php

namespace App\Service;

use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UberEatsService
{
    private $client;
    private $params;
    private $accessToken;
    private $isSimulation;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->isSimulation = $params->get('uber_eats.simulation_mode');
        
        if (!$this->isSimulation) {
            $this->client = new Client([
                'base_uri' => 'https://api.uber.com/v1/eats/',
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
        }
    }

    private function getAccessToken()
    {
        if ($this->isSimulation) {
            return 'simulation_token';
        }

        if (!$this->accessToken) {
            try {
                $response = $this->client->post('https://login.uber.com/oauth/v2/token', [
                    'form_params' => [
                        'client_id' => $this->params->get('uber_eats.client_id'),
                        'client_secret' => $this->params->get('uber_eats.client_secret'),
                        'grant_type' => 'client_credentials',
                        'scope' => 'eats.store.orders.read eats.store.orders.write eats.store.menu.write'
                    ]
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
        if ($this->isSimulation) {
            return [
                'status' => 'success',
                'message' => '[SIMULATION] Menu synchronisé avec succès',
                'menu_data' => [
                    'id' => 'sim_menu_' . $menu->getId(),
                    'title' => $menu->getNom(),
                    'price' => $menu->getPrix(),
                    'status' => 'ACTIVE'
                ]
            ];
        }

        $token = $this->getAccessToken();
        
        $menuData = [
            'items' => [[
                'title' => $menu->getNom(),
                'description' => $menu->getDescription(),
                'price' => [
                    'amount' => (int)($menu->getPrix() * 100),
                    'currency' => 'EUR'
                ],
                'external_id' => 'menu_' . $menu->getId(),
                'available' => true,
                'price_info' => [
                    'price' => $menu->getPrix(),
                    'currency' => 'EUR'
                ],
                'tax_info' => [
                    'tax_rate' => 0.10
                ]
            ]]
        ];

        try {
            $response = $this->client->post('stores/' . $this->params->get('uber_eats.store_id') . '/menu', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'json' => $menuData
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la synchronisation avec Uber Eats: ' . $e->getMessage());
        }
    }

    public function getOrders()
    {
        if ($this->isSimulation) {
            // Générer quelques commandes simulées réalistes
            $statuses = ['CREATED', 'ACCEPTED', 'PREPARING', 'READY', 'COMPLETED'];
            $orders = [];
            
            // Commande récente en attente
            $orders[] = [
                'id' => 'sim_order_' . uniqid(),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'status' => 'CREATED',
                'items' => [
                    [
                        'quantity' => 2,
                        'title' => 'Menu Signature',
                        'price' => 15.90
                    ],
                    [
                        'quantity' => 1,
                        'title' => 'Dessert du jour',
                        'price' => 5.90
                    ]
                ],
                'total_price' => '37.70',
                'customer' => [
                    'first_name' => 'Jean',
                    'phone' => '+33 6XX XX XX XX'
                ],
                'delivery' => [
                    'estimated_time' => '20-30 min',
                    'address' => '123 Rue de la Simulation'
                ]
            ];

            // Commande en préparation
            $orders[] = [
                'id' => 'sim_order_' . uniqid(),
                'created_at' => (new \DateTime())->modify('-30 minutes')->format('Y-m-d H:i:s'),
                'status' => 'PREPARING',
                'items' => [
                    [
                        'quantity' => 1,
                        'title' => 'Menu du Jour',
                        'price' => 12.90
                    ]
                ],
                'total_price' => '12.90',
                'customer' => [
                    'first_name' => 'Marie',
                    'phone' => '+33 6XX XX XX XX'
                ],
                'delivery' => [
                    'estimated_time' => '15-25 min',
                    'address' => '456 Avenue de la Demo'
                ]
            ];

            // Commande complétée
            $orders[] = [
                'id' => 'sim_order_' . uniqid(),
                'created_at' => (new \DateTime())->modify('-2 hours')->format('Y-m-d H:i:s'),
                'status' => 'COMPLETED',
                'items' => [
                    [
                        'quantity' => 3,
                        'title' => 'Menu Express',
                        'price' => 9.90
                    ],
                    [
                        'quantity' => 3,
                        'title' => 'Boisson',
                        'price' => 2.50
                    ]
                ],
                'total_price' => '37.20',
                'customer' => [
                    'first_name' => 'Pierre',
                    'phone' => '+33 6XX XX XX XX'
                ],
                'delivery' => [
                    'completed_at' => (new \DateTime())->modify('-1 hour 30 minutes')->format('Y-m-d H:i:s'),
                    'address' => '789 Boulevard Test'
                ]
            ];

            return $orders;
        }

        $token = $this->getAccessToken();

        try {
            $response = $this->client->get('stores/' . $this->params->get('uber_eats.store_id') . '/orders', [
                'headers' => ['Authorization' => 'Bearer ' . $token]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la récupération des commandes: ' . $e->getMessage());
        }
    }

    public function updateOrderStatus($orderId, $status)
    {
        if ($this->isSimulation) {
            return [
                'status' => 'success',
                'message' => '[SIMULATION] Statut de la commande mis à jour',
                'order' => [
                    'id' => $orderId,
                    'status' => $status,
                    'updated_at' => (new \DateTime())->format('Y-m-d H:i:s')
                ]
            ];
        }

        $token = $this->getAccessToken();

        try {
            $response = $this->client->post('orders/' . $orderId . '/status', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'json' => ['status' => $status]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la mise à jour du statut: ' . $e->getMessage());
        }
    }
}
