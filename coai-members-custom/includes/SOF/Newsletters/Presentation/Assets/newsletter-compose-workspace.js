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
                    .find('[name]')
                    .each(function () {
                        const field = $(this);
                        const name = field.attr('name');

                        if (!name) {
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

            const newSection =
                firstSection.clone(false, false);

            newSection
                .find('input, textarea')
                .val('');

            newSection
                .find('.sof-newsletter-image-preview')
                .empty();

            newSection
                .find('.sof-newsletter-remove-image')
                .prop(
                    'hidden',
                    true
                );

            container.append(newSection);

            renumberSections();      }
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

            $(this)
                .closest('.sof-newsletter-content-section')
                .remove();

            renumberSections();
        }
    );


    renumberSections();
    
    
})(jQuery);

