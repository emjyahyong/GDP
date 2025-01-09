<?php

namespace App\Controller;

use App\Entity\Plat2;
use App\Form\Plat2Type;
use App\Repository\Plat2Repository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/plat2')]
final class Plat2Controller extends AbstractController
{
    #[Route(name: 'app_plat2_index', methods: ['GET'])]
    public function index(Plat2Repository $plat2Repository): Response
    {
        return $this->render('plat2/index.html.twig', [
            'plat2s' => $plat2Repository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_plat2_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $plat2 = new Plat2();
        $form = $this->createForm(Plat2Type::class, $plat2);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($plat2);
            $entityManager->flush();

            return $this->redirectToRoute('app_plat2_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('plat2/new.html.twig', [
            'plat2' => $plat2,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_plat2_show', methods: ['GET'])]
    public function show(Plat2 $plat2): Response
    {
        return $this->render('plat2/show.html.twig', [
            'plat2' => $plat2,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plat2_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Plat2 $plat2, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(Plat2Type::class, $plat2);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_plat2_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('plat2/edit.html.twig', [
            'plat2' => $plat2,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_plat2_delete', methods: ['POST'])]
    public function delete(Request $request, Plat2 $plat2, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$plat2->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($plat2);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_plat2_index', [], Response::HTTP_SEE_OTHER);
    }
}
