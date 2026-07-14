<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $compact = (bool) $options['compact'];
        $builder
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'name',
                'disabled' => $options['locked_project'] instanceof Project,
            ])
            ->add('assignees', EntityType::class, [
                'class' => User::class,
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->andWhere('u.role = :role')->andWhere('u.isActive = true')
                    ->setParameter('role', User::ROLE_EMPLOYEE)->orderBy('u.fullName', 'ASC'),
                'choice_label' => 'fullName',
                'label' => 'Assignees',
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
            ])
            ->add('title', TextType::class)
            ->add('description', TextareaType::class, ['required' => false, 'attr' => ['rows' => 4]])
            ->add('priority', ChoiceType::class, ['choices' => ['Low' => 'low', 'Normal' => 'normal', 'High' => 'high', 'Urgent' => 'urgent']])
            ->add('status', ChoiceType::class, ['choices' => ['To do' => Task::STATUS_TODO, 'In progress' => Task::STATUS_IN_PROGRESS, 'Paused' => Task::STATUS_PAUSED, 'Completed' => Task::STATUS_COMPLETED]])
            ->add('estimatedMinutes', IntegerType::class, ['label' => 'Estimated minutes', 'required' => false, 'attr' => ['min' => 0]]);

        if (!$compact) {
            $builder
                ->add('startDate', DateType::class, ['required' => false, 'widget' => 'single_text'])
                ->add('dueDate', DateType::class, ['required' => false, 'widget' => 'single_text'])
                ->add('tags', TextType::class, ['required' => false, 'help' => 'Separate tags with commas.'])
                ->add('reminderAt', DateTimeType::class, ['required' => false, 'widget' => 'single_text'])
                ->add('recurrence', ChoiceType::class, ['required' => false, 'placeholder' => 'Does not repeat', 'choices' => ['Daily' => Task::RECURRENCE_DAILY, 'Weekly' => Task::RECURRENCE_WEEKLY, 'Monthly' => Task::RECURRENCE_MONTHLY]])
                ->add('billingType', ChoiceType::class, ['choices' => ['Billable' => Task::BILLING_BILLABLE, 'Non-billable' => Task::BILLING_NON_BILLABLE]])
                ->add('managerNote', TextareaType::class, ['required' => false, 'attr' => ['rows' => 3]]);
            $builder->get('tags')->addModelTransformer(new CallbackTransformer(
                static fn (array $tags): string => implode(', ', $tags),
                static fn (?string $tags): array => preg_split('/\s*,\s*/', trim((string) $tags), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            ));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Task::class, 'locked_project' => null, 'parent_task' => null, 'compact' => false]);
        $resolver->setAllowedTypes('locked_project', ['null', Project::class]);
        $resolver->setAllowedTypes('parent_task', ['null', Task::class]);
        $resolver->setAllowedTypes('compact', 'bool');
    }
}
