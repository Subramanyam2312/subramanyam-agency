<?php

$this->extend('layouts/site');

/* Founder portrait, cache-busted by mtime (same asset as the home hero). */
$founderPath = '/uploads/founder/founder.jpg';
$founderFile = PUBLIC_PATH . $founderPath;
$founderImg  = is_file($founderFile) ? $founderPath . '?v=' . filemtime($founderFile) : '';

/* The five-step method. */
$approach = [
    ['t' => 'Understand the brand before touching anything',
     'b' => "I don't start with tactics. I start with what the brand actually is, who it's really for, and where the current funnel leaks. Get that read wrong and every ad after it is wasted — often the real moat is community or trust, not the spec sheet."],
    ['t' => 'Creative that earns attention',
     'b' => 'The creative is the targeting. I shoot and edit video — reels and long-form — and structure it around hooks, pacing, and story, not just "make it look nice." I\'ve built everything from UGC-style ads to a full cinematic brand film.'],
    ['t' => 'Build for the local audience, in the local language',
     'b' => "Scripts and voiceovers in English, Tamil, and Tanglish depending on who's watching. A message that lands in Chennai isn't the same one that lands nationally, and pretending otherwise is how good budgets get wasted."],
    ['t' => 'SEO and structure so the work compounds',
     'b' => 'Ad spend stops the day you stop paying. Search and on-page structure keep working. I treat technical SEO, keyword strategy, and content as the part of the funnel that builds equity over time.'],
    ['t' => 'Show the work honestly',
     'b' => "If something isn't working, I say so early instead of dressing it up. Correcting course fast is cheaper than defending a losing plan."],
];

/* Real client engagements. */
$clients = [
    ['n' => 'Acme Drones', 'm' => 'My own FPV drone build service', 'href' => 'https://example.com',
     'b' => "A custom FPV drone build brand I run myself. Because it's mine, it's where I test everything first — including an AI content system that writes and publishes SEO blog posts straight to the site's custom CMS. If an idea can't survive on my own brand, I don't sell it."],
    ['n' => 'Northwind Packaging', 'm' => 'Packaging manufacturer · Chennai',
     'b' => "Recurring content for a B2B manufacturer — Instagram Reel packages for their flagship product (scripts, shot lists and voiceovers across English, Tamil and Tanglish), plus LinkedIn headlines and 3D packaging poster design. B2B doesn't get a pass on good creative."],
    ['n' => 'Cobblestone Footwear', 'm' => 'Footwear brand · Chennai',
     'b' => 'Advertising creative including UGC-style ads and "Sample Brand Film" — a cinematic brand film in a regional language, produced with AI video tools and documented end to end.'],
    ['n' => 'Beacon Labs', 'm' => 'Drone education',
     'b' => 'Training content for their drone education vertical — speaker scripts and branded presentation decks.'],
];

/* Validated skills. */
$credentials = [
    ['t' => 'SEO & WordPress', 'b' => 'A technical certification covering technical SEO, structured data, keyword strategy and hosting.'],
    ['t' => 'AI-assisted content & video', 'b' => 'Prompt engineering and AI video production, used daily across client and personal projects.'],
    ['t' => 'Video editing & production', 'b' => 'Hands-on shooting and editing, phone-first workflow.'],
];

/* About-page FAQs (distinct from the /faq page). */
$faqs = [
    ['question' => 'What exactly do you do — strategy, or content?',
     'answer'   => "Both, and that's the point. I build the brand and media strategy and the creative that executes it, so nothing gets lost in the handoff between a strategist and a separate content team."],
    ['question' => 'What makes your video work different?',
     'answer'   => 'I use AI-assisted production, but built on real editing skill and a phone-first workflow. The tools speed things up; knowing how to hold attention is what makes them actually work. I shoot and cut across reels and long-form, in English, Tamil and Tanglish.'],
    ['question' => 'Do you only work with big brands?',
     'answer'   => 'No. I keep a small client roster and work with both new brands on tight budgets and established ones. For smaller brands, the whole approach is testing before scaling so nothing gets wasted.'],
    ['question' => 'Can you handle work in Tamil / for a South Indian audience?',
     'answer'   => "Yes — that's a core part of what I do, not a translation step bolted on at the end. Scripts, voiceovers and cultural cues are built for the local audience from the start."],
];
?>

<?php $this->start('content'); ?>

<?= $this->include('partials/page-hero', [
    'eyebrow' => 'About · Subramanyam M N',
    'heading' => 'Strategy and creative, under one roof',
    'lede'    => 'I\'m a digital marketing strategist and content creator in Chennai — building brand strategy, ad creative, SEO and AI-assisted video for brands across South India.',
]) ?>

