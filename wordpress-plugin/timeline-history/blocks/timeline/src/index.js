import './editor.scss';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';

registerBlockType(metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
});

function Edit({ attributes, setAttributes }) {
	const blockProps = useBlockProps({ className: 'th-block-placeholder' });

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Timeline settings', 'prix-historical-timeline')}>
					<SelectControl
						label={__('Layout', 'prix-historical-timeline')}
						value={attributes.layout}
						options={[
							{ label: __('Site default', 'prix-historical-timeline'), value: '' },
							{ label: __('Vertical', 'prix-historical-timeline'), value: 'vertical' },
							{ label: __('Horizontal', 'prix-historical-timeline'), value: 'horizontal' },
						]}
						onChange={(layout) => setAttributes({ layout })}
					/>
					<SelectControl
						label={__('Theme', 'prix-historical-timeline')}
						value={attributes.theme}
						options={[
							{ label: __('Site default', 'prix-historical-timeline'), value: '' },
							{ label: 'minimal', value: 'minimal' },
							{ label: 'card', value: 'card' },
							{ label: 'editorial', value: 'editorial' },
						]}
						onChange={(theme) => setAttributes({ theme })}
					/>
					<TextControl
						label={__('Tidslinje (slug)', 'prix-historical-timeline')}
						help={__('Fx. ansager-1940 – tom = alle historier', 'prix-historical-timeline')}
						value={attributes.timeline}
						onChange={(timeline) => setAttributes({ timeline })}
					/>
					<TextControl
						label={__('Categories (slugs, comma-separated)', 'prix-historical-timeline')}
						value={attributes.category}
						onChange={(category) => setAttributes({ category })}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<p>
					<strong>{__( 'Historical Timeline', 'prix-historical-timeline')}</strong>
				</p>
				<p>
					{attributes.timeline
						? `${__('Tidslinje:', 'prix-historical-timeline')} ${attributes.timeline}`
						: __('Viser alle historier (vælg tidslinje-slug i sidebaren).', 'prix-historical-timeline')}
				</p>
			</div>
		</>
	);
}
