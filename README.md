XenForo-ConversationSearch
======================

Adds conversation search, with options to search by recipient.

Users must be a member of the conversation to see the conversation in search results.

Does not permit moderators/administrators to see another person's conversations in search results.

Due to XenForo's design, this addon impacts general 'everything' search as per search handler constrains are not invoked resulting in false positives which are removed by XenForo rather than the search subsystem.

Adds each conversation, and conversation message do the XenForo Search store (MySQL or Elastic Search), which may result in a larger search index.

#Manual post-installation steps

On installing for he first time, please rebuild the Search Index for the following content types:
•Conversation
•Conversation Messages

#Performance impact

1 extra query per conversation message posted due to indexing, and indexing itself.
