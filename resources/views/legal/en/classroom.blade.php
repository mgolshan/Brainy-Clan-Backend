@extends('legal.layout')

@section('title', 'Classroom terms and consent statement')
@section('other_document', 'Terms of Service')

@section('content')
    <p><span class="review">[DRAFT FOR LAWYER REVIEW — this page describes how the Classroom plan works and must be reviewed by a qualified lawyer (Canada and the United States, including Quebec law on personal information and school use) before launch.]</span></p>

    <p>These Classroom terms add to our Terms of Service and Privacy Policy. They apply to groups on the Classroom plan, which teachers, tutors and coaches use with their students.</p>

    <h2>1. The consent statement</h2>
    <p>Before adding students, the teacher who runs the group (or the co-teacher) reads and accepts this statement in the app:</p>
    <p class="notice"><strong>{{ $statement }}</strong></p>
    <p>We store who accepted it, which version, when, and a scrambled (hashed) form of the network address, never the address itself. When the statement changes, a teacher accepts the new version before using the Classroom tools again.</p>

    <h2>2. Who runs a Classroom</h2>
    <ul>
        <li>The owner of a Classroom group must be at least {{ $ownerMinAge }}.</li>
        @if ($maxAdults !== null)
        <li>A Classroom has {{ $maxAdults }} adult seats: the teacher and one co-teacher. Only adults can be admins.</li>
        @endif
        <li>The teacher is responsible for the names and birth years they enter and for keeping printed sign-in code cards safe.</li>
    </ul>

    <h2>3. Students</h2>
    <ul>
        <li>Students are added with a nickname and a birth year only. We do not ask for a student's email address, full name, school or photo. Please use first names or nicknames, not full names.</li>
        <li>Students sign in with a one-time code card. A card works once and expires after {{ $cardDays }} days. Printing new cards replaces the old ones.</li>
        <li>Because the teacher's consent statement covers the class, no separate parental consent is asked for each student in the app. The teacher keeps any permission their school or the parents require.</li>
        <li>Students see quizzes and puzzles suited to their age and never see ads or purchase screens.</li>
    </ul>

    <h2>4. Class summary</h2>
    <p>The teacher can download a class summary (nicknames, points, games, accuracy and streaks). The teacher is responsible for keeping that file safe and for sharing it only as their school allows.</p>

    <h2>5. End of the school year</h2>
    <p>The teacher can archive the year and start a new one: remove the students, start a new points season, delete the chat and delete the goals. Removing a student erases the student's account (it has no login of its own). Starting a new points season does not delete points; class totals simply count from the new season. This cannot be undone, so the app offers the class summary first.</p>

    <h2>6. Contact</h2>
    <p>Questions about the Classroom plan or a student's data: {{ $contact['email'] }}. A parent or guardian can also ask the teacher to remove their child from the group.</p>
@endsection
