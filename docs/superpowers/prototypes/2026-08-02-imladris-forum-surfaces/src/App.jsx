import { useEffect, useRef, useState } from "react";
import { Sidebar, Topbar } from "./components/Chrome.jsx";
import { BoardView } from "./screens/BoardView.jsx";
import { ForumIndex } from "./screens/ForumIndex.jsx";
import { ThreadView } from "./screens/ThreadView.jsx";

const routeFromHash = () => {
  const hash = window.location.hash.replace(/^#/, "") || "/";
  if (hash.startsWith("/t/")) return "thread";
  if (hash === "/c/the-archive" || hash === "/c/the-archive/") return "board";
  return "home";
};

const useMediaQuery = (query) => {
  const [matches, setMatches] = useState(() => (
    typeof window === "undefined" ? false : window.matchMedia(query).matches
  ));

  useEffect(() => {
    const media = window.matchMedia(query);
    const onChange = (event) => setMatches(event.matches);
    setMatches(media.matches);
    media.addEventListener("change", onChange);
    return () => media.removeEventListener("change", onChange);
  }, [query]);

  return matches;
};

export function App() {
  const [route, setRoute] = useState(routeFromHash);
  const [navOpen, setNavOpen] = useState(false);
  const [notice, setNotice] = useState("");
  const navButtonRef = useRef(null);
  const isMobileNavigation = useMediaQuery("(max-width: 840px)");

  useEffect(() => {
    const onHashChange = () => {
      setRoute(routeFromHash());
      setNavOpen(false);
      document.querySelector(".main-stage")?.scrollTo({ top: 0 });
    };
    window.addEventListener("hashchange", onHashChange);
    return () => window.removeEventListener("hashchange", onHashChange);
  }, []);

  useEffect(() => {
    if (!notice) return undefined;
    const timer = window.setTimeout(() => setNotice(""), 3600);
    return () => window.clearTimeout(timer);
  }, [notice]);

  useEffect(() => {
    const onKeyDown = (event) => {
      if (event.key === "Escape" && navOpen) {
        setNavOpen(false);
        window.requestAnimationFrame(() => navButtonRef.current?.focus());
      }
    };
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [navOpen]);

  const skipToContent = (event) => {
    event.preventDefault();
    const main = document.getElementById("main-content");
    main?.focus({ preventScroll: true });
    main?.scrollTo({ top: 0 });
  };

  return (
    <div className="prototype-app" data-route={route}>
      <a className="skip-link" href="#main-content" onClick={skipToContent}>Skip to content</a>
      <Topbar navOpen={navOpen} setNavOpen={setNavOpen} setNotice={setNotice} navButtonRef={navButtonRef} />
      <div className="app-body">
        <Sidebar
          route={route}
          navOpen={navOpen}
          closeNav={() => setNavOpen(false)}
          setNotice={setNotice}
          isMobileNavigation={isMobileNavigation}
        />
        <main className="main-stage" id="main-content" tabIndex="-1">
          {route === "home" ? <ForumIndex /> : null}
          {route === "board" ? <BoardView setNotice={setNotice} /> : null}
          {route === "thread" ? <ThreadView setNotice={setNotice} /> : null}
        </main>
      </div>
      <div className={`prototype-toast${notice ? " is-visible" : ""}`} role="status" aria-live="polite">
        {notice}
      </div>
    </div>
  );
}
