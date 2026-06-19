import {__} from '@wordpress/i18n';
import {cloud} from '@wordpress/icons';
import {useBlockProps, InspectorControls} from '@wordpress/block-editor';
import {
    __experimentalHeading as Heading,
    __experimentalDivider as Divider,
    CheckboxControl,
    Button,
    PanelBody,
    SelectControl,
    TextControl,
    Placeholder,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import {useState} from '@wordpress/element';
import FolderTree from './components/FolderTree';
import FolderToolbar from './components/FolderToolbar';
import './editor.scss';


interface EditProps {
    attributes: {
        isInitialSetup: boolean;
        path: string;
        view: 'list' | 'table';
        show: ('name' | 'type' | 'size' | 'modified')[];
        sort: 'asc' | 'desc';
        orderby: 'name' | 'size' | 'type' | 'modified';
        show_title: boolean;
        changetitle: string;
        filetype: string[];
        heading_level: 'h2' | 'h3' | 'h4' | 'h5';
    };
    setAttributes: (attributes: Partial<EditProps['attributes']>) => void;
}

export default function Edit({attributes, setAttributes}: EditProps) {
    const {
        isInitialSetup,
        path,
        view,
        show,
        sort,
        orderby,
        show_title,
        changetitle,
        filetype,
        heading_level,
    } = attributes;

    const [isEditing, setIsEditing] = useState(false);

    const blockProps = useBlockProps();

    const filetypeOptions = ['pdf', 'docx', 'xlsx', 'txt', 'zip', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'svg', 'webp'];

    const toggleShow = (key: 'type' | 'size' | 'modified') => {
        const updated = show.includes(key)
            ? show.filter((item) => item !== key)
            : [...show, key];
        setAttributes({show: updated});
    };

    const treeProps = {
        selectedPath: path,
        onSelect: (folderPath: string) => setAttributes({path: folderPath}),
    };


    return (
        <div {...blockProps}>
            <FolderToolbar
                isEditing={isEditing}
                onToggle={() => setIsEditing(!isEditing)}
            />
            <InspectorControls>
                <PanelBody title={__('Data', 'rrze-faubox')}
                           initialOpen={true}>
                    <div style={{maxHeight: '380px', overflowY: 'auto'}}>
                        <FolderTree {...treeProps} />
                    </div>
                </PanelBody>
                <PanelBody title={__('Display Options',
                    'rrze-faubox')} initialOpen={false}>
                    <SelectControl
                        label={__('View', 'rrze-faubox')}
                        value={view}
                        options={[
                            {disabled: true, label: __('Select an Option', 'rrze-faubox'), value: ''},
                            {label: __('List', 'rrze-faubox'), value: 'list'},
                            {label: __('Table', 'rrze-faubox'), value: 'table'},
                        ]}
                        onChange={(val: 'list' | 'table') =>
                            setAttributes({view: val})}
                    />
                    <Divider margin="3"/>
                    <Heading color="#03316a">{__('Displayed file information', 'rrze-faubox')}</Heading>
                    <CheckboxControl
                        label={__('File Type', 'rrze-faubox')}
                        checked={show.includes('type')}
                        onChange={() => toggleShow('type')}
                    />
                    <CheckboxControl
                        label={__('File Size', 'rrze-faubox')}
                        checked={show.includes('size')}
                        onChange={() => toggleShow('size')}
                    />
                    <CheckboxControl
                        label={__('Last Modified', 'rrze-faubox')}
                        checked={show.includes('modified')}
                        onChange={() => toggleShow('modified')}
                    />
                    <CheckboxControl
                        label={__('Folder Title', 'rrze-faubox')}
                        checked={show_title}
                        onChange={(value) =>
                            setAttributes({show_title: value})}
                    />
                    <Divider margin="2"/>
                    <TextControl
                        label={__('Overwrite folder name', 'rrze-faubox')}
                        help={__('If left blank, the FAUbox folder name will be used automatically.', 'rrze-faubox')}
                        value={changetitle}
                        onChange={(val) => setAttributes({changetitle: val})}
                    />
                    <SelectControl
                        label={__('Heading level', 'rrze-faubox')}
                        value={heading_level}
                        options={[
                            {label: 'H2', value: 'h2'},
                            {label: 'H3', value: 'h3'},
                            {label: 'H4', value: 'h4'},
                            {label: 'H5', value: 'h5'},
                        ]}
                        onChange={(val: 'h2' | 'h3' | 'h4' | 'h5') => setAttributes({heading_level: val})}
                    />
                    <Divider margin="2"/>
                    <Heading color="#03316a">{__('Permitted file formats', 'rrze-faubox')}</Heading>
                    {filetypeOptions.map((type) => (
                        <CheckboxControl
                            key={type}
                            label={type}
                            checked={filetype.includes(type)}
                            onChange={(isChecked) => {
                                const updated = isChecked
                                    ? [...filetype, type]
                                    : filetype.filter((item) => item !== type);
                                setAttributes({filetype: updated});
                            }}
                        />
                    ))}
                    <Divider margin="2"/>
                    <SelectControl
                        label={__('Sorting', 'rrze-faubox')}
                        value={sort}
                        options={[
                            {
                                label: __('Ascending', 'rrze-faubox'),
                                value: 'asc'
                            },
                            {
                                label: __('Descending', 'rrze-faubox'),
                                value: 'desc'
                            },
                        ]}
                        onChange={(val: 'asc' | 'desc') => setAttributes({sort: val})}
                    />
                    <SelectControl
                        label={__('Sort by', 'rrze-faubox')}
                        value={orderby}
                        options={[
                            {
                                label: __('Name', 'rrze-faubox'),
                                value: 'name'
                            },
                            {
                                label: __('Size', 'rrze-faubox'),
                                value: 'size'
                            },
                            {
                                label: __('Type', 'rrze-faubox'),
                                value: 'type'
                            },
                            {
                                label: __('Date', 'rrze-faubox'),
                                value: 'modified'
                            },
                        ]}
                        onChange={(val: 'name' | 'size' | 'type' | 'modified') => setAttributes({orderby: val as 'name' | 'size' | 'type' | 'modified'})}
                    />
                </PanelBody>
            </InspectorControls>

            {(isInitialSetup || isEditing) ? (
                <Placeholder
                    label={__('FAUbox Block', 'rrze-faubox')}
                    instructions={__('Select a FAUbox folder to display its files.', 'rrze-faubox')}
                    icon={cloud}
                    isColumnLayout={true}
                >
                    <FolderTree {...treeProps} />
                    <div style={{display: 'inline-block', marginTop: '12px'}}>
                        <Button
                            variant="primary"
                            disabled={!path}
                            onClick={() => {
                                setAttributes({isInitialSetup: false});
                                setIsEditing(false);
                            }}
                        >
                            {__('Save', 'rrze-faubox')}
                        </Button>
                    </div>
                </Placeholder>
            ) : (
                !path ? (
                    <p style={{opacity: 0.7}}>
                        {__('No folder selected. Please select a folder in the sidebar.', 'rrze-faubox')}
                    </p>
                ) : (
                    <ServerSideRender
                        block="rrze/faubox"
                        attributes={attributes}
                    />
                )
            )}
        </div>
    );
}
