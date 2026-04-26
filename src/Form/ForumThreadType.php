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

use App\Entity\ForumCategory;
use App\Entity\ForumThread;
use App\Form\Type\TrixEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumThreadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Thread Title',
                'attr' => ['placeholder' => 'Give your thread a clear title'],
            ])
            ->add('category', EntityType::class, [
                'class' => ForumCategory::class,
                'choice_label' => 'name',
                'label' => 'Category',
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'General Discussion' => 'discussion',
                    'Question (Q&A)' => 'question',
                    'Bug Report' => 'bug_report',
                    'Feature Request' => 'feature_request',
                ],
                'label' => 'Thread Type',
            ])
            ->add('content', TrixEditorType::class, [
                'label' => 'Content',
                'attr' => ['placeholder' => 'Describe your topic in detail...'],
            ])
            ->add('poll', ForumPollType::class, [
                'label' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumThread::class,
        ]);
    }
}
