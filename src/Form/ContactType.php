<?php
/**
 * Copyright © 2019 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 16.03.2019 17:30
 */

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options) : void
    {
        $builder
            ->add('name', TextType::class, array('label' => 'Name', 'attr' => array('class' => 'form-input')))
            ->add('email', EmailType::class, array('label' => 'E-mail', 'attr' => array('class' => 'form-input')))
            ->add('subject', TextType::class, array('label' => 'Subject', 'attr' => array('class' => 'form-input')))
            ->add('message', TextareaType::class, array('label' => 'Message', 'attr' => array('class' => 'form-input')))
            ->add('submit', SubmitType::class, array('label' => 'Send', 'attr' => array('class' => 'button button-primary form-button text-center', 'style' => 'margin-top:5px')))
        ;
    }

    public function configureOptions(OptionsResolver $resolver) : void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
