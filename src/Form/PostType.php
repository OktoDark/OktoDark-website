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
use App\Form\Type\TrixEditorType;
use Symfony\Component\Form\AbstractType;
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
                'label' => 'blog.label.title',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'autofocus' => true,
                ],
            ])

            // Summary
            ->add('summary', TrixEditorType::class, [
                'label' => 'blog.label.summary',
                'help' => 'blog.help.post_summary',
            ])

            // Content
            ->add('content', TrixEditorType::class, [
                'label' => 'blog.label.content',
                'help' => 'blog.help.post_content',
            ])

            // Publication date
            ->add('publishedAt', DateTimePickerType::class, [
                'label' => 'blog.label.published_at',
                'help' => 'blog.help.post_publication',
            ])

            // Tags
            ->add('tags', TagsInputType::class, [
                'label' => 'blog.label.tags',
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
            })

            // Set default publishedAt if empty
            ->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) {
                /** @var Post|null $post */
                $post = $event->getData();
                if (!$post || null === $post->getPublishedAt()) {
                    $post?->setPublishedAt(new \DateTimeImmutable());
                }
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
            'translation_domain' => 'blog', // Changed from 'labels' to 'blog'
        ]);
    }
}
