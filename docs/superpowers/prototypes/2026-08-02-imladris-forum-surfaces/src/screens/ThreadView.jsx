import {
  BellSimple,
  Check,
  CheckCircle,
  DotsThree,
  PaperPlaneTilt,
  Star,
  X,
} from "@phosphor-icons/react";
import { useCallback, useEffect, useRef, useState } from "react";
import { threadPosts } from "../data.js";

const focusPost = (postId) => {
  const target = document.getElementById(`post-${postId}`);
  target?.scrollIntoView({ block: "start" });
  target?.focus({ preventScroll: true });
};

function Post({ post }) {
  return (
    <article className={`post${post.accepted ? " is-accepted" : ""}`} id={`post-${post.id}`} tabIndex="-1">
      <span className={`avatar avatar-${post.initials.toLowerCase()}`}>{post.initials}</span>
      <div className="post-content">
        <header className="post-header">
          <div>
            <strong>{post.author}</strong>
            {post.role ? <span className={`role-chip${post.accepted ? " is-accepted" : ""}`}>{post.accepted ? <CheckCircle size={12} weight="fill" /> : null}{post.role}</span> : null}
          </div>
          <time>{post.time}</time>
          <button className="post-menu" type="button" aria-label={`More actions for ${post.author}'s post`}>
            <DotsThree size={20} />
          </button>
        </header>
        {post.body.map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
        <footer className="post-footer">
          <button type="button">Quote</button>
          <button type="button">Commend</button>
          <a
            href={`#post-${post.id}`}
            onClick={(event) => {
              event.preventDefault();
              focusPost(post.id);
            }}
          >
            #{post.id}
          </a>
        </footer>
      </div>
    </article>
  );
}

export function ThreadView({ setNotice }) {
  const [starred, setStarred] = useState(false);
  const [toolsOpen, setToolsOpen] = useState(false);
  const [pollChoice, setPollChoice] = useState("");
  const [reply, setReply] = useState("");
  const [replies, setReplies] = useState(threadPosts);
  const toolsTriggerRef = useRef(null);
  const toolsDrawerRef = useRef(null);
  const toolsCloseRef = useRef(null);

  const closeTools = useCallback(() => {
    setToolsOpen(false);
    window.requestAnimationFrame(() => toolsTriggerRef.current?.focus());
  }, []);

  useEffect(() => {
    if (!toolsOpen) return undefined;

    const focusFrame = window.requestAnimationFrame(() => toolsCloseRef.current?.focus());
    const onKeyDown = (event) => {
      if (event.key === "Escape") {
        event.preventDefault();
        closeTools();
        return;
      }

      if (event.key !== "Tab") return;
      const focusable = toolsDrawerRef.current?.querySelectorAll(
        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      );
      if (!focusable?.length) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    window.addEventListener("keydown", onKeyDown);
    return () => {
      window.cancelAnimationFrame(focusFrame);
      window.removeEventListener("keydown", onKeyDown);
    };
  }, [closeTools, toolsOpen]);

  const submitReply = (event) => {
    event.preventDefault();
    if (!reply.trim()) {
      setNotice("Write a reply before posting.");
      return;
    }
    setReplies((currentReplies) => {
      const nextReplyId = Math.max(452, ...currentReplies.map((item) => item.id)) + 1;
      return [
        ...currentReplies,
        {
          id: nextReplyId,
          author: "Elrond",
          initials: "EL",
          time: "just now",
          body: [reply.trim()],
        },
      ];
    });
    setReply("");
    setNotice("Reply added to the prototype thread.");
  };

  return (
    <div className="page page-thread">
      <article className="thread-study">
        <nav className="breadcrumb" aria-label="Breadcrumb">
          <a href="#/">Forum index</a>
          <span aria-hidden="true">/</span>
          <a href="#/c/the-archive">#the-archive</a>
        </nav>

        <header className="thread-hero">
          <div className="thread-title-line">
            <span className="status-chip is-solved"><Check size={10} weight="bold" />Solved</span>
            <h1>Where should ratified decisions live once the council has spoken?</h1>
          </div>
          <div className="thread-meta-row">
            <span>Opened by Erestor</span>
            <span>42 replies</span>
            <span>Tended by @Elrond</span>
            <a className="tag-link" href="#/c/the-archive">governance</a>
            <a className="tag-link" href="#/c/the-archive">records</a>
          </div>
          <div className="thread-participants-row">
            <span className="label-text">In council</span>
            <div className="participant-stack" aria-label="Participants: Erestor, Glorfindel, Arwen, and 14 others">
              <span className="avatar avatar-mini avatar-er">ER</span>
              <span className="avatar avatar-mini avatar-gl">GL</span>
              <span className="avatar avatar-mini avatar-ar">AR</span>
              <span className="avatar avatar-mini avatar-more">+14</span>
            </div>
            <div className="thread-actions">
              <button
                className={`button button-secondary button-small${starred ? " is-starred" : ""}`}
                type="button"
                aria-pressed={starred}
                onClick={() => setStarred(!starred)}
              >
                <Star size={15} weight={starred ? "fill" : "regular"} />
                {starred ? "Starred" : "Star"}
              </button>
              <button ref={toolsTriggerRef} className="button button-secondary button-small" type="button" aria-expanded={toolsOpen} onClick={() => setToolsOpen(true)}>
                <img src="/assets/commend-star.svg" alt="" />
                Topic tools
              </button>
            </div>
          </div>
        </header>

        <section className="poll-card" aria-labelledby="poll-heading">
          <div className="panel-heading">
            <span className="eyebrow">Poll · choose one</span>
            <span className="status-chip is-neutral">Open</span>
          </div>
          <h2 id="poll-heading">Where should ratified decisions live?</h2>
          <form onSubmit={(event) => {
            event.preventDefault();
            setNotice(pollChoice ? "Vote recorded in the prototype." : "Choose an option before voting.");
          }}>
            {[
              "A pinned Decisions topic per board",
              "A shared wiki, one page per season",
              "A quarterly ledger post",
            ].map((choice) => (
              <label key={choice} className="poll-option">
                <input type="radio" name="poll" value={choice} checked={pollChoice === choice} onChange={() => setPollChoice(choice)} />
                <span>{choice}</span>
              </label>
            ))}
            <div className="poll-footer">
              <span>Open to the council</span>
              <button className="button button-secondary button-small" type="submit">Vote</button>
            </div>
          </form>
        </section>

        <section className="living-brief" aria-labelledby="brief-heading">
          <div className="panel-heading">
            <span className="eyebrow">AI-generated living brief</span>
            <span className="panel-meta">Updated automatically · version 3</span>
          </div>
          <h2 id="brief-heading">Where the discussion stands</h2>
          <p>The council is converging on treating each verdict as a standalone artifact. A short written decision, with its evidence trail attached, remains pinned above the discussion that produced it.</p>
          <div className="brief-sources">
            <span>Sources</span>
            <a href="#post-441" onClick={(event) => { event.preventDefault(); focusPost(441); }}>Post #441 by Erestor</a>
            <a href="#post-447" onClick={(event) => { event.preventDefault(); focusPost(447); }}>Post #447 by Glorfindel</a>
          </div>
        </section>

        <section className="post-stream" aria-label="Topic replies">
          {replies.map((post) => <Post key={post.id} post={post} />)}
        </section>

        <form className="reply-composer" onSubmit={submitReply}>
          <div className="reply-identity">
            <span className="avatar avatar-small avatar-green">EL</span>
            <span><strong>Add your reply</strong><small>Posting as Elrond</small></span>
          </div>
          <textarea value={reply} onChange={(event) => setReply(event.target.value)} placeholder="Answer the strongest reading of the post…" rows={4} aria-label="Reply" />
          <div className="reply-footer">
            <span>Markdown supported</span>
            <button className="button button-primary" type="submit">
              <PaperPlaneTilt size={16} weight="fill" />
              Post reply
            </button>
          </div>
        </form>
      </article>

      <button className={`drawer-scrim${toolsOpen ? " is-visible" : ""}`} type="button" tabIndex={toolsOpen ? 0 : -1} aria-label="Close topic tools" onClick={closeTools} />
      <aside
        ref={toolsDrawerRef}
        className={`topic-tools${toolsOpen ? " is-open" : ""}`}
        role="dialog"
        aria-modal={toolsOpen ? "true" : undefined}
        aria-labelledby="topic-tools-heading"
        aria-hidden={!toolsOpen}
        inert={!toolsOpen ? true : undefined}
      >
        <header>
          <img src="/assets/commend-star.svg" alt="" />
          <div>
            <span className="eyebrow">The Study</span>
            <h2 id="topic-tools-heading">Topic tools</h2>
          </div>
          <button ref={toolsCloseRef} className="icon-button" type="button" aria-label="Close topic tools" onClick={closeTools}><X size={20} /></button>
        </header>
        <div className="tools-body">
          <section>
            <h3><BellSimple size={17} />Watch</h3>
            <label>
              <span>Notifications</span>
              <select defaultValue="all">
                <option value="all">Every new reply</option>
                <option value="mentions">Mentions only</option>
                <option value="none">Not watching</option>
              </select>
            </label>
          </section>
          <section>
            <h3>Standing</h3>
            <p><span className="status-chip is-solved"><Check size={10} weight="bold" />Solved</span></p>
            <button className="text-button" type="button" onClick={() => setNotice("Status history remains available in the canonical thread.")}>View status history</button>
          </section>
          <section>
            <h3>Tags</h3>
            <div className="tool-tags"><span>governance</span><span>records</span></div>
          </section>
        </div>
      </aside>
    </div>
  );
}
