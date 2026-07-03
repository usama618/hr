<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'name',
            ])
            ->add('assignedTo', EntityType::class, [
                'class' => User::class,
                'query_builder' => static fn (UserRepository $repository) => $repository
                    ->createQueryBuilder('u')
                    ->andWhere('u.role = :role')
                    ->andWhere('u.isActive = true')
                    ->setParameter('role', User::ROLE_EMPLOYEE)
                    ->orderBy('u.fullName', 'ASC'),
                'choice_label' => 'fullName',
                'required' => false,
                'placeholder' => 'Unassigned',
            ])
            ->add('title', TextType::class)
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('priority', ChoiceType::class, [
                'choices' => [
                    'Low' => 'low',
                    'Normal' => 'normal',
                    'High' => 'high',
                    'Urgent' => 'urgent',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'To do' => Task::STATUS_TODO,
                    'In progress' => Task::STATUS_IN_PROGRESS,
                    'Paused' => Task::STATUS_PAUSED,
                    'Completed' => Task::STATUS_COMPLETED,
                ],
            ])
            ->add('estimatedMinutes', IntegerType::class, [
                'label' => 'Estimated minutes',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
        ]);
    }
}
