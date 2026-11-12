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
				<PanelBody title={__('Timeline settings', 'timeline-history')}>
					<SelectControl
						label={__('Layout', 'timeline-history')}
						value={attributes.layout}
						options={[
							{ label: __('Site default', 'timeline-history'), value: '' },
							{ label: __('Vertical', 'timeline-history'), value: 'vertical' },
							{ label: __('Horizontal', 'timeline-history'), value: 'horizontal' },
						]}
						onChange={(layout) => setAttributes({ layout })}
					/>
					<SelectControl
						label={__('Theme', 'timeline-history')}
						value={attributes.theme}
						options={[
							{ label: __('Site default', 'timeline-history'), value: '' },
							{ label: 'minimal', value: 'minimal' },
							{ label: 'card', value: 'card' },
							{ label: 'editorial', value: 'editorial' },
						]}
						onChange={(theme) => setAttributes({ theme })}
					/>
					<TextControl
						label={__('Tidslinje (slug)', 'timeline-history')}
						help={__('Fx. ansager-1940 – tom = alle historier', 'timeline-history')}
						value={attributes.timeline}
						onChange={(timeline) => setAttributes({ timeline })}
					/>
					<TextControl
						label={__('Categories (slugs, comma-separated)', 'timeline-history')}
						value={attributes.category}
						onChange={(category) => setAttributes({ category })}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<p>
					<strong>{__('ArkivTimeline', 'timeline-history')}</strong>
				</p>
				<p>
					{attributes.timeline
						? `${__('Tidslinje:', 'timeline-history')} ${attributes.timeline}`
						: __('Viser alle historier (vælg tidslinje-slug i sidebaren).', 'timeline-history')}
				</p>
			</div>
		</>
	);
}