<!-- ============================================================ INTRO -->
<section class="section rule">
    <div class="container-site grid items-center gap-12 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <p class="section-index">More than just a content guy</p>
            <h2 class="display-lg reveal mt-3" data-split>The thinking and the making, in one place</h2>
            <div class="prose-body reveal mt-8 space-y-5">
                <p>I work across the parts of marketing most people split into separate hires — brand and media strategy, advertising creative, SEO and video production. I handle both the thinking and the making, for a handful of clients at a time.</p>
                <p>Most brands hit the same wall: the strategy lives in one deck, the creative lives in another head, and the two never quite line up. I work end to end so they do — the plan and the thing you actually post come from the same place.</p>
                <p>I work a lot in Tamil and South Indian advertising contexts, so the language, the cultural cues and the local consumer behaviour aren't an afterthought — they're built in from the first draft.</p>
            </div>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="/contact" class="btn-bone">Work with me</a>
                <a href="/services" class="btn-outline">See my services</a>
            </div>
        </div>

        <?php if ($founderImg !== ''): ?>
            <div class="lg:col-span-4 lg:col-start-9">
                <div class="about-portrait-wrap reveal">
                    <img src="<?= e($founderImg) ?>" class="about-portrait"
                         alt="Subramanyam M N, digital marketing strategist and content creator in Chennai"
                         width="1080" height="1446" loading="lazy" decoding="async">
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ======================================================= PHILOSOPHY -->
<section class="section rule" aria-labelledby="philosophy-heading">
    <div class="container-site grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <p class="section-index">My philosophy</p>
            <h2 id="philosophy-heading" class="display-lg reveal mt-3" data-split>Evidence over elegance</h2>
        </div>
        <div class="lg:col-span-6 lg:col-start-7">
            <div class="prose-body reveal space-y-5">
                <p>The rule I work by: if something sounds correct but doesn't come from anything real or testable, I don't trust it enough to pass it on to a client.</p>
                <p>A lot of marketing advice is coherent, confident and wrong. It sounds right in a meeting and falls apart the moment it meets a real audience. So I anchor decisions in what's actually been observed — what a video's retention curve is doing, which hook held attention, which segment actually converted — instead of what looks good on a slide.</p>
            </div>
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <div class="card-flat">
                    <p class="eyebrow">New &amp; small brands</p>
                    <p class="prose-body mt-3 text-sm">You don't have budget to waste on guesses. I keep things lean and test before scaling, so the money goes where there's evidence it works.</p>
                </div>
                <div class="card-flat">
                    <p class="eyebrow">Established brands</p>
                    <p class="prose-body mt-3 text-sm">I plug into what you already have, read the existing data honestly — including the parts that aren't flattering — and build from there.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================= APPROACH -->
<section class="section rule" aria-labelledby="approach-heading">
    <div class="container-site">
        <p class="section-index">How I work</p>
        <h2 id="approach-heading" class="display-lg reveal mt-3" data-split>My approach</h2>

        <ol class="mt-12 divide-y divide-line/60 border-y border-line/60">
            <?php foreach ($approach as $i => $step): ?>
                <li class="reveal grid gap-4 py-8 sm:grid-cols-12">
                    <div class="sm:col-span-4">
                        <p class="font-mono text-sm text-accent"><?= sprintf('%02d', $i + 1) ?></p>
                        <h3 class="display-md mt-2"><?= e($step['t']) ?></h3>
                    </div>
                    <p class="prose-body text-sm sm:col-span-7 sm:col-start-6"><?= e($step['b']) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<!-- ================================================ CREATIVE ADVANTAGE -->
<section class="section rule" aria-labelledby="creative-heading">
    <div class="container-site grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <p class="section-index">The creative advantage</p>
            <h2 id="creative-heading" class="display-lg reveal mt-3" data-split>AI-assisted video, built on real editing</h2>
        </div>
        <div class="prose-body reveal space-y-5 lg:col-span-6 lg:col-start-7">
            <p>I use AI-assisted video production, but on top of real hands-on editing — not as a replacement for knowing how a video is actually put together.</p>
            <p>I shoot and cut entirely on the phone, reels and long-form both. That's deliberate, not a limitation — it keeps the workflow fast, cheap and close to how the audience actually watches. Knowing how to hold a hook, pace a cut and structure a story is what makes the AI tooling useful instead of gimmicky. The tools speed up the boring parts; the judgement about what makes something watchable is still the work.</p>
            <p class="border-l-2 border-accent/50 pl-5 text-body">For Cobblestone Footwear, a Chennai footwear brand, I made a cinematic brand film — "Sample Brand Film" — regional-language dialogue, produced with AI video tools and documented end to end. Same brand, also UGC-style ads and a family brand film. The range matters: the right format depends on the goal, not on what I happen to be comfortable making.</p>
        </div>
    </div>
