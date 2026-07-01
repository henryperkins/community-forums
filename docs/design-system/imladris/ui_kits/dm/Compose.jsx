/* Messages kit — the new-message composer (right pane). Mirrors dm/new.php:
   recipients, an optional group title, and the body. */
(function () {
  const chev = <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 18l-6-6 6-6" /></svg>;

  function Compose({ onBack, onSend }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { Input, Textarea, Button } = DS;
    const [to, setTo] = React.useState('');
    const [title, setTitle] = React.useState('');
    const [body, setBody] = React.useState('');
    const isGroup = to.includes(',');

    return (
      <section className="dm-compose">
        <div className="dm-compose-wrap">
          <button className="breadcrumb" onClick={onBack}>{chev} Messages</button>
          <h1>New message</h1>
          <form className="dm-form" onSubmit={(e) => { e.preventDefault(); onSend(); }}>
            <Input label="To" value={to} onChange={(e) => setTo(e.target.value)}
              placeholder="username, username" maxLength={255} />
            <p className="field-hint">Separate multiple usernames with commas to start a group.</p>

            {isGroup ? (
              <Input label="Group title" value={title} onChange={(e) => setTitle(e.target.value)}
                placeholder="Optional" maxLength={120} />
            ) : null}

            <Textarea label="Message" rows={6} value={body} onChange={(e) => setBody(e.target.value)}
              placeholder="Write your counsel…" maxLength={5000} />

            <div className="form-actions">
              <Button type="submit" disabled={!to.trim() || !body.trim()}>Send message</Button>
              <Button type="button" variant="ghost" onClick={onBack}>Cancel</Button>
            </div>
          </form>
        </div>
      </section>
    );
  }
  window.DMCompose = Compose;
})();
