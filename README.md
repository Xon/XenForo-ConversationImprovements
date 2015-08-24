XenForo-ConversationImprovements
======================

A collection of improvements to the XenForo Conversation system.

Features:
- Deadlock workaround.
- Adds conversation search, with options to search by recipient.
- New Conversation Permissions

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

#Permissions

- Can Reply to Conversation.
- Reply Limit for Conversation.

#Manual post-installation steps

On installing for he first time, please rebuild the Search Index for the following content types:
- Conversation
- Conversation Messages

#Performance impact

1 extra query per conversation message posted due to indexing, and indexing itself.
