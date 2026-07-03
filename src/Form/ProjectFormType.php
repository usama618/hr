<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('clientName', TextType::class, [
                'label' => 'Client',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Active' => 'active',
                    'Paused' => 'paused',
                    'Completed' => 'completed',
                    'Archived' => 'archived',
                ],
            ])
            ->add('startDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('deadline', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('employees', EntityType::class, [
                'class' => User::class,
                'query_builder' => static fn (UserRepository $repository) => $repository
                    ->createQueryBuilder('u')
                    ->andWhere('u.role = :role')
                    ->andWhere('u.isActive = true')
                    ->setParameter('role', User::ROLE_EMPLOYEE)
                    ->orderBy('u.fullName', 'ASC'),
                'choice_label' => 'fullName',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Employees with access',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
