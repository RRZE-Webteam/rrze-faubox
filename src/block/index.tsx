import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import type { Attributes } from '@wordpress/blocks';
import Edit from './edit';
import Save from './save';
import metadata from './block.json';

// Typen für die Block-Attribute definieren
type FAUboxAttributes = {
    folder: string;
};

// Metadaten korrekt typisieren
const blockSettings: BlockConfiguration<FAUboxAttributes> = {
    ...metadata,
    edit: Edit,
    save: Save,
};

// Block registrieren
registerBlockType<FAUboxAttributes>(metadata.name, {
    ...metadata,
    edit: Edit,
    save: () => null
});
