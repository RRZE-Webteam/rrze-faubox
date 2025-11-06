import {__} from '@wordpress/i18n';

import {
    useBlockProps,
    BlockControls,
    InspectorControls
} from "@wordpress/block-editor";

import {
    Placeholder,
    __experimentalGrid as Grid,
    __experimentalHeading as Heading,
    __experimentalSpacer as Spacer,
    __experimentalToggleGroupControl as ToggleGroupControl,
    __experimentalToggleGroupControlOption as ToggleGroupControlOption,
    CheckboxControl,
    Button,
    TextControl,
    PanelBody,
    SelectControl,
} from "@wordpress/components";

import {cloud} from "@wordpress/icons";

import {useEffect, useState} from '@wordpress/element';

import ServerSideRender from '@wordpress/server-side-render';
import "./editor.scss";


interface EditProps {
    attributes: {
        view: 'list' | 'table' | 'gallery';
        show: ('name' | 'type' | 'size' | 'modified')[];
        sort: 'asc' | 'desc';
        index: string;
        show_title: boolean;
        isInitialSetup: boolean;
        orderby: string;
        filetype: string[];
    }
    setAttributes: (attributes: Partial<EditProps["attributes"]>) => void;
}

export default function Edit({attributes, setAttributes}: EditProps) {
    const {view, show, sort, index, show_title, isInitialSetup, orderby, filetype} = attributes;
    const blockProps = useBlockProps();

    const toggleShow = (key: 'size' | 'type' | 'modified') => {
        const newShow = show.includes(key)
            ? show.filter((item) => item !== key)
            : [...show, key];

        setAttributes({show: newShow});
    };

    const [folderOptions, setFolderOptions] = useState<{ label: string; value: string }[]>([]);
    // Load folder options via REST
    useEffect(() => {
        fetch('/wp-json/rrze-faubox/v1/folders')
            .then((res) => res.json())
            .then((data) => {
                setFolderOptions(data);
            })
            .catch(() => {
                setFolderOptions([
                    {value: '/ss25', label: 'SS25'},
                    {value: '/ss24', label: 'SS24'},
                    {value: '/ws23', label: 'WS23'},
                ]);
            });
    }, []);


    const filetypeOptions = ['pdf', 'docx', 'txt', 'jpg', 'zip', 'ppt', 'svg', 'png'];

    return (
        <div {...blockProps}>
            {isInitialSetup ? (
                // 🟩 Placeholder-Bereich (Setup)
                <Placeholder
                    label={__("Faubox Block", "rrze-faubox")}
                    instructions={__("Configure your Faubox block.", "rrze-faubox")}
                    isColumnLayout={true}
                    icon={cloud}
                >
                    {/* === Deine Setup-Einstellungen === */}
                    <div>
                        <hr/>
                        <Spacer paddingBottom={"1rem"}/>
                        <Heading level={3}>{__("Faubox Einstellungen", "rrze-faubox")}</Heading>
                        <p>{__("Bitte richten Sie vorab Ihren FAUbox Zugang über das WordPress Dashboard > Einstellungen > FAUbox ein.", "rrze-faubox")}</p>
                        <Spacer paddingBottom={"1rem"}/>

                        <Grid columns={7}>
                            {/* Linke Spalte */}
                            <div style={{gridColumn: "span 3"}}>
                                <SelectControl
                                    label="Ordnerauswahl"
                                    __next40pxDefaultSize
                                    value={index}
                                    options={[
                                        { value: "", label: __("Unterordner auswählen", "rrze-faubox"), disabled: true },
                                        ...folderOptions //
                                    ]}
                                    onChange={(val: string) => setAttributes({ index: val })}
                                />

                                <SelectControl
                                    label="View"
                                    value={view}
                                    __next40pxDefaultSize
                                    options={[
                                        {disabled: true, label: 'Select an Option', value: ''},
                                        {label: 'List', value: 'list'},
                                        {label: 'Table', value: 'table'},
                                        {label: 'Gallery', value: 'gallery'},
                                    ]}
                                    onChange={(val: 'list' | 'table' | 'gallery') =>
                                        setAttributes({view: val as 'list' | 'table' | 'gallery'})
                                    }
                                />

                                <SelectControl
                                    label="Sortierung"
                                    __next40pxDefaultSize
                                    value={sort}
                                    options={[
                                        {label: 'Aufsteigend', value: 'asc'},
                                        {label: 'Absteigend', value: 'desc'},
                                    ]}
                                    onChange={(val: 'asc' | 'desc') =>
                                        setAttributes({sort: val as 'asc' | 'desc'})
                                    }
                                />
                                <SelectControl
                                    label="Reihenfolge"
                                    __next40pxDefaultSize
                                    value={orderby}
                                    options={[
                                        {label: 'Name', value: 'name'},
                                        {label: 'Size', value: 'size'},
                                        {label: 'Type', value: 'type'},
                                        {label: 'Changed', value: 'modified'}
                                    ]}
                                    onChange={(val: 'name' | 'size' | 'type' | 'modified') =>
                                        setAttributes({orderby: val as 'name' | 'size' | 'type' | 'modified' })
                                    }
                                />

                            </div>
                            <Spacer paddingTop=".5rem" />
                            {/* Rechte Spalte */}
                            <div style={{gridColumn: "span 3"}}>
                                <Heading level={4}>{__("Show", "rrze-faubox")}</Heading>
                                <Spacer paddingTop={"0.5rem"}/>
                                <CheckboxControl
                                    label={__("File Size", "rrze-faubox")}
                                    checked={show.includes('size')}
                                    onChange={() => toggleShow('size')}
                                />
                                <CheckboxControl
                                    label={__("File Type", "rrze-faubox")}
                                    checked={show.includes('type')}
                                    onChange={() => toggleShow('type')}
                                />
                                <CheckboxControl
                                    label={__("File Changed Date", "rrze-faubox")}
                                    checked={show.includes('modified')}
                                    onChange={() => toggleShow('modified')}
                                />
                                <CheckboxControl
                                    label={__("Ordnertitel anzeigen", "rrze-faubox")}
                                    checked={show_title}
                                    onChange={(value) => setAttributes({show_title: value})}
                                />

                                <Heading level={4}>{__("Anzuzeigende Dateien", "rrze-faubox")}</Heading>
                                <Spacer paddingTop={"0.5rem"}/>
                                {filetypeOptions.map((type) => (
                                    <CheckboxControl
                                        key={type}
                                        label={type.toUpperCase()}
                                        checked={filetype.includes(type)}
                                        onChange={(isChecked) => {
                                            const updated = isChecked
                                                ? [...filetype, type]
                                                : filetype.filter((item) => item !== type);
                                            setAttributes({ filetype: updated });
                                        }}
                                    />
                                ))}
                            </div>
                        </Grid>

                        <Spacer paddingBottom={"0.5rem"}/>
                        <Button
                            variant="primary"
                            onClick={() => setAttributes({isInitialSetup: false})}
                        >
                            {__("Finish initial setup", "rrze-faubox")}
                        </Button>
                        <Spacer paddingBottom={"0.5rem"}/>
                    </div>


                    <div>
                        <hr/>
                        <Spacer paddingTop={"1rem"}/>
                        <Heading level={3}>{__("Data Preview", "rrze-faubox")}</Heading>
                        <Spacer paddingBottom={"0.2rem"}/>
                        <ServerSideRender
                            block="rrze/faubox"
                            attributes={attributes}
                        />
                        <Spacer/>
                    </div>
                </Placeholder>
            ) : (
                // 🟦 Hier beginnt der zweite Zustand (nach Setup)
                <>
                    <InspectorControls>
                        <PanelBody title={__("Datenauswahl", "rrze-faubox")} initialOpen={false}>
                            <SelectControl
                                label="Ordnerauswahl"
                                value={index}
                                options={folderOptions}
                                onChange={(val: string) => setAttributes({index: val})}
                            />
                        </PanelBody>

                        <PanelBody title={__("Darstellungsoptionen", "rrze-faubox")} initialOpen={false}>
                            <Spacer paddingTop={"0.5rem"}/>
                            <SelectControl
                                label="View"
                                value={view}
                                options={[
                                    {disabled: true, label: 'Select an Option', value: ''},
                                    {label: 'List', value: 'list'},
                                    {label: 'Table', value: 'table'},
                                    {label: 'Gallery', value: 'gallery'},
                                ]}
                                onChange={(val: 'list' | 'table' | 'gallery') =>
                                    setAttributes({view: val as 'list' | 'table' | 'gallery'})
                                }
                            />
                            <Spacer paddingTop={"0.5rem"}/>
                            <CheckboxControl
                                label={__("File Size", "rrze-faubox")}
                                checked={show.includes('size')}
                                onChange={() => toggleShow('size')}
                            />
                            <CheckboxControl
                                label={__("File Type", "rrze-faubox")}
                                checked={show.includes('type')}
                                onChange={() => toggleShow('type')}
                            />
                            <CheckboxControl
                                label={__("File Changed Date", "rrze-faubox")}
                                checked={show.includes('modified')}
                                onChange={() => toggleShow('modified')}
                            />
                            <CheckboxControl
                                label={__("Ordnertitel anzeigen", "rrze-faubox")}
                                checked={show_title}
                                onChange={(value) => setAttributes({show_title: value})}
                            />
                        </PanelBody>

                        <PanelBody title={__("Sortierung & Reihenfolge", "rrze-faubox")} initialOpen={false}>
                            <Spacer paddingTop={"0.5rem"}/>
                            <SelectControl
                                label="Sortierung"
                                help={__("Blabla?", "rrze-downloads")}
                                value={sort}
                                options={[
                                    {label: 'Aufsteigend', value: 'asc'},
                                    {label: 'Absteigend', value: 'desc'},
                                ]}
                                onChange={(val: 'asc' | 'desc') =>
                                    setAttributes({sort: val as 'asc' | 'desc'})
                                }
                            />
                            <SelectControl
                                label="Reihenfolge"
                                help={__("Blabla?", "rrze-downloads")}
                                value={orderby}
                                options={[
                                    {label: 'Name', value: 'name'},
                                    {label: 'Size', value: 'size'},
                                    {label: 'Type', value: 'type'},
                                    {label: 'Changed', value: 'modified'}
                                ]}
                                onChange={(val: 'name' | 'size' | 'type' | 'modified') =>
                                    setAttributes({orderby: val as 'name' | 'size' | 'type' | 'modified' })
                                }
                            />
                            {filetypeOptions.map((type) => (
                                <CheckboxControl
                                    key={type}
                                    label={type.toUpperCase()}
                                    checked={filetype.includes(type)}
                                    onChange={(isChecked) => {
                                        const updated = isChecked
                                            ? [...filetype, type]
                                            : filetype.filter((item) => item !== type);
                                        setAttributes({ filetype: updated });
                                    }}
                                />
                            ))}
                        </PanelBody>
                    </InspectorControls>

                    {/* Ausgabe nach Setup */}
                    <ServerSideRender
                        block="rrze/faubox"
                        attributes={attributes}
                    />
                </>
            )}
        </div>
    );
}