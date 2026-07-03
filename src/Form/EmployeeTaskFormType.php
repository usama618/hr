<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmployeeTaskFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => 'name',
            ])
            ->add('assignedTo', EntityType::class, [
                'class' => User::class,
                'choices' => $options['employees'],
                'choice_label' => 'fullName',
                'label' => 'Assign to',
            ])
            ->add('title', TextType::class)
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('priority', ChoiceType::class, [
                'choices' => [
                    'Low' => 'low',
                    'Normal' => 'normal',
                    'High' => 'high',
                    'Urgent' => 'urgent',
                ],
            ])
            ->add('estimatedMinutes', IntegerType::class, [
                'label' => 'Estimated minutes',
                'required' => false,
                'empty_data' => '0',
                'attr' => [
                    'min' => 0,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'projects' => [],
            'employees' => [],
        ]);

        $resolver->setAllowedTypes('projects', 'array');
        $resolver->setAllowedTypes('employees', 'array');
    }
}
