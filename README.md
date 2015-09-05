XenForo-ConversationImprovements
======================

A collection of improvements to the XenForo Conversation system.

Features:
- Deadlock workaround.
- Adds conversation search, with options to search by recipient.
- New Conversation Permissions
- Adds an 'IP' button like posts have which allows the IP of the user to be viewed.
- Conversation Likes

### Deadlock workaround

Fixes an issue where updating conversation counters can cause deadlocks

### Adds conversation search, with options to search by recipient

Users must be a member of the conversation to see the conversation in search results.

Does not permit moderators/administrators to see another person's conversations in search results.

Due to XenForo's design, this addon impacts general 'everything' search as per search handler constrains are not invoked resulting in false positives which are removed by XenForo rather than the search subsystem.

Adds each conversation, and conversation message to the XenForo Search store (MySQL or Elastic Search), which may result in a larger search index.

### New Conversation Permissions

Just takes away a user's "reply" button, no banners.

The reply limit is for the entire conversation, but the limit is per user group. Consider when User A & User B are members of a conversation.

User A can have a reply limit of 5.
User B can have a reply limit of 10.

Once the conversation has >5 replies, User A can no longer post.
Once the conversation has >10 replies, User A and User B can no longer post.

### Conversation Likes

Adds conversation likes. These Likes generate alerts, and additions to users news feed (with permission checks) as expected.

#Permissions

- Can Reply to Conversation.
- Reply Limit for Conversation.
- Like conversation messages.

#Manual post-installation steps

The add-on will report conversation related content types that require re-indexing.

#Performance impact

- 1 extra query per conversation message posted due to indexing, and indexing itself.
- 2 extra columns per conversation message for Like data.
