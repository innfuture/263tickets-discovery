<?php

namespace App\Enums;

/**
 * Categorical taxonomy for the organizing entity (Team).
 *
 * Picks are inspired by Eventbrite's organizer-type field + Ticketmaster's
 * promoter categories — broad enough to cover indie promoters, corporate
 * conference hosts, and civic / cultural institutions, narrow enough that
 * filtering on an organizer page (and per-type analytics) stays useful.
 *
 * Stored as a string on `teams.organizer_type` rather than an integer
 * foreign-key into a lookup table, on purpose: the set turns over slowly
 * and `Rule::enum()` validation gives us schema-level safety.
 */
enum OrganizerType: string
{
    // Business / professional
    case CorporateEvents = 'corporate_events';
    case ConferenceProducer = 'conference_producer';
    case TradeShowOrganizer = 'trade_show_organizer';

    // Social / community
    case SocialEvents = 'social_events';
    case CommunityGroup = 'community_group';

    // Culture & entertainment
    case MusicPromoter = 'music_promoter';
    case FestivalOrganizer = 'festival_organizer';
    case ArtsAndCulture = 'arts_and_culture';
    case ComedyAndPerforming = 'comedy_and_performing';
    case SportsAndFitness = 'sports_and_fitness';
    case FoodAndDrink = 'food_and_drink';
    case NightlifeAndClub = 'nightlife_and_club';

    // Non-profit / civic
    case NonProfit = 'non_profit';
    case Government = 'government';
    case Religious = 'religious';
    case EducationalInstitution = 'educational_institution';

    // Lifestyle
    case WellnessAndYoga = 'wellness_and_yoga';
    case FashionAndBeauty = 'fashion_and_beauty';
    case TravelAndOutdoor = 'travel_and_outdoor';
    case FamilyAndKids = 'family_and_kids';

    // Other / catch-all
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CorporateEvents => 'Corporate events',
            self::ConferenceProducer => 'Conference producer',
            self::TradeShowOrganizer => 'Trade show organizer',
            self::SocialEvents => 'Social events',
            self::CommunityGroup => 'Community group',
            self::MusicPromoter => 'Music promoter',
            self::FestivalOrganizer => 'Festival organizer',
            self::ArtsAndCulture => 'Arts & culture',
            self::ComedyAndPerforming => 'Comedy & performing arts',
            self::SportsAndFitness => 'Sports & fitness',
            self::FoodAndDrink => 'Food & drink',
            self::NightlifeAndClub => 'Nightlife / club',
            self::NonProfit => 'Non-profit',
            self::Government => 'Government / civic',
            self::Religious => 'Religious organization',
            self::EducationalInstitution => 'Educational institution',
            self::WellnessAndYoga => 'Wellness & yoga',
            self::FashionAndBeauty => 'Fashion & beauty',
            self::TravelAndOutdoor => 'Travel & outdoor',
            self::FamilyAndKids => 'Family & kids',
            self::Other => 'Other',
        };
    }

    /**
     * Grouping for the type-picker UI — keeps the dropdown scannable.
     *
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        return [
            'Business & professional' => [
                self::CorporateEvents,
                self::ConferenceProducer,
                self::TradeShowOrganizer,
            ],
            'Social & community' => [
                self::SocialEvents,
                self::CommunityGroup,
            ],
            'Culture & entertainment' => [
                self::MusicPromoter,
                self::FestivalOrganizer,
                self::ArtsAndCulture,
                self::ComedyAndPerforming,
                self::SportsAndFitness,
                self::FoodAndDrink,
                self::NightlifeAndClub,
            ],
            'Non-profit & civic' => [
                self::NonProfit,
                self::Government,
                self::Religious,
                self::EducationalInstitution,
            ],
            'Lifestyle' => [
                self::WellnessAndYoga,
                self::FashionAndBeauty,
                self::TravelAndOutdoor,
                self::FamilyAndKids,
            ],
            'Other' => [
                self::Other,
            ],
        ];
    }
}
