<?php

namespace App\Controller;

use Facebook\Facebook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\RequestStack;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Exceptions\FacebookResponseException;

class FacebookImageController extends AbstractController
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @Route("/facebook/login", name="facebook_login")
     */
    public function loginWithFacebook(Facebook $fb): Response
    {
        $helper = $fb->getRedirectLoginHelper();
        $permissions = ['email', 'publish_actions'];
        $callbackUrl = $this->generateUrl('facebook_callback', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
        
        $loginUrl = $helper->getLoginUrl($callbackUrl, $permissions);

        return $this->redirect($loginUrl);
    }

    /**
     * @Route("/facebook/callback", name="facebook_callback")
     */
    public function facebookCallback(Request $request, Facebook $fb): Response
    {
        $helper = $fb->getRedirectLoginHelper();

        try {
            $accessToken = $helper->getAccessToken();

            if (!isset($accessToken)) {
                return new Response('Erreur lors de la récupération du token');
            }

            $session = $this->requestStack->getSession();
            $session->set('facebook_access_token', (string) $accessToken);

            return $this->redirectToRoute('facebook_share_image');
        } catch (FacebookResponseException $e) {
            return new Response('Erreur Graph : ' . $e->getMessage());
        } catch (FacebookSDKException $e) {
            return new Response('Erreur SDK : ' . $e->getMessage());
        }
    }

    /**
     * @Route("/facebook/share-image", name="facebook_share_image", methods={"GET", "POST"})
     */
    public function shareImage(Request $request, Facebook $fb): Response
    {
        $session = $this->requestStack->getSession();
        $accessToken = $session->get('facebook_access_token');
        
        if (!$accessToken) {
            return $this->redirectToRoute('facebook_login');
        }

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('facebook_share_image'))
            ->add('image', FileType::class, [
                'label' => 'Choisissez une image à partager sur Facebook',
                'required' => true,
            ])
            ->add('share', SubmitType::class, ['label' => 'Partager sur Facebook'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    return new Response('Erreur lors du téléchargement de l\'image.');
                }

                $fb->setDefaultAccessToken($accessToken);

                $data = [
                    'message' => 'Voici une nouvelle image partagée depuis mon site Symfony !',
                    'source' => $fb->fileToUpload($this->getParameter('uploads_directory') . '/' . $newFilename),
                ];

                try {
                    $response = $fb->post('/me/photos', $data);
                    $graphNode = $response->getGraphNode();

                    return new Response('Image publiée avec succès ! ID de la publication : ' . $graphNode['id']);
                } catch (FacebookResponseException $e) {
                    return new Response('Erreur Graph : ' . $e->getMessage());
                } catch (FacebookSDKException $e) {
                    return new Response('Erreur SDK : ' . $e->getMessage());
                }
            }
        }

        return $this->render('facebook/share_image.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}