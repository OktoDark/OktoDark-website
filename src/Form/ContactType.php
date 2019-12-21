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

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options) : void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'label.contact_name',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray'
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('email', EmailType::class, [
                'required' => true,
                'label' => 'label.contact_email',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray'
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('category', ChoiceType::class, array(
                'label' => 'label.contact_category',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray'
                ],
                'choices' => array(
                    'Staff' => false,
                    'Webmaster' => false,
                ),
                'required' => true,
                'placeholder' => 'Choice your departament',
                'empty_data'  => null
            ))
            ->add('subject', TextType::class, [
                'required' => true,
                'label' => 'label.contact_subject',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray'
                ],
                'constraints' => [
                    new NotBlank()
                ],
            ])
            ->add('message', TextareaType::class, array(
                'label' => 'label.contact_message',
                'attr' => array(
                    'class' => 'form-input form-input-circle form-input-gray')
            ))
            ->add('submit', SubmitType::class, array(
                'label' => 'Send',
                'attr' => array(
                    'class' => 'button button-block button-gradient button-circle',
                    'style' => 'margin-top:20px')
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver) : void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class
        ]);
    }
}
