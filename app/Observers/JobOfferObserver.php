<?php

namespace App\Observers;

use App\Models\JobOffer;

class JobOfferObserver
{
    /**
     * Handle the JobOffer "creating" event.
     */
    public function creating(JobOffer $jobOffer)
    {
        $date = now()->format('Ymd'); // e.g., 20250410
        $prefix = 'SMRU-BHF';

        // Count how many job offers were already created today
        $count = JobOffer::whereDate('created_at', now()->toDateString())->count() + 1;
        $sequence = str_pad($count, 4, '0', STR_PAD_LEFT); // e.g., 0001

        $jobOffer->custom_offer_id = "{$date}-{$prefix}-{$sequence}";
    }


    /**
     * Handle the JobOffer "created" event.
     */
    public function created(JobOffer $jobOffer): void
    {
        //
    }

    /**
     * Handle the JobOffer "updated" event.
     */
    public function updated(JobOffer $jobOffer): void
    {
        //
    }

    /**
     * Handle the JobOffer "deleted" event.
     */
    public function deleted(JobOffer $jobOffer): void
    {
        //
    }

    /**
     * Handle the JobOffer "restored" event.
     */
    public function restored(JobOffer $jobOffer): void
    {
        //
    }

    /**
     * Handle the JobOffer "force deleted" event.
     */
    public function forceDeleted(JobOffer $jobOffer): void
    {
        //
    }
}
