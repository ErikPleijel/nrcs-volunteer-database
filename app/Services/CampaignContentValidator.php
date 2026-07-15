<?php

namespace App\Services;

use App\Models\MessagingCampaign;

class CampaignContentValidator
{
    public function __construct(
        private readonly UrlDomainValidator $domainValidator,
        private readonly PlaceholderBracketValidator $bracketValidator,
    ) {}

    /**
     * Re-validates a campaign's stored message content (filter_json['_content'],
     * the canonical source from Step 4 onward) against the same two checks
     * enforced at Step 4 entry: approved link domains and unfilled bracket
     * placeholders.
     *
     * @return array<int, string> Flat list of human-readable error messages; empty when content is clean.
     */
    public function validate(MessagingCampaign $campaign): array
    {
        $content = data_get($campaign->filter_json ?? [], '_content', []);

        $emailBody = (string) ($content['email_body'] ?? '');
        $smsBody = (string) ($content['sms_body'] ?? '');

        $errors = [];

        $disallowedDomains = $this->domainValidator->findDisallowedDomains([$emailBody, $smsBody]);

        if (! empty($disallowedDomains)) {
            $allowList = $this->domainValidator->allowedDomains();
            $allowListText = empty($allowList)
                ? 'no approved domains configured'
                : implode(', ', $allowList);

            $errors[] = 'Links must use approved domains ('.$allowListText.'). NOT ALLOWED: '.implode(', ', $disallowedDomains).'.';
        }

        $bracketFields = $this->bracketValidator->findBracketPlaceholders([
            'email_body' => $emailBody,
            'sms_body' => $smsBody,
        ]);

        if (! empty($bracketFields)) {
            $labels = array_map(
                fn ($field) => $field === 'sms_body' ? 'SMS body' : 'Email body',
                $bracketFields
            );
            $labelText = implode(' and ', $labels);
            $verb = count($labels) > 1 ? 'still contain' : 'still contains';

            $errors[] = "{$labelText} {$verb} a placeholder marker ([...]). Please replace the bracketed text with your actual content before submitting.";
        }

        return $errors;
    }
}
