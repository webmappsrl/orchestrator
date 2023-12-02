@component('mail::message')
    # Story Status Updated

    Hello,

    We wanted to inform you that the status of your story has been updated.

    New Status: {{ $story->status }}

    Yoou can check the story at this link: {{ url('resources/stories/' . $story->id) }}

    If you have any questions or need further assistance, please feel free to contact us.

    Thank you,
    The Team
@endcomponent
