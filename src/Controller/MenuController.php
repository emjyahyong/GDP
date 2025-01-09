<?php

namespace App\Controller;

use App\Entity\Menu;
use App\Form\MenuType;
use App\Repository\MenuRepository;
use App\Service\UberEatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/menu')]
class MenuController extends AbstractController
{
    private $uberEatsService;

    public function __construct(UberEatsService $uberEatsService)
    {
        $this->uberEatsService = $uberEatsService;
    }

    #[Route('/', name: 'app_menu_index', methods: ['GET'])]
    public function index(MenuRepository $menuRepository): Response
    {
        return $this->render('menu/index.html.twig', [
            'menus' => $menuRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_menu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $menu = new Menu();
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Sauvegarder dans la base de données locale
                $entityManager->persist($menu);
                $entityManager->flush();

                // Synchroniser avec Uber Eats
                try {
                    $this->uberEatsService->syncMenu($menu);
                    $this->addFlash('success', 'Le menu a été créé et synchronisé avec Uber Eats avec succès');
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Le menu a été créé mais la synchronisation avec Uber Eats a échoué: ' . $e->getMessage());
                }

                return $this->redirectToRoute('app_menu_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la création du menu');
            }
        }

        return $this->render('menu/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_menu_show', methods: ['GET'])]
    public function show(Menu $menu): Response
    {
        return $this->render('menu/show.html.twig', [
            'menu' => $menu,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_menu_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Menu $menu, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Le menu a été modifié avec succès');
            return $this->redirectToRoute('app_menu_index');
        }

        return $this->render('menu/edit.html.twig', [
            'menu' => $menu,
            'form' => $form,
        ]);
    }

    #[Route('/uber-eats/orders', name: 'app_menu_orders', methods: ['GET'])]
    public function orders(): Response
    {
        try {
            $orders = $this->uberEatsService->getOrders();
            return $this->render('menu/orders.html.twig', [
                'orders' => $orders
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la récupération des commandes Uber Eats: ' . $e->getMessage());
            return $this->redirectToRoute('app_menu_index');
        }
    }

    #[Route('/uber-eats/orders/{orderId}/status', name: 'app_menu_order_status', methods: ['POST'])]
    public function updateOrderStatus(string $orderId, Request $request): Response
    {
        $status = $request->request->get('status');
        
        try {
            $this->uberEatsService->updateOrderStatus($orderId, $status);
            $this->addFlash('success', 'Statut de la commande mis à jour avec succès');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la mise à jour du statut: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_menu_orders');
    }

    #[Route('/{id}', name: 'app_menu_delete', methods: ['POST'])]
    public function delete(Request $request, Menu $menu, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$menu->getId(), $request->request->get('_token'))) {
            $entityManager->remove($menu);
            $entityManager->flush();
            $this->addFlash('success', 'Le menu a été supprimé avec succès');
        }

        return $this->redirectToRoute('app_menu_index');
    }
}
