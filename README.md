# UCF University Writing Center Work Schedule Submission Form

A form allowing tutors working for the UWC to submit their work availability to the UWC staff, prioritizing hours to correspond to their preferences.

## Install

Clone repo to wherever you like. Make sure your PHP include path can find the external libraries we'll need, like ReCaptcha, PHPMailer, and dot-env-lite. Copy `.env.example`, rename copy to `.env`, and fill in required values so we have the right site URL and SMTP credentials. Once it's ready to be public, make sure `ENV` is set to `production`.