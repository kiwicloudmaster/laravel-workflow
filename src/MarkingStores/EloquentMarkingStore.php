<?php

namespace ZeroDaHero\LaravelWorkflow\MarkingStores;

use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

/*
 * @see MethodMarkingStore
 *
 * NOTE: This is very similar to MethodMarkingStore, except that we're letting Eloquent handle the getters/setters.
 */
class EloquentMarkingStore implements MarkingStoreInterface
{
    private $singleState;

    private $property;

    /**
     * @param bool $singleState Used to determine Single/Multi place marking
     * @param string $property Used to determine methods to call
     */
    public function __construct(bool $singleState = false, string $property = 'marking')
    {
        $this->singleState = $singleState;
        $this->property = $property;
    }

    /**
     * {@inheritdoc}
     */
    public function getMarking(object $subject): Marking
    {
        $marking = $subject->{$this->property};

        if (null === $marking) {
            return new Marking();
        }

        if ($this->singleState) {
            $marking = [(string) $marking => 1];
        } else if (!\is_array($marking)) {
            throw new LogicException(sprintf('The marking stored in "%s::$%s" is not an array and the Workflow\'s Marking store is instantiated with $singleState=false.', get_debug_type($subject), $this->property));
        }

        return new Marking($marking);
    }

    /**
     * {@inheritdoc}
     */
    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        $marking = $marking->getPlaces();

        if ($this->singleState) {
            $marking = key($marking);
        }

        // We'll check for the mutator first, and use that with the context.
        $method = 'set' . ucfirst($this->property) . 'Attribute';

        if (method_exists($subject, $method)) {
            $subject->{$method}($marking, $context);

            return;
        }

        // If the mutator doesn't exist, defer to Eloquent for setting/casting/etc
        $subject->{$this->property} = $marking;
    }

    /**
     * Return the property name of the marking
     *
     * @return mixed
     */
    public function getProperty()
    {
        return $this->property;
    }
}
