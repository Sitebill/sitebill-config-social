import { gql } from '@apollo/client/core'

// GraphQL-документы соцсети (configs/social/api/graphql/social.graphql).

const PROFILE = `user_id handle name bio website avatar_url followers_count following_count posts_count is_banned followed_by_me is_me`
const POST = `id body image_url likes_count comments_count created_at liked_by_me can_delete author { ${PROFILE} }`
const COMMENT = `id post_id body created_at can_delete author { ${PROFILE} }`

export const SC_CONFIG = gql`query ScConfig { socialConfig { public_feed can_post is_moderator me { ${PROFILE} } } }`
export const SC_FEED = gql`query ScFeed($scope: SocialFeedScope, $after_id: ID, $limit: Int) { socialFeed(scope: $scope, after_id: $after_id, limit: $limit) { ${POST} } }`
export const SC_POST = gql`query ScPost($id: ID!) { socialPost(id: $id) { ${POST} } }`
export const SC_USER_POSTS = gql`query ScUserPosts($user_id: Int!, $after_id: ID, $limit: Int) { socialUserPosts(user_id: $user_id, after_id: $after_id, limit: $limit) { ${POST} } }`
export const SC_COMMENTS = gql`query ScComments($post_id: Int!) { socialComments(post_id: $post_id) { ${COMMENT} } }`
export const SC_PROFILE = gql`query ScProfile($handle: String!) { socialProfile(handle: $handle) { ${PROFILE} } }`
export const SC_PEOPLE = gql`query ScPeople($query: String, $limit: Int) { socialPeople(query: $query, limit: $limit) { ${PROFILE} } }`
export const SC_CONNECTIONS = gql`query ScConnections($user_id: Int!, $kind: SocialConnectionKind, $limit: Int) { socialConnections(user_id: $user_id, kind: $kind, limit: $limit) { ${PROFILE} } }`
export const SC_ADMIN_REPORTS = gql`query ScAdminReports($status: String) { socialAdminReports(status: $status) { id target_type target_id reporter_name reason status created_at excerpt } }`
export const SC_ADMIN_MEMBERS = gql`query ScAdminMembers($query: String) { socialAdminMembers(query: $query) { ${PROFILE} } }`
export const SC_ADMIN_SETTINGS = gql`query ScAdminSettings { socialAdminSettings { public_feed posts_per_day comments_per_day } }`

export const SC_SAVE_PROFILE = gql`mutation ScSaveProfile($input: SocialProfileInput!) { socialSaveProfile(input: $input) { ${PROFILE} } }`
export const SC_CREATE_POST = gql`mutation ScCreatePost($body: String!, $image_path: String) { socialCreatePost(body: $body, image_path: $image_path) { ${POST} } }`
export const SC_DELETE_POST = gql`mutation ScDeletePost($id: ID!) { socialDeletePost(id: $id) }`
export const SC_TOGGLE_LIKE = gql`mutation ScToggleLike($post_id: Int!) { socialToggleLike(post_id: $post_id) { liked likes_count } }`
export const SC_ADD_COMMENT = gql`mutation ScAddComment($post_id: Int!, $body: String!) { socialAddComment(post_id: $post_id, body: $body) { ${COMMENT} } }`
export const SC_DELETE_COMMENT = gql`mutation ScDeleteComment($id: ID!) { socialDeleteComment(id: $id) }`
export const SC_TOGGLE_FOLLOW = gql`mutation ScToggleFollow($user_id: Int!) { socialToggleFollow(user_id: $user_id) { following followers_count } }`
export const SC_REPORT = gql`mutation ScReport($target_type: String!, $target_id: Int!, $reason: String) { socialReport(target_type: $target_type, target_id: $target_id, reason: $reason) }`
export const SC_RESOLVE_REPORT = gql`mutation ScResolveReport($id: ID!, $action: String!) { socialResolveReport(id: $id, action: $action) }`
export const SC_SET_BANNED = gql`mutation ScSetBanned($user_id: Int!, $banned: Boolean!) { socialSetBanned(user_id: $user_id, banned: $banned) { ${PROFILE} } }`
export const SC_SAVE_SETTINGS = gql`mutation ScSaveSettings($input: SocialSettingsInput!) { socialSaveSettings(input: $input) { public_feed posts_per_day comments_per_day } }`
