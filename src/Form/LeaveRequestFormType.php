<?php

namespace App\Form;

use App\Entity\LeaveRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LeaveRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('leaveType', ChoiceType::class, [
                'label' => 'Leave type',
                'choices' => [
                    'Vacation' => 'vacation',
                    'Sick leave' => 'sick_leave',
                    'Personal' => 'personal',
                    'Unpaid leave' => 'unpaid',
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'From',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'To',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('reason', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LeaveRequest::class,
        ]);
    }
}
