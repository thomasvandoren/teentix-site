CKEDITOR.stylesSet.add( 'my_styles',
[
    // Block Styles
    { name: 'Body Copy', element: 'p' },
    { name: 'Big Headline', element: 'h3', attributes: { 'class': 'big_headline' } },
    { name: 'Medium Title' , element: 'h4', attributes: { 'class': 'medium_headline' } },
    { name: 'Photo Caption' , element: 'p', attributes: { 'class': 'entry-image-caption' } },

    // Inline Styles
    { name: 'Image Left', element: 'img', attributes: { 'class': 'float_left' } },
    { name: 'Image Right', element: 'img', attributes: { 'class': 'float_right' } },
    { name: 'Image Center', element: 'img', attributes: { 'class': 'image_center' } }
]);