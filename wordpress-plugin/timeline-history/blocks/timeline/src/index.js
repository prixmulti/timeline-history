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
				<PanelBody title={__('Timeline settings', 'arkivtimeline')}>
					<SelectControl
						label={__('Layout', 'arkivtimeline')}
						value={attributes.layout}
						options={[
							{ label: __('Site default', 'arkivtimeline'), value: '' },
							{ label: __('Vertical', 'arkivtimeline'), value: 'vertical' },
							{ label: __('Horizontal', 'arkivtimeline'), value: 'horizontal' },
						]}
						onChange={(layout) => setAttributes({ layout })}
					/>
					<SelectControl
						label={__('Theme', 'arkivtimeline')}
						value={attributes.theme}
						options={[
							{ label: __('Site default', 'arkivtimeline'), value: '' },
							{ label: 'minimal', value: 'minimal' },
							{ label: 'card', value: 'card' },
							{ label: 'editorial', value: 'editorial' },
						]}
						onChange={(theme) => setAttributes({ theme })}
					/>
					<TextControl
						label={__('Tidslinje (slug)', 'arkivtimeline')}
						help={__('Fx. ansager-1940 – tom = alle historier', 'arkivtimeline')}
						value={attributes.timeline}
						onChange={(timeline) => setAttributes({ timeline })}
					/>
					<TextControl
						label={__('Categories (slugs, comma-separated)', 'arkivtimeline')}
						value={attributes.category}
						onChange={(category) => setAttributes({ category })}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<p>
					<strong>{__('ArkivTimeline', 'arkivtimeline')}</strong>
				</p>
				<p>
					{attributes.timeline
						? `${__('Tidslinje:', 'arkivtimeline')} ${attributes.timeline}`
						: __('Viser alle historier (vælg tidslinje-slug i sidebaren).', 'arkivtimeline')}
				</p>
			</div>
		</>
	);
}
