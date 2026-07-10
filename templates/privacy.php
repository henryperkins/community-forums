<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Privacy');
$this->section('description', 'How community content and account data are handled.');
?>
<article class="content-page">
    <h1>Privacy</h1>
    <section id="thread-intelligence" aria-labelledby="thread-intelligence-heading">
        <h2 id="thread-intelligence-heading">Thread intelligence</h2>
        <p>eligible public post text may be processed by OpenAI to prepare living summaries and explanations for related public discussions.</p>
        <p>Private and hidden content is excluded, and account metadata is not included in these requests.</p>
        <p>Provider storage is disabled by the application request. Member-facing pages show the resulting brief and its current sources, but do not expose model or runtime evidence.</p>
    </section>
</article>
