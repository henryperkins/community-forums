# Redundant index cleanup — migrations 0079 + 0080

**Date:** 2026-08-04 · **Owner:** Henry (lakefrontdigital.io) · **Trigger:** PlanetScale
schema recommendation on `hello-hperkins/imladris-db` ("Remove redundant index
`idx_user_profile_field_user` on `user_profile_fields`", issue #3).

Per PRODUCT_DESIGN §13, this file is the completion evidence for the two migrations. A
dropped index is a behavioural change to every query that could have used it, so
"the ALTER succeeded" is not evidence on its own — what follows is the plan and
constraint proof.

## 1. Scope: the whole schema, not just the reported index

The reported index was verified, then the same class of defect was swept for
across all 116 tables of the fully migrated schema (`information_schema.STATISTICS`,
self-joined on same-table indexes whose column tuple is a leftmost prefix of
another's; FULLTEXT/SPATIAL and prefix-`SUB_PART` indexes excluded because their
coverage cannot be compared on column names alone). Exactly three non-unique
BTREE indexes were redundant:

| Table | Redundant index | Covered by | Origin |
|---|---|---|---|
| `user_profile_fields` | `idx_user_profile_field_user (user_id, position)` | `uq_user_profile_field_position (user_id, position)` | exact duplicate, both from `0062` |
| `conversation_participants` | `idx_cp_user (user_id)` | `idx_cp_active_user (user_id, left_at)` | `0025`, superseded by `0048` |
| `link_previews` | `idx_preview_source (source_type, source_id)` | `uq_preview_source_url (source_type, source_id, url_hash)` | both from `0058` |

Migration `0079` drops the first, `0080` the other two. Both migrations guard
each direction with `information_schema` so an out-of-band drop or re-add — for
example the recommendation applied from the PlanetScale console before the
container's next boot — cannot break the migration run.

## 2. Rehearsal method

A throwaway database (`retroboards_idxproof`) was migrated from empty through
all 80 migrations, then seeded with synthetic volume so the optimizer had real
statistics to work with (`ANALYZE TABLE` after seeding):

- `conversations` 5 000 rows
- `conversation_participants` 10 000 rows (2 per conversation, ~14 % `left_at` set, `user_id` spread over 500 accounts)
- `link_previews` 8 000 rows (3 source types, 2 000 source ids, 4 statuses)

`EXPLAIN` was captured with both indexes present, the migration applied, and the
same statements re-explained.

## 3. Read-path coverage: before → after

| # | Query (source) | Before | After |
|---|---|---|---|
| Q1 | `WHERE user_id = ? AND left_at IS NULL` (`ConversationRepository::listForUser`) | `index_merge` **intersect**(`idx_cp_user`,`idx_cp_active_user`) | `ref` on `idx_cp_active_user`, key_len 14 |
| Q2 | `DELETE … WHERE user_id = ?` (`AccountLifecycleService`) | `range` on `idx_cp_user`, 20 rows | `range` on `idx_cp_active_user`, 20 rows |
| Q3 | inbox join `me → conversations` (`listForUser`) | `ref` on `idx_cp_active_user`, Using index | unchanged |
| Q4 | `WHERE source_type = ? AND source_id IN (…) AND status = 'fetched'` (`LinkPreviewService::cardsForSources`) | `range` on `uq_preview_source_url` | unchanged |
| Q5 | `WHERE user_id = ? ORDER BY position` (`UserProfileFieldRepository::forUser`) | — | `ref` on `uq_user_profile_field_position`, no filesort |

No query lost an index. Q1 **improved**: with both indexes present the optimizer
chose an index-merge intersection of two indexes to answer a predicate that the
composite alone satisfies; dropping the prefix index collapsed it to a single
`ref` lookup. The link-preview upsert (`INSERT … ON DUPLICATE KEY UPDATE`)
resolves through `uq_preview_source_url`, which is untouched.

## 4. Foreign keys still covered and still enforced

Both dropped `user_id` indexes sat under an `ON DELETE CASCADE` foreign key.
InnoDB refuses to drop the last index covering a constraint, so the successful
`ALTER` is itself part of the proof; enforcement was then probed directly by
inserting an orphan after the drop:

```
-- user_profile_fields (0079, dev DB)
ERROR 1452 … CONSTRAINT `fk_user_profile_field_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
-- conversation_participants (0080, scratch DB)
ERROR 1452 … CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
```

`user_id` remains the leftmost column of `uq_user_profile_field_position` and
`idx_cp_active_user` respectively. `link_previews` has no foreign key on
`(source_type, source_id)` — the reference is polymorphic.

## 5. Reversibility and idempotency

`0080`'s `up()`/`down()` were driven directly against the seeded scratch DB in
the sequence up → up → down → down → up. Each pair was a no-op on the second
call and the index set returned exactly to its prior state, confirming the
`information_schema` guards work in both directions:

```
after up()          conversation_participants: fk_cp_removed_by, idx_cp_active_user, PRIMARY
                    link_previews:             idx_preview_status, PRIMARY, uq_preview_source_url
after down()        conversation_participants: fk_cp_removed_by, idx_cp_active_user, idx_cp_user, PRIMARY
                    link_previews:             idx_preview_source, idx_preview_status, PRIMARY, uq_preview_source_url
```

## 6. What the drops reclaim

Measured from `mysql.innodb_index_stats` on the seeded scratch DB:

| Index | Rows | Size |
|---|---|---|
| `conversation_participants.idx_cp_user` | 10 000 | 240 KB |
| `link_previews.idx_preview_source` | 8 000 | 208 KB |

Roughly 25 bytes per row each — the durable win is the removed write
amplification on every insert and delete, not the disk.

## 7. Regression guard

`tests/Integration/Core/AppSchemaIndexHygieneTest` asserts the invariant against
the migrated test schema: no non-unique BTREE index may be a leftmost prefix of
another index on the same table, and the three dropped indexes stay dropped while
their covering indexes survive. A reintroduction now fails `composer test`
instead of reappearing as a provider advisory.

## 8. Applying to production

Production migrations run in the container entrypoint (`RUN_MIGRATIONS=true`,
see `docs/runbooks/deployment-cloudflare.md` §2), so both migrations land on
`imladris-db` at the next deploy of the Cloudflare production worktree. The
alternative — applying the two `ALTER`s as isolated PlanetScale deploy requests
with the revert window open, which is that provider's own guidance for index
recommendations — is compatible: the guards make the migrations no-op if the
index is already gone. `DROP INDEX` is an in-place, metadata-only operation in
InnoDB; it does not rebuild the table.
