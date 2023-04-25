# Paragraphs Paste

This module provides functionality to create various paragraph entities by pasting content into an area of a content form.
Configuration is done via the widget settings of a paragraphs field, f.e. at "admin/structure/types/manage/article/form-display"
It determines the paragraph type created based on the content provided, for example, a youtube link triggers creation of a paragraph suitable to hold a youtube or video media entity.
By default a paragraph supporting text is createed.

Support for paragraph types using multiple fields can be added by creating a custom ParagraphsPastePlugin plugins.

Optionally text can be processed using the [textile](https://textile-lang.com) parser, requiring the php lib: `composer require netcarver/textile`.

### Creating a custom ParagraphsPastePlugin plugin.


