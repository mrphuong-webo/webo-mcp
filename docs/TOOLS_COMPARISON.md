# So sánh tool: InstaWP (mcp-wordpress-instaWP) ↔ webo-wordpress-mcp

Tham chiếu: [mcp-wordpress-instaWP trên Glama](https://glama.ai/mcp/servers/pace8/mcp-wordpress-instaWP).

## Content (posts, pages, CPT)

| InstaWP | webo-wordpress-mcp | Ghi chú |
|--------|--------------------|--------|
| `list_content` | `webo/list-posts` | webo: dùng `post_type` trong args |
| `get_content` | `webo/get-post` | webo: `post_id` |
| `create_content` | `webo/create-post` | webo: `title`, `content`, `post_type`, `status` |
| `update_content` | `webo/update-post` | webo: `post_id` + fields |
| `delete_content` | `webo/delete-post` | webo: `post_id`, `force` |
| `discover_content_types` | `webo/discover-content-types` | **webo có từ bản mới** |
| `find_content_by_url` | `webo/find-content-by-url` | **webo có từ bản mới** |
| `get_content_by_slug` | `webo/get-content-by-slug` | **webo có từ bản mới** |
| — | `webo/bulk-update-post-status` | Chỉ webo |
| — | `webo/list-revisions` | Chỉ webo |
| — | `webo/restore-revision` | Chỉ webo |
| — | `webo/search-replace-posts` | Chỉ webo |

## Taxonomies

| InstaWP | webo-wordpress-mcp | Ghi chú |
|--------|--------------------|--------|
| `discover_taxonomies` | `webo/discover-taxonomies` | webo: name, label, object_type, hierarchical |
| `list_terms` | `webo/list-terms` | webo: `taxonomy`, `per_page` |
| `get_term` | `webo/get-term` | webo: `term_id`, `taxonomy` |
| `create_term` | `webo/create-term` | webo: `taxonomy`, `name`, `slug`, `description`, `parent_id` |
| `update_term` | `webo/update-term` | webo: `term_id`, `taxonomy`, … |
| `delete_term` | `webo/delete-term` | webo: `term_id`, `taxonomy` |
| `assign_terms_to_content` | `webo/assign-terms-to-content` | webo: `post_id`, `taxonomy`, `term_ids` (thay thế terms hiện có) |
| `get_content_terms` | `webo/get-content-terms` | webo: `post_id`, `taxonomy` (optional) |

## Media

| InstaWP | webo-wordpress-mcp | Ghi chú |
|--------|--------------------|--------|
| `list_media` | `webo/list-media` | |
| `get_media` | `webo/get-media` | webo: `attachment_id` |
| `create_media` | `webo/upload-media-from-url` | webo: từ URL |
| `update_media` | `webo/update-media` | webo: title, alt_text, caption |
| `delete_media` | `webo/delete-media` | |

## Users

| InstaWP | webo-wordpress-mcp | Ghi chú |
|--------|--------------------|--------|
| `list_users` | `webo/list-users` | webo: per_page, search |
| `get_user` | — | Chưa có (có thể bổ sung) |
| `create_user` | — | Chưa có |
| `update_user` | — | Chưa có |
| `delete_user` | — | Chưa có |

## Comments

| InstaWP | webo-wordpress-mcp | Ghi chú |
|--------|--------------------|--------|
| `list_comments` | `webo/list-comments` | |
| `get_comment` | `webo/get-comment` | |
| `create_comment` | — | Chưa có |
| `update_comment` | `webo/update-comment` | webo: status, reply |
| `delete_comment` | `webo/delete-comment` | |

## Plugins

| InstaWP | webo-wordpress-mcp | Ghi chú |
|--------|--------------------|--------|
| `list_plugins` | `webo/list-active-plugins` | webo: include_inactive |
| `get_plugin` | — | Có thể lấy từ list |
| `activate_plugin` | `webo/toggle-plugin` | webo: plugin path, action |
| `deactivate_plugin` | `webo/toggle-plugin` | |
| `create_plugin` | — | Chưa có (WP.org-safe) |
| `search_plugins` (WP.org) | — | Chưa có |
| `get_plugin_info` (WP.org) | — | Chưa có |

## Khác

| InstaWP | webo-wordpress-mcp | Ghi chú |
|--------|--------------------|--------|
| — | `webo/get-site-info` | Chỉ webo |
| — | `webo/get-options` | Chỉ webo (allowlist) |
| — | `webo/update-options` | Chỉ webo (allowlist) |

---

### Có thể bổ sung sau (webo)

- **Users:** `webo/get-user`, `webo/create-user`, `webo/update-user`, `webo/delete-user`
- **Comments:** `webo/create-comment`
