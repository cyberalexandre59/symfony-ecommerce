<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class SellController extends AbstractController
{
    #[Route('/sell', name: 'app_sell')]
    public function sell(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image_link')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('upload_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gérer l'erreur
                }

                $article->setImageLink($newFilename);
            }

            // Vous récupérez l'auteur sélectionné dans le formulaire
            $article->setAuthor($form->get('author')->getData());

            // Vous pouvez aussi garder la date de publication à "now"
            $article->setPublicationDate(new \DateTime());

            // Persiste l'article
            $entityManager->persist($article);
            $entityManager->flush();

            $this->addFlash('success', 'Article mis en vente avec succès !');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('sell/index.html.twig', [
            'sellForm' => $form->createView(),
        ]);
    }
}