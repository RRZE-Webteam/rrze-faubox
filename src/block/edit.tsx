import {__} from '@wordpress/i18n';
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
import {cloud} from '@wordpress/icons';
import {useEffect, useState} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';
import FolderTree from './components/FolderTree';
import {FolderOption} from './components/FolderTreeNode';
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

    const blockProps = useBlockProps();

    const [rootFolders, setRootFolders] = useState<FolderOption[]>([]);
    const [rootLoading, setRootLoading] = useState<boolean>(false);
    const [loadError, setLoadError] = useState<boolean>(false);
    const [noFolderConfigured, setNoFolderConfigured] =
        useState<boolean>(false);

    const [expandedPaths, setExpandedPaths] = useState<string[]>([]);
    const [childrenMap, setChildrenMap] = useState<Record<string,
        FolderOption[]>>({});
    const [loadingPaths, setLoadingPaths] = useState<string[]>([]);

    const filetypeOptions = ['pdf', 'docx', 'xlsx', 'txt', 'zip', 'ppt',
        'pptx', 'jpg', 'jpeg', 'png', 'svg', 'webp'];

    const toggleShow = (key: 'type' | 'size' | 'modified') => {
        const updated = show.includes(key)
            ? show.filter((item) => item !== key)
            : [...show, key];
        setAttributes({show: updated});
    };

    useEffect(() => {
        setRootLoading(true);
        setLoadError(false);

        apiFetch<FolderOption[]>({path: '/rrze-faubox/v1/folders'})
            .then((data) => {
                setRootFolders(Array.isArray(data) ? data : []);
                setRootLoading(false);
            })
            .catch((error: {code?: string}) => {
                if (error?.code === 'no_folder_configured') {
                    setNoFolderConfigured(true);
                } else {
                    setLoadError(true);
                }
                setRootLoading(false);
            });
    }, []);

    const handleToggle = (folderPath: string) => {
        const isExpanded = expandedPaths.includes(folderPath);

        if (isExpanded) {
            setExpandedPaths(expandedPaths.filter((p) => p !== folderPath));
            return;
        }

        setExpandedPaths((prev) => [...prev, folderPath]);

        if (childrenMap[folderPath] !== undefined) {
            return;
        }

        setLoadingPaths((prev) => [...prev, folderPath]);

        apiFetch<FolderOption[]>({
            path:
                `/rrze-faubox/v1/folders?path=${encodeURIComponent(folderPath)}`,
        })
            .then((data) => {
                setChildrenMap((prev) => ({
                    ...prev,
                    [folderPath]: Array.isArray(data) ? data : [],
                }));
                setLoadingPaths((prev) => prev.filter((p) => p !==
                    folderPath));
            })
            .catch(() => {
                setChildrenMap((prev) => ({...prev, [folderPath]: []}));
                setLoadingPaths((prev) => prev.filter((p) => p !==
                    folderPath));
            });
    };

    const handleSelect = (folderPath: string) => {
        setAttributes({path: folderPath});
    };

    const treeProps = {
        rootFolders,
        rootLoading,
        loadError,
        noFolderConfigured,
        selectedPath: path,
        expandedPaths,
        childrenMap,
        loadingPaths,
        onToggle: handleToggle,
        onSelect: handleSelect,
    };

    return (
        <div {...blockProps}>
            {isInitialSetup ? (
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
                        onClick={() => setAttributes({isInitialSetup:
                                false})}
                    >
                        {__('Save', 'rrze-faubox')}
                    </Button>
                </div>
                </Placeholder>
                ) : (
                <>
                <InspectorControls>
                <PanelBody title={__('Data', 'rrze-faubox')}
             initialOpen={true}>
            <FolderTree {...treeProps} />
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
                <Divider margin="3" />
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
        <Divider margin="2" />
        <TextControl
            label={__('Overwrite folder name',
                'rrze-faubox')}
            help={__('If left blank, the FAUbox folder name will be used automatically.', 'rrze-faubox')}
                value={changetitle}
                onChange={(val) => setAttributes({changetitle:val})}
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
            onChange={(val: 'h2' | 'h3' | 'h4' | 'h5') =>
                setAttributes({heading_level: val})}
        />
        <Divider margin="2" />
        <Heading color="#03316a">{__('Permitted file formats', 'rrze-faubox')}</Heading>
        {filetypeOptions.map((type) => (
            <CheckboxControl
            key={type}
                 label={type}
                 checked={filetype.includes(type)}
                 onChange={(isChecked) => {
                     const updated = isChecked
                         ? [...filetype, type]
                         : filetype.filter((item) => item
                             !== type);
                     setAttributes({filetype: updated});
                 }}
        />
        ))}
        <Divider margin="2" />
        <SelectControl
            label={__('Sorting', 'rrze-faubox')}
            value={sort}
            options={[
                {label: __('Ascending', 'rrze-faubox'),
                    value: 'asc'},
                {label: __('Descending', 'rrze-faubox'),
                    value: 'desc'},
            ]}
            onChange={(val: 'asc' | 'desc') =>
                setAttributes({sort: val})}
        />
        <SelectControl
            label={__('Sort by', 'rrze-faubox')}
            value={orderby}
            options={[
                {label: __('Name', 'rrze-faubox'), value:
                        'name'},
                {label: __('Size', 'rrze-faubox'), value:
                        'size'},
                {label: __('Type', 'rrze-faubox'), value:
                        'type'},
                {label: __('Date', 'rrze-faubox'), value:
                        'modified'},
            ]}
            onChange={(val) => setAttributes({orderby: val as 'name' | 'size' | 'type' | 'modified'})}
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
