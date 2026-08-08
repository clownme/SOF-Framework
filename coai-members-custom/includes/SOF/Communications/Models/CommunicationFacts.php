<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Facts
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Model:
 *     Communication Facts
 *
 * Purpose:
 *     Represent the currently known business facts for a
 *     Communication before assessment and recommendation.
 *
 * Responsibilities:
 *     - Hold the Communication audience
 *     - Hold the available audience population
 *     - Hold the currently resolved recipients
 *     - Provide the factual basis for assessment
 *
 * Does NOT:
 *     - Discover audiences
 *     - Discover audience populations
 *     - Discover recipients
 *     - Assess communication readiness
 *     - Recommend a business path
 *     - Determine available actions
 *     - Render presentation
 *     - Deliver communications
 *
 * Business Question:
 *     What do we currently know about this Communication?
 *
 * ============================================================
 */

class SOF_CommunicationFacts
{
    // -------------------------------------------------
    // Audience
    // -------------------------------------------------

    protected SOF_CommunicationAudience $audience;

    // -------------------------------------------------
    // Audience Population
    // -------------------------------------------------

    protected SOF_CommunicationAudiencePopulation $audience_population;

    // -------------------------------------------------
    // Recipients
    // -------------------------------------------------

    protected SOF_CommunicationRecipients $recipients;

    // -------------------------------------------------
    // Construction
    // -------------------------------------------------

    public function __construct(
        SOF_CommunicationAudience $audience,
        SOF_CommunicationAudiencePopulation $audience_population,
        SOF_CommunicationRecipients $recipients
    ) {
        $this->audience =
            $audience;

        $this->audience_population =
            $audience_population;

        $this->recipients =
            $recipients;
    }

    // -------------------------------------------------
    // Business Information
    // -------------------------------------------------

    public function audience(): SOF_CommunicationAudience
    {
        return $this->audience;
    }

    public function audience_population():
        SOF_CommunicationAudiencePopulation
    {
        return $this->audience_population;
    }

    public function recipients(): SOF_CommunicationRecipients
    {
        return $this->recipients;
    }

    // -------------------------------------------------
    // Convenient Facts
    // -------------------------------------------------

    public function get_audience_name(): string
    {
        return $this->audience->get_name();
    }

    public function get_eligible_population_count(): int
    {
        return $this->audience_population
            ->get_eligible_total();
    }

    public function get_selected_recipient_count(): int
    {
        return $this->recipients
            ->get_total_count();
    }

    public function get_available_recipient_count(): int
    {
        return $this->recipients
            ->get_available_count();
    }

    public function get_unavailable_recipient_count(): int
    {
        return $this->recipients
            ->get_unavailable_count();
    }

    // -------------------------------------------------
    // Serialization
    // -------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return [
            'audience' =>
                $this->audience->to_array(),

            'audience_population' =>
                $this->audience_population->to_array(),

            'recipients' => [
                'total_count' =>
                    $this->recipients
                        ->get_total_count(),

                'available_count' =>
                    $this->recipients
                        ->get_available_count(),

                'unavailable_count' =>
                    $this->recipients
                        ->get_unavailable_count(),
            ],
        ];
    }
}