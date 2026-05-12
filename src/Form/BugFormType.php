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

use App\Entity\Bug;
use App\Entity\Card;
use App\Entity\Mods;
use App\Entity\OurGames;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BugFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Bug Title',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter bug title',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Describe the bug',
                ],
            ])
            ->add('severity', ChoiceType::class, [
                'label' => 'Severity',
                'choices' => [
                    'Low' => Bug::SEVERITY_LOW,
                    'Medium' => Bug::SEVERITY_MEDIUM,
                    'High' => Bug::SEVERITY_HIGH,
                    'Critical' => Bug::SEVERITY_CRITICAL,
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('reproduction_steps', TextareaType::class, [
                'label' => 'Steps to Reproduce',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'How to reproduce the bug',
                ],
            ])
            ->add('expected_result', TextareaType::class, [
                'label' => 'Expected Result',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'What should happen',
                ],
            ])
            ->add('actual_result', TextareaType::class, [
                'label' => 'Actual Result',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'What actually happens',
                ],
            ])
            ->add('operatingSystem', ChoiceType::class, [
                'label' => 'Operating System',
                'choices' => [
                    'Windows' => 'Windows',
                    'Linux' => 'Linux',
                    'macOS' => 'macOS',
                    'Android' => 'Android',
                    'iOS' => 'iOS',
                    'Other' => 'Other',
                ],
                'required' => false,
                'placeholder' => 'Choose an operating system',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('operatingSystemVersion', TextType::class, [
                'label' => 'OS Version',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., 10, 11, Ubuntu 22.04, iOS 17.5',
                ],
            ])
            ->add('kanbanCard', EntityType::class, [
                'class' => Card::class,
                'choice_label' => 'title',
                'placeholder' => 'Link to a Kanban Card (optional)',
                'required' => false,
                'label' => 'Linked Kanban Card',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('ourGame', EntityType::class, [
                'class' => OurGames::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a game (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('mod', EntityType::class, [ // Add mod field
                'class' => Mods::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a mod (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Bug::class,
            'csrf_protection' => false, // Disable CSRF for API forms
            'block_prefix' => '', // Make the form expect flat data
        ]);
    }

    public function getBlockPrefix(): string
    {
        return ''; // Ensure the form's name is empty for flat data
    }
}
