<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\NotNull;

class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer votre nom d\'utilisateur',
                    ]),
                    new Type([
                        'type' => 'string',
                        'message' => 'Le nom d\'utilisateur doit être une chaîne de caractères',
                    ]),
                    new NotNull([
                        'message' => 'Le nom d\'utilisateur ne peut pas être nul',
                    ]),
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Password',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection' => true,  // ✅ Symfony gère automatiquement le CSRF
            'csrf_token_id'   => 'authenticate',
        ]);
    }
}
