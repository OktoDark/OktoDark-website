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

use App\Entity\Post;
use App\Form\Type\DateTimePickerType;
use App\Form\Type\TagsInputType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\SluggerInterface;

class PostType extends AbstractType
{
    public function __construct(
        private SluggerInterface $slugger,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Title
            ->add('title', TextType::class, [
                'label' => 'label.post_title',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                    'autofocus' => true,
                ],
            ])

            // Summary
            ->add('summary', TextareaType::class, [
                'label' => 'label.post_summary',
                'help' => 'help.post_summary',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                    'rows' => 4,
                ],
            ])

            // Content
            ->add('content', TextareaType::class, [
                'label' => 'label.post_content',
                'help' => 'help.post_content',
                'attr' => [
                    'class' => 'form-input form-input-circle form-input-gray',
                    'rows' => 20,
                ],
            ])

            // Publication date
            ->add('publishedAt', DateTimePickerType::class, [
                'label' => 'label.published_at',
                'help' => 'help.post_publication',
            ])

            // Tags
            ->add('tags', TagsInputType::class, [
                'label' => 'label.tags',
                'required' => false,
            ])

            // Auto‑slug on submit
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
                /** @var Post $post */
                $post = $event->getData();

                if ($postTitle = $post->getTitle()) {
                    $post->setSlug(
                        $this->slugger->slug($postTitle)->lower()
                    );
                }
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
            'translation_domain' => 'labels',
        ]);
    }
}
