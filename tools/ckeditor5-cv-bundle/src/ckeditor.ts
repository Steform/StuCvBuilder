/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */

import { ClassicEditor as ClassicEditorBase } from '@ckeditor/ckeditor5-editor-classic';
import { Essentials } from '@ckeditor/ckeditor5-essentials';
import { Bold, Italic } from '@ckeditor/ckeditor5-basic-styles';
import { Heading } from '@ckeditor/ckeditor5-heading';
import { Link } from '@ckeditor/ckeditor5-link';
import { List } from '@ckeditor/ckeditor5-list';
import { Paragraph } from '@ckeditor/ckeditor5-paragraph';
import { FontColor, FontBackgroundColor } from '@ckeditor/ckeditor5-font';
import { TextTransformation } from '@ckeditor/ckeditor5-typing';

/**
 * Slim Classic build for CV dashboard: headings, basic styles, link, lists, undo, font colors.
 * Toolbar is overridden at runtime from {@see public/js/ckeditor-init.js}.
 */
export default class ClassicEditor extends ClassicEditorBase {
  public static override builtinPlugins = [
    Essentials,
    Paragraph,
    Heading,
    Bold,
    Italic,
    Link,
    List,
    FontColor,
    FontBackgroundColor,
    TextTransformation,
  ];

  public static override defaultConfig = {
    toolbar: {
      items: [
        'undo',
        'redo',
        '|',
        'heading',
        '|',
        'bold',
        'italic',
        '|',
        'fontColor',
        'fontBackgroundColor',
        '|',
        'link',
        '|',
        'bulletedList',
        'numberedList',
      ],
    },
    language: 'en',
  };
}
