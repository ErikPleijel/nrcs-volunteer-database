<?php

namespace Database\Seeders;

use App\Models\CampaignPurpose;
use Illuminate\Database\Seeder;

class CampaignPurposesSeeder extends Seeder
{
    public function run(): void
    {
        $purposes = [
            [
                'name' => 'Membership Pre-Expiry Notice',
                'slug' => 'membership_pre_expiry',
                'default_channel' => 'email_fallback_sms',
                'default_call_window' => false,
                'default_subject' => 'Your Nigerian Red Cross membership is expiring soon',
                'default_email_body' => '
<p>Dear {{user.first_name}},</p>
<p>This is a friendly reminder that your <strong>{{user.current_membership}}</strong> membership with the Nigerian Red Cross Society is due to expire soon.</p>
<p>Renewing your membership ensures you continue to enjoy the benefits of being part of our community and helps us continue our humanitarian work across Nigeria.</p>
<p>Please contact your branch or visit our member portal to renew at your earliest convenience.</p>
<p>We will never ask you to enter your password after clicking a link — if a page asks for your password, close it and open the app directly</p>
<p>Thank you for your continued support.</p>
<p>Warm regards,<br>Nigerian Red Cross Society</p>
',
                'default_sms_body' => 'Dear {{user.first_name}}, your NRCS {{user.current_membership}} membership is expiring soon. Please renew at your branch. Thank you.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Membership Post-Expiry Notice',
                'slug' => 'membership_post_expiry',
                'default_channel' => 'email_fallback_sms',
                'default_call_window' => false,
                'default_subject' => 'Your Nigerian Red Cross membership has expired',
                'default_email_body' => '
<p>Dear {{user.first_name}},</p>
<p>Our records show that your <strong>{{user.current_membership}}</strong> membership with the Nigerian Red Cross Society has now expired.</p>
<p>We would love to have you continue as a member. Renewing is quick and easy — please contact your local branch or visit our member portal.</p>
<p>We hope to welcome you back soon.</p>
<p>Warm regards,<br>Nigerian Red Cross Society</p>
',
                'default_sms_body' => 'Dear {{user.first_name}}, your NRCS {{user.current_membership}} membership has expired. Please renew at your branch to stay connected.',
                'sort_order' => 20,
            ],
            [
                'name' => 'Training Expiry Notice',
                'slug' => 'training_expiry',
                'default_channel' => 'email_fallback_sms',
                'default_call_window' => true,
                'default_subject' => 'Your Red Cross training certification is expiring',
                'default_email_body' => '
<p>Dear {{user.first_name}},</p>
<p>This is a reminder that one or more of your Red Cross training certifications are due to expire or have recently expired.</p>
<p>Keeping your training up to date is important for your role and ensures you are prepared to help those in need. Please contact your branch training coordinator to arrange a refresher course.</p>
<p>Thank you for your commitment to the Red Cross mission.</p>
<p>Warm regards,<br>Nigerian Red Cross Society</p>
',
                'default_sms_body' => 'Dear {{user.first_name}}, one or more of your NRCS training certifications are expiring. Contact your branch to arrange renewal.',
                'sort_order' => 30,
            ],
            [
                'name' => 'Training Invitation',
                'slug' => 'training_invitation',
                'default_channel' => 'email_fallback_sms',
                'default_call_window' => true,
                'sort_order' => 35,
                'default_subject' => 'Invitation to an upcoming Red Cross training',
                'default_email_body' => '
<p>Dear {{user.first_name}},</p>
<p>We would like to invite you to participate in an upcoming training course organised by the Nigerian Red Cross Society at <strong>{{user.branch}}</strong> Branch.</p>
<p>Training is a key part of our mission — it equips our members and volunteers with the skills to help those in need. We encourage you to take this opportunity to develop your competencies and connect with fellow Red Cross members.</p>
<p>Please contact your branch training coordinator for details on dates, location, and how to register.</p>
<p>We look forward to seeing you there.</p>
<p>Warm regards,<br>Nigerian Red Cross Society</p>
',
                'default_sms_body' => 'Dear {{user.first_name}}, you are invited to an upcoming Red Cross training at {{user.branch}} Branch. Contact your branch coordinator for details.',
            ],
            [
                'name' => 'First Aid Refresher',
                'slug' => 'first_aid_refresher',
                'default_channel' => 'email_fallback_sms',
                'default_call_window' => true,
                'sort_order' => 37,
                'default_subject' => 'Time for a Red Cross first aid refresher',
                'default_email_body' => '
<p>Dear {{user.first_name}},</p>
<p>Our records show that your most recent first aid training was <strong>{{user.time_since_last_first_aid}}</strong> ago.</p>
<p>First aid skills fade over time, and keeping them current ensures you are ready to help when it matters most. We encourage you to attend a refresher course at <strong>{{user.branch}}</strong> Branch.</p>
<p>Please contact your branch training coordinator to arrange a date.</p>
<p>Thank you for your commitment to the Red Cross mission.</p>
<p>Warm regards,<br>Nigerian Red Cross Society</p>
',
                'default_sms_body' => 'Dear {{user.first_name}}, your last first aid training was {{user.time_since_last_first_aid}} ago. Contact {{user.branch}} Branch to arrange a refresher.',
            ],
            [
                'name' => 'Donation Appreciation',
                'slug' => 'donation_appreciation',
                'default_channel' => 'email_fallback_sms',
                'default_call_window' => false,
                'default_subject' => 'Thank you for your generous donation',
                'default_email_body' => '
<p>Dear {{user.first_name}},</p>
<p>On behalf of the Nigerian Red Cross Society, we would like to express our sincere gratitude for your generous donation.</p>
<p>Your contribution makes a real difference in the lives of people across Nigeria. Every donation helps us respond to emergencies, support vulnerable communities, and carry out our humanitarian mission.</p>
<p>This is what is recorded in our database:<br>{{user.donations_summary}}</p>
<p>Thank you for standing with us.</p>
<p>With gratitude,<br>Nigerian Red Cross Society</p>
',
                'default_sms_body' => 'Dear {{user.first_name}}, thank you for your generous donation to the Nigerian Red Cross. Your support makes a real difference. Recorded: {{user.donations_summary}}',
                'sort_order' => 40,
            ],
            [
                'name' => 'Fundraising Appeal',
                'slug' => 'fundraising_appeal',
                'default_channel' => 'email_fallback_sms',
                'default_call_window' => false,
                'default_subject' => 'An urgent appeal from the Nigerian Red Cross',
                'default_email_body' => '
<p>Hi {{user.first_name}},</p>
<p>We\'re reaching out to you directly at {{user.branch}} because your support has always meant something to our work.</p>
<p>[Briefly describe the situation and what you\'re asking for — e.g. an emergency response, a specific need, cash or in-kind donations, and how it will be used.]</p>
<p>Whatever you\'re able to give, cash or in-kind, will go directly toward this effort. Thank you for standing with the Red Cross.</p>
',
                'default_sms_body' => 'Red Cross {{user.branch}}: [Briefly describe the situation and what\'s needed — cash/in-kind]. Any support you can give would help. Thank you.',
                'sort_order' => 45,
            ],
            [
                'name' => 'Newsletter',
                'slug' => 'newsletter',
                'default_channel' => 'email',
                'default_call_window' => false,
                'default_subject' => 'Nigerian Red Cross — News & Updates',
                'default_email_body' => '
<p>Dear {{user.first_name}},</p>
<p>Welcome to the latest edition of the Nigerian Red Cross Society newsletter.</p>
<p>[Add your news and updates here]</p>
<p>Thank you for being part of our community.</p>
<p>Warm regards,<br>Nigerian Red Cross Society<br>{{user.branch}} Branch</p>
',
                'default_sms_body' => 'Dear {{user.first_name}}, you have a message from the Nigerian Red Cross {{user.branch}} Branch. Please check your email for the latest news.',
                'sort_order' => 50,
            ],
            [
                'name' => 'Welcome & Onboarding',
                'slug' => 'onboarding',
                'default_channel' => 'email_fallback_sms',
                'default_call_window' => true,
                'default_subject' => 'Welcome to the Nigerian Red Cross Society',
                'default_email_body' => '
<p>Dear {{user.first_name}},</p>
<p>Welcome to the Nigerian Red Cross Society! We are delighted to have you with us.</p>
<p>You are now registered as a member of the <strong>{{user.branch}}</strong> Branch. Here are a few things you can do to get started:</p>
<ul>
    <li>Complete your membership registration at your local branch</li>
    <li>Join a Red Cross Unit and connect with fellow volunteers</li>
    <li>Sign up for a First Aid training course</li>
    <li>Update your profile with your photo and contact details</li>
</ul>
<p>If you have any questions, please do not hesitate to contact your branch coordinator.</p>
<p>We look forward to seeing you make a difference.</p>
<p>Warm regards,<br>Nigerian Red Cross Society</p>
',
                'default_sms_body' => 'Welcome to the Nigerian Red Cross, {{user.first_name}}! You are registered at {{user.branch}} Branch. Contact your branch coordinator to get started.',
                'sort_order' => 60,
            ],
            [
                'name' => 'Re-engagement — Dormant Users',
                'slug' => 'dormant_reengagement',
                'default_channel' => 'email_fallback_sms',
                'default_call_window' => true,
                'default_subject' => 'We miss you — come back to the Red Cross',
                'default_email_body' => '
<p>Dear {{user.first_name}},</p>
<p>It has been a while since we last heard from you, and we wanted to reach out.</p>
<p>The Nigerian Red Cross Society values every member and volunteer, and we would love to reconnect with you. Whether you would like to renew your membership, join a training, or simply get back in touch with your local branch — we are here for you.</p>
<p>Please contact your branch at <strong>{{user.branch}}</strong> or log in to your member profile to see what is available.</p>
<p>We hope to hear from you soon.</p>
<p>Warm regards,<br>Nigerian Red Cross Society</p>
',
                'default_sms_body' => 'Dear {{user.first_name}}, we miss you at the Nigerian Red Cross! Please get in touch with {{user.branch}} Branch — we would love to reconnect.',
                'sort_order' => 70,
            ],
            [
                'name' => 'Other',
                'slug' => 'other',
                'default_channel' => 'email_fallback_sms',
                'default_call_window' => false,
                'sort_order' => 80,
                'default_subject' => 'Message from the Nigerian Red Cross Society',
                'default_email_body' => '
<p>Dear {{user.first_name}},</p>
<p>[Add your message here]</p>
<p>Warm regards,<br>Nigerian Red Cross Society<br>{{user.branch}} Branch</p>
',
                'default_sms_body' => 'Dear {{user.first_name}}, you have a message from the Nigerian Red Cross {{user.branch}} Branch.',
            ],
        ];

        foreach ($purposes as $data) {
            CampaignPurpose::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
