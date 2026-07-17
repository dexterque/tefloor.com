# Blog Pagination Design

## Goal

Show nine published posts per page on the custom Blog page and make older posts reachable through standard WordPress pagination URLs.

## Design

- Keep the existing `page-blog.php` template and `weiyintex_render_blog_posts()` renderer.
- Change the renderer's default page size from 12 to 9.
- Read the current page from WordPress query variables and pass it to the custom `WP_Query` as `paged`.
- Render WordPress pagination links below the post grid using the custom query's `max_num_pages` value.
- Preserve `/blog/` as page one and use `/blog/page/2/`, `/blog/page/3/`, and so on for subsequent pages.
- Add narrowly scoped pagination styles that fit the existing Blog card design and remain usable on mobile.

## Empty and Boundary States

- Keep the existing empty-state message when there are no published posts.
- Do not render pagination when the result has only one page.
- Requests beyond the final page may show the existing empty state; no custom redirect is introduced.

## Verification

- A regression test must fail against the current implementation and verify that the renderer defaults to nine posts, supplies a page number to `WP_Query`, and outputs pagination based on `max_num_pages`.
- PHP syntax checks must pass for every changed PHP file.
- The complete project test command, when available, must pass before commit.
- The final Git diff must contain only the pagination change, its tests, and this design document.
