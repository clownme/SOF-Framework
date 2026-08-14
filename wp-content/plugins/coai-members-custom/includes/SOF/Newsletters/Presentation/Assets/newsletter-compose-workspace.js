/**
 * ============================================================
 * SOF Newsletter Compose Workspace
 * ============================================================
 *
 * Media Library integration for Newsletter images.
 * ============================================================
 */

(function ($) {
    'use strict';

    function openMediaLibrary(button) {
        const targetId = button.data('target-id');
        const previewId = button.data('preview-id');

        const frame = wp.media({
            title: 'Choose Newsletter Image',
            button: {
                text: 'Use This Image'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });

        frame.on('select', function () {
            const attachment = frame
                .state()
                .get('selection')
                .first()
                .toJSON();

            $('#' + targetId).val(attachment.id);

            const imageUrl =
                attachment.sizes &&
                attachment.sizes.medium
                    ? attachment.sizes.medium.url
                    : attachment.url;

            $('#' + previewId)
                .html(
                    $('<img>', {
                        src: imageUrl,
                        alt: attachment.alt || '',
                        class: 'sof-newsletter-selected-image'
                    })
                );

            button
                .siblings('.sof-newsletter-remove-image')
                .prop('hidden', false);
        });

        frame.open();
    }

    $(document).on(
        'click',
        '.sof-newsletter-choose-image',
        function (event) {
            event.preventDefault();

            openMediaLibrary($(this));
        }
    );

    $(document).on(
        'click',
        '.sof-newsletter-remove-image',
        function (event) {
            event.preventDefault();

            const button = $(this);
            const targetId = button.data('target-id');
            const previewId = button.data('preview-id');

            $('#' + targetId).val('');
            $('#' + previewId).empty();

            button.prop('hidden', true);
        }
    );

    /**
     * --------------------------------------------------------
     * Newsletter Sections
     * --------------------------------------------------------
     */

        function renumberSections() {
        $('.sof-newsletter-content-section')
            .each(function (index) {
                const section = $(this);

                section.attr(
                    'data-section-index',
                    index
                );

                section
                    .find('.sof-newsletter-section-number')
                    .text(index + 1);

                section
                    .find('input[name], textarea[name], select[name]')
                    .each(function () {
                        const field = $(this);
                        const name = field.attr('name');

                        if (
                            !name ||
                            !/^sections\[\d+\]/.test(name)
                        ) {
                            return;
                        }

                        field.attr(
                            'name',
                            name.replace(
                                /sections\[\d+\]/,
                                'sections[' + index + ']'
                            )
                        );
                    });

                const imageInput =
                    section.find(
                        'input[type="hidden"][name*="[image_attachment_id]"]'
                    );

                const imagePreview =
                    section.find(
                        '.sof-newsletter-image-preview'
                    );

                const imageInputId =
                    'sof-newsletter-section-image-id-' +
                    index;

                const imagePreviewId =
                    'sof-newsletter-section-image-preview-' +
                    index;

                imageInput.attr(
                    'id',
                    imageInputId
                );

                imagePreview.attr(
                    'id',
                    imagePreviewId
                );

                section
                    .find('.sof-newsletter-choose-image')
                    .attr(
                        'data-target-id',
                        imageInputId
                    )
                    .attr(
                        'data-preview-id',
                        imagePreviewId
                    );

                section
                    .find('.sof-newsletter-remove-image')
                    .attr(
                        'data-target-id',
                        imageInputId
                    )
                    .attr(
                        'data-preview-id',
                        imagePreviewId
                    );
            });

        const sectionCount =
            $('.sof-newsletter-content-section').length;

        $('.sof-newsletter-remove-section')
            .prop(
                'hidden',
                sectionCount <= 1
            );
    }


    function initializeSectionEditor(editorId) {

        if (
            !window.wp ||
            !wp.editor ||
            typeof wp.editor.initialize !== 'function'
        ) {
            return;
        }

        wp.editor.initialize(
            editorId,
            {
                tinymce: {
                    toolbar1:
                        'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,undo,redo',

                    toolbar2:
                        'forecolor,removeformat,charmap,outdent,indent'
                },

                quicktags: true,

                mediaButtons: false
            }
        );
    }


    $('#sof-newsletter-add-section').on(
        'click',
        function () {

            const container =
                $('#sof-newsletter-sections');

            const firstSection =
                container
                    .find('.sof-newsletter-content-section')
                    .first();

            if (!firstSection.length) {
                return;
            }

            /*
             * Clone the business section structure.
             *
             * TinyMCE itself must NOT be cloned as an active
             * editor. The cloned editor wrapper will be replaced
             * with a fresh textarea and WordPress will initialize
             * a brand-new editor for the new section.
             */
            const newSection =
                firstSection.clone(false, false);

            const newIndex =
                container
                    .find('.sof-newsletter-content-section')
                    .length;

            const editorId =
                'sof_newsletter_section_content_dynamic_' +
                Date.now() +
                '_' +
                newIndex;

            const editorWrap =
                newSection.find('.wp-editor-wrap').first();

            if (editorWrap.length) {

                const editorTextarea =
                    $('<textarea>', {
                        id: editorId,
                        name:
                            'sections[' +
                            newIndex +
                            '][content]',
                        rows: 10,
                        class: 'wp-editor-area'
                    });

                editorWrap.replaceWith(
                    editorTextarea
                );
            }

            newSection
                .find('input')
                .not(
                    'input[type="button"], ' +
                    'input[type="submit"]'
                )
                .val('');

            newSection
                .find('textarea')
                .not('#' + editorId)
                .val('');
                
            newSection
                .find('.sof-newsletter-image-size')
                .val('medium');

            newSection
                .find('.sof-newsletter-image-preview')
                .empty();

            newSection
                .find('.sof-newsletter-remove-image')
                .prop(
                    'hidden',
                    true
                );

            container.append(
                newSection
            );

            renumberSections();

            initializeSectionEditor(
                editorId
            );
        }
    );


    $(document).on(
        'click',
        '.sof-newsletter-remove-section',
        function () {

            const sections =
                $('.sof-newsletter-content-section');

            if (sections.length <= 1) {
                return;
            }

            const section =
                $(this)
                    .closest(
                        '.sof-newsletter-content-section'
                    );

            const editor =
                section
                    .find('textarea.wp-editor-area')
                    .first();

            const editorId =
                editor.attr('id');

            /*
             * Properly detach TinyMCE before removing the
             * Newsletter section from the page.
             */
            if (
                editorId &&
                window.wp &&
                wp.editor &&
                typeof wp.editor.remove === 'function'
            ) {
                wp.editor.remove(
                    editorId
                );
            }

            section.remove();

            renumberSections();
        }
    );


    /*
     * Make sure TinyMCE pushes the current visual-editor
     * contents back into the underlying textareas before
     * the Newsletter form is submitted.
     */
    $(document).on(
        'submit',
        'form',
        function () {

            if (
                window.tinymce &&
                typeof tinymce.triggerSave === 'function'
            ) {
                tinymce.triggerSave();
            }
        }
    );


    renumberSections();
    
    
})(jQuery);

