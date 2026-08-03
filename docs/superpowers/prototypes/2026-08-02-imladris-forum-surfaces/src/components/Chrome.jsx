import {
  Bell,
  ChatCircleDots,
  FileText,
  GearSix,
  House,
  List,
  MagnifyingGlass,
  Tray,
  Trophy,
  X,
} from "@phosphor-icons/react";
import { categories } from "../data.js";

const navigate = (path, closeNav) => {
  window.location.hash = path;
  closeNav?.();
};

function RailItem({ icon: Icon, label, detail, active, badge, onClick }) {
  return (
    <button
      className={`rail-item${active ? " is-active" : ""}`}
      type="button"
      onClick={onClick}
      aria-current={active ? "page" : undefined}
    >
      <Icon size={17} weight="regular" aria-hidden="true" />
      <span className="rail-item-copy">
        <span>{label}</span>
        {detail ? <small>{detail}</small> : null}
      </span>
      {badge ? <span className="rail-badge">{badge}</span> : null}
    </button>
  );
}

export function Topbar({ navOpen, setNavOpen, setNotice, navButtonRef }) {
  return (
    <header className="topbar">
      <div className="brand-cluster">
        <button
          ref={navButtonRef}
          className="icon-button nav-button"
          type="button"
          aria-label={navOpen ? "Close navigation" : "Open navigation"}
          aria-expanded={navOpen}
          onClick={() => setNavOpen(!navOpen)}
        >
          {navOpen ? <X size={20} /> : <List size={20} />}
        </button>
        <button className="brand" type="button" onClick={() => navigate("/", () => setNavOpen(false))}>
          <img src="/assets/elven-star.svg" alt="" />
          <span>RetroBoards</span>
        </button>
      </div>

      <label className="global-search">
        <MagnifyingGlass size={17} aria-hidden="true" />
        <input
          type="search"
          placeholder="Search the forum…"
          aria-label="Search the forum"
          onKeyDown={(event) => {
            if (event.key === "Enter") {
              event.preventDefault();
              setNotice("Search remains unchanged; it is outside this three-screen prototype.");
            }
          }}
        />
        <kbd>⌘K</kbd>
      </label>

      <div className="topbar-actions">
        <button className="icon-button bell-button" type="button" aria-label="Notifications" onClick={() => setNotice("Notifications are outside this prototype.")}>
          <Bell size={19} />
          <span className="notification-dot" aria-hidden="true" />
        </button>
        <button className="identity-button" type="button" onClick={() => setNotice("Profile is outside this prototype.")}>
          <span className="avatar avatar-small avatar-green">EL</span>
          <span className="presence-dot" aria-hidden="true" />
          <span className="identity-name">Elrond</span>
        </button>
        <button className="icon-button settings-button" type="button" aria-label="Settings" onClick={() => setNotice("Settings are outside this prototype.")}>
          <GearSix size={19} />
        </button>
      </div>
    </header>
  );
}

export function Sidebar({ route, navOpen, closeNav, setNotice, isMobileNavigation }) {
  const activeBoard = route === "board" || route === "thread" ? "the-archive" : null;

  return (
    <>
      <button
        className={`nav-scrim${navOpen ? " is-visible" : ""}`}
        type="button"
        tabIndex={navOpen ? 0 : -1}
        aria-label="Close navigation"
        onClick={closeNav}
      />
      <aside
        className={`sidebar${navOpen ? " is-open" : ""}`}
        aria-label="Forum navigation"
        aria-hidden={isMobileNavigation && !navOpen ? true : undefined}
        inert={isMobileNavigation && !navOpen ? true : undefined}
      >
        <nav className="primary-rail" aria-label="Main">
          <RailItem
            icon={House}
            label="Forum index"
            detail="Browse boards"
            active={route === "home"}
            onClick={() => navigate("/", closeNav)}
          />
          <RailItem
            icon={Tray}
            label="Forum inbox"
            detail="Your personal queue"
            badge="8"
            onClick={() => setNotice("Forum inbox is a separate personal, cross-board queue.")}
          />
          <RailItem
            icon={ChatCircleDots}
            label="Messages"
            detail="Private conversations"
            badge="2"
            onClick={() => setNotice("Messages are private conversations, separate from forum topics.")}
          />
          <RailItem icon={FileText} label="Drafts" onClick={() => setNotice("Drafts are outside this prototype.")} />
          <RailItem icon={Trophy} label="Top contributors" onClick={() => setNotice("Top contributors is outside this prototype.")} />
        </nav>

        <nav className="board-rail" aria-label="Boards">
          {categories.map((category) => (
            <section key={category.name} className="rail-category">
              <h2>{category.name}</h2>
              <ul>
                {category.boards.map((board) => (
                  <li key={board.slug}>
                    <button
                      type="button"
                      className={activeBoard === board.slug ? "is-active" : ""}
                      aria-current={activeBoard === board.slug ? "page" : undefined}
                      onClick={() => {
                        if (board.slug === "the-archive") {
                          navigate("/c/the-archive", closeNav);
                        } else {
                          setNotice(`#${board.name} is represented in the forum index; only #the-archive is interactive here.`);
                        }
                      }}
                    >
                      <span aria-hidden="true">#</span>
                      <span>{board.name}</span>
                    </button>
                  </li>
                ))}
              </ul>
            </section>
          ))}
        </nav>

        <section className="online-block" aria-label="Members online">
          <div>
            <span className="online-leaf" aria-hidden="true" />
            <strong>12 online</strong>
          </div>
          <p>Active in the last fifteen minutes</p>
        </section>
      </aside>
    </>
  );
}
