<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\ControlId;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Label, control slot, help and error text sharing ONE control id.
 *
 * A class component on purpose: the constructor runs before the slot renders, so
 * the resolved id ($controlId) and name ($fieldName) reach the child x-ui.input /
 * x-ui.select / x-ui.textarea through @aware. `id` and `name` are control props,
 * never wrapper attributes. If the slot control brings its own id, the view
 * follows that id instead (see ControlId::fromMarkup()).
 */
class Field extends Component
{
    /** The control id label, help and error text point at (null without for/name/id). */
    public ?string $controlId;

    /** The control name used for $errors lookups and as the child's default name. */
    public ?string $fieldName;

    public function __construct(
        public ?string $label = null,
        public ?string $for = null,
        public ?string $help = null,
        public ?string $error = null,
        public bool $required = false,
        ?string $id = null,
        ?string $name = null,
    ) {
        $this->fieldName = $for ?? $name;
        $this->controlId = match (true) {
            $id !== null && $id !== '' => ControlId::reserve($id),
            $this->fieldName !== null && $this->fieldName !== '' => ControlId::claim($this->fieldName),
            default => null,
        };
    }

    public function render(): View
    {
        return view('components.ui.field');
    }
}
