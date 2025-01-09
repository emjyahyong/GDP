<?php

namespace App\Controller;

use App\Entity\Categories;
use App\Form\CategoriesType;
use App\Repository\CategoriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CategoriesController extends AbstractController
{
    #[Route('/categories', name: 'app_categories')]
    public function index(CategoriesRepository $categoriesRepository): Response
    {
        $categories = $categoriesRepository->findAll();

        return $this->render('categories/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/categories/new', name: 'app_categories_new')]
    public function new(Request $request, CategoriesRepository $categoriesRepository, EntityManagerInterface $entityManager): Response
    {
        $category = new Categories();
        $form = $this->createForm(CategoriesType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // On verifie que la catégorie n'existe pas
            $existingCategory = $categoriesRepository->findOneByName($category->getName());
            if ($existingCategory) {
                $this->addFlash('error', 'Une catégorie avec ce nom existe déjà.');
                return $this->redirectToRoute('app_categories_new');
            }

            $entityManager->persist($category);
            $entityManager->flush();

            return $this->redirectToRoute('app_categories');
        }

        return $this->render('categories/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/categories/{id}/edit', name: 'app_categories_edit')]
    public function edit(Request $request, Categories $category, CategoriesRepository $categoriesRepository, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CategoriesType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // On check si la catégorie existe déjà
            $existingCategory = $categoriesRepository->findOneByName($category->getName());
            if ($existingCategory && $existingCategory->getId() !== $category->getId()) {
                $this->addFlash('error', 'Une catégorie avec ce nom existe déjà.');
                return $this->redirectToRoute('app_categories_edit', ['id' => $category->getId()]);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_categories');
        }

        return $this->render('categories/edit.html.twig', [
            'form' => $form->createView(),
            'category' => $category,
        ]);
    }

    #[Route('/categories/{id}/delete', name: 'app_categories_delete', methods: ['POST'])]
    public function delete(Request $request, Categories $category, CategoriesRepository $categoriesRepository, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
            $existingCategory = $categoriesRepository->findOneByName($category->getName());
            if ($existingCategory && $existingCategory->getId() !== $category->getId()) {
                $entityManager->remove($category);
                $entityManager->flush();
            }
            $this->addFlash('error', 'Cette catégorie nexiste pas');
            return $this->redirectToRoute('app_categories_edit', ['id' => $category->getId()]);
        }

        return $this->redirectToRoute('app_categories');
    }
}
