<?php

namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Article;
use App\Entity\User;
use App\Entity\Cart;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class PageController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Récupérer les articles triés par publication_date, du plus récent au plus ancien
        $articles = $entityManager->getRepository(Article::class)
            ->findBy([], ['publication_date' => 'DESC']);  // Tri par publication_date en ordre décroissant

        return $this->render('page/index.html.twig', [
            'articles' => $articles,
        ]);
    }


    #[Route('/detail/{id}', name: 'app_article_detail')]
    public function detail(Article $article): Response
    {
        return $this->render('page/detail.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/cart', name: 'app_cart')]
    public function cart(EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find(2);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $cartItems = $entityManager->getRepository(Cart::class)
            ->findBy(['user' => $user]);

        $groupedArticles = [];
        foreach ($cartItems as $cartItem) {
            $article = $cartItem->getArticle();
            $articleId = $article->getId();

            if (isset($groupedArticles[$articleId])) {
                $groupedArticles[$articleId]['quantity']++;
            } else {
                $groupedArticles[$articleId] = [
                    'article' => $article,
                    'quantity' => 1,
                ];
            }
        }

        return $this->render('page/cart.html.twig', [
            'groupedArticles' => $groupedArticles,
        ]);
    }

    #[Route('/cart/add/{articleId}', name: 'app_cart_add')]
    public function addToCart($articleId, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find(2);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $article = $entityManager->getRepository(Article::class)->find($articleId);

        if (!$article) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        $cartItem = new Cart();
        $cartItem->setUser($user);
        $cartItem->setArticle($article);

        $entityManager->persist($cartItem);
        $entityManager->flush();

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/remove/{articleId}', name: 'app_cart_remove')]
    public function removeFromCart($articleId, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find(2);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $article = $entityManager->getRepository(Article::class)->find($articleId);

        if (!$article) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        $cartItem = $entityManager->getRepository(Cart::class)
            ->findOneBy(['user' => $user, 'article' => $article]);

        if ($cartItem) {
            $entityManager->remove($cartItem);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/add-amount', name: 'app_cart_add_amount')]
    public function addAmount(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find(2);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        if ($request->isMethod('POST')) {
            $amount = (float) $request->request->get('amount');
            $paymentMethod = $request->request->get('payment_method');
            $cardNumber = $request->request->get('card_number');
            $cardExpiration = $request->request->get('card_expiration');
            $cardCvc = $request->request->get('card_cvc');
            $paypalEmail = $request->request->get('paypal_email');

            if ($amount <= 0) {
                $this->addFlash('error', 'Le montant doit être supérieur à 0€.');
                return $this->redirectToRoute('app_cart_add_amount');
            }

            if ($paymentMethod === 'card') {
                if (empty($cardNumber) || empty($cardExpiration) || empty($cardCvc)) {
                    $this->addFlash('error', 'Veuillez remplir toutes les informations de carte bancaire.');
                    return $this->redirectToRoute('app_cart_add_amount');
                }
            } elseif ($paymentMethod === 'paypal') {
                if (empty($paypalEmail)) {
                    $this->addFlash('error', 'Veuillez entrer votre adresse PayPal.');
                    return $this->redirectToRoute('app_cart_add_amount');
                }
            } else {
                $this->addFlash('error', 'Veuillez choisir un moyen de paiement.');
                return $this->redirectToRoute('app_cart_add_amount');
            }

            $user->addBalance($amount);
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Montant ajouté avec succès !');
            return $this->redirectToRoute('app_cart');
        }

        return $this->render('page/add_amount.html.twig');
    }

    #[Route('/cart/validate', name: 'app_cart_validate')]
    public function validateCart(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find(2);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $cartItems = $entityManager->getRepository(Cart::class)->findBy(['user' => $user]);

        if (empty($cartItems)) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart');
        }

        $totalPrice = 0;
        foreach ($cartItems as $cartItem) {
            $totalPrice += $cartItem->getArticle()->getPrice();
        }

        if ($user->getBalance() < $totalPrice) {
            $this->addFlash('error', 'Votre solde est insuffisant, veuillez ajouter des fonds.');
            return $this->redirectToRoute('app_cart_add_amount');
        }

        if ($request->isMethod('POST')) {
            $address = $request->request->get('address');
            $city = $request->request->get('city');
            $zipCode = $request->request->get('zip_code');

            if (empty($address) || empty($city) || empty($zipCode)) {
                $this->addFlash('error', 'Veuillez remplir toutes les informations de facturation.');
                return $this->redirectToRoute('app_cart_validate');
            }

            $user->addBalance(-$totalPrice);
            $entityManager->persist($user);

            $invoice = new Invoice();
            $invoice->setUser($user);
            $invoice->setTransactionDate(new \DateTime());
            $invoice->setAmount($totalPrice);
            $invoice->setBillingAddress($address);
            $invoice->setBillingCity($city);
            $invoice->setBillingPostcode($zipCode);

            $entityManager->persist($invoice);

            foreach ($cartItems as $cartItem) {
                $entityManager->remove($cartItem);
            }

            $entityManager->flush();

            $invoicePdf = $this->generateInvoicePdf($user, $cartItems, $totalPrice, $address, $city, $zipCode);

            $response = new Response(
                $invoicePdf,
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="facture_' . $invoice->getId() . '.pdf"',
                ]
            );
    
            $response->headers->set('Refresh', '0;url=' . $this->generateUrl('app_home'));
    
            return $response;
        }

        return $this->render('page/validate_cart.html.twig', [
            'totalPrice' => $totalPrice,
        ]);
    }


    private function generateInvoicePdf(User $user, array $cartItems, float $totalPrice, string $address, string $city, string $zipCode): string
    {
        $dompdf = new Dompdf();
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf->setOptions($options);

        $html = '<h1>Facture</h1>';
        $html .= '<p>Nom de l\'utilisateur : ' . $user->getId() . '</p>';
        $html .= '<p>Adresse : ' . $address . ', ' . $zipCode . ' ' . $city . '</p>';
        $html .= '<h2>Articles achetés :</h2>';
        foreach ($cartItems as $cartItem) {
            $html .= '<p>' . $cartItem->getArticle()->getName() . ' - ' . $cartItem->getArticle()->getPrice() . '€</p>';
        }
        $html .= '<h3>Total : ' . $totalPrice . '€</h3>';
        $html .= '<p>Merci pour votre achat !</p>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    #[Route('/edit/{id}', name: 'app_edit_article')]
    public function editArticle(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $article = $entityManager->getRepository(Article::class)->find($id);

        if (!$article) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        if ($request->isMethod('POST')) {
            if ($request->request->has('delete')) {
                // Suppression de l'article
                $entityManager->remove($article);
                $entityManager->flush();

                $this->addFlash('success', 'Article supprimé avec succès.');
                return $this->redirectToRoute('app_home');
            }

            // Mise à jour de l'article
            $name = $request->request->get('name');
            $description = $request->request->get('description');
            $price = (float) $request->request->get('price');

            if (!empty($name)) {
                $article->setName($name);
            }
            if (!empty($description)) {
                $article->setDescription($description);
            }
            if ($price > 0) {
                $article->setPrice($price);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Article mis à jour avec succès.');
            return $this->redirectToRoute('app_edit_article', ['id' => $id]);
        }

        return $this->render('page/edit.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/account', name: 'app_account')]
    public function account(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find(2);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $userId = $user->getId();

        $articles = $entityManager->getRepository(Article::class)->findBy(['author' => $userId]);
        $invoices = $entityManager->getRepository(Invoice::class)->findBy(['user' => $userId]);

        $form = $this->createFormBuilder($user)
            ->add('username', TextType::class, ['label' => 'Nom d\'utilisateur'])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('submit', SubmitType::class, ['label' => 'Mettre à jour'])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($user);
            $entityManager->flush();
            $this->addFlash('success', 'Informations mises à jour !');
            return $this->redirectToRoute('app_account');
        }

        return $this->render('page/account.html.twig', [
            'user' => $user,
            'articles' => $articles,
            'invoices' => $invoices,
            'form' => $form->createView(),
        ]);
    }
}