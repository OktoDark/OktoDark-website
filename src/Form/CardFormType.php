<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Form;

use App\Entity\Card;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CardFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Card Title',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter card title',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Enter card description',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Task' => Card::TYPE_TASK,
                    'Bug' => Card::TYPE_BUG,
                    'Feature' => Card::TYPE_FEATURE,
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priority',
                'choices' => [
                    'Low' => Card::PRIORITY_LOW,
                    'Medium' => Card::PRIORITY_MEDIUM,
                    'High' => Card::PRIORITY_HIGH,
                    'Critical' => Card::PRIORITY_CRITICAL,
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Due Date',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Card::class,
            'csrf_protection' => false, // Disable CSRF for API forms
            'block_prefix' => '', // Make the form expect flat data
        ]);
    }

    public function getBlockPrefix(): string
    {
        return ''; // Ensure the form's name is empty for flat data
    }
}
