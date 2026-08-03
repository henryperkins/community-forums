import { ArrowRight } from "@phosphor-icons/react";
import { categories } from "../data.js";

export function ForumIndex() {
  const totals = categories.flatMap((category) => category.boards).reduce(
    (sum, board) => ({
      boards: sum.boards + 1,
      threads: sum.threads + board.threads,
      posts: sum.posts + board.posts,
    }),
    { boards: 0, threads: 0, posts: 0 },
  );

  return (
    <div className="page page-home">
      <header className="directory-hero">
        <span className="eyebrow">Forum index</span>
        <h1>RetroBoards</h1>
        <p>Browse every board you can read. Pick a room to see its topics, or use Forum inbox when you want your personal cross-board queue.</p>
        <div className="directory-stats" aria-label="Forum totals">
          <span>{totals.boards} boards</span>
          <span>{totals.threads.toLocaleString()} topics</span>
          <span>{totals.posts.toLocaleString()} posts</span>
        </div>
      </header>

      <div className="category-directory">
        {categories.map((category) => (
          <section key={category.name} className="category-block" aria-labelledby={`category-${category.name.replaceAll(" ", "-")}`}>
            <div className="category-heading">
              <h2 id={`category-${category.name.replaceAll(" ", "-")}`}>{category.name}</h2>
              <span aria-hidden="true" />
            </div>
            <ul className="board-directory">
              {category.boards.map((board) => {
                const isPrototypeBoard = board.slug === "the-archive";
                const boardContent = (
                  <>
                    <span className="directory-copy">
                      <strong><span aria-hidden="true">#</span>{board.name}</strong>
                      <span>{board.description}</span>
                    </span>
                    <span className="directory-counts">
                      <span><strong>{board.threads}</strong> topics</span>
                      <span><strong>{board.posts.toLocaleString()}</strong> posts</span>
                    </span>
                    {isPrototypeBoard ? <ArrowRight className="directory-arrow" size={18} aria-hidden="true" /> : null}
                  </>
                );

                return (
                  <li key={board.slug}>
                    {isPrototypeBoard ? (
                      <a
                        className="directory-board is-prototype-route"
                        href="#/c/the-archive"
                        aria-label={`${board.name}, ${board.threads} topics and ${board.posts} posts`}
                      >
                        {boardContent}
                      </a>
                    ) : (
                      <div
                        className="directory-board"
                        aria-label={`${board.name}, ${board.threads} topics and ${board.posts} posts`}
                      >
                        {boardContent}
                      </div>
                    )}
                  </li>
                );
              })}
            </ul>
          </section>
        ))}
      </div>
    </div>
  );
}
