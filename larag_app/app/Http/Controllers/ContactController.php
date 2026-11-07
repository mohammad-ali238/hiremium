<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessage;
use App\Support\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * Handle an AJAX contact-form submission: validate, (optionally) verify
     * reCAPTCHA, and email the message to the admin-configured address.
     */
    public function send(ContactRequest $request): JsonResponse
    {
        $data = $request->validated();

        Mail::to(SiteSettings::contactSendToEmail())->send(new ContactMessage(
            senderName: $data['name'],
            senderEmail: $data['email'],
            topicLabel: $request->topicLabel(),
            subjectText: $data['subject'],
            messageBody: $data['message'],
        ));

        return response()->json([
            'ok' => true,
            'message' => 'Thanks for reaching out! We\'ll get back to you within one business day.',
        ]);
    }
}
