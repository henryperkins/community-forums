import { Check, Plus, Star, X } from "@phosphor-icons/react";
import { useState } from "react";
import { archiveThreads } from "../data.js";

function TopicRow({ topic }) {
  const statusClass = topic.status.toLowerCase().replaceAll(" ", "-");
  const isPrototypeThread = topic.id === 184;
  return (
    <li className={`topic-row${topic.unread ? " is-unread" : ""}${topic.pinned ? " is-pinned" : ""}`}>
      {topic.unread ? <span className="unread-dot" aria-label="Unread topic" /> : null}
      <span className={`avatar avatar-${topic.initials.toLowerCase()}`}>{topic.initials}</span>
      <div className="topic-copy">
        <div className="topic-flags">
          {topic.pinned ? <span className="status-chip is-neutral">Pinned</span> : null}
          {topic.status !== "Open" ? <span className={`status-chip is-${statusClass}`}>{topic.status === "Solved" ? <Check size={10} weight="bold" /> : null}{topic.status}</span> : null}
          {topic.locked ? <span className="status-chip is-neutral">Locked</span> : null}
        </div>
        {isPrototypeThread ? (
          <a className="topic-title" href={`#/t/${topic.id}-${topic.slug}`}>{topic.title}</a>
        ) : (
          <span className="topic-title topic-title-static">{topic.title}</span>
        )}
        <div className="topic-meta">
          <span>by {topic.author}</span>
          <span>{topic.replies} {topic.replies === 1 ? "reply" : "replies"}</span>
          <span>last post {topic.activity}</span>
        </div>
      </div>
      <div className="topic-trailing" aria-label={`${topic.replies} replies, last post ${topic.activity}`}>
        <strong>{topic.replies}</strong>
        <span>replies</span>
        <time>{topic.activity}</time>
      </div>
    </li>
  );
}

export function BoardView({ setNotice }) {
  const [composerOpen, setComposerOpen] = useState(false);
  const [following, setFollowing] = useState(true);
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");

  const submitTopic = (event) => {
    event.preventDefault();
    if (!title.trim() || !body.trim()) {
      setNotice("Add both a title and opening post to preview topic creation.");
      return;
    }
    setNotice("Topic created in the prototype. Production keeps the board fixed by the route.");
    setTitle("");
    setBody("");
    setComposerOpen(false);
  };

  return (
    <div className="page page-board">
      <nav className="breadcrumb" aria-label="Breadcrumb">
        <a href="#/">Forum index</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">#the-archive</span>
      </nav>

      <header className="board-hero">
        <div className="board-hero-copy">
          <span className="eyebrow">Board</span>
          <h1><span aria-hidden="true">#</span>the-archive</h1>
          <p>Durable decisions, ratified guidance, and the history behind both.</p>
          <div className="board-facts">
            <span>142 topics</span>
            <span>1,144 posts</span>
            <span>Public board</span>
          </div>
        </div>
        <div className="board-actions">
          <button
            className={`button button-secondary${following ? " is-following" : ""}`}
            type="button"
            aria-pressed={following}
            onClick={() => {
              setFollowing(!following);
              setNotice(following ? "Board removed from your discovery feed." : "Board added to your discovery feed.");
            }}
          >
            <Star size={16} weight={following ? "fill" : "regular"} />
            {following ? "Following" : "Follow board"}
          </button>
          <button className="button button-primary" type="button" onClick={() => setComposerOpen(true)} aria-expanded={composerOpen}>
            <Plus size={17} weight="bold" />
            New topic
          </button>
        </div>
      </header>

      <p className="follow-note">Following affects your discovery feed; it does not change this board’s order.</p>

      {composerOpen ? (
        <section className="new-topic-card" aria-labelledby="new-topic-heading">
          <div className="composer-card-heading">
            <div>
              <span className="eyebrow">Posting to #the-archive</span>
              <h2 id="new-topic-heading">Start a new topic</h2>
            </div>
            <button className="icon-button" type="button" aria-label="Close new topic composer" onClick={() => setComposerOpen(false)}>
              <X size={20} />
            </button>
          </div>
          <form onSubmit={submitTopic}>
            <label>
              <span>Title</span>
              <input value={title} onChange={(event) => setTitle(event.target.value)} placeholder="What should this board consider?" maxLength={160} />
            </label>
            <label>
              <span>Opening post</span>
              <textarea value={body} onChange={(event) => setBody(event.target.value)} placeholder="Add the context readers will need…" rows={5} />
            </label>
            <div className="composer-actions">
              <button className="button button-quiet" type="button" onClick={() => setComposerOpen(false)}>Cancel</button>
              <button className="button button-primary" type="submit">Post topic</button>
            </div>
          </form>
        </section>
      ) : null}

      <section className="topics-section" aria-labelledby="topics-heading">
        <div className="list-heading">
          <div>
            <span className="eyebrow">Latest activity</span>
            <h2 id="topics-heading">Topics</h2>
          </div>
          <p>Pinned first, then last post</p>
        </div>
        <ul className="topic-list">
          {archiveThreads.map((topic) => <TopicRow key={topic.id} topic={topic} />)}
        </ul>
        <nav className="pagination" aria-label="Topic pages">
          <button type="button" disabled>Previous</button>
          <span>Page 1 of 8</span>
          <button type="button" onClick={() => setNotice("Pagination remains a normal server route in production.")}>Next</button>
        </nav>
      </section>
    </div>
  );
}