</section>

<!-- ===================================================== TRACK RECORD -->
<section class="section rule" aria-labelledby="track-heading">
    <div class="container-site">
        <p class="section-index">Track record</p>
        <h2 id="track-heading" class="display-lg reveal mt-3" data-split>Work across different industries</h2>
        <p class="lede mt-6">I've worked across drones, packaging, footwear and automotive strategy — different enough that I've had to actually understand each business rather than run one playbook everywhere.</p>

        <div class="mt-12 grid gap-6 sm:grid-cols-2">
            <?php foreach ($clients as $c): ?>
                <article class="card reveal lift p-7">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="display-md"><?= e($c['n']) ?></h3>
                        <?php if (!empty($c['href'])): ?>
                            <a href="<?= e($c['href']) ?>" target="_blank" rel="noopener"
                               class="shrink-0 text-xs text-accent hover:underline">Visit&nbsp;&#8599;</a>
                        <?php endif; ?>
                    </div>
                    <p class="eyebrow mt-2"><?= e($c['m']) ?></p>
                    <p class="prose-body mt-4 text-sm"><?= e($c['b']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="prose-body mt-10 max-w-2xl text-sm text-muted">I'm also building my own agency practice — bringing this strategy-plus-creative approach under one roof instead of scattering it across freelancers.</p>
    </div>
</section>

<!-- ====================================================== CREDENTIALS -->
<section class="section rule" aria-labelledby="credentials-heading">
    <div class="container-site grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <p class="section-index">Credentials</p>
            <h2 id="credentials-heading" class="display-lg reveal mt-3" data-split>Skills I've actually validated</h2>
            <p class="prose-body mt-6 text-sm text-muted">I'd rather show the work than list badges. But where I've formally trained, it's training I use on real projects.</p>
        </div>
        <ul class="space-y-4 lg:col-span-6 lg:col-start-7">
            <?php foreach ($credentials as $cred): ?>
                <li class="card-flat reveal">
                    <h3 class="text-base font-medium text-body"><?= e($cred['t']) ?></h3>
                    <p class="prose-body mt-2 text-sm"><?= e($cred['b']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<!-- ========================================================== JOURNEY -->
<section class="section rule" aria-labelledby="journey-heading">
    <div class="container-site grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <p class="section-index">The journey</p>
            <h2 id="journey-heading" class="display-lg reveal mt-3" data-split>How I got here</h2>
        </div>
        <div class="prose-body reveal space-y-5 lg:col-span-6 lg:col-start-7">
            <p>I didn't start with a fixed playbook — I built the approach by doing the work across very different clients and watching what held up. A drone brand, a packaging manufacturer and a footwear label don't share a template. What they share is a method: understand the business first, ground every decision in something observable, and make the creative earn its place.</p>
            <p>That's also why I run my own brand, Acme Drones. It's the one place I can test an idea with real money and real stakes before recommending it to anyone else. If it works there, I trust it. If it doesn't, it doesn't leave the lab.</p>
            <ul class="space-y-3 border-l-2 border-accent/40 pl-5 text-sm text-body">
                <li>I don't just "make content" — I build the strategy and the creative so they point the same direction.</li>
                <li>I work natively in Tamil and South Indian contexts, which most national playbooks miss.</li>
            </ul>
        </div>
    </div>
</section>

<!-- ============================================================== FAQ -->
<section class="section rule" aria-labelledby="about-faq-heading">
    <div class="container-site grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <p class="section-index">FAQ</p>
            <h2 id="about-faq-heading" class="display-lg reveal mt-3" data-split>Common questions</h2>
        </div>
        <div class="lg:col-span-7 lg:col-start-6">
            <?= $this->include('partials/accordion', ['items' => $faqs, 'level' => 3]) ?>
        </div>
    </div>
</section>

<!-- ============================================================== CTA -->
<section class="section rule">
    <div class="container-site text-center">
        <p class="eyebrow">Let's work together</p>
        <h2 class="display-lg gilt reveal mx-auto mt-4 max-w-[22ch]">Strategy and creative from the same place</h2>
        <p class="lede mx-auto mt-6">If you want decisions grounded in what actually works rather than what sounds good on a deck — let's talk.</p>
        <div class="mt-9 flex flex-wrap justify-center gap-3">
            <a href="/contact" class="btn-bone">Get in touch</a>
            <a href="/services" class="btn-outline">See all services</a>
        </div>
    </div>
</section>

<?php $this->stop(); ?>
