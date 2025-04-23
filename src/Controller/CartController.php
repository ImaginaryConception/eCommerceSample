<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cart', name: 'cart_')]
class CartController extends AbstractController
{
    #[Route('/', name: 'index')]
    #[IsGranted('ROLE_USER')]
    public function index(SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $cart = $session->get('cart', []);
        $cartWithData = [];
        $total = 0;

        foreach ($cart as $id => $quantity) {
            $product = $entityManager->getRepository(Product::class)->find($id);
            if ($product) {
                $cartWithData[] = [
                    'product' => $product,
                    'quantity' => $quantity
                ];
                $total += $product->getPrice() * $quantity;
            }
        }

        return $this->render('cart/index.html.twig', [
            'items' => $cartWithData,
            'total' => $total
        ]);
    }

    #[Route('/add/{id}', name: 'add')]
    #[IsGranted('ROLE_USER')]
    public function add(Product $product, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $id = $product->getId();

        if (!empty($cart[$id])) {
            $cart[$id]++;
            $this->addFlash('success', 'La quantité du produit a été augmentée dans votre panier');
        } else {
            $cart[$id] = 1;
            $this->addFlash('success', 'Le produit a été ajouté à votre panier');
        }

        $session->set('cart', $cart);
        return $this->redirectToRoute('product_show', ['id' => $id]);
    }

    #[Route('/remove/{id}', name: 'remove')]
    #[IsGranted('ROLE_USER')]
    public function remove(Product $product, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $id = $product->getId();

        if (!empty($cart[$id])) {
            if ($cart[$id] > 1) {
                $cart[$id]--;
                $this->addFlash('info', 'La quantité du produit a été diminuée');
            } else {
                unset($cart[$id]);
                $this->addFlash('info', 'Le produit a été retiré de votre panier');
            }
        }

        $session->set('cart', $cart);
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/delete/{id}', name: 'delete')]
    #[IsGranted('ROLE_USER')]
    public function delete(Product $product, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $id = $product->getId();

        if (!empty($cart[$id])) {
            unset($cart[$id]);
            $this->addFlash('info', 'Le produit a été supprimé de votre panier');
        }

        $session->set('cart', $cart);
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/checkout', name: 'checkout')]
    #[IsGranted('ROLE_USER')]
    public function checkout(SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $cart = $session->get('cart', []);
        
        if (empty($cart)) {
            $this->addFlash('warning', 'Votre panier est vide');
            return $this->redirectToRoute('cart_index');
        }

        // Vérifier le stock et créer la commande
        foreach ($cart as $id => $quantity) {
            $product = $entityManager->getRepository(Product::class)->find($id);
            if (!$product || $product->getStock() < $quantity) {
                $this->addFlash('error', 'Un problème est survenu avec le stock d\'un produit');
                return $this->redirectToRoute('cart_index');
            }
        }

        // Vider le panier après la commande réussie
        $session->set('cart', []);
        $this->addFlash('success', 'Votre commande a été validée avec succès');

        return $this->redirectToRoute('cart_index');
    }
}