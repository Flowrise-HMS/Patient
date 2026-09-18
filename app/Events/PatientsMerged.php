<?php

namespace Modules\Patient\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientMerge;

class PatientsMerged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Patient $source,
        public Patient $target,
        public PatientMerge $merge,
    ) {}
}
