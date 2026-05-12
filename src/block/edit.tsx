import {__} from '@wordpress/i18n';
import {useBlockProps, InspectorControls} from '@wordpress/block-editor';
import {
    Placeholder,
    __experimentalHeading as Heading,
    __experimentalSpacer as Spacer,
    __experimentalDivider as Divider,
    CheckboxControl,
    Button,
    PanelBody,
    SelectControl,
    Spinner,
} from '@wordpress/components';
import {cloud} from '@wordpress/icons';
import {useEffect, useState} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';
import './editor.scss';

interface FolderOption {
    name: string;
    path: string;
}

interface EditProps {
    attributes: {
        isInitialSetup: boolean;
        path: string;
        view: 'list' | 'table';
        show: ('name' | 'type')[];
        sort: 'asc' | 'desc';
        orderby: 'name' | 'size' | 'type' | 'modified';
        show_title: boolean;
        changetitle: string;
        filetype: string[];
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
    } = attributes;

    const blockProps = useBlockProps();

    const [folders, setFolders] = useState<FolderOption[]>([]);
    const [loading, setLoading] = useState<boolean>(false);
    const [loadError, setLoadError] = useState<boolean>(false);

    const filetypeOptions = ['pdf', 'docx', 'xlsx', 'txt', 'zip', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'svg', 'webp'];

    const toggleShow = (key: 'type') => {
        const updated = show.includes(key)
            ? show.filter((item) => item !== key)
            : [...show, key];
        setAttributes({show: updated});
    };

    // Load root folders from REST endpoint (uses stored token server-side)
    useEffect(() => {
        setLoading(true);
        setLoadError(false);

        apiFetch<FolderOption[]>({path: '/rrze-faubox/v1/folders'})
            .then((data) => {
                setFolders(Array.isArray(data) ? data : []);
                setLoading(false);
            })
            .catch(() => {
                setLoadError(true);
                setLoading(false);
            });
    }, []);

    const folderSelectOptions = [
        {label: __('— Select folder —', 'rrze-faubox'), value: ''},
        ...folders.map((f) => ({label: f.name, value: f.path})),
    ];

    const FolderSelect = () => (
        <>
            {loading && <Spinner/>}
            {loadError && (
                <p style={{color: 'red'}}>
                    {__('Folders could not be loaded. Please check your FAUbox credentials in the settings.', 'rrze-faubox')}
                </p>
            )}
            {!loading && !loadError && (
                <SelectControl
                    label={__('FAUbox Folder', 'rrze-faubox')}
                    value={path}
                    options={folderSelectOptions}
                    onChange={(val) => setAttributes({path: val})}
                />
            )}
        </>
    );

    return (
        <div {...blockProps}>
            {isInitialSetup ? (
                <Placeholder
                    label={__('FAUbox Block', 'rrze-faubox')}
                    instructions={__('Select a FAUbox folder to display its files.', 'rrze-faubox')}
                    icon={cloud}
                    isColumnLayout={true}
                >
                    <Spacer paddingBottom={'0.5rem'}/>
                    <FolderSelect/>
                    <Spacer paddingTop={'1rem'}/>
                    <Button
                        variant="primary"
                        disabled={!path}
                        onClick={() => setAttributes({isInitialSetup: false})}
                    >
                        {__('Save', 'rrze-faubox')}
                    </Button>
                    <Spacer paddingBottom={'0.5rem'}/>
                </Placeholder>
            ) : (
                <>
                    <InspectorControls>
                        <PanelBody title={__('Data', 'rrze-faubox')} initialOpen={true}>
                            <FolderSelect/>
                        </PanelBody>

                        <PanelBody title={__('Display Options', 'rrze-faubox')} initialOpen={false}>
                            <SelectControl
                                label={__('View', 'rrze-faubox')}
                                value={view}
                                options={[
                                    {label: __('List', 'rrze-faubox'), value: 'list'},
                                    {label: __('Table', 'rrze-faubox'), value: 'table'},
                                ]}
                                onChange={(val) => setAttributes({view: val as 'list' | 'table'})}
                            />
                            <Divider margin="3"/>
                            <Heading color="#03316a">{__('Displayed file information', 'rrze-faubox')}</Heading>
                            <CheckboxControl
                                label={__('File Type', 'rrze-faubox')}
                                checked={show.includes('type')}
                                onChange={() => toggleShow('type')}
                            />
                            <CheckboxControl
                                label={__('Folder Title', 'rrze-faubox')}
                                checked={show_title}
                                onChange={(value) => setAttributes({show_title: value})}
                            />
                            <Divider margin="2"/>
                            <SelectControl
                                label={__('Sort by', 'rrze-faubox')}
                                value={orderby}
                                options={[
                                    {label: __('Name', 'rrze-faubox'), value: 'name'},
                                    {label: __('Size', 'rrze-faubox'), value: 'size'},
                                    {label: __('Type', 'rrze-faubox'), value: 'type'},
                                    {label: __('Date', 'rrze-faubox'), value: 'modified'},
                                ]}
                                onChange={(val) => setAttributes({orderby: val as 'name' | 'size' | 'type' | 'modified'})}
                            />
                            <SelectControl
                                label={__('Sort direction', 'rrze-faubox')}
                                value={sort}
                                options={[
                                    {label: __('Ascending', 'rrze-faubox'), value: 'asc'},
                                    {label: __('Descending', 'rrze-faubox'), value: 'desc'},
                                ]}
                                onChange={(val) => setAttributes({sort: val as 'asc' | 'desc'})}
                            />
                        </PanelBody>

                        <PanelBody title={__('File Filter', 'rrze-faubox')} initialOpen={false}>
                            <Heading color="#03316a">{__('Permitted file formats', 'rrze-faubox')}</Heading>
                            <Spacer paddingTop={'0.5rem'}/>
                            {filetypeOptions.map((type) => (
                                <CheckboxControl
                                    key={type}
                                    label={type}
                                    checked={filetype.includes(type)}
                                    onChange={(checked) => {
                                        const updated = checked
                                            ? [...filetype, type]
                                            : filetype.filter((item) => item !== type);
                                        setAttributes({filetype: updated});
                                    }}
                                />
                            ))}
                        </PanelBody>

                        <PanelBody title={__('Title', 'rrze-faubox')} initialOpen={false}>
                            <SelectControl
                                label={__('Overwrite folder name', 'rrze-faubox')}
                                value={changetitle}
                                options={[
                                    {label: __('Use FAUbox folder name', 'rrze-faubox'), value: ''},
                                    ...folders.map((f) => ({label: f.name, value: f.name})),
                                ]}
                                onChange={(val) => setAttributes({changetitle: val})}
                            />
                        </PanelBody>
                    </InspectorControls>

                    {!path ? (
                        <p style={{opacity: 0.7}}>
                            {__('No folder selected. Please select a folder in the sidebar.', 'rrze-faubox')}
                        </p>
                    ) : (
                        <ServerSideRender
                            block="rrze/faubox"
                            attributes={attributes}
                        />
                    )}
                </>
            )}
        </div>
    );
}