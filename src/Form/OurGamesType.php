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

use App\Entity\Board;
use App\Entity\OurGames;
use App\Form\Type\TrixEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class OurGamesType extends AbstractType
{
    private $router;
    private $slugger;

    public function __construct(RouterInterface $router, SluggerInterface $slugger) // Inject SluggerInterface
    {
        $this->router = $router;
        $this->slugger = $slugger; // Assign slugger
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $ourGame = $options['data']; // Get the OurGames entity from the form options

        $builder
            // General Info Tab
            ->add('Name', TextType::class, [
                'label' => 'Game Name',
                'required' => true,
            ])
            ->add('shortDescription', TextType::class, [
                'label' => 'Short Description',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('description', TrixEditorType::class, [
                'label' => 'Description',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('CoverFile', FileType::class, [
                'label' => 'Game Cover (PNG/JPG image)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image(
                        maxSize: '1024k',
                        mimeTypes: [
                            'image/png',
                            'image/jpeg',
                        ],
                        mimeTypesMessage: 'Please upload a valid PNG or JPG image',
                    ),
                ],
            ])

            // Links Tab
            ->add('WebsiteLink', TextType::class, [
                'label' => 'Website Link (URL or Route Name)',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Callback($this->validateWebsiteLink(...)),
                ],
            ])
            ->add('FreeLink', UrlType::class, [
                'label' => 'Free Download Link',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('SourceCode', UrlType::class, [
                'label' => 'Source Code Link',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('URLCDN', UrlType::class, [
                'label' => 'CDN URL',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('bugLink', EntityType::class, [
                'class' => Board::class,
                'choice_label' => 'title',
                'placeholder' => 'Choose a bug report project (optional)',
                'required' => false,
                'label' => 'Bug Report Project',
                // 'mapped' => true, // Default for EntityType, but explicitly stating for clarity
            ])

            // Play Online Tab
            ->add('PlayOnlineID', TextType::class, [
                'label' => 'Play Online ID',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('PlayOnline', TextType::class, [
                'label' => 'Play Online Link (URL or Route Name)',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Callback($this->validateOptionalLink(...)),
                ],
            ])
            ->add('PlayOnlineText', TextareaType::class, [
                'label' => 'Play Online Text',
                'required' => false,
                'empty_data' => '',
            ])

            // Versions Tab
            ->add('isAlpha', CheckboxType::class, [
                'label' => 'Alpha Version Available',
                'required' => false,
            ])
            ->add('Alpha', TextType::class, [
                'label' => 'Alpha Download Link (URL or Route Name)',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Callback($this->validateOptionalLink(...)),
                ],
            ])
            ->add('isBeta', CheckboxType::class, [
                'label' => 'Beta Version Available',
                'required' => false,
            ])
            ->add('Beta', TextType::class, [
                'label' => 'Beta Download Link (URL or Route Name)',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Callback($this->validateOptionalLink(...)),
                ],
            ])
            ->add('stable', CheckboxType::class, [
                'label' => 'Stable Version Available',
                'required' => false,
            ])
            ->add('StableLink', TextType::class, [
                'label' => 'Stable Download Link (URL or Route Name)',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Callback($this->validateOptionalLink(...)),
                ],
            ])

            // System Requirements Tab
            ->add('systemRequirements', TrixEditorType::class, [
                'label' => 'System Requirements',
                'required' => false,
                'empty_data' => '',
                'data' => $ourGame->getSystemRequirements() ?? $this->getDefaultSystemRequirementsTemplate(), // Set default template
            ])

            // Media Gallery Tab
            ->add('ImagesFiles', FileType::class, [
                'label' => 'Upload Images (PNG/JPG)',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new All([
                        new Image(
                            maxSize: '5000k',
                            mimeTypes: [
                                'image/png',
                                'image/jpeg',
                            ],
                            mimeTypesMessage: 'Please upload valid PNG or JPG images (max 5MB each).',
                        ),
                    ]),
                ],
            ])
            ->add('VideosFiles', FileType::class, [
                'label' => 'Upload Videos (MP4/WebM)',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new All([
                        new File(
                            maxSize: '20000k',
                            mimeTypes: [
                                'video/mp4',
                                'video/webm',
                            ],
                            mimeTypesMessage: 'Please upload valid MP4 or WebM videos (max 20MB each).',
                        ),
                    ]),
                ],
            ])
        ;

        // Add event listener to set shortNameSlug before validation
        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event) {
                /** @var OurGames $ourGame */
                $ourGame = $event->getData();
                $form = $event->getForm();

                // Get the 'Name' field data from the submitted form
                $name = $form->get('Name')->getData();

                if ($name && null === $ourGame->getShortNameSlug()) {
                    $safeShortName = $this->slugger->slug($name);
                    $ourGame->setShortNameSlug($safeShortName->lower());
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OurGames::class,
        ]);
    }

    /**
     * Custom validator for the required website link field.
     */
    public function validateWebsiteLink(?string $value, ExecutionContextInterface $context): void
    {
        if (empty($value)) {
            $context->addViolation('This field cannot be empty.');

            return;
        }

        if (!$this->isValidUrlOrRoute($value)) {
            $context->addViolation('This is not a valid URL or a known route name.');
        }
    }

    /**
     * Custom validator for optional link fields.
     */
    public function validateOptionalLink(?string $value, ExecutionContextInterface $context): void
    {
        if (empty($value)) {
            return; // Optional field, no validation if empty
        }

        if (!$this->isValidUrlOrRoute($value)) {
            $context->addViolation('This is not a valid URL or a known route name.');
        }
    }

    /**
     * Checks if a given string is a valid URL or a known Symfony route name.
     */
    private function isValidUrlOrRoute(string $value): bool
    {
        // Check if it's a valid URL
        if (filter_var($value, \FILTER_VALIDATE_URL)) {
            return true;
        }

        // Check if it's a valid route name
        try {
            return null !== $this->router->getRouteCollection()->get($value);
        } catch (RouteNotFoundException $e) {
            return false;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Returns a default HTML template for system requirements.
     */
    private function getDefaultSystemRequirementsTemplate(): string
    {
        return <<<HTML
            <h4>Minimum Requirements</h4>
            <ul>
                <li><strong>OS:</strong> [e.g., Windows 10 64-bit, macOS 10.15]</li>
                <li><strong>Processor:</strong> [e.g., Intel Core i5-4460 / AMD FX-8350]</li>
                <li><strong>Memory:</strong> [e.g., 8 GB RAM]</li>
                <li><strong>Graphics:</strong> [e.g., NVIDIA GeForce GTX 760 / AMD Radeon R7 260x]</li>
                <li><strong>Storage:</strong> [e.g., 50 GB available space]</li>
            </ul>
            <h4>Recommended Requirements</h4>
            <ul>
                <li><strong>OS:</strong> [e.g., Windows 10 64-bit, macOS 11]</li>
                <li><strong>Processor:</strong> [e.g., Intel Core i7-4790 / AMD Ryzen 5 1500X]</li>
                <li><strong>Memory:</strong> [e.g., 16 GB RAM]</li>
                <li><strong>Graphics:</strong> [e.g., NVIDIA GeForce GTX 970 / AMD Radeon RX 480]</li>
                <li><strong>Storage:</strong> [e.g., 50 GB SSD available space]</li>
            </ul>
            <p><em>For any requirement that is not applicable, please write "None".</em></p>
            HTML;
    }
}
