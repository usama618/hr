<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class EmployeeProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Name',
            ])
            ->add('profileImageFile', FileType::class, [
                'label' => 'Profile image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File(
                        maxSize: '3M',
                        extensions: [
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'webp' => 'image/webp',
                            'gif' => 'image/gif',
                        ],
                        extensionsMessage: 'Please upload a JPG, PNG, WebP, or GIF image.'
                    ),
                ],
            ])
            ->add('croppedProfileImage', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('removeProfileImage', CheckboxType::class, [
                'label' => 'Use default avatar',
                'mapped' => false,
                'required' => false,
            ])
            ->add('jobTitle', TextType::class, [
                'label' => 'Role',
                'required' => false,
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 8,
                    'class' => 'profile-bio-input',
                ],
            ])
            ->add('skills', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'class' => 'rich-text-source',
                    'data-rich-source' => '',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
