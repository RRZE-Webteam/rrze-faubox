import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';

// Block registrieren
registerBlockType(metadata.name as any, {
    ...metadata,
    edit: Edit,
    save: (): any => null,
} as any);
